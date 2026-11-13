import 'package:material_ui/material_ui.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/testing.dart';

import 'package:farsell_mobile/src/app.dart';
import 'package:farsell_mobile/src/buyer_api.dart';
import 'package:farsell_mobile/src/buyer_state.dart';
import 'package:farsell_mobile/src/shop_state.dart';

import 'buyer_flow_test.dart' as fixtures;

void main() {
  testWidgets(
    'buyer shortcuts persist and reuse cart when returning from chat',
    (tester) async {
      tester.view.physicalSize = const Size(320, 720);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = BuyerApi(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/cart')) {
            return fixtures.data(fixtures.emptyCart());
          }
          return fixtures.data(<dynamic>[]);
        }),
      );
      final guest = ShopState(store: fixtures.MemoryGuest());
      final buyer = BuyerState(
        api: api,
        guest: guest,
        store: fixtures.MemoryPrivate(),
      );
      await buyer.restore();
      await buyer.finishLogin(fixtures.tokenJson());
      final navigator = GlobalKey<NavigatorState>();
      await tester.pumpWidget(
        FarSellApp(
          repository: BuyerCatalog(api),
          state: guest,
          buyer: buyer,
          navigatorKey: navigator,
        ),
      );
      await tester.pumpAndSettle();

      for (final page in ['Saved', 'Notifications', 'Profile']) {
        await tester.tap(find.widgetWithText(NavigationDestination, page));
        await tester.pumpAndSettle();
        expect(find.byTooltip('Cart'), findsOneWidget);
        expect(find.byTooltip('Chat'), findsOneWidget);
        expect(
          find.byTooltip('Settings'),
          page == 'Profile' ? findsOneWidget : findsNothing,
        );
        expect(tester.takeException(), isNull);
      }
      expect(find.widgetWithText(ListTile, 'Messages'), findsNothing);
      expect(
        tester.getCenter(find.byTooltip('Settings')).dx,
        lessThan(tester.getCenter(find.byTooltip('Cart')).dx),
      );
      await tester.tap(find.byTooltip('Settings'));
      await tester.pumpAndSettle();
      expect(find.text('Settings'), findsOneWidget);
      expect(find.byTooltip('Settings'), findsNothing);
      expect(find.byTooltip('Cart'), findsOneWidget);
      expect(find.byTooltip('Chat'), findsOneWidget);
      navigator.currentState!.pop();
      await tester.pumpAndSettle();

      await tester.tap(find.byTooltip('Cart'));
      await tester.pumpAndSettle();
      expect(find.text('Cart'), findsOneWidget);
      expect(find.byTooltip('Settings'), findsNothing);
      await tester.tap(find.byTooltip('Chat'));
      await tester.pumpAndSettle();
      expect(find.text('Messages'), findsOneWidget);
      expect(find.byTooltip('Cart'), findsOneWidget);
      await tester.tap(find.byTooltip('Cart'));
      await tester.pumpAndSettle();
      expect(find.text('Cart'), findsOneWidget);
      navigator.currentState!.pop();
      await tester.pumpAndSettle();
      // A single Back returns to the profile rather than an older cart or chat.
      expect(find.text('My orders'), findsOneWidget);
      expect(find.text('Messages'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox.shrink());
      buyer.dispose();
      guest.dispose();
      api.close();
    },
  );
}
