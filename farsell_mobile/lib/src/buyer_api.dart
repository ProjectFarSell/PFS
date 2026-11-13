import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import 'catalog.dart';

typedef Json = Map<String, dynamic>;

class BuyerUpload {
  const BuyerUpload(this.field, this.name, this.bytes);
  final String field, name;
  final Uint8List bytes;
}

Json object(dynamic value) => Map<String, dynamic>.from(value as Map);
List<Json> objects(dynamic value) => (value as List).map(object).toList();

String requestId() {
  final bytes = List<int>.generate(16, (_) => Random.secure().nextInt(256));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  final s = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
  return '${s.substring(0, 8)}-${s.substring(8, 12)}-${s.substring(12, 16)}-${s.substring(16, 20)}-${s.substring(20)}';
}

class ApiFailure implements Exception {
  const ApiFailure(
    this.message, {
    this.status = 0,
    this.code = 'connection_error',
    this.errors = const {},
    this.retryAfter,
  });
  final String message, code;
  final int status;
  final Json errors;
  final int? retryAfter;
  bool get uncertain => status == 0 || status >= 500 || status == 429;
  String get display => {
    message,
    ...errors.values.expand((v) => v is List ? v : [v]).map((v) => '$v'),
  }.join('\n');
  @override
  String toString() => display;
}

abstract class PrivateStore {
  Future<String?> read(String key);
  Future<void> write(String key, String? value);
}

class SecurePrivateStore implements PrivateStore {
  const SecurePrivateStore();
  static const _storage = FlutterSecureStorage();
  @override
  Future<String?> read(String key) => _storage.read(key: key);
  @override
  Future<void> write(String key, String? value) => value == null
      ? _storage.delete(key: key)
      : _storage.write(key: key, value: value);
}

class BuyerApi {
  BuyerApi({String? baseUrl, http.Client? client})
    : base = Uri.parse(
        baseUrl ??
            const String.fromEnvironment(
              'API_BASE_URL',
              defaultValue: 'http://10.0.2.2:8000/api/v1',
            ),
      ),
      _client = client ?? http.Client();
  final Uri base;
  final http.Client _client;
  String? token;
  VoidCallback? onExpired;
  String get namespace =>
      'farsell.buyer.${Uri.encodeComponent(base.toString())}';
  Uri uri(String path, [Map<String, String>? query]) => base.replace(
    path: '${base.path.replaceFirst(RegExp(r'/$'), '')}/$path',
    queryParameters: query,
  );

  Future<http.Response> _send(
    String method,
    String path, {
    Json? body,
    Map<String, String>? query,
    String? key,
    bool authenticated = true,
  }) async {
    if (!['http', 'https'].contains(base.scheme) ||
        base.host.isEmpty ||
        (!kDebugMode && base.scheme != 'https') ||
        base.userInfo.isNotEmpty) {
      throw const ApiFailure('A secure API server address is required.');
    }
    final sentToken = authenticated ? token : null;
    final request = http.Request(method, uri(path, query));
    request.headers.addAll({
      'Accept': 'application/json',
      if (sentToken != null) 'Authorization': 'Bearer $sentToken',
      'Idempotency-Key': ?key,
    });
    if (body != null) {
      request.headers['Content-Type'] = 'application/json';
      request.body = jsonEncode(body);
    }
    http.Response response;
    try {
      response = await _client
          .send(request)
          .then(http.Response.fromStream)
          .timeout(const Duration(seconds: 20));
    } catch (_) {
      throw const ApiFailure(
        'Cannot reach FarSell. Check your connection and retry.',
      );
    }
    // Never let a response from a previous account update the current account.
    if (authenticated && sentToken != token) {
      throw const ApiFailure(
        'The signed-in account changed. Refresh this page.',
        code: 'session_changed',
        status: 409,
      );
    }
    if (response.statusCode >= 400) {
      Json error = {};
      try {
        error = object(jsonDecode(response.body));
      } catch (_) {
        /* Safe fallback; never print server bodies. */
      }
      if (authenticated && sentToken != null && response.statusCode == 401) {
        onExpired?.call();
      }
      throw ApiFailure(
        error['message'] as String? ??
            'The request could not be completed. Please retry.',
        status: response.statusCode,
        code: error['code'] as String? ?? 'request_failed',
        errors: error['errors'] is Map ? object(error['errors']) : {},
        retryAfter: int.tryParse(response.headers['retry-after'] ?? ''),
      );
    }
    return response;
  }

  Future<Json> call(
    String method,
    String path, {
    Json? body,
    Map<String, String>? query,
    String? key,
    bool authenticated = true,
  }) async {
    final response = await _send(
      method,
      path,
      body: body,
      query: query,
      key: key,
      authenticated: authenticated,
    );
    if (response.statusCode == 204) return {};
    try {
      return object(jsonDecode(response.body));
    } catch (_) {
      throw const ApiFailure(
        'FarSell returned an unreadable response. Refresh before trying again.',
      );
    }
  }

  Future<Json> data(
    String method,
    String path, {
    Json? body,
    String? key,
    bool authenticated = true,
  }) async {
    final result = await call(
      method,
      path,
      body: body,
      key: key,
      authenticated: authenticated,
    );
    try {
      return object(result['data']);
    } catch (_) {
      throw const ApiFailure(
        'FarSell returned incomplete data. Refresh before trying again.',
      );
    }
  }

  Future<Uint8List> proof(String shipment) async =>
      (await _send('GET', 'buyer/shipments/$shipment/proof-photo')).bodyBytes;
  Future<Json> multipart(
    String path,
    Map<String, String> fields,
    List<BuyerUpload> files,
    String key,
  ) async {
    if (!['http', 'https'].contains(base.scheme) ||
        base.host.isEmpty ||
        (!kDebugMode && base.scheme != 'https')) {
      throw const ApiFailure('A secure API server address is required.');
    }
    final ownerToken = token;
    if (ownerToken == null) {
      throw const ApiFailure('Sign in again to continue.', status: 401);
    }
    final request = http.MultipartRequest('POST', uri(path))
      ..headers.addAll({
        'Accept': 'application/json',
        'Authorization': 'Bearer $ownerToken',
        'Idempotency-Key': key,
      })
      ..fields.addAll(fields);
    for (final file in files) {
      request.files.add(
        http.MultipartFile.fromBytes(
          file.field,
          file.bytes,
          filename: file.name,
        ),
      );
    }
    http.Response response;
    try {
      response = await _client
          .send(request)
          .then(http.Response.fromStream)
          .timeout(const Duration(seconds: 75));
    } catch (_) {
      throw const ApiFailure(
        'Upload interrupted. Retry the same submission to check its result.',
      );
    }
    if (ownerToken != token) {
      throw const ApiFailure(
        'The signed-in account changed.',
        status: 409,
        code: 'session_changed',
      );
    }
    Json json;
    try {
      json = object(jsonDecode(response.body));
    } catch (_) {
      throw const ApiFailure(
        'The submission result is unclear. Retry the same submission.',
      );
    }
    if (response.statusCode >= 400) {
      if (response.statusCode == 401) onExpired?.call();
      throw ApiFailure(
        json['message'] as String? ?? 'Upload failed.',
        status: response.statusCode,
        code: json['code'] as String? ?? 'upload_failed',
        errors: json['errors'] is Map ? object(json['errors']) : {},
      );
    }
    return object(json['data']);
  }

  Future<Uint8List> privateBytes(String path) async =>
      (await _send('GET', path)).bodyBytes;
  void close() => _client.close();
}

// The new mobile contract uses UUIDs and integer centavos. The legacy repository
// remains available for older clients, but the buyer app runs this adapter.
class BuyerCatalog extends CatalogRepository {
  BuyerCatalog(this.api) : super(baseUrl: api.base.toString());
  final BuyerApi api;
  final Map<String, String> _cursors = {};
  @override
  Future<CatalogPage> products({
    int page = 1,
    String query = '',
    String sort = 'newest',
  }) async {
    final scope = '$query|$sort';
    try {
      final cursor = page > 1 ? _cursors['$scope|$page'] : null;
      if (page > 1 && cursor == null) {
        throw const CatalogFailure(
          'Refresh the catalogue before loading more.',
        );
      }
      final json = await api.call(
        'GET',
        'products',
        authenticated: false,
        query: {
          'limit': '20',
          'sort': sort,
          if (query.trim().isNotEmpty) 'q': query.trim(),
          'cursor': ?cursor,
        },
      );
      final next = json['next_cursor'] as String?;
      if (next != null) _cursors['$scope|${page + 1}'] = next;
      final products = objects(json['data']).map(Product.fromMobile).toList();
      return CatalogPage(
        products: products,
        hasMore: json['has_more'] == true,
        total: (page - 1) * 20 + products.length,
      );
    } on ApiFailure catch (e) {
      throw CatalogFailure(e.display);
    } on CatalogFailure {
      rethrow;
    } catch (_) {
      throw const CatalogFailure(
        'The catalogue could not be loaded. Please retry.',
      );
    }
  }

  Future<Product> resolve(Product product) async {
    if (product.id is String) return product;
    // Upgrade old device selections by exact slug, never by a guessed database ID.
    for (var page = 1; page <= 50; page++) {
      final result = await products(page: page, query: product.name);
      for (final p in result.products) {
        if (p.slug == product.slug) return p;
      }
      if (!result.hasMore) break;
    }
    throw const CatalogFailure(
      'This saved listing is unavailable. Remove it and choose an active listing.',
    );
  }

  @override
  Future<Availability> availability(Product product) async {
    try {
      final current = await resolve(product);
      return Availability.fromMobile(
        await api.data('GET', 'products/${current.id}', authenticated: false),
      );
    } on ApiFailure catch (e) {
      throw CatalogFailure(e.display);
    } on CatalogFailure {
      rethrow;
    } catch (_) {
      throw const CatalogFailure('Stock could not be checked. Please retry.');
    }
  }
}
