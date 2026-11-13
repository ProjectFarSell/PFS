import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:material_ui/material_ui.dart';

import 'package:farsell_mobile/src/app.dart';
import 'package:farsell_mobile/src/catalog.dart';
import 'package:farsell_mobile/src/product_screen.dart';
import 'package:farsell_mobile/src/shop_state.dart';

Map<String, dynamic> productJson({int stock = 3}) => {
  'id': 1,
  'slug': 'demo-shirt',
  'name': 'Everyday shirt',
  'description': 'A soft everyday shirt.',
  'price': 199.95,
  'compare_at_price': null,
  'stock': stock,
  'image_url': null,
  'shop': {'name': 'Demo Seller Shop'},
  'category': {'name': 'Clothing'},
};
Availability availability({bool variants = false, int stock = 3}) =>
    Availability.fromJson({
      'has_variants': variants,
      'stock': stock,
      'variants': variants
          ? [
              {
                'id': 10,
                'sku': 'SHRT-BKS1',
                'stock': stock,
                'price': 199.95,
                'options': {'Size': 'S', 'Color': 'Black'},
              },
              {
                'id': 11,
                'sku': 'SHRT-WHM1',
                'stock': 0,
                'price': 249,
                'options': {'Size': 'M', 'Color': 'White'},
              },
            ]
          : [],
    });

class MemoryStore implements SelectionStore {
  String? value;
  bool fail = false;
  @override
  Future<String?> read() async => value;
  @override
  Future<void> write(String data) async {
    if (fail) throw StateError('Disk unavailable');
    value = data;
  }
}

class FakeCatalog extends CatalogRepository {
  final List<Completer<CatalogPage>> pending = [];
  bool defer = false, failStock = false;
  int currentStock = 3;
  bool useVariants = false;
  @override
  Future<CatalogPage> products({
    int page = 1,
    String query = '',
    String sort = 'newest',
  }) async {
    if (defer) {
      final result = Completer<CatalogPage>();
      pending.add(result);
      return result.future;
    }
    return CatalogPage(
      products: [Product.fromJson(productJson())],
      hasMore: false,
      total: 1,
    );
  }

  @override
  Future<Availability> availability(Product product) async {
    if (failStock) throw const CatalogFailure('Stock unavailable');
    return availabilityFixture();
  }

  Availability availabilityFixture() =>
      availabilityData(variants: useVariants, stock: currentStock);
}

Availability availabilityData({required bool variants, required int stock}) =>
    availability(variants: variants, stock: stock);

void main() {
  test('legacy peso prices convert to centavos and format correctly', () {
    final product = Product.fromJson(productJson());
    expect(product.price, 19995);
    expect(pesos(123456), '₱1,234.56');
    expect(Product.fromJson(product.toJson()).price, 19995);
  });

  test(
    'catalogue passes bounded search/sort/page and preserves pagination',
    () async {
      final repo = CatalogRepository(
        baseUrl: 'http://localhost:8000/api/v1',
        client: MockClient((request) async {
          expect(request.url.path, '/api/v1/catalog');
          expect(request.url.queryParameters, {
            'page': '2',
            'q': 'shirt',
            'sort': 'price_asc',
          });
          expect(request.headers['Accept'], 'application/json');
          return http.Response(
            jsonEncode({
              'data': [productJson()],
              'current_page': 2,
              'last_page': 3,
              'total': 45,
            }),
            200,
          );
        }),
      );
      final page = await repo.products(
        page: 2,
        query: ' shirt ',
        sort: 'price_asc',
      );
      expect(page.hasMore, isTrue);
      expect(page.total, 45);
      expect(page.products.single.price, 19995);
      repo.close();
    },
  );

  test('provider errors are safe and malformed data does not become an empty success', () async {
    for (final response in [
      http.Response('<secret>server stack trace</secret>', 500),
      http.Response('{"data": []}', 200),
    ]) {
      final repo = CatalogRepository(client: MockClient((_) async => response));
      await expectLater(
        repo.products(),
        throwsA(
          isA<CatalogFailure>().having(
            (e) => e.message.contains('secret'),
            'no raw server body',
            isFalse,
          ),
        ),
      );
      repo.close();
    }
  });

  test('stock endpoint uses public slug and excludes API prefix', () async {
    final repo = CatalogRepository(
      baseUrl: 'http://localhost:8000/api/v1',
      client: MockClient((request) async {
        expect(request.url.path, '/p/demo-shirt/availability');
        return http.Response(
          '{"has_variants":false,"stock":2,"variants":[]}',
          200,
        );
      }),
    );
    expect((await repo.availability(Product.fromJson(productJson()))).stock, 2);
    repo.close();
  });

  test('invalid and sold-out option combinations cannot become a purchasable variant', () {
    final stock = availability(variants: true);
    expect(stock.selected({'Size': 'S'}), isNull);
    expect(stock.selected({'Size': 'S', 'Color': 'White'}), isNull);
    expect(stock.selected({'Size': 'M', 'Color': 'White'})!.stock, 0);
    expect(stock.selected({'Size': 'S', 'Color': 'Black'})!.sku, 'SHRT-BKS1');
  });

  test(
    'cart enforces option and aggregate stock limits and persists selections',
    () async {
      final store = MemoryStore();
      final state = ShopState(store: store);
      final product = Product.fromJson(productJson());
      final stock = availability(variants: true);
      expect(state.add(product, stock, null, 1), isNotNull);
      expect(state.add(product, stock, stock.variants.first, 2), isNull);
      expect(state.add(product, stock, stock.variants.first, 2), isNotNull);
      expect(state.add(product, stock, stock.variants.last, 1), isNotNull);
      state.toggleSaved(product);
      state.setQuantity(state.cart.single, 3);
      await state.flush();
      final restored = ShopState(store: store);
      await restored.restore();
      expect(restored.cartCount, 3);
      expect(restored.subtotal, 59985);
      expect(restored.cart.single.sku, 'SHRT-BKS1');
      expect(restored.saved.single.id, 1);
      restored.setQuantity(restored.cart.single, 0);
      expect(restored.cart, isEmpty);
      state.dispose();
      restored.dispose();
    },
  );

  test(
    'corrupt local storage and write failures do not crash browsing',
    () async {
      final store = MemoryStore()..value = 'broken json';
      final state = ShopState(store: store);
      await state.restore();
      expect(state.storageWarning, isTrue);
      expect(state.cart, isEmpty);
      store.fail = true;
      state.toggleSaved(Product.fromJson(productJson()));
      await state.flush();
      expect(state.saved, hasLength(1));
      state.dispose();
    },
  );

  testWidgets(
    'native storefront replaces counter and supports saved/cart navigation at narrow width',
    (tester) async {
      tester.view.physicalSize = const Size(320, 720);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final state = ShopState(store: MemoryStore());
      final repo = FakeCatalog();
      await tester.pumpWidget(FarSellApp(repository: repo, state: state));
      await tester.pumpAndSettle();
      expect(find.text('Flutter Demo Home Page'), findsNothing);
      await tester.scrollUntilVisible(
        find.text('Everyday shirt'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Everyday shirt'), findsOneWidget);
      await tester.ensureVisible(find.byTooltip('Save Everyday shirt'));
      await tester.tap(find.byTooltip('Save Everyday shirt'));
      await tester.pump();
      await tester.tap(find.text('Saved').last);
      await tester.pumpAndSettle();
      expect(find.text('Your saved finds'), findsOneWidget);
      await tester.tap(find.text('Explore').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Cart'));
      await tester.pumpAndSettle();
      expect(find.text('Your cart is waiting'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
      repo.close();
      state.dispose();
    },
  );

  testWidgets('slow older search result cannot replace newer results', (
    tester,
  ) async {
    final repo = FakeCatalog()..defer = true;
    final state = ShopState(store: MemoryStore());
    await tester.pumpWidget(FarSellApp(repository: repo, state: state));
    await tester.enterText(find.byType(TextField), 'new search');
    await tester.pump(const Duration(milliseconds: 450));
    expect(repo.pending, hasLength(2));
    repo.pending.last.complete(
      CatalogPage(products: [], hasMore: false, total: 0),
    );
    await tester.pumpAndSettle();
    repo.pending.first.complete(
      CatalogPage(
        products: [Product.fromJson(productJson())],
        hasMore: false,
        total: 1,
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Everyday shirt'), findsNothing);
    expect(find.text('No finds just yet'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    repo.close();
    state.dispose();
  });

  testWidgets(
    'stock is rechecked before add and sellout prevents a stale cart addition',
    (tester) async {
      final repo = FakeCatalog();
      final state = ShopState(store: MemoryStore());
      await tester.pumpWidget(
        MaterialApp(
          home: ProductScreen(
            product: Product.fromJson(productJson()),
            repository: repo,
            state: state,
          ),
        ),
      );
      await tester.pumpAndSettle();
      repo.currentStock = 0;
      await tester.tap(find.text('Add to cart'));
      await tester.pumpAndSettle();
      expect(state.cartCount, 0);
      await tester.scrollUntilVisible(
        find.text('OUT OF STOCK'),
        250,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('OUT OF STOCK'), findsOneWidget);
      expect(
        tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
        isNull,
      );
      await tester.pumpWidget(const SizedBox());
      repo.close();
      state.dispose();
    },
  );
}
