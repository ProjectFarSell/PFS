import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'catalog.dart';

abstract class SelectionStore {
  Future<String?> read();
  Future<void> write(String value);
}

class DeviceSelectionStore implements SelectionStore {
  final _preferences = SharedPreferencesAsync();
  static const _key = 'farsell.guest-selections.v1';
  @override
  Future<String?> read() => _preferences.getString(_key);
  @override
  Future<void> write(String value) => _preferences.setString(_key, value);
}

class CartLine {
  CartLine({
    required this.product,
    required this.quantity,
    required this.price,
    required this.stock,
    this.variantId,
    this.sku,
    this.options = const {},
  });
  final Product product;
  final Object? variantId;
  final String? sku;
  final Map<String, String> options;
  final int price;
  int quantity, stock;
  String get key => '${product.id}:${variantId ?? 'plain'}';
  String get optionLabel =>
      options.entries.map((e) => '${e.key}: ${e.value}').join(' · ');
  int get total => price * quantity;

  factory CartLine.fromJson(Map<String, dynamic> json) => CartLine(
    product: Product.fromJson(
      Map<String, dynamic>.from(json['product'] as Map),
    ),
    quantity: (json['quantity'] as int).clamp(1, 99),
    price: json['price'] as int,
    stock: (json['stock'] as int).clamp(0, 2147483647),
    variantId: json['variant_id'] as Object?,
    sku: json['sku'] as String?,
    options: Map<String, String>.from(json['options'] as Map),
  );
  Map<String, dynamic> toJson() => {
    'product': product.toJson(),
    'quantity': quantity,
    'price': price,
    'stock': stock,
    'variant_id': variantId,
    'sku': sku,
    'options': options,
  };
}

class ShopState extends ChangeNotifier {
  ShopState({SelectionStore? store}) : _store = store ?? DeviceSelectionStore();
  final SelectionStore _store;
  final Map<Object, Product> _saved = {};
  final Map<String, CartLine> _cart = {};
  final Set<String> _merged = {};
  Future<void> _writes = Future.value();
  bool storageWarning = false;
  List<Product> get saved => List.unmodifiable(_saved.values);
  List<CartLine> get cart => List.unmodifiable(_cart.values);
  int get cartCount => _cart.values.fold(0, (sum, line) => sum + line.quantity);
  int get subtotal => _cart.values.fold(0, (sum, line) => sum + line.total);
  bool isSaved(Product product) => _saved.containsKey(product.id);

  Future<void> restore() async {
    try {
      final value = await _store.read();
      if (value == null) return;
      final json = jsonDecode(value) as Map;
      _merged.addAll((json['applied_merges'] as List? ?? []).cast<String>());
      final saved = (json['saved'] as List)
          .take(100)
          .map((p) => Product.fromJson(Map<String, dynamic>.from(p as Map)))
          .toList();
      final cart = (json['cart'] as List)
          .take(100)
          .map((p) => CartLine.fromJson(Map<String, dynamic>.from(p as Map)))
          .toList();
      _saved.addEntries(saved.map((p) => MapEntry(p.id, p)));
      _cart.addEntries(cart.map((p) => MapEntry(p.key, p)));
    } catch (_) {
      storageWarning = true;
      _saved.clear();
      _cart.clear();
    }
  }

  void toggleSaved(Product product) {
    if (_saved.containsKey(product.id)) {
      _saved.remove(product.id);
    } else {
      if (_saved.length >= 100) return;
      _saved[product.id] = product;
    }
    _changed();
  }

  String? add(
    Product product,
    Availability stock,
    Variant? variant,
    int quantity,
  ) {
    if (stock.hasVariants &&
        (variant == null || !stock.variants.contains(variant))) {
      return 'Choose an available option first.';
    }
    final available = variant?.stock ?? stock.stock;
    final key = '${product.id}:${variant?.id ?? 'plain'}';
    final total = (_cart[key]?.quantity ?? 0) + quantity;
    if (quantity < 1 || total > 99 || total > available) {
      return 'Only ${available.clamp(0, 99)} units can be selected.';
    }
    if (!_cart.containsKey(key) && _cart.length >= 100) {
      return 'Your cart has reached its limit.';
    }
    _cart[key] = CartLine(
      product: product,
      quantity: total,
      price: variant?.price ?? product.price,
      stock: available,
      variantId: variant?.id,
      sku: variant?.sku ?? product.sku,
      options: variant?.options ?? const {},
    );
    _changed();
    return null;
  }

  void setQuantity(CartLine line, int quantity) {
    if (!_cart.containsKey(line.key)) return;
    if (quantity <= 0) {
      _cart.remove(line.key);
    } else if (quantity <= line.stock && quantity <= 99) {
      line.quantity = quantity;
    } else {
      return;
    }
    _changed();
  }

  void remove(CartLine line) {
    _cart.remove(line.key);
    _changed();
  }

  Future<void> flush() => _writes;

  void removeMerged(CartLine line, int mergedQuantity) {
    final current = _cart[line.key];
    if (current == null) return;
    if (current.quantity <= mergedQuantity) {
      _cart.remove(line.key);
    } else {
      current.quantity -= mergedQuantity;
    }
    _changed();
  }

  Future<void> acknowledgeMerge(
    String id,
    List<Map<String, dynamic>> selections,
    Set<dynamic> rejected,
  ) async {
    if (_merged.contains(id)) return;
    for (var i = 0; i < selections.length; i++) {
      if (rejected.contains(i)) continue;
      final current = _cart[selections[i]['key']];
      if (current == null) continue;
      final count = selections[i]['qty'] as int;
      if (current.quantity <= count) {
        _cart.remove(current.key);
      } else {
        current.quantity -= count;
      }
    }
    _merged.add(id);
    if (_merged.length > 100) _merged.remove(_merged.first);
    _changed();
    await flush();
    if (storageWarning) {
      throw StateError('Device selections could not be saved.');
    }
  }

  void _changed() {
    final snapshot = jsonEncode({
      'applied_merges': _merged.toList(),
      'saved': _saved.values.map((p) => p.toJson()).toList(),
      'cart': _cart.values.map((p) => p.toJson()).toList(),
    });
    // Serialize writes so a slow older snapshot cannot overwrite a newer cart.
    _writes = _writes.then((_) => _store.write(snapshot)).catchError((
      Object _,
    ) {
      storageWarning = true;
    });
    notifyListeners();
  }
}
