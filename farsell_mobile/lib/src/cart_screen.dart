import 'package:material_ui/material_ui.dart';

import 'auth_screen.dart';
import 'buyer_api.dart';
import 'buyer_navigation.dart';
import 'buyer_screens.dart';
import 'buyer_state.dart';
import 'catalog.dart';
import 'design.dart';
import 'product_screen.dart';
import 'shop_state.dart';

class BuyerCartPage extends StatelessWidget {
  const BuyerCartPage({
    super.key,
    required this.repository,
    required this.state,
  });
  final CatalogRepository repository;
  final ShopState state;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: const BuyerAppBar(title: Text('Cart')),
    body: ListenableBuilder(
      listenable: state,
      builder: (context, _) {
        if (BuyerScope.maybeOf(context)?.signedIn == true &&
            repository is BuyerCatalog) {
          return BuyerCartScreen(catalog: repository as BuyerCatalog);
        }
        return GuestCartView(
          repository: repository,
          state: state,
          onBrowse: () => Navigator.pop(context),
        );
      },
    ),
  );
}

class GuestCartView extends StatelessWidget {
  const GuestCartView({
    super.key,
    required this.repository,
    required this.state,
    required this.onBrowse,
  });
  final CatalogRepository repository;
  final ShopState state;
  final VoidCallback onBrowse;
  void _open(BuildContext context, Product product) => Navigator.of(context)
      .push(
        MaterialPageRoute<void>(
          builder: (_) => ProductScreen(
            product: product,
            repository: repository,
            state: state,
          ),
        ),
      );
  @override
  Widget build(BuildContext context) {
    final state = this.state;
    if (state.cart.isEmpty) {
      return Center(
        child: EmptyMessage(
          icon: Icons.shopping_bag_outlined,
          title: 'Your cart is waiting',
          message:
              'Find something you love, choose its options and add it here.',
          action: 'Find something good',
          onAction: onBrowse,
        ),
      );
    }
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Text(
          'Your cart',
          style: TextStyle(fontSize: 26, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 6),
        Text(
          '${state.cartCount} items · saved on this device',
          style: const TextStyle(color: muted),
        ),
        const SizedBox(height: 22),
        for (final line in state.cart)
          Container(
            margin: const EdgeInsets.only(bottom: 14),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFEFEAF5)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    GestureDetector(
                      onTap: () => _open(context, line.product),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: SizedBox.square(
                          dimension: 70,
                          child: ProductPhoto(
                            url: repository.imageUrl(line.product.imageUrl),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            line.product.shop,
                            style: const TextStyle(fontSize: 11, color: muted),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            line.product.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          if (line.options.isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(top: 6),
                              child: Text(
                                line.optionLabel,
                                style: const TextStyle(
                                  color: muted,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                          if (line.sku != null)
                            Text(
                              'SKU: ${line.sku}',
                              style: const TextStyle(
                                color: muted,
                                fontSize: 11,
                              ),
                            ),
                        ],
                      ),
                    ),
                    IconButton(
                      tooltip: 'Remove ${line.product.name}',
                      onPressed: () => state.remove(line),
                      icon: const Icon(Icons.close, size: 18),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 16,
                  runSpacing: 10,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    QuantityControl(
                      quantity: line.quantity,
                      maximum: line.stock,
                      allowRemove: true,
                      onChanged: (value) => state.setQuantity(line, value),
                    ),
                    Text(
                      pesos(line.total),
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        color: brand,
                        fontSize: 17,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text('Item subtotal', style: TextStyle(fontSize: 16)),
            Text(
              pesos(state.subtotal),
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 23),
            ),
          ],
        ),
        const SizedBox(height: 8),
        const Text(
          'Estimated item prices. Stock and totals must be confirmed when ordering.',
          style: TextStyle(color: muted, fontSize: 12, height: 1.5),
        ),
        const SizedBox(height: 22),
        FilledButton(
          onPressed:
              BuyerScope.maybeOf(context) == null ||
                  BuyerScope.of(context).restoring
              ? null
              : () async {
                  await signInBuyer(context);
                },
          child: const Text('Sign in to checkout'),
        ),
        const SizedBox(height: 14),
        const Notice(
          'Sign in, then add these device selections to your account cart. Stock and prices are checked again before checkout.',
        ),
        const SizedBox(height: 20),
      ],
    );
  }
}
