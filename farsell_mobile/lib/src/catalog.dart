import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

String pesos(int cents) {
  final parts = (cents / 100).toStringAsFixed(2).split('.');
  final whole = parts.first.replaceAllMapped(
    RegExp(r'(\d)(?=(\d{3})+(?!\d))'),
    (m) => '${m[1]},',
  );
  return '₱$whole.${parts.last}';
}

// Existing public catalogue prices are pesos. Convert once at the adapter boundary.
int _cents(dynamic value) {
  final number = num.tryParse('$value');
  if (number == null || !number.isFinite || number < 0) {
    throw const FormatException('Invalid price');
  }
  return (number * 100).round();
}

class Product {
  const Product({
    required this.id,
    required this.slug,
    required this.name,
    required this.description,
    required this.price,
    required this.stock,
    required this.shop,
    required this.category,
    this.imageUrl,
    this.comparePrice,
    this.sku,
  });
  final Object id;
  final String slug, name, description, shop, category;
  final int price, stock;
  final int? comparePrice;
  final String? imageUrl;
  final String? sku;

  factory Product.fromJson(Map<String, dynamic> json) {
    if (json.containsKey('unit_price')) return Product.fromMobile(json);
    final id = json['id'];
    final slug = json['slug'];
    final name = json['name'];
    if (id is! int || id < 1 || slug is! String || name is! String) {
      throw const FormatException('Invalid product');
    }
    return Product(
      id: id,
      slug: slug,
      name: name,
      description: json['description'] as String? ?? '',
      price: _cents(json['price']),
      comparePrice: json['compare_at_price'] == null
          ? null
          : _cents(json['compare_at_price']),
      stock: (json['stock'] as num).toInt().clamp(0, 2147483647),
      shop: (json['shop'] as Map)['name'] as String,
      category: (json['category'] as Map)['name'] as String,
      imageUrl: json['image_url'] as String?,
    );
  }

  factory Product.fromMobile(Map<String, dynamic> json) => Product(
    id: json['id'] as String,
    slug: json['slug'] as String,
    name: json['name'] as String,
    description: json['description'] as String? ?? '',
    price: json['unit_price'] as int,
    stock: json['stock'] as int,
    shop: (json['shop'] as Map)['name'] as String,
    category: (json['category'] as Map)['name'] as String,
    imageUrl: json['image_url'] as String?,
    sku: json['sku'] as String?,
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'slug': slug,
    'name': name,
    'description': description,
    'price': price / 100,
    'compare_at_price': comparePrice == null ? null : comparePrice! / 100,
    'stock': stock,
    'shop': {'name': shop},
    'category': {'name': category},
    'image_url': imageUrl,
    if (id is String) 'unit_price': price,
    'sku': sku,
  };
}

class Variant {
  Variant.fromMobile(Map<String, dynamic> json)
    : id = json['id'] as String,
      sku = json['sku'] as String,
      stock = json['stock'] as int,
      price = json['unit_price'] as int,
      options = Map<String, String>.from(json['options'] as Map);
  Variant.fromJson(Map<String, dynamic> json)
    : id = json['id'] as int,
      sku = json['sku'] as String,
      stock = (json['stock'] as num).toInt(),
      price = _cents(json['price']),
      options = Map<String, String>.from(json['options'] as Map);
  final Object id;
  final int stock, price;
  final String sku;
  final Map<String, String> options;
}

class Availability {
  Availability.fromMobile(Map<String, dynamic> json)
    : hasVariants = json['has_variants'] as bool,
      stock = json['stock'] as int,
      variants = (json['variants'] as List)
          .map((v) => Variant.fromMobile(Map<String, dynamic>.from(v as Map)))
          .toList();
  Availability.fromJson(Map<String, dynamic> json)
    : hasVariants = json['has_variants'] as bool,
      stock = (json['stock'] as num).toInt(),
      variants = (json['variants'] as List)
          .map((v) => Variant.fromJson(Map<String, dynamic>.from(v as Map)))
          .toList();
  final bool hasVariants;
  final int stock;
  final List<Variant> variants;
  List<String> get dimensions =>
      {for (final variant in variants) ...variant.options.keys}.toList();
  List<String> valuesFor(String dimension) => {
    for (final variant in variants)
      if (variant.options.containsKey(dimension)) variant.options[dimension]!,
  }.toList();
  Variant? selected(Map<String, String> choices) {
    if (choices.length != dimensions.length) return null;
    for (final variant in variants) {
      if (variant.options.length == choices.length &&
          choices.entries.every((e) => variant.options[e.key] == e.value)) {
        return variant;
      }
    }
    return null;
  }
}

class CatalogPage {
  CatalogPage({
    required this.products,
    required this.hasMore,
    required this.total,
  });
  final List<Product> products;
  final bool hasMore;
  final int total;
}

class CatalogFailure implements Exception {
  const CatalogFailure(this.message);
  final String message;
  @override
  String toString() => message;
}

class CatalogRepository {
  CatalogRepository({String? baseUrl, http.Client? client})
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

  Future<Map<String, dynamic>> _get(Uri uri) async {
    if (!['http', 'https'].contains(uri.scheme) ||
        uri.host.isEmpty ||
        (!kDebugMode && uri.scheme != 'https')) {
      throw const CatalogFailure('A secure server address is required.');
    }
    try {
      final response = await _client
          .get(uri, headers: {'Accept': 'application/json'})
          .timeout(const Duration(seconds: 12));
      if (response.statusCode == 404) {
        throw const CatalogFailure('This listing is no longer available.');
      }
      if (response.statusCode == 429) {
        throw const CatalogFailure(
          'Too many requests. Wait a moment and retry.',
        );
      }
      if (response.statusCode != 200) {
        throw const CatalogFailure(
          'The shop is unavailable right now. Try again.',
        );
      }
      return Map<String, dynamic>.from(jsonDecode(response.body) as Map);
    } on CatalogFailure {
      rethrow;
    } on TimeoutException {
      throw const CatalogFailure('The connection took too long. Try again.');
    } catch (_) {
      throw const CatalogFailure(
        'Cannot reach FarSell. Check your connection and retry.',
      );
    }
  }

  Future<CatalogPage> products({
    int page = 1,
    String query = '',
    String sort = 'newest',
  }) async {
    final json = await _get(
      base.replace(
        path: '${base.path.replaceFirst(RegExp(r'/$'), '')}/catalog',
        queryParameters: {
          'page': '$page',
          'sort': sort,
          if (query.trim().isNotEmpty) 'q': query.trim(),
        },
      ),
    );
    try {
      return CatalogPage(
        products: (json['data'] as List)
            .map((p) => Product.fromJson(Map<String, dynamic>.from(p as Map)))
            .toList(),
        hasMore: (json['current_page'] as int) < (json['last_page'] as int),
        total: json['total'] as int,
      );
    } catch (_) {
      throw const CatalogFailure(
        'The catalogue could not be loaded. Please retry.',
      );
    }
  }

  Future<Availability> availability(Product product) async {
    // Existing public stock endpoint. This does not share browser cookies/login.
    final json = await _get(
      base.replace(
        path: '/p/${Uri.encodeComponent(product.slug)}/availability',
        query: '',
      ),
    );
    try {
      return Availability.fromJson(json);
    } catch (_) {
      throw const CatalogFailure('Stock could not be checked. Please retry.');
    }
  }

  String? imageUrl(String? value) {
    final uri = Uri.tryParse(value ?? '');
    if (uri == null || !['http', 'https'].contains(uri.scheme)) return null;
    if (kDebugMode && ['localhost', '127.0.0.1'].contains(uri.host)) {
      return uri.replace(host: base.host).toString();
    }
    return uri.toString();
  }

  void close() => _client.close();
}
