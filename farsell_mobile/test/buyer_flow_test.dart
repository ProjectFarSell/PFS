import 'dart:async';
import 'dart:convert';

import 'package:material_ui/material_ui.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import 'package:farsell_mobile/src/app.dart';
import 'package:farsell_mobile/src/buyer_api.dart';
import 'package:farsell_mobile/src/buyer_state.dart';
import 'package:farsell_mobile/src/catalog.dart';
import 'package:farsell_mobile/src/shop_state.dart';

const uid = '10000000-0000-4000-8000-000000000001';
const pid = '20000000-0000-4000-8000-000000000001';
Json userJson() => {
  'id': uid,
  'role': 'buyer',
  'name': 'Test Buyer',
  'email': 'buyer@example.test',
};
Json tokenJson() => {'access_token': 'unit-test-token', 'user': userJson()};
Json emptyCart() => {
  'version': 0,
  'lines': <Json>[],
  'subtotal': 0,
  'currency': 'PHP',
};
Json mobileProduct() => {
  'id': pid,
  'name': 'Everyday shirt',
  'slug': 'everyday-shirt',
  'description': '',
  'unit_price': 19995,
  'stock': 3,
  'has_variants': false,
  'sku': 'SHRT-BKS1',
  'image_url': null,
  'shop': {'id': uid, 'name': 'Test shop', 'slug': 'test'},
  'category': {'id': uid, 'name': 'Clothing', 'slug': 'clothing'},
  'variants': <Json>[],
};
http.Response data(dynamic value, [int status = 200]) => http.Response(
  jsonEncode({'data': value}),
  status,
  headers: {'content-type': 'application/json'},
);

class MemoryPrivate implements PrivateStore {
  final values = <String, String>{};
  bool fail = false;
  @override
  Future<String?> read(String key) async => values[key];
  @override
  Future<void> write(String key, String? value) async {
    if (fail) throw StateError('storage unavailable');
    if (value == null) {
      values.remove(key);
    } else {
      values[key] = value;
    }
  }
}

class MemoryGuest implements SelectionStore {
  String? value;
  @override
  Future<String?> read() async => value;
  @override
  Future<void> write(String v) async {
    value = v;
  }
}

void main() {
  test('malformed mobile responses produce recoverable errors', () async {
    final api = BuyerApi(
      client: MockClient((_) async => http.Response('{"data":null}', 200)),
    );
    final catalog = BuyerCatalog(api);
    await expectLater(catalog.products(), throwsA(isA<CatalogFailure>()));
    await expectLater(api.data('GET', 'session'), throwsA(isA<ApiFailure>()));
    catalog.close();
    api.close();
  });

  testWidgets(
    'buyer can review server quote, place COD order and see shipment details on a phone layout',
    (tester) async {
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final line = {
        'id': pid,
        'product_id': pid,
        'variant_id': null,
        'name': 'Everyday shirt',
        'sku': 'SHRT-BKS1',
        'options': <String, String>{},
        'qty': 1,
        'unit_price': 19995,
        'line_total': 19995,
        'stock': 3,
        'available': true,
      };
      final address = {
        'id': uid,
        'label': 'Home',
        'line1': 'Test street',
        'formatted': 'Test street, Test city',
        'phone': null,
        'is_default': true,
      };
      final order = {
        'id': pid,
        'number': 'TEST-COD-1',
        'status': 'pending',
        'payment_method': 'cod',
        'total': 24895,
        'created_at': '2026-11-13T00:00:00Z',
        'ship_to': 'Test street, Test city',
        'shipments': [
          {
            'id': uid,
            'shop_name': 'Test shop',
            'status': 'pending',
            'items': [line],
            'events': <Json>[],
            'allowed_actions': <String>[],
            'tracking_url': null,
            'proof': null,
          },
        ],
      };
      var placed = 0;
      final api = BuyerApi(
        client: MockClient((r) async {
          final path = r.url.path;
          if (path.endsWith('/products')) {
            return http.Response(
              '{"data":[],"has_more":false,"next_cursor":null}',
              200,
            );
          }
          if (path.endsWith('/cart')) {
            return data({
              'version': placed == 0 ? 1 : 2,
              'subtotal': placed == 0 ? 19995 : 0,
              'lines': placed == 0 ? [line] : <Json>[],
            });
          }
          if (path.endsWith('/addresses')) {
            return http.Response(
              jsonEncode({
                'data': [address],
                'has_more': false,
                'next_cursor': null,
              }),
              200,
            );
          }
          if (path.endsWith('/checkout/quotes')) {
            expect(jsonDecode(r.body)['cart_version'], 1);
            return data({
              'id': pid,
              'cart_version': 1,
              'address_id': uid,
              'subtotal': 19995,
              'shipping_fee': 4900,
              'total': 24895,
              'payment_methods': ['cod'],
              'shipments': <Json>[],
              'expires_at': DateTime.now()
                  .toUtc()
                  .add(const Duration(minutes: 5))
                  .toIso8601String(),
            }, 201);
          }
          if (path.endsWith('/orders') && r.method == 'POST') {
            placed++;
            expect(jsonDecode(r.body)['payment_method'], 'cod');
            expect(r.headers['Idempotency-Key'], isNotEmpty);
            return data(order, 201);
          }
          if (path.endsWith('/orders/$pid')) return data(order);
          fail('Unexpected route $path');
        }),
      );
      final guest = ShopState(store: MemoryGuest());
      final buyer = BuyerState(api: api, guest: guest, store: MemoryPrivate());
      await buyer.restore();
      await buyer.finishLogin(tokenJson());
      final catalog = BuyerCatalog(api);
      await tester.pumpWidget(
        FarSellApp(repository: catalog, state: guest, buyer: buyer),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Cart'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Checkout'));
      await tester.tap(find.text('Checkout'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Choose an address'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Use this address'));
      await tester.pumpAndSettle();
      expect(find.textContaining('SHRT-BKS1'), findsOneWidget);
      expect(find.text(pesos(24895)), findsOneWidget);
      await tester.ensureVisible(find.text('Place order · Cash on delivery'));
      await tester.tap(find.text('Place order · Cash on delivery'));
      await tester.pumpAndSettle();
      expect(placed, 1);
      expect(find.text('Order TEST-COD-1'), findsOneWidget);
      expect(find.text('Your order has been placed.'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
      buyer.dispose();
      catalog.close();
      api.close();
    },
  );
  test(
    'mobile catalog uses UUIDs, centavos, cursors and never a bearer token',
    () async {
      var requests = 0;
      final api = BuyerApi(
        baseUrl: 'http://localhost:8000/api/v1',
        client: MockClient((r) async {
          requests++;
          expect(r.url.path, '/api/v1/products');
          expect(r.headers.containsKey('Authorization'), isFalse);
          expect(r.url.queryParameters['q'], 'shirt');
          if (requests == 2) {
            expect(r.url.queryParameters['cursor'], 'opaque-cursor');
          }
          return http.Response(
            jsonEncode({
              'data': [mobileProduct()],
              'has_more': requests == 1,
              'next_cursor': requests == 1 ? 'opaque-cursor' : null,
            }),
            200,
          );
        }),
      );
      api.token = 'private-token';
      final catalog = BuyerCatalog(api);
      final first = await catalog.products(query: 'shirt');
      expect(first.products.single.id, pid);
      expect(first.products.single.price, 19995);
      expect(Product.fromJson(first.products.single.toJson()).sku, 'SHRT-BKS1');
      await catalog.products(page: 2, query: 'shirt');
      catalog.close();
      api.close();
    },
  );

  test('401 only expires the authenticated session and exposes bounded validation errors', () async {
    var expired = 0;
    final api = BuyerApi(
      client: MockClient(
        (_) async => http.Response(
          jsonEncode({'message': 'Sign in again', 'code': 'unauthenticated'}),
          401,
        ),
      ),
    );
    api.token = 'test-token';
    api.onExpired = () => expired++;
    await expectLater(
      api.call('POST', 'auth/login', authenticated: false),
      throwsA(isA<ApiFailure>()),
    );
    expect(expired, 0);
    await expectLater(
      api.call('GET', 'session'),
      throwsA(isA<ApiFailure>().having((e) => e.status, 'status', 401)),
    );
    expect(expired, 1);
    api.close();
  });

  test(
    'late response from a previous account cannot populate the next account',
    () async {
      final response = Completer<http.Response>();
      final started = Completer<void>();
      final api = BuyerApi(
        client: MockClient((_) {
          started.complete();
          return response.future;
        }),
      );
      api.token = 'first-account';
      final future = api.data('GET', 'buyer/cart');
      await started.future;
      api.token = 'second-account';
      response.complete(data(emptyCart()));
      await expectLater(
        future,
        throwsA(
          isA<ApiFailure>().having((e) => e.code, 'code', 'session_changed'),
        ),
      );
      api.close();
    },
  );

  test('secure storage failure revokes a newly issued token', () async {
    var revoked = false;
    final api = BuyerApi(
      client: MockClient((r) async {
        expect(r.url.path.endsWith('auth/logout'), isTrue);
        revoked = true;
        return http.Response('', 204);
      }),
    );
    final state = BuyerState(
      api: api,
      guest: ShopState(store: MemoryGuest()),
      store: MemoryPrivate()..fail = true,
    );
    await expectLater(
      state.finishLogin(tokenJson()),
      throwsA(isA<ApiFailure>()),
    );
    expect(revoked, isTrue);
    expect(state.signedIn, isFalse);
    expect(api.token, isNull);
    state.dispose();
    api.close();
  });

  test(
    'restore network failure retains token for reconnect; expiry clears it',
    () async {
      final store = MemoryPrivate();
      var offline = true;
      final api = BuyerApi(
        client: MockClient((_) async {
          if (offline) throw http.ClientException('offline');
          return http.Response(
            '{"message":"expired","code":"unauthenticated"}',
            401,
          );
        }),
      );
      store.values['${api.namespace}.token'] = 'test-token';
      final state = BuyerState(
        api: api,
        guest: ShopState(store: MemoryGuest()),
        store: store,
      );
      await state.restore();
      expect(api.token, 'test-token');
      expect(state.signedIn, isFalse);
      offline = false;
      await state.restore();
      await Future<void>.delayed(Duration.zero);
      expect(api.token, isNull);
      expect(store.values.containsKey('${api.namespace}.token'), isFalse);
      state.dispose();
      api.close();
    },
  );

  test(
    'interrupted order survives restart and retries identical body and key',
    () async {
      final store = MemoryPrivate(), guestStore = MemoryGuest();
      final sent = <http.Request>[];
      var interrupted = true;
      http.Client client() => MockClient((r) async {
        if (r.url.path.endsWith('/session')) return data(userJson());
        if (r.url.path.endsWith('/cart')) return data(emptyCart());
        sent.add(r);
        if (interrupted) throw http.ClientException('response lost');
        return data({'id': pid, 'number': 'TEST-ORDER'}, 201);
      });
      final api1 = BuyerApi(client: client());
      final state1 = BuyerState(
        api: api1,
        guest: ShopState(store: guestStore),
        store: store,
      );
      await state1.finishLogin(tokenJson());
      await expectLater(
        state1.place({'id': pid, 'cart_version': 4}),
        throwsA(isA<ApiFailure>()),
      );
      expect(state1.pendingOrder, isNotNull);
      state1.dispose();
      api1.close();
      interrupted = false;
      final api2 = BuyerApi(client: client());
      final state2 = BuyerState(
        api: api2,
        guest: ShopState(store: guestStore),
        store: store,
      );
      await state2.restore();
      final order = await state2.place({});
      expect(order['number'], 'TEST-ORDER');
      expect(sent.length, 2);
      expect(sent[0].body, sent[1].body);
      expect(
        sent[0].headers['Idempotency-Key'],
        sent[1].headers['Idempotency-Key'],
      );
      expect(state2.pendingOrder, isNull);
      state2.dispose();
      api2.close();
    },
  );

  test('definitive stale quote clears pending request and never retries a new quote automatically', () async {
    var orders = 0;
    final api = BuyerApi(
      client: MockClient((r) async {
        if (r.url.path.endsWith('/cart')) return data(emptyCart());
        orders++;
        return http.Response(
          '{"message":"Request another quote","code":"quote_stale"}',
          409,
        );
      }),
    );
    final state = BuyerState(
      api: api,
      guest: ShopState(store: MemoryGuest()),
      store: MemoryPrivate(),
    );
    await state.finishLogin(tokenJson());
    await expectLater(
      state.place({'id': pid, 'cart_version': 0}),
      throwsA(isA<ApiFailure>()),
    );
    expect(orders, 1);
    expect(state.pendingOrder, isNull);
    state.dispose();
    api.close();
  });

  test('guest merge cleanup is durable and replay cannot remove new guest additions', () async {
    final store = MemoryGuest();
    final guest = ShopState(store: store);
    final product = Product.fromMobile(mobileProduct());
    final stock = Availability.fromMobile(mobileProduct());
    guest.add(product, stock, null, 1);
    final selections = [
      {'key': guest.cart.single.key, 'qty': 1},
    ];
    await guest.acknowledgeMerge('merge-once', selections, {});
    guest.add(product, stock, null, 2);
    await guest.flush();
    final restored = ShopState(store: store);
    await restored.restore();
    await restored.acknowledgeMerge('merge-once', selections, {});
    expect(restored.cart.single.quantity, 2);
  });

  testWidgets(
    'buyer login handles email challenge before exposing the account',
    (tester) async {
      var verified = false;
      final api = BuyerApi(
        client: MockClient((r) async {
          final path = r.url.path;
          if (path.endsWith('/products')) {
            return http.Response(
              '{"data":[],"has_more":false,"next_cursor":null}',
              200,
            );
          }
          if (path.endsWith('/auth/login')) {
            expect(jsonDecode(r.body)['app'], 'buyer');
            return data({
              'challenge_token': 'test-challenge',
              'methods': ['email'],
              'email_hint': 'b***@example.test',
            }, 202);
          }
          if (path.endsWith('/challenges/send')) {
            return data({
              'resend_after': DateTime.now()
                  .toUtc()
                  .add(const Duration(seconds: 60))
                  .toIso8601String(),
            });
          }
          if (path.endsWith('/challenges/verify')) {
            expect(jsonDecode(r.body)['code'], '123456');
            verified = true;
            return data(tokenJson());
          }
          if (path.endsWith('/cart')) return data(emptyCart());
          fail('Unexpected route $path');
        }),
      );
      final guest = ShopState(store: MemoryGuest());
      final buyer = BuyerState(api: api, guest: guest, store: MemoryPrivate());
      await buyer.restore();
      final catalog = BuyerCatalog(api);
      await tester.pumpWidget(
        FarSellApp(repository: catalog, state: guest, buyer: buyer),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Profile').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sign in / create account'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Email'),
        'buyer@example.test',
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Password'),
        'password',
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
      await tester.pumpAndSettle();
      expect(buyer.signedIn, isFalse);
      await tester.tap(find.text('Send code'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.widgetWithText(TextField, 'Six-digit code'),
        '123456',
      );
      await tester.tap(find.text('Verify and sign in'));
      await tester.pumpAndSettle();
      expect(verified, isTrue);
      expect(buyer.signedIn, isTrue);
      expect(find.text('My orders'), findsOneWidget);
      expect(find.text('Rider workspace'), findsNothing);
      await tester.pumpWidget(const SizedBox.shrink());
      buyer.dispose();
      catalog.close();
      api.close();
    },
  );
}
