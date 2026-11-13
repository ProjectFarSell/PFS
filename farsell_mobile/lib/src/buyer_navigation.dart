import 'package:material_ui/material_ui.dart';

import 'auth_screen.dart';
import 'buyer_state.dart';
import 'cart_screen.dart';
import 'catalog.dart';
import 'chat_screen.dart';
import 'design.dart';
import 'shop_state.dart';

class BuyerRouteTracker extends NavigatorObserver {
  final List<Route<dynamic>> _routes = [];
  Route<dynamic>? named(String name) {
    for (final route in _routes.reversed) {
      if (route.settings.name == name) return route;
    }
    return null;
  }

  @override
  void didPush(Route<dynamic> route, Route<dynamic>? previousRoute) {
    _routes.add(route);
  }

  @override
  void didPop(Route<dynamic> route, Route<dynamic>? previousRoute) {
    _routes.remove(route);
  }

  @override
  void didRemove(Route<dynamic> route, Route<dynamic>? previousRoute) {
    _routes.remove(route);
  }

  @override
  void didReplace({Route<dynamic>? newRoute, Route<dynamic>? oldRoute}) {
    final index = oldRoute == null ? -1 : _routes.indexOf(oldRoute);
    if (index >= 0) {
      if (newRoute == null) {
        _routes.removeAt(index);
      } else {
        _routes[index] = newRoute;
      }
    } else if (newRoute != null) {
      _routes.add(newRoute);
    }
  }
}

class BuyerNavigationScope extends InheritedWidget {
  const BuyerNavigationScope({
    super.key,
    required this.repository,
    required this.state,
    required this.tracker,
    required super.child,
  });
  final CatalogRepository repository;
  final ShopState state;
  final BuyerRouteTracker tracker;
  static BuyerNavigationScope? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<BuyerNavigationScope>();
  @override
  bool updateShouldNotify(BuyerNavigationScope oldWidget) =>
      repository != oldWidget.repository ||
      state != oldWidget.state ||
      tracker != oldWidget.tracker;

  Future<void> _open(
    BuildContext context,
    String name,
    WidgetBuilder builder,
  ) async {
    final existing = tracker.named(name);
    final navigator = Navigator.of(context);
    if (existing != null) {
      navigator.popUntil((route) => route == existing);
      return;
    }
    await navigator.push(
      MaterialPageRoute<void>(
        settings: RouteSettings(name: name),
        builder: builder,
      ),
    );
  }

  Future<void> openCart(BuildContext context) => _open(
    context,
    '/buyer-cart',
    (_) => BuyerCartPage(repository: repository, state: state),
  );
  Future<void> openChat(BuildContext context) async {
    final buyer = BuyerScope.maybeOf(context);
    if (buyer == null || buyer.restoring) return;
    if (!buyer.signedIn) {
      final signingIn = tracker.named('/buyer-sign-in');
      if (signingIn != null) {
        Navigator.of(context).popUntil((route) => route == signingIn);
        return;
      }
      if (!await signInBuyer(context)) return;
    }
    if (!context.mounted || !buyer.signedIn) return;
    await _open(context, '/buyer-messages', (_) => const ConversationsScreen());
  }
}

class BuyerHeaderActions extends StatelessWidget {
  const BuyerHeaderActions({super.key, this.onSettings});
  final VoidCallback? onSettings;
  @override
  Widget build(BuildContext context) {
    final navigation = BuyerNavigationScope.maybeOf(context);
    if (navigation == null) return const SizedBox.shrink();
    return ListenableBuilder(
      listenable: navigation.state,
      builder: (context, _) {
        final buyer = BuyerScope.maybeOf(context);
        final route = ModalRoute.of(context)?.settings.name;
        final count = buyer?.signedIn == true
            ? buyer!.lines.fold<int>(
                0,
                (sum, line) => sum + (line['qty'] as int),
              )
            : navigation.state.cartCount;
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (onSettings != null)
              IconButton(
                tooltip: 'Settings',
                onPressed: onSettings,
                icon: const Icon(Icons.settings_outlined, color: brand),
              ),
            IconButton(
              tooltip: 'Cart',
              onPressed: route == '/buyer-cart'
                  ? null
                  : () => navigation.openCart(context),
              icon: Badge(
                isLabelVisible: count > 0,
                label: Text('$count'),
                child: const Icon(Icons.shopping_bag_outlined, color: brand),
              ),
            ),
            IconButton(
              tooltip: 'Chat',
              onPressed:
                  buyer == null ||
                      buyer.restoring ||
                      route == '/buyer-messages' ||
                      route == '/buyer-sign-in'
                  ? null
                  : () => navigation.openChat(context),
              icon: const Icon(Icons.chat_bubble_outline, color: brand),
            ),
          ],
        );
      },
    );
  }
}

class BuyerAppBar extends StatelessWidget implements PreferredSizeWidget {
  const BuyerAppBar({
    super.key,
    this.title,
    this.actions,
    this.onSettings,
    this.toolbarHeight = kToolbarHeight,
  });
  final Widget? title;
  final List<Widget>? actions;
  final VoidCallback? onSettings;
  final double toolbarHeight;
  @override
  Size get preferredSize => Size.fromHeight(toolbarHeight);
  @override
  Widget build(BuildContext context) => AppBar(
    toolbarHeight: toolbarHeight,
    title: title == null
        ? null
        : DefaultTextStyle.merge(
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            child: title!,
          ),
    actions: [
      ...?actions,
      BuyerHeaderActions(onSettings: onSettings),
      const SizedBox(width: 4),
    ],
  );
}
