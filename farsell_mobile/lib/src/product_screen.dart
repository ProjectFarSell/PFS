import 'buyer_navigation.dart';

import 'dart:async';

import 'package:material_ui/material_ui.dart';

import 'catalog.dart';
import 'design.dart';
import 'shop_state.dart';
import 'auth_screen.dart';
import 'buyer_api.dart';
import 'buyer_state.dart';
import 'buyer_screens.dart';
import 'chat_screen.dart';
import 'review_screens.dart';

class ProductScreen extends StatefulWidget {
  const ProductScreen({
    super.key,
    required this.product,
    required this.repository,
    required this.state,
  });
  final Product product;
  final CatalogRepository repository;
  final ShopState state;
  @override
  State<ProductScreen> createState() => _ProductScreenState();
}

class _ProductScreenState extends State<ProductScreen>
    with WidgetsBindingObserver {
  Availability? _stock;
  String? _error;
  bool _checking = false;
  bool _submitting = false;
  int _quantity = 1;
  final Map<String, String> _choices = {};
  Timer? _timer;
  bool _visible = true;

  Variant? get _variant => _stock?.selected(_choices);
  int get _available => _stock == null
      ? 0
      : _stock!.hasVariants
      ? (_variant?.stock ?? 0)
      : _stock!.stock;
  bool get _ready => !_checking && _error == null && _available > 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _refresh();
    _timer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (_visible) _refresh();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _visible = state == AppLifecycleState.resumed;
    if (_visible) _refresh();
  }

  Future<void> _refresh() async {
    if (_checking) return;
    setState(() => _checking = true);
    try {
      final stock = await widget.repository.availability(widget.product);
      if (!mounted) return;
      setState(() {
        _stock = stock;
        _error = null;
        _choices.removeWhere(
          (key, value) =>
              !stock.dimensions.contains(key) ||
              !stock.valuesFor(key).contains(value),
        );
        if (_available > 0) {
          _quantity = _quantity.clamp(1, _available.clamp(1, 99));
        }
      });
    } on CatalogFailure catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  Future<void> _add({bool buyNow = false}) async {
    if (_submitting) return;
    final buyer = BuyerScope.maybeOf(context);
    if (buyer?.restoring == true) return;
    if (buyNow && buyer != null && !buyer.signedIn) {
      if (!await signInBuyer(context) || !mounted) return;
    }
    setState(() => _submitting = true);
    try {
      await _refresh();
      if (!mounted || !_ready) return;
      String? message;
      if (buyer?.signedIn == true && widget.repository is BuyerCatalog) {
        await buyer!.add(
          widget.product,
          _variant,
          _quantity,
          widget.repository as BuyerCatalog,
        );
      } else {
        message = widget.state.add(
          widget.product,
          _stock!,
          _variant,
          _quantity,
        );
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).hideCurrentSnackBar();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(message ?? 'Added to your cart.'),
          duration: const Duration(seconds: 3),
        ),
      );
      if (buyNow && message == null && buyer?.signedIn == true && mounted) {
        await Navigator.push(
          context,
          MaterialPageRoute<void>(builder: (_) => const CheckoutScreen()),
        );
        if (mounted) await _refresh();
      }
    } on ApiFailure catch (e) {
      if (mounted) buyerMessage(context, e.display);
    } on CatalogFailure catch (e) {
      if (mounted) buyerMessage(context, e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final product = widget.product;
    final selected = _variant;
    final needsOptions = _stock?.hasVariants == true && selected == null;
    final buyer = BuyerScope.maybeOf(context);
    final canBuy =
        _ready &&
        !_submitting &&
        buyer?.busy != true &&
        buyer?.restoring != true;
    return Scaffold(
      appBar: BuyerAppBar(
        title: const Text('Product details'),
        actions: [
          ListenableBuilder(
            listenable: widget.state,
            builder: (context, _) => IconButton(
              tooltip: widget.state.isSaved(product)
                  ? 'Unsave product'
                  : 'Save product',
              onPressed: () => widget.state.toggleSaved(product),
              icon: Icon(
                widget.state.isSaved(product)
                    ? Icons.favorite
                    : Icons.favorite_border,
                color: brand,
              ),
            ),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 32),
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(24),
              child: AspectRatio(
                aspectRatio: 1,
                child: ProductPhoto(
                  url: widget.repository.imageUrl(product.imageUrl),
                  fit: BoxFit.contain,
                ),
              ),
            ),
            const SizedBox(height: 24),
            Text(
              product.shop,
              style: const TextStyle(color: brand, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 8),
            Text(
              product.name,
              style: const TextStyle(
                fontSize: 26,
                fontWeight: FontWeight.w700,
                height: 1.2,
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 12,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Text(
                  pesos(selected?.price ?? product.price),
                  style: const TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w700,
                    color: brand,
                  ),
                ),
                if (selected == null &&
                    product.comparePrice != null &&
                    product.comparePrice! > product.price)
                  Text(
                    pesos(product.comparePrice!),
                    style: const TextStyle(
                      color: muted,
                      decoration: TextDecoration.lineThrough,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 20),
            if (_error != null) ...[
              Notice(_error!),
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: _checking ? null : _refresh,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Retry stock check'),
                ),
              ),
            ],
            if (_stock == null && _error == null)
              const Padding(
                padding: EdgeInsets.all(16),
                child: Center(child: CircularProgressIndicator()),
              ),
            if (_stock?.hasVariants == true) ...[
              for (final dimension in _stock!.dimensions) ...[
                Text(
                  dimension,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    for (final value in _stock!.valuesFor(dimension))
                      ChoiceChip(
                        label: Text(value),
                        selected: _choices[dimension] == value,
                        onSelected: (_) => setState(() {
                          _choices[dimension] = value;
                          _quantity = 1;
                        }),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
              ],
              if (needsOptions)
                Text(
                  _choices.length == _stock!.dimensions.length
                      ? 'This combination is not available.'
                      : 'Choose your options to check stock.',
                  style: const TextStyle(color: muted),
                ),
            ],
            if (selected != null || product.sku != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: SelectableText(
                  'SKU: ${selected?.sku ?? product.sku}',
                  style: const TextStyle(color: muted, fontSize: 12),
                ),
              ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 14,
              runSpacing: 12,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                QuantityControl(
                  quantity: _quantity,
                  maximum: _ready ? _available : 1,
                  onChanged: (value) => setState(() => _quantity = value),
                ),
                Text(
                  _error != null
                      ? 'STOCK UNAVAILABLE'
                      : _checking
                      ? 'CHECKING STOCK'
                      : needsOptions
                      ? 'SELECT OPTIONS'
                      : _available > 0
                      ? 'IN STOCK · $_available available'
                      : 'OUT OF STOCK',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: _ready ? const Color(0xFF27845B) : muted,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            const Divider(),
            const SizedBox(height: 14),
            const Text(
              'About this item',
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
            ),
            const SizedBox(height: 10),
            Text(
              product.description.isEmpty
                  ? 'No description has been added yet.'
                  : product.description,
              style: const TextStyle(color: muted, height: 1.6),
            ),
            const SizedBox(height: 24),
            const Notice(
              'Stock and prices are checked again at checkout. Items are reserved when an order is placed.',
            ),
            if (buyer != null && widget.repository is BuyerCatalog) ...[
              const SizedBox(height: 20),
              OutlinedButton.icon(
                onPressed: () => buyerAction(context, () async {
                  final current = await (widget.repository as BuyerCatalog)
                      .resolve(product);
                  if (!context.mounted) return;
                  await Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) =>
                          ProductReviewsScreen(product: current.id as String),
                    ),
                  );
                }),
                icon: const Icon(Icons.star_outline),
                label: const Text('Customer reviews'),
              ),
              OutlinedButton.icon(
                onPressed: () => buyerAction(context, () async {
                  if (!buyer.signedIn && !await signInBuyer(context)) return;
                  final current = await (widget.repository as BuyerCatalog)
                      .resolve(product);
                  final thread = await buyer.api.data(
                    'POST',
                    'buyer/products/${current.id}/conversation',
                  );
                  if (!context.mounted) return;
                  await Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) => ChatScreen(
                        id: thread['id'] as String,
                        title: thread['shop_name'] as String,
                      ),
                    ),
                  );
                }),
                icon: const Icon(Icons.chat_bubble_outline),
                label: const Text('Chat with seller'),
              ),
            ],
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 14),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: Color(0xFFEDE9F2))),
          ),
          child: Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: canBuy ? _add : null,
                  icon: const Icon(Icons.shopping_bag_outlined),
                  label: Text(_submitting ? 'Adding…' : 'Add to cart'),
                ),
              ),
              if (buyer != null) ...[
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton(
                    onPressed: canBuy ? () => _add(buyNow: true) : null,
                    child: const Text('Buy now'),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
