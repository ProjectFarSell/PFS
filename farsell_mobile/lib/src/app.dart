import 'buyer_navigation.dart';

import 'dart:async';

import 'package:material_ui/material_ui.dart';

import 'catalog.dart';
import 'design.dart';
import 'product_screen.dart';
import 'shop_state.dart';
import 'buyer_state.dart';
import 'buyer_screens.dart';
import 'after_sales.dart';

class FarSellApp extends StatefulWidget {
  const FarSellApp({
    super.key,
    required this.repository,
    required this.state,
    this.buyer,
    this.navigatorKey,
  });
  final BuyerState? buyer;
  final GlobalKey<NavigatorState>? navigatorKey;
  final CatalogRepository repository;
  final ShopState state;
  @override
  State<FarSellApp> createState() => _FarSellAppState();
}

class _FarSellAppState extends State<FarSellApp> {
  final _navigation = BuyerRouteTracker();
  CatalogRepository get repository => widget.repository;
  ShopState get state => widget.state;
  BuyerState? get buyer => widget.buyer;
  GlobalKey<NavigatorState>? get navigatorKey => widget.navigatorKey;
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'FarSell',
    navigatorKey: navigatorKey,
    navigatorObservers: [_navigation],
    debugShowCheckedModeBanner: false,
    builder: (context, child) => BuyerNavigationScope(
      repository: repository,
      state: state,
      tracker: _navigation,
      child: buyer == null ? child! : BuyerScope(state: buyer!, child: child!),
    ),
    theme: ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: brand,
        surface: Colors.white,
      ),
      scaffoldBackgroundColor: canvas,
      appBarTheme: const AppBarTheme(
        backgroundColor: canvas,
        foregroundColor: ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: ink,
          fontSize: 18,
          fontWeight: FontWeight.w600,
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: brand,
          foregroundColor: Colors.white,
          minimumSize: const Size(48, 52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: Color(0xFFE9E4F0)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: Color(0xFFE9E4F0)),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: lavender,
      ),
      textTheme: const TextTheme(
        bodyMedium: TextStyle(color: ink),
        bodyLarge: TextStyle(color: ink),
      ),
    ),
    home: Storefront(repository: repository, state: state),
  );
}

class Storefront extends StatefulWidget {
  const Storefront({super.key, required this.repository, required this.state});
  final CatalogRepository repository;
  final ShopState state;
  @override
  State<Storefront> createState() => _StorefrontState();
}

class _StorefrontState extends State<Storefront> {
  int _tab = 0;
  void _browse() => setState(() => _tab = 0);
  Widget _notifications() {
    final buyer = BuyerScope.maybeOf(context);
    if (buyer?.signedIn == true) return const BuyerNotificationsScreen();
    return Scaffold(
      appBar: BuyerAppBar(title: const Text('Notifications')),
      body: buyer == null
          ? const Center(child: Notice('Sign in to view your notifications.'))
          : buyer.restoring
          ? const Center(child: CircularProgressIndicator())
          : const SignInRequired(),
    );
  }

  void _open(Product product) => Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) => ProductScreen(
        product: product,
        repository: widget.repository,
        state: widget.state,
      ),
    ),
  );

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: widget.state,
    builder: (context, _) => Scaffold(
      appBar: _tab == 2
          ? null
          : BuyerAppBar(
              toolbarHeight: 72,
              onSettings: _tab == 3
                  ? () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => const BuyerSettingsScreen(),
                      ),
                    )
                  : null,
              title: _tab == 3
                  ? const Text('Profile')
                  : Row(
                      children: [
                        Container(
                          width: 36,
                          height: 36,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: brand,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Text(
                            'F',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              fontSize: 21,
                            ),
                          ),
                        ),
                        const SizedBox(width: 9),
                        const Flexible(
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            alignment: Alignment.centerLeft,
                            child: Text(
                              'FarSell',
                              style: TextStyle(
                                color: brand,
                                fontWeight: FontWeight.w800,
                                fontSize: 24,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
              actions: [
                if (_tab != 3 && MediaQuery.sizeOf(context).width >= 440)
                  Padding(
                    padding: const EdgeInsets.only(right: 20),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 7,
                      ),
                      decoration: BoxDecoration(
                        color: lavender,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Text(
                        'Explore locally',
                        style: TextStyle(
                          color: brand,
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
      body: IndexedStack(
        index: _tab,
        children: [
          ExploreScreen(
            repository: widget.repository,
            state: widget.state,
            onOpen: _open,
          ),
          _saved(),
          if (_tab == 2) _notifications() else const SizedBox.shrink(),
          if (BuyerScope.maybeOf(context) != null)
            const BuyerAccount()
          else
            _account(),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (value) => setState(() => _tab = value),
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.storefront_outlined),
            selectedIcon: Icon(Icons.storefront),
            label: 'Explore',
          ),
          const NavigationDestination(
            icon: Icon(Icons.favorite_border),
            selectedIcon: Icon(Icons.favorite),
            label: 'Saved',
          ),
          const NavigationDestination(
            icon: Icon(Icons.notifications_outlined),
            selectedIcon: Icon(Icons.notifications),
            label: 'Notifications',
          ),
          const NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Profile',
          ),
        ],
      ),
    ),
  );

  Widget _saved() {
    final products = widget.state.saved;
    if (products.isEmpty) {
      return Center(
        child: EmptyMessage(
          icon: Icons.favorite_border,
          title: 'Keep your good finds',
          message:
              'Tap the heart on an item to save it for later on this device.',
          action: 'Explore products',
          onAction: _browse,
        ),
      );
    }
    return CustomScrollView(
      slivers: [
        const SliverToBoxAdapter(
          child: Padding(
            padding: EdgeInsets.fromLTRB(20, 12, 20, 20),
            child: Text(
              'Your saved finds',
              style: TextStyle(fontSize: 25, fontWeight: FontWeight.w700),
            ),
          ),
        ),
        ProductGrid(
          products: products,
          repository: widget.repository,
          state: widget.state,
          onOpen: _open,
        ),
        const SliverToBoxAdapter(child: SizedBox(height: 20)),
      ],
    );
  }

  Widget _account() => ListView(
    padding: const EdgeInsets.all(20),
    children: [
      const SizedBox(height: 16),
      const CircleAvatar(
        radius: 38,
        backgroundColor: lavender,
        child: Icon(Icons.person_outline, color: brand, size: 40),
      ),
      const SizedBox(height: 20),
      const Text(
        'Welcome to FarSell',
        textAlign: TextAlign.center,
        style: TextStyle(fontSize: 25, fontWeight: FontWeight.w700),
      ),
      const SizedBox(height: 10),
      const Text(
        'Good finds, local shops,\nand a community closer to you.',
        textAlign: TextAlign.center,
        style: TextStyle(color: muted, height: 1.6),
      ),
      const SizedBox(height: 28),
      const Notice(
        'You are browsing as a guest. Mobile sign-in is not connected yet; your saved items and cart stay on this device.',
      ),
      const SizedBox(height: 22),
      const ListTile(
        contentPadding: EdgeInsets.zero,
        leading: Icon(Icons.shopping_bag_outlined, color: brand),
        title: Text('Buyer account'),
        subtitle: Text(
          'Order history, checkout and delivery updates are next.',
        ),
      ),
      if (widget.state.storageWarning)
        const Padding(
          padding: EdgeInsets.only(top: 16),
          child: Notice(
            'Device storage is unavailable. Some selections may not remain after restarting the app.',
          ),
        ),
      const SizedBox(height: 22),
      const Text(
        'FarSell mobile · storefront preview',
        textAlign: TextAlign.center,
        style: TextStyle(fontSize: 12, color: muted),
      ),
    ],
  );
}

class ExploreScreen extends StatefulWidget {
  const ExploreScreen({
    super.key,
    required this.repository,
    required this.state,
    required this.onOpen,
  });
  final CatalogRepository repository;
  final ShopState state;
  final ValueChanged<Product> onOpen;
  @override
  State<ExploreScreen> createState() => _ExploreScreenState();
}

class _ExploreScreenState extends State<ExploreScreen> {
  final _search = TextEditingController();
  final List<Product> _products = [];
  Timer? _debounce;
  int _requestId = 0, _page = 0, _total = 0;
  bool _loading = false, _hasMore = false;
  String _sort = 'newest';
  String? _error;
  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  Future<void> _load({bool reset = false}) async {
    if (!reset && (_loading || !_hasMore)) return;
    final generation = ++_requestId;
    final next = reset ? 1 : _page + 1;
    setState(() {
      _loading = true;
      _error = null;
      if (reset) _products.clear();
    });
    try {
      final page = await widget.repository.products(
        page: next,
        query: _search.text,
        sort: _sort,
      );
      if (!mounted || generation != _requestId) return;
      setState(() {
        final seen = _products.map((p) => p.id).toSet();
        _products.addAll(page.products.where((p) => seen.add(p.id)));
        _page = next;
        _total = page.total;
        _hasMore = page.hasMore;
      });
    } on CatalogFailure catch (error) {
      if (mounted && generation == _requestId) {
        setState(() => _error = error.message);
      }
    } finally {
      if (mounted && generation == _requestId) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: () => _load(reset: true),
    child: CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 6, 20, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  maxLength: 120,
                  onChanged: (_) {
                    _debounce?.cancel();
                    _debounce = Timer(
                      const Duration(milliseconds: 400),
                      () => _load(reset: true),
                    );
                  },
                  onSubmitted: (_) {
                    _debounce?.cancel();
                    _load(reset: true);
                  },
                  decoration: InputDecoration(
                    counterText: '',
                    hintText: 'Search products',
                    prefixIcon: const Icon(Icons.search, color: muted),
                    suffixIcon: IconButton(
                      tooltip: 'Clear search',
                      icon: const Icon(Icons.close, size: 18),
                      onPressed: () {
                        _debounce?.cancel();
                        _search.clear();
                        _load(reset: true);
                      },
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFFEEE5FC), Color(0xFFF6F1FE)],
                    ),
                    borderRadius: BorderRadius.circular(24),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'YOUR LOCAL MARKETPLACE',
                        style: TextStyle(
                          color: brand,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.7,
                        ),
                      ),
                      const SizedBox(height: 13),
                      const Text(
                        'Good finds.\nCloser to home.',
                        style: TextStyle(
                          color: ink,
                          fontSize: 30,
                          height: 1.13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'Discover something worth bringing home\nfrom the shops in your community.',
                        style: TextStyle(
                          color: muted,
                          fontSize: 12,
                          height: 1.6,
                        ),
                      ),
                      const SizedBox(height: 16),
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.storefront_outlined,
                            size: 15,
                            color: brand,
                          ),
                          const SizedBox(width: 7),
                          Flexible(
                            child: Text(
                              'Browse. Find. FarSell.',
                              style: TextStyle(
                                color: brand,
                                fontWeight: FontWeight.w600,
                                fontSize: 12,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _search.text.isEmpty
                                ? 'Fresh finds'
                                : 'Search results',
                            style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            _loading && _products.isEmpty
                                ? 'Finding your next favourite…'
                                : '$_total products${_hasMore ? '+' : ''} loaded',
                            style: const TextStyle(color: muted, fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                    PopupMenuButton<String>(
                      tooltip: 'Sort products',
                      initialValue: _sort,
                      onSelected: (sort) {
                        _sort = sort;
                        _load(reset: true);
                      },
                      itemBuilder: (_) => const [
                        PopupMenuItem(
                          value: 'newest',
                          child: Text('Newest first'),
                        ),
                        PopupMenuItem(
                          value: 'price_asc',
                          child: Text('Price: low to high'),
                        ),
                        PopupMenuItem(
                          value: 'price_desc',
                          child: Text('Price: high to low'),
                        ),
                      ],
                      icon: const Icon(Icons.sort, color: ink),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
              ],
            ),
          ),
        ),
        if (_products.isNotEmpty)
          ProductGrid(
            products: _products,
            repository: widget.repository,
            state: widget.state,
            onOpen: widget.onOpen,
          ),
        if (_loading)
          const SliverToBoxAdapter(
            child: Padding(
              padding: EdgeInsets.all(30),
              child: Center(child: CircularProgressIndicator()),
            ),
          ),
        if (!_loading && _error != null)
          SliverToBoxAdapter(
            child: EmptyMessage(
              icon: Icons.wifi_off_outlined,
              title: _products.isEmpty
                  ? 'Let’s reconnect'
                  : 'Could not load more',
              message: _error!,
              action: 'Try again',
              onAction: () => _load(reset: _products.isEmpty),
            ),
          ),
        if (!_loading && _error == null && _products.isEmpty)
          const SliverToBoxAdapter(
            child: EmptyMessage(
              icon: Icons.search_off,
              title: 'No finds just yet',
              message: 'Try a different search, or pull down to refresh the catalogue.',
            ),
          ),
        if (!_loading && _error == null && _hasMore)
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: OutlinedButton(
                onPressed: _load,
                child: const Text('Explore more'),
              ),
            ),
          ),
        const SliverToBoxAdapter(child: SizedBox(height: 24)),
      ],
    ),
  );
}

class ProductGrid extends StatelessWidget {
  const ProductGrid({
    super.key,
    required this.products,
    required this.repository,
    required this.state,
    required this.onOpen,
  });
  final List<Product> products;
  final CatalogRepository repository;
  final ShopState state;
  final ValueChanged<Product> onOpen;
  @override
  Widget build(BuildContext context) => SliverPadding(
    padding: const EdgeInsets.symmetric(horizontal: 20),
    sliver: SliverLayoutBuilder(
      builder: (context, constraints) {
        final columns = (constraints.crossAxisExtent / 170).floor().clamp(2, 4);
        final width =
            (constraints.crossAxisExtent - (columns - 1) * 14) / columns;
        final scale = MediaQuery.textScalerOf(context).scale(14) / 14;
        return SliverGrid(
          delegate: SliverChildBuilderDelegate((context, index) {
            final product = products[index];
            return Material(
              color: Colors.white,
              clipBehavior: Clip.antiAlias,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(18),
                side: const BorderSide(color: Color(0xFFEFEAF5)),
              ),
              child: InkWell(
                onTap: () => onOpen(product),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      height: width * .88,
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          ProductPhoto(
                            url: repository.imageUrl(product.imageUrl),
                          ),
                          Positioned(
                            top: 6,
                            right: 6,
                            child: Material(
                              color: Colors.white.withValues(alpha: .94),
                              shape: const CircleBorder(),
                              child: IconButton(
                                tooltip: state.isSaved(product)
                                    ? 'Unsave ${product.name}'
                                    : 'Save ${product.name}',
                                onPressed: () => state.toggleSaved(product),
                                icon: Icon(
                                  state.isSaved(product)
                                      ? Icons.favorite
                                      : Icons.favorite_border,
                                  color: state.isSaved(product) ? brand : muted,
                                  size: 20,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              product.shop,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 10,
                                color: muted,
                              ),
                            ),
                            const SizedBox(height: 5),
                            Text(
                              product.name,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontWeight: FontWeight.w600,
                                fontSize: 13,
                                height: 1.25,
                              ),
                            ),
                            const Spacer(),
                            Text(
                              pesos(product.price),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 16,
                                color: brand,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 5),
                            Text(
                              product.stock > 0
                                  ? product.category
                                  : 'Out of stock',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 10,
                                color: muted,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          }, childCount: products.length),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisSpacing: 14,
            crossAxisSpacing: 14,
            mainAxisExtent: width * .88 + 133 * scale,
          ),
        );
      },
    ),
  );
}
