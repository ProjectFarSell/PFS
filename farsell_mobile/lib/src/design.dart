import 'package:material_ui/material_ui.dart';

const brand = Color(0xFF7950D6);
const ink = Color(0xFF282334);
const muted = Color(0xFF777180);
const canvas = Color(0xFFFAF9FC);
const lavender = Color(0xFFF0EAFB);

class ProductPhoto extends StatelessWidget {
  const ProductPhoto({super.key, required this.url, this.fit = BoxFit.cover});
  final String? url;
  final BoxFit fit;
  @override
  Widget build(BuildContext context) {
    const fallback = ColoredBox(
      color: lavender,
      child: Center(
        child: Icon(Icons.shopping_bag_outlined, size: 44, color: brand),
      ),
    );
    if (url == null) return fallback;
    return Image.network(
      url!,
      fit: fit,
      width: double.infinity,
      errorBuilder: (_, _, _) => fallback,
      loadingBuilder: (context, child, progress) => progress == null
          ? child
          : const ColoredBox(
              color: lavender,
              child: Center(
                child: SizedBox.square(
                  dimension: 22,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            ),
    );
  }
}

class EmptyMessage extends StatelessWidget {
  const EmptyMessage({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.action,
    this.onAction,
  });
  final IconData icon;
  final String title, message;
  final String? action;
  final VoidCallback? onAction;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 30, vertical: 40),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          padding: const EdgeInsets.all(22),
          decoration: const BoxDecoration(
            color: lavender,
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: brand, size: 34),
        ),
        const SizedBox(height: 20),
        Text(
          title,
          textAlign: TextAlign.center,
          style: const TextStyle(
            fontSize: 21,
            fontWeight: FontWeight.w700,
            color: ink,
          ),
        ),
        const SizedBox(height: 10),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(height: 1.5, color: muted),
        ),
        if (action != null) ...[
          const SizedBox(height: 20),
          FilledButton(onPressed: onAction, child: Text(action!)),
        ],
      ],
    ),
  );
}

class Notice extends StatelessWidget {
  const Notice(this.message, {super.key, this.icon = Icons.info_outline});
  final String message;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: lavender,
      borderRadius: BorderRadius.circular(16),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 19, color: brand),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(color: ink, fontSize: 13, height: 1.5),
          ),
        ),
      ],
    ),
  );
}

class QuantityControl extends StatelessWidget {
  const QuantityControl({
    super.key,
    required this.quantity,
    required this.maximum,
    required this.onChanged,
    this.allowRemove = false,
  });
  final int quantity, maximum;
  final ValueChanged<int> onChanged;
  final bool allowRemove;
  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      border: Border.all(color: const Color(0xFFE6E1ED)),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          tooltip: allowRemove && quantity == 1
              ? 'Remove item'
              : 'Decrease quantity',
          onPressed: quantity > (allowRemove ? 0 : 1)
              ? () => onChanged(quantity - 1)
              : null,
          icon: const Icon(Icons.remove, size: 18),
        ),
        Semantics(
          label: 'Quantity $quantity',
          child: SizedBox(
            width: 28,
            child: Text(
              '$quantity',
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ),
        IconButton(
          tooltip: 'Increase quantity',
          onPressed: quantity < maximum.clamp(0, 99)
              ? () => onChanged(quantity + 1)
              : null,
          icon: const Icon(Icons.add, size: 18),
        ),
      ],
    ),
  );
}
