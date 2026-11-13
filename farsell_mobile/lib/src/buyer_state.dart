import 'dart:convert';

import 'package:flutter/widgets.dart';

import 'buyer_api.dart';
import 'catalog.dart';
import 'shop_state.dart';

class BuyerState extends ChangeNotifier {
  BuyerState({required this.api, required this.guest, PrivateStore? store})
    : store = store ?? const SecurePrivateStore() {
    api.onExpired = _expire;
  }
  final BuyerApi api;
  final ShopState guest;
  final PrivateStore store;
  Json? user, cart, pendingOrder;
  bool busy = false, restoring = true;
  String? notice;
  int revision = 0;
  String? liveStatus;
  Future<void> Function()? enablePush;
  void invalidate() {
    revision++;
    notifyListeners();
  }

  void liveMessage(String message) {
    liveStatus = message;
    notifyListeners();
  }

  bool get signedIn => user != null;
  int get version => cart?['version'] as int? ?? 0;
  List<Json> get lines => cart == null ? [] : objects(cart!['lines']);
  String _key(String name) => '${api.namespace}.$name';
  void updateSession(Json updated) {
    if (updated['id'] != user?['id'] || updated['role'] != 'buyer') {
      throw const ApiFailure(
        'The account changed. Sign in again.',
        status: 409,
      );
    }
    user = updated;
    notifyListeners();
  }

  Future<void> _tokenWrites = Future.value();
  Future<void> _saveToken(String? token) {
    _tokenWrites = _tokenWrites
        .catchError((Object _) {})
        .then((_) => store.write(_key('token'), token));
    return _tokenWrites;
  }

  void _expire() {
    api.token = null;
    user = null;
    cart = null;
    pendingOrder = null;
    notice = 'Your session expired. Sign in again to continue.';
    _saveToken(null).catchError((Object _) {
      notice = 'Session expired. Secure storage could not be cleared; please retry signing out.';
    });
    notifyListeners();
  }

  Future<void> restore() async {
    restoring = true;
    notifyListeners();
    try {
      api.token = await store.read(_key('token'));
      if (api.token != null) {
        user = await api.data('GET', 'session');
        if (user!['role'] != 'buyer') {
          await logout();
          return;
        }
        await _loadPending();
        await refreshCart();
      }
    } on ApiFailure catch (e) {
      notice = e.display;
    } catch (_) {
      notice =
          'Secure account storage is unavailable. Retry before signing in.';
    } finally {
      restoring = false;
      notifyListeners();
    }
  }

  Future<void> finishLogin(Json result) async {
    if (object(result['user'])['role'] != 'buyer') {
      throw const ApiFailure(
        'Use a buyer account in the FarSell shopping app.',
        status: 403,
      );
    }
    // Persist before exposing an authenticated screen. No SharedPreferences tokens.
    final token = result['access_token'] as String;
    try {
      await _saveToken(token);
    } catch (_) {
      // Revoke an issued token that cannot be stored securely.
      api.token = token;
      try {
        await api.call('POST', 'auth/logout');
      } catch (_) {
        /* Expiry also limits this token. */
      }
      api.token = null;
      throw const ApiFailure(
        'Secure sign-in storage is unavailable. Please retry.',
      );
    }
    api.token = token;
    user = object(result['user']);
    notice = null;
    await _loadPending();
    notifyListeners();
    try {
      await refreshCart();
    } on ApiFailure catch (e) {
      notice = e.display;
    }
  }

  Future<void> _loadPending() async {
    final value = await store.read(_key('order.${user!['id']}'));
    pendingOrder = value == null ? null : object(jsonDecode(value));
  }

  Future<void> logout() async {
    // A network failure leaves the session active so revocation can be retried.
    if (api.token != null) await api.call('POST', 'auth/logout');
    await _saveToken(null);
    api.token = null;
    user = null;
    cart = null;
    pendingOrder = null;
    notice = null;
    notifyListeners();
  }

  Future<void> refreshCart() async {
    if (!signedIn) return;
    cart = await api.data('GET', 'buyer/cart');
    notifyListeners();
  }

  Future<T> exclusive<T>(Future<T> Function() action) async {
    if (busy) {
      throw const ApiFailure('Please wait for the current action to finish.');
    }
    busy = true;
    notifyListeners();
    try {
      return await action();
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  Future<void> add(
    Product product,
    Variant? variant,
    int qty,
    BuyerCatalog catalog,
  ) => exclusive(() async {
    await refreshCart();
    final current = await catalog.resolve(product);
    try {
      cart = await api.data(
        'POST',
        'buyer/cart/lines',
        body: {
          'product_id': current.id,
          'variant_id': variant?.id,
          'qty': qty,
          'cart_version': version,
        },
      );
    } on ApiFailure catch (e) {
      try {
        await refreshCart();
      } catch (_) {
        /* Show original error. */
      }
      if (e.uncertain) {
        throw const ApiFailure(
          'The request was interrupted. Check your cart before adding again.',
        );
      }
      rethrow;
    }
  });

  Future<void> quantity(Json line, int qty) => exclusive(() async {
    try {
      cart = await api.data(
        'PATCH',
        'buyer/cart/lines/${line['id']}',
        body: {'qty': qty, 'cart_version': version},
      );
    } on ApiFailure {
      try {
        await refreshCart();
      } catch (_) {}
      rethrow;
    }
  });

  Future<void> mergeGuest(BuyerCatalog catalog) => exclusive(() async {
    await refreshCart();
    final owner = user!['id'] as String;
    final storageKey = _key('merge.$owner');
    final old = await store.read(storageKey);
    Json pending;
    if (old != null) {
      pending = object(jsonDecode(old));
    } else {
      if (guest.cart.isEmpty) return;
      final converted = <Json>[];
      final selections = <Json>[];
      for (final line in guest.cart) {
        final product = await catalog.resolve(line.product);
        final stock = await catalog.availability(product);
        Variant? variant;
        if (stock.hasVariants) {
          variant = stock.selected(line.options);
          if (variant == null) {
            throw const ApiFailure(
              'A saved item has changed options. Remove it and select its options again.',
            );
          }
        }
        converted.add({
          'product_id': product.id,
          'variant_id': variant?.id,
          'qty': line.quantity,
        });
        selections.add({'key': line.key, 'qty': line.quantity});
      }
      pending = {
        'body': {
          'merge_id': requestId(),
          'cart_version': version,
          'lines': converted,
        },
        'selections': selections,
      };
      await store.write(storageKey, jsonEncode(pending));
    }
    Json result;
    try {
      result = await api.data(
        'POST',
        'buyer/cart/merge',
        body: object(pending['body']),
      );
    } on ApiFailure catch (e) {
      if (!e.uncertain) await store.write(storageKey, null);
      rethrow;
    }
    cart = object(result['cart']);
    final rejected = objects(result['rejected_lines'])
        .map((v) => v['index'])
        .toSet();
    final selections = objects(pending['selections']);
    await guest.acknowledgeMerge(
      object(pending['body'])['merge_id'] as String,
      selections,
      rejected,
    );
    await store.write(storageKey, null);
    notice = rejected.isEmpty
        ? 'Device cart added to your account.'
        : '${rejected.length} device item(s) could not be added. Check their options and stock.';
  });

  Future<Json> place(Json quote) => exclusive(() async {
    final owner = user!['id'] as String;
    final path = _key('order.$owner');
    pendingOrder ??= {
      'key': requestId(),
      'body': {
        'quote_id': quote['id'],
        'cart_version': quote['cart_version'],
        'payment_method': 'cod',
      },
    };
    await store.write(path, jsonEncode(pendingOrder));
    notifyListeners();
    Json result;
    try {
      result = await api.data(
        'POST',
        'buyer/orders',
        body: object(pendingOrder!['body']),
        key: pendingOrder!['key'] as String,
      );
    } on ApiFailure catch (e) {
      if (!e.uncertain && e.status != 401 && e.code != 'session_changed') {
        await store.write(path, null);
        pendingOrder = null;
      }
      rethrow;
    }
    await store.write(path, null);
    pendingOrder = null;
    try {
      await refreshCart();
    } catch (_) {
      cart = null;
    }
    return result;
  });

  @override
  void dispose() {
    api.onExpired = null;
    super.dispose();
  }
}

class BuyerScope extends InheritedNotifier<BuyerState> {
  const BuyerScope({super.key, required BuyerState state, required super.child})
    : super(notifier: state);
  static BuyerState? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<BuyerScope>()?.notifier;
  static BuyerState of(BuildContext context) => maybeOf(context)!;
}
