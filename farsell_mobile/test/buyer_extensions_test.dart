import 'dart:convert';
import 'dart:typed_data';

import 'package:material_ui/material_ui.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import 'package:farsell_mobile/src/auth_screen.dart';
import 'package:farsell_mobile/src/buyer_api.dart';
import 'package:farsell_mobile/src/buyer_state.dart';
import 'package:farsell_mobile/src/chat_screen.dart';
import 'package:farsell_mobile/src/native_services.dart';
import 'package:farsell_mobile/src/review_screens.dart';
import 'package:farsell_mobile/src/shop_state.dart';

import 'buyer_flow_test.dart' as fixtures;

const oldestId = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
const latestId = '00000000-0000-4000-8000-000000000001';

Widget scope(BuyerState buyer, Widget child) => MaterialApp(
  builder: (_, child) => BuyerScope(state: buyer, child: child!),
  home: child,
);

void main() {
  testWidgets('chat recovers missed history after a disconnection gap', (
    tester,
  ) async {
    var gap = false;
    Json message(String id, String body) => {
      'id': id,
      'body': body,
      'mine': false,
      'sender': 'Seller',
      'created_at': '2026-11-13T00:00:00Z',
    };
    final api = BuyerApi(
      client: MockClient((r) async {
        if (r.url.path.endsWith('/cart')) {
          return fixtures.data(fixtures.emptyCart());
        }
        final older = r.url.queryParameters['cursor'] == 'gap-history';
        return http.Response(
          jsonEncode({
            'data': [
              gap && !older
                  ? message(fixtures.pid, 'Latest after reconnect')
                  : message(oldestId, 'Earlier conversation'),
            ],
            'has_more': gap && !older,
            'next_cursor': gap && !older ? 'gap-history' : null,
          }),
          200,
        );
      }),
    );
    final buyer = BuyerState(
      api: api,
      guest: ShopState(store: fixtures.MemoryGuest()),
      store: fixtures.MemoryPrivate(),
    );
    await buyer.restore();
    await buyer.finishLogin(fixtures.tokenJson());
    await tester.pumpWidget(
      scope(buyer, const ChatScreen(id: latestId, title: 'Seller')),
    );
    await tester.pumpAndSettle();
    expect(find.text('Earlier conversation'), findsOneWidget);
    gap = true;
    await tester.pump(const Duration(seconds: 6));
    await tester.pumpAndSettle();
    expect(find.text('Latest after reconnect'), findsOneWidget);
    await tester.tap(find.text('Load earlier messages'));
    await tester.pumpAndSettle();
    expect(find.text('Earlier conversation'), findsOneWidget);
    expect(find.text('Latest after reconnect'), findsOneWidget);
    await tester.pumpWidget(const SizedBox.shrink());
    buyer.dispose();
    api.close();
  });
  test('notification payload ignores rider offers and strips personal or credential fields', () {
    final event = safeBuyerEvent({
      'event_id': oldestId,
      'type': 'chat.message_created',
      'conversation_id': latestId,
      'body': 'private text',
      'delivery_pin': '123456',
      'token': 'secret',
    });
    expect(event, {
      'event_id': oldestId,
      'type': 'chat.message_created',
      'conversation_id': latestId,
    });
    expect(
      safeBuyerEvent({'event_id': oldestId, 'type': 'offer.created'}),
      isNull,
    );
    expect(
      safeBuyerEvent({
        'event_id': oldestId,
        'type': 'order.placed',
        'expires_at': '2020-01-01T00:00:00Z',
      }),
      isNull,
    );
    expect(
      safeBuyerEvent({
        'event_id': oldestId,
        'type': 'order.placed',
        'expires_at': 'not-a-date',
      }),
      isNull,
    );
  });

  test(
    'app links accept only configured HTTPS host and opaque buyer targets',
    () {
      expect(
        safeBuyerLink(
          Uri.parse('https://shop.example/app/orders/$oldestId'),
          'shop.example',
        ),
        {'order_id': oldestId},
      );
      expect(
        safeBuyerLink(
          Uri.parse('https://shop.example/app/products/$latestId'),
          'shop.example',
        ),
        {'product_id': latestId},
      );
      for (final value in [
        'http://shop.example/app/orders/$oldestId',
        'https://attacker.example/app/orders/$oldestId',
        'https://shop.example/app/orders/123',
        'https://shop.example/app/orders/$oldestId?token=secret',
        'https://shop.example/app/rider/$oldestId',
      ]) {
        expect(safeBuyerLink(Uri.parse(value), 'shop.example'), isNull);
      }
    },
  );

  test('multipart preserves explicit key and bearer without retrying automatically', () async {
    var calls = 0;
    final api = BuyerApi(
      client: MockClient((r) async {
        calls++;
        expect(r.headers['Authorization'], 'Bearer test-token');
        expect(r.headers['Idempotency-Key'], oldestId);
        expect(r.headers['Content-Type'], startsWith('multipart/form-data'));
        expect(r.body, contains('name="quantity"'));
        expect(r.body, contains('filename="photo.jpg"'));
        return fixtures.data({'id': latestId}, 201);
      }),
    );
    api.token = 'test-token';
    final response = await api.multipart(
      'buyer/order-items/$latestId/returns',
      {'quantity': '1'},
      [
        BuyerUpload('evidence', 'photo.jpg', Uint8List.fromList([1, 2, 3])),
      ],
      oldestId,
    );
    expect(response['id'], latestId);
    expect(calls, 1);
    api.close();
  });

  testWidgets(
    'chat keeps server order for same-second messages and retries with the same key',
    (tester) async {
      final sent = <http.Request>[];
      final messages = [
        {
          'id': latestId,
          'body': 'Second message',
          'mine': false,
          'sender': 'Seller',
          'created_at': '2026-11-13T00:00:00Z',
        },
        {
          'id': oldestId,
          'body': 'First message',
          'mine': false,
          'sender': 'Seller',
          'created_at': '2026-11-13T00:00:00Z',
        },
      ];
      final api = BuyerApi(
        client: MockClient((r) async {
          if (r.url.path.endsWith('/cart')) {
            return fixtures.data(fixtures.emptyCart());
          }
          if (r.method == 'POST') {
            sent.add(r);
            if (sent.length == 1) {
              throw http.ClientException('response lost');
            }
            return fixtures.data({
              'id': fixtures.pid,
              'body': 'Hello seller',
              'mine': true,
              'sender': 'Buyer',
              'created_at': '2026-11-13T00:00:01Z',
            }, 201);
          }
          return http.Response(
            jsonEncode({
              'data': messages,
              'next_cursor': null,
              'has_more': false,
            }),
            200,
          );
        }),
      );
      final buyer = BuyerState(
        api: api,
        guest: ShopState(store: fixtures.MemoryGuest()),
        store: fixtures.MemoryPrivate(),
      );
      await buyer.restore();
      await buyer.finishLogin(fixtures.tokenJson());
      await tester.pumpWidget(
        scope(buyer, const ChatScreen(id: fixtures.pid, title: 'Seller')),
      );
      await tester.pumpAndSettle();
      expect(
        tester.getTopLeft(find.text('First message')).dy,
        lessThan(tester.getTopLeft(find.text('Second message')).dy),
      );
      await tester.enterText(find.byType(TextField), 'Hello seller');
      await tester.tap(find.byTooltip('Send message'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Send message'));
      await tester.pumpAndSettle();
      expect(sent.length, 2);
      expect(
        sent[0].headers['Idempotency-Key'],
        sent[1].headers['Idempotency-Key'],
      );
      expect(find.text('Hello seller'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
      buyer.dispose();
      api.close();
    },
  );

  testWidgets(
    'guest sees anonymous public reviews without sending a stored bearer',
    (tester) async {
      final api = BuyerApi(
        client: MockClient((r) async {
          expect(r.headers.containsKey('Authorization'), isFalse);
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': oldestId,
                  'rating': 5,
                  'feedback': 'Good quality',
                  'author': 'Anonymous buyer',
                  'is_anonymous': true,
                  'created_at': '2026-11-13T00:00:00Z',
                  'media': <Json>[],
                },
              ],
              'next_cursor': null,
              'has_more': false,
            }),
            200,
          );
        }),
      );
      api.token = 'not-for-public-reads';
      final buyer = BuyerState(
        api: api,
        guest: ShopState(store: fixtures.MemoryGuest()),
        store: fixtures.MemoryPrivate(),
      );
      await tester.pumpWidget(
        scope(buyer, const ProductReviewsScreen(product: fixtures.pid)),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('Anonymous buyer'), findsOneWidget);
      expect(find.text('Good quality'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
      buyer.dispose();
      api.close();
    },
  );

  testWidgets(
    'Google button explains unavailable client setup without calling native sign in',
    (tester) async {
      final api = BuyerApi(
        client: MockClient(
          (_) async => throw StateError('No API request expected'),
        ),
      );
      final buyer = BuyerState(
        api: api,
        guest: ShopState(store: fixtures.MemoryGuest()),
        store: fixtures.MemoryPrivate(),
      );
      await tester.pumpWidget(scope(buyer, const AuthScreen()));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Sign in with Google'));
      await tester.tap(find.text('Sign in with Google'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Google sign-in is temporarily unavailable'),
        findsOneWidget,
      );
      await tester.pumpWidget(const SizedBox.shrink());
      buyer.dispose();
      api.close();
    },
  );
}
