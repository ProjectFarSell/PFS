import 'package:material_ui/material_ui.dart';

import 'src/app.dart';
import 'src/shop_state.dart';
import 'src/buyer_api.dart';
import 'src/buyer_state.dart';
import 'src/native_services.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final state = ShopState();
  await state.restore();
  final api = BuyerApi();
  final buyer = BuyerState(api: api, guest: state);
  final catalog = BuyerCatalog(api);
  final navigator = GlobalKey<NavigatorState>();
  final native = BuyerNativeServices(
    buyer: buyer,
    catalog: catalog,
    navigator: navigator,
  );
  runApp(
    FarSellApp(
      repository: catalog,
      state: state,
      buyer: buyer,
      navigatorKey: navigator,
    ),
  );
  await buyer.restore();
  await native.start();
}
