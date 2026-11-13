import 'buyer_navigation.dart';

import 'dart:typed_data';

import 'package:material_ui/material_ui.dart';

import 'address_screen.dart';
import 'auth_screen.dart';
import 'buyer_api.dart';
import 'buyer_state.dart';
import 'catalog.dart';
import 'design.dart';
import 'contact_verification.dart';
import 'after_sales.dart';
import 'review_screens.dart';

void buyerMessage(BuildContext context, String text) {
  if (!context.mounted) return;
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(content: Text(text), duration: const Duration(seconds: 3)),
    );
}

Future<void> buyerAction(
  BuildContext context,
  Future<void> Function() action,
) async {
  try {
    await action();
  } on ApiFailure catch (e) {
    if (context.mounted) buyerMessage(context, e.display);
  } catch (_) {
    if (context.mounted) {
      buyerMessage(context, 'Could not complete this action. Please retry.');
    }
  }
}

class SignInRequired extends StatelessWidget {
  const SignInRequired({super.key});
  @override
  Widget build(BuildContext context) => Center(
    child: EmptyMessage(
      icon: Icons.lock_outline,
      title: 'Sign in to continue',
      message: 'Your session may have expired. Your account cart and orders remain on the server.',
      action: 'Sign in',
      onAction: () => signInBuyer(context),
    ),
  );
}

class BuyerAccount extends StatelessWidget {
  const BuyerAccount({super.key});
  @override
  Widget build(BuildContext context) {
    final buyer = BuyerScope.of(context);
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const CircleAvatar(
          radius: 36,
          backgroundColor: lavender,
          child: Icon(Icons.person_outline, color: brand, size: 38),
        ),
        const SizedBox(height: 20),
        Text(
          buyer.signedIn ? buyer.user!['name'] as String : 'Welcome to FarSell',
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 25, fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 12),
        if (buyer.notice != null) ...[
          Notice(buyer.notice!),
          const SizedBox(height: 16),
        ],
        if (buyer.restoring)
          const Center(child: CircularProgressIndicator())
        else if (!buyer.signedIn) ...[
          const Text(
            'Sign in to shop, track deliveries and manage your orders.',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: () => signInBuyer(context),
            child: const Text('Sign in / create account'),
          ),
          if (buyer.api.token != null)
            TextButton(
              onPressed: buyer.restore,
              child: const Text('Reconnect saved session'),
            ),
        ] else ...[
          Text(
            buyer.user!['email'] as String,
            textAlign: TextAlign.center,
            style: const TextStyle(color: muted),
          ),
          const SizedBox(height: 24),
          ListTile(
            leading: const Icon(Icons.notifications_none, color: brand),
            title: const Text('Notifications'),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute<void>(
                builder: (_) => const BuyerNotificationsScreen(),
              ),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.assignment_return_outlined, color: brand),
            title: const Text('Returns and refunds'),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute<void>(
                builder: (_) => const BuyerReturnsScreen(),
              ),
            ),
          ),
          if (buyer.enablePush != null)
            ListTile(
              leading: const Icon(
                Icons.notifications_active_outlined,
                color: brand,
              ),
              title: const Text('Enable phone notifications'),
              subtitle: buyer.liveStatus == null
                  ? null
                  : Text(buyer.liveStatus!),
              onTap: () => buyerAction(context, buyer.enablePush!),
            ),
          if (buyer.user!['email_verified'] != true &&
              buyer.user!['phone_verified'] != true)
            ListTile(
              leading: const Icon(Icons.verified_user_outlined, color: brand),
              title: const Text('Verify your contact'),
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute<void>(
                  builder: (_) => const ContactVerificationScreen(),
                ),
              ),
            ),
          ListTile(
            leading: const Icon(Icons.receipt_long_outlined, color: brand),
            title: const Text('My orders'),
            subtitle: const Text(
              'Delivery progress, proof and receipt confirmation',
            ),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute<void>(builder: (_) => const OrdersScreen()),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.location_on_outlined, color: brand),
            title: const Text('Delivery addresses'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute<void>(builder: (_) => const AddressesScreen()),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.delete_outline),
            title: const Text('Request account deletion'),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute<void>(
                builder: (_) => const DeleteAccountScreen(),
              ),
            ),
          ),
          const SizedBox(height: 16),
          OutlinedButton(
            onPressed: buyer.busy
                ? null
                : () =>
                      buyerAction(context, () => buyer.exclusive(buyer.logout)),
            child: const Text('Sign out'),
          ),
        ],
        const Divider(height: 40),
        ListTile(
          title: const Text('Privacy notice'),
          onTap: () => openSite(context, buyer.api, '/privacy'),
        ),
        ListTile(
          title: const Text('Help and support'),
          onTap: () => openSite(context, buyer.api, '/support'),
        ),
      ],
    );
  }
}

class BuyerSettingsScreen extends StatelessWidget {
  const BuyerSettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final buyer = BuyerScope.maybeOf(context);
    return Scaffold(
      appBar: const BuyerAppBar(title: Text('Settings')),
      body: buyer == null
          ? const Center(child: Notice('Sign in to manage your settings.'))
          : buyer.restoring
          ? const Center(child: CircularProgressIndicator())
          : !buyer.signedIn
          ? const SignInRequired()
          : ListView(
              padding: const EdgeInsets.all(24),
              children: [
                if (buyer.user!['email_verified'] != true &&
                    buyer.user!['phone_verified'] != true)
                  ListTile(
                    leading: const Icon(Icons.verified_user_outlined),
                    title: const Text('Verify your contact'),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => const ContactVerificationScreen(),
                      ),
                    ),
                  ),
                if (buyer.enablePush != null)
                  ListTile(
                    leading: const Icon(Icons.notifications_active_outlined),
                    title: const Text('Enable phone notifications'),
                    subtitle: buyer.liveStatus == null
                        ? null
                        : Text(buyer.liveStatus!),
                    onTap: () => buyerAction(context, buyer.enablePush!),
                  ),
                ListTile(
                  leading: const Icon(Icons.privacy_tip_outlined),
                  title: const Text('Privacy notice'),
                  onTap: () => openSite(context, buyer.api, '/privacy'),
                ),
                ListTile(
                  leading: const Icon(Icons.help_outline),
                  title: const Text('Help and support'),
                  onTap: () => openSite(context, buyer.api, '/support'),
                ),
                ListTile(
                  leading: const Icon(Icons.delete_outline),
                  title: const Text('Request account deletion'),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) => const DeleteAccountScreen(),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                OutlinedButton(
                  onPressed: buyer.busy
                      ? null
                      : () => buyerAction(
                          context,
                          () => buyer.exclusive(buyer.logout),
                        ),
                  child: const Text('Sign out'),
                ),
              ],
            ),
    );
  }
}

class BuyerCartScreen extends StatefulWidget {
  const BuyerCartScreen({super.key, required this.catalog});
  final BuyerCatalog catalog;
  @override
  State<BuyerCartScreen> createState() => _BuyerCartScreenState();
}

class _BuyerCartScreenState extends State<BuyerCartScreen> {
  String? _owner, _error;
  bool _loading = false;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final owner = BuyerScope.of(context).user?['id'] as String?;
    if (_owner != owner) {
      _owner = owner;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && owner != null) _refresh();
      });
    }
  }

  Future<void> _refresh() async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await BuyerScope.of(context).refreshCart();
    } on ApiFailure catch (e) {
      if (mounted) setState(() => _error = e.display);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _retryOrder() async {
    final result = await BuyerScope.of(context).place({});
    if (mounted) {
      Navigator.push(
        context,
        MaterialPageRoute<void>(
          builder: (_) => OrderScreen(id: result['id'] as String),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final buyer = BuyerScope.of(context);
    final blocked = buyer.busy || _loading || buyer.pendingOrder != null;
    return RefreshIndicator(
      onRefresh: _refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          const Text(
            'Your cart',
            style: TextStyle(fontSize: 26, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 16),
          if (_loading || buyer.busy) const LinearProgressIndicator(),
          if (_error != null) ...[
            Notice(_error!),
            TextButton(
              onPressed: _loading ? null : _refresh,
              child: const Text('Refresh cart'),
            ),
          ],
          if (buyer.pendingOrder != null) ...[
            const Notice(
              'An order request needs its result checked. Retry the same request before starting another checkout.',
            ),
            FilledButton(
              onPressed: buyer.busy
                  ? null
                  : () => buyerAction(context, _retryOrder),
              child: const Text('Check interrupted order'),
            ),
          ],
          if (buyer.guest.cart.isNotEmpty) ...[
            Notice(
              '${buyer.guest.cartCount} item(s) are saved in the device cart.',
            ),
            OutlinedButton(
              onPressed: blocked
                  ? null
                  : () => buyerAction(
                      context,
                      () => buyer.mergeGuest(widget.catalog),
                    ),
              child: const Text('Add device cart to my account'),
            ),
          ],
          if (buyer.notice != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Text(buyer.notice!),
            ),
          for (final line in buyer.lines)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      line['name'] as String,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                    if (line['sku'] != null)
                      Text(
                        'SKU: ${line['sku']}',
                        style: const TextStyle(color: muted, fontSize: 12),
                      ),
                    Text(
                      object(line['options']).entries
                          .map((e) => '${e.key}: ${e.value}')
                          .join(' · '),
                      style: const TextStyle(color: muted),
                    ),
                    if (line['available'] != true)
                      const Text(
                        'Unavailable quantity — reduce or remove this item.',
                        style: TextStyle(color: Colors.red),
                      ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 12,
                      runSpacing: 8,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        IgnorePointer(
                          ignoring: blocked,
                          child: QuantityControl(
                            quantity: line['qty'] as int,
                            maximum: line['stock'] as int,
                            allowRemove: true,
                            onChanged: (qty) => buyerAction(
                              context,
                              () => buyer.quantity(line, qty),
                            ),
                          ),
                        ),
                        Text(
                          pesos(line['line_total'] as int),
                          style: const TextStyle(
                            color: brand,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        IconButton(
                          tooltip: 'Remove item',
                          onPressed: blocked
                              ? null
                              : () => buyerAction(
                                  context,
                                  () => buyer.quantity(line, 0),
                                ),
                          icon: const Icon(Icons.delete_outline),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          if (buyer.cart != null && buyer.lines.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Text(
                'Your account cart is empty. Explore products to add an item.',
              ),
            ),
          if (buyer.cart != null) ...[
            const SizedBox(height: 16),
            Text(
              'Item subtotal: ${pesos(buyer.cart!['subtotal'] as int)}',
              style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            const Text(
              'Shipping and the final total are calculated at checkout.',
              style: TextStyle(color: muted),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed:
                  blocked ||
                      _error != null ||
                      buyer.lines.isEmpty ||
                      buyer.lines.any((l) => l['available'] != true)
                  ? null
                  : () async {
                      await Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (_) => const CheckoutScreen(),
                        ),
                      );
                      if (mounted) _refresh();
                    },
              child: const Text('Checkout'),
            ),
          ],
        ],
      ),
    );
  }
}

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  Json? _address, _quote;
  String? _error, _owner;
  bool _busy = false;
  Future<void> _choose() async {
    final selected = await Navigator.push<Json>(
      context,
      MaterialPageRoute(builder: (_) => const AddressesScreen(choose: true)),
    );
    if (!mounted || selected == null) return;
    setState(() {
      _address = selected;
      _quote = null;
    });
    await _getQuote();
  }

  Future<void> _getQuote() async {
    if (_address == null || _busy) return;
    final buyer = BuyerScope.of(context);
    setState(() {
      _busy = true;
      _error = null;
      _quote = null;
    });
    try {
      await buyer.refreshCart();
      final quote = await buyer.api.data(
        'POST',
        'buyer/checkout/quotes',
        body: {'address_id': _address!['id'], 'cart_version': buyer.version},
      );
      if (mounted) setState(() => _quote = quote);
    } on ApiFailure catch (e) {
      if (mounted) setState(() => _error = e.display);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _place() async {
    if (_busy) return;
    final buyer = BuyerScope.of(context);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final order = await buyer.place(_quote ?? {});
      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute<void>(
            builder: (_) =>
                OrderScreen(id: order['id'] as String, justPlaced: true),
          ),
        );
      }
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(() {
          _error = e.display;
          if (buyer.pendingOrder == null) _quote = null;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Could not confirm the order result. Retry the same request.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final buyer = BuyerScope.of(context);
    final owner = buyer.user?['id'] as String?;
    if (_owner != owner) {
      _owner = owner;
      _address = null;
      _quote = null;
    }
    final pending = buyer.pendingOrder != null;
    final expired =
        _quote != null &&
        !(DateTime.tryParse(_quote!['expires_at'] as String)
                ?.isAfter(DateTime.now().toUtc()) ??
            false);
    return Scaffold(
      appBar: BuyerAppBar(title: const Text('Checkout')),
      body: !buyer.signedIn
          ? const SignInRequired()
          : ListView(
              padding: const EdgeInsets.all(20),
              children: [
                if (_busy) const LinearProgressIndicator(),
                if (_error != null) ...[
                  Notice(_error!),
                  const SizedBox(height: 16),
                ],
                if (pending) ...[
                  const Notice(
                    'Check the result of your previous order request. The same request key is reused to prevent duplicate orders.',
                  ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: _busy ? null : _place,
                    child: const Text('Check order result'),
                  ),
                ] else ...[
                  const Text(
                    'Delivery address',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 19),
                  ),
                  const SizedBox(height: 8),
                  if (_address != null) Text(_address!['formatted'] as String),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : _choose,
                    icon: const Icon(Icons.location_on_outlined),
                    label: Text(
                      _address == null ? 'Choose an address' : 'Change address',
                    ),
                  ),
                  const SizedBox(height: 20),
                  for (final line in buyer.lines)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(line['name'] as String),
                      subtitle: Text(
                        'SKU: ${line['sku'] ?? '—'} · Qty ${line['qty']}\n${object(line['options']).entries.map((e) => '${e.key}: ${e.value}').join(' · ')}',
                      ),
                      trailing: Text(pesos(line['line_total'] as int)),
                    ),
                  if (_quote != null) ...[
                    const Divider(),
                    _amount('Items', _quote!['subtotal'] as int),
                    _amount('Shipping', _quote!['shipping_fee'] as int),
                    _amount('Total', _quote!['total'] as int, bold: true),
                    const SizedBox(height: 12),
                    const ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(Icons.payments_outlined, color: brand),
                      title: Text('Cash on delivery'),
                      subtitle: Text('Pay when your order is delivered.'),
                    ),
                    Text(
                      'Quote valid until ${localTime(_quote!['expires_at'])}. Stock is checked again when you place your order.',
                      style: const TextStyle(color: muted),
                    ),
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _busy || expired ? null : _place,
                      child: const Text('Place order · Cash on delivery'),
                    ),
                  ],
                  if (_address != null)
                    TextButton(
                      onPressed: _busy ? null : _getQuote,
                      child: const Text('Refresh price and availability'),
                    ),
                ],
              ],
            ),
    );
  }

  Widget _amount(String label, int value, {bool bold = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label),
        Text(
          pesos(value),
          style: TextStyle(
            fontSize: bold ? 22 : 16,
            fontWeight: bold ? FontWeight.w700 : FontWeight.w400,
          ),
        ),
      ],
    ),
  );
}

String localTime(dynamic value) =>
    DateTime.tryParse('$value')?.toLocal().toString().split('.').first ?? '—';
String statusLabel(dynamic value) => '$value'.replaceAll('_', ' ');

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});
  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final List<Json> _orders = [];
  String? _cursor, _error, _owner;
  bool _busy = false, _more = true;
  int _generation = 0;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final owner = BuyerScope.of(context).user?['id'] as String?;
    if (owner != _owner) {
      _owner = owner;
      _generation++;
      _orders.clear();
      _busy = false;
      _cursor = null;
      _more = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && owner != null) _load(reset: true);
      });
    }
  }

  Future<void> _load({bool reset = false}) async {
    if (_busy) return;
    final generation = ++_generation;
    setState(() {
      _busy = true;
      _error = null;
      if (reset) {
        _orders.clear();
        _cursor = null;
      }
    });
    try {
      final result = await BuyerScope.of(context).api
          .call('GET', 'buyer/orders', query: {'cursor': ?_cursor});
      if (mounted && generation == _generation) {
        setState(() {
          _orders.addAll(objects(result['data']));
          _cursor = result['next_cursor'] as String?;
          _more = result['has_more'] == true;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted && generation == _generation) {
        setState(() => _error = e.display);
      }
    } finally {
      if (mounted && generation == _generation) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: BuyerAppBar(title: const Text('My orders')),
    body: !BuyerScope.of(context).signedIn
        ? const SignInRequired()
        : RefreshIndicator(
            onRefresh: () => _load(reset: true),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              children: [
                if (_error != null) ...[
                  Notice(_error!),
                  TextButton(
                    onPressed: () => _load(reset: _orders.isEmpty),
                    child: const Text('Retry'),
                  ),
                ],
                for (final order in _orders)
                  Card(
                    child: ListTile(
                      title: Text('Order ${order['number']}'),
                      subtitle: Text(
                        '${statusLabel(order['status'])}\n${localTime(order['created_at'])}',
                      ),
                      trailing: Text(pesos(order['total'] as int)),
                      isThreeLine: true,
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (_) =>
                              OrderScreen(id: order['id'] as String),
                        ),
                      ),
                    ),
                  ),
                if (_busy) const Center(child: CircularProgressIndicator()),
                if (!_busy && _error == null && _orders.isEmpty)
                  const Notice('You have no orders yet.'),
                if (!_busy && _more && _orders.isNotEmpty)
                  TextButton(onPressed: _load, child: const Text('Load more')),
              ],
            ),
          ),
  );
}

class OrderScreen extends StatefulWidget {
  const OrderScreen({super.key, required this.id, this.justPlaced = false});
  final String id;
  final bool justPlaced;
  @override
  State<OrderScreen> createState() => _OrderScreenState();
}

class _OrderScreenState extends State<OrderScreen> with WidgetsBindingObserver {
  Json? _order;
  String? _error, _owner;
  bool _busy = false;
  final Map<String, String> _receiptKeys = {};
  int _revision = -1;
  final String _cancelKey = requestId();
  String? _cancelReason;
  Future<void> _cancel() async {
    var entered = _cancelReason ?? '';
    final reason = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Cancel this order?'),
        content: TextFormField(
          initialValue: entered,
          onChanged: (value) => entered = value,
          maxLength: 1000,
          maxLines: 3,
          decoration: const InputDecoration(
            labelText: 'Reason (at least 5 characters)',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c),
            child: const Text('Keep order'),
          ),
          FilledButton(
            onPressed: () {
              if (entered.trim().length >= 5) {
                Navigator.pop(c, entered.trim());
              }
            },
            child: const Text('Confirm cancellation'),
          ),
        ],
      ),
    );
    if (reason == null || !mounted) return;
    _cancelReason = reason;
    setState(() => _busy = true);
    await buyerAction(context, () async {
      await BuyerScope.of(context).api.data(
        'POST',
        'buyer/orders/${widget.id}/cancel',
        body: {'reason': reason},
        key: _cancelKey,
      );
    });
    if (mounted) {
      setState(() => _busy = false);
      await _load();
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed &&
        mounted &&
        BuyerScope.of(context).signedIn) {
      _load();
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final owner = BuyerScope.of(context).user?['id'] as String?;
    if (_owner != owner) {
      _owner = owner;
      _order = null;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && owner != null) _load();
      });
    }
    final revision = BuyerScope.of(context).revision;
    if (_revision != revision) {
      _revision = revision;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted &&
            owner != null &&
            ModalRoute.of(context)?.isCurrent == true) {
          _load();
        }
      });
    }
  }

  Future<void> _load() async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final order = await BuyerScope.of(context).api
          .data('GET', 'buyer/orders/${widget.id}');
      if (mounted) setState(() => _order = order);
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(() {
          _error = e.display;
          _order = null;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirm(Json part) async {
    final accepted = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Confirm receipt?'),
        content: const Text(
          'Confirm only after you have received this shipment. This completes the delivery.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('I received it'),
          ),
        ],
      ),
    );
    if (accepted != true || !mounted || _busy) return;
    final buyer = BuyerScope.of(context);
    setState(() => _busy = true);
    await buyerAction(context, () async {
      await buyer.api.data(
        'POST',
        'buyer/shipments/${part['id']}/confirm-receipt',
        body: {},
        key: _receiptKeys.putIfAbsent(part['id'] as String, requestId),
      );
    });
    if (mounted) {
      setState(() => _busy = false);
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final buyer = BuyerScope.of(context);
    final order = _order;
    return Scaffold(
      appBar: BuyerAppBar(
        title: Text(
          order == null ? 'Order details' : 'Order ${order['number']}',
        ),
        actions: [
          IconButton(
            tooltip: 'Refresh order',
            onPressed: _busy ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: !buyer.signedIn
          ? const SignInRequired()
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  if (_busy) const LinearProgressIndicator(),
                  if (widget.justPlaced) ...[
                    const Notice('Your order has been placed.'),
                    const SizedBox(height: 16),
                  ],
                  if (_error != null) ...[
                    Notice(_error!),
                    TextButton(onPressed: _load, child: const Text('Retry')),
                  ],
                  if (order != null) ...[
                    Text(
                      statusLabel(order['status']).toUpperCase(),
                      style: const TextStyle(
                        color: brand,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text('Ordered ${localTime(order['created_at'])}'),
                    Text(
                      'Total ${pesos(order['total'] as int)} · ${statusLabel(order['payment_method'])}',
                    ),
                    const SizedBox(height: 16),
                    Text(order['ship_to'] as String),
                    if (order['can_cancel'] == true)
                      OutlinedButton(
                        onPressed: _busy ? null : _cancel,
                        child: const Text('Cancel order'),
                      ),
                    const SizedBox(height: 16),
                    for (final part in objects(order['shipments']))
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                part['shop_name'] as String,
                                style: const TextStyle(
                                  fontSize: 19,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              Text(
                                statusLabel(part['status']),
                                style: const TextStyle(color: brand),
                              ),
                              if (part['status'] == 'completed')
                                TextButton(
                                  onPressed: () => Navigator.push(
                                    context,
                                    MaterialPageRoute<void>(
                                      builder: (_) => FeedbackScreen(
                                        shipment: part['id'] as String,
                                      ),
                                    ),
                                  ),
                                  child: const Text(
                                    'Rate products and delivery',
                                  ),
                                ),
                              if ([
                                'delivered',
                                'completed',
                              ].contains(part['status']))
                                Wrap(
                                  children: [
                                    for (final item in objects(part['items']))
                                      TextButton(
                                        onPressed: () => Navigator.push(
                                          context,
                                          MaterialPageRoute<void>(
                                            builder: (_) =>
                                                RequestReturnScreen(item: item),
                                          ),
                                        ),
                                        child: Text(
                                          'Return / refund: ${item['name']}',
                                        ),
                                      ),
                                  ],
                                ),
                              for (final line in objects(part['items']))
                                Padding(
                                  padding: const EdgeInsets.symmetric(
                                    vertical: 8,
                                  ),
                                  child: Text(
                                    '${line['name']} × ${line['qty']} · ${pesos(line['line_total'] as int)}\nSKU: ${line['sku'] ?? '—'}\n${object(line['options']).entries.map((e) => '${e.key}: ${e.value}').join(' · ')}',
                                  ),
                                ),
                              if (part['delivery_pin'] != null) ...[
                                const Divider(),
                                const Text(
                                  'Your delivery PIN',
                                  style: TextStyle(fontWeight: FontWeight.w700),
                                ),
                                SelectableText(
                                  part['delivery_pin'] as String,
                                  style: const TextStyle(
                                    fontSize: 30,
                                    letterSpacing: 5,
                                    fontWeight: FontWeight.w800,
                                    color: brand,
                                  ),
                                ),
                                const Text(
                                  'Share this only when the rider hands over your shipment.',
                                ),
                              ],
                              if (part['tracking_url'] != null)
                                TextButton(
                                  onPressed: () =>
                                      _tracking(part['tracking_url'] as String),
                                  child: const Text('Courier tracking'),
                                ),
                              const Divider(),
                              for (final event in objects(part['events']))
                                ListTile(
                                  dense: true,
                                  contentPadding: EdgeInsets.zero,
                                  leading: const Icon(
                                    Icons.check_circle_outline,
                                    size: 20,
                                    color: brand,
                                  ),
                                  title: Text(event['message'] as String),
                                  subtitle: Text(
                                    localTime(event['occurred_at']),
                                  ),
                                ),
                              if (part['proof'] != null) ...[
                                const Divider(),
                                Text(
                                  'Received by ${object(part['proof'])['recipient_name']}',
                                ),
                                Text(
                                  'Delivered ${localTime(object(part['proof'])['delivered_at'])}',
                                ),
                                Text(
                                  object(part['proof'])['delivery_note']
                                      as String,
                                ),
                                if (object(part['proof'])['has_photo'] == true)
                                  TextButton(
                                    onPressed: () => Navigator.push(
                                      context,
                                      MaterialPageRoute<void>(
                                        builder: (_) => ProofScreen(
                                          shipment: part['id'] as String,
                                        ),
                                      ),
                                    ),
                                    child: const Text(
                                      'View private delivery photo',
                                    ),
                                  ),
                              ],
                              if ((part['allowed_actions'] as List).contains(
                                'confirm_receipt',
                              ))
                                FilledButton(
                                  onPressed: _busy
                                      ? null
                                      : () => _confirm(part),
                                  child: const Text('Confirm receipt'),
                                ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ],
              ),
            ),
    );
  }

  Future<void> _tracking(String value) async {
    final uri = Uri.tryParse(value);
    if (uri == null ||
        uri.scheme != 'https' ||
        uri.host.isEmpty ||
        uri.userInfo.isNotEmpty) {
      buyerMessage(context, 'Tracking link is unavailable.');
      return;
    }
    // Browser launch only; never attach bearer tokens to a courier URL.
    if (mounted) await buyerAction(context, () => openExternalTracking(uri));
  }
}

class ProofScreen extends StatefulWidget {
  const ProofScreen({super.key, required this.shipment});
  final String shipment;
  @override
  State<ProofScreen> createState() => _ProofScreenState();
}

class _ProofScreenState extends State<ProofScreen> {
  Future<Uint8List>? _photo;
  String? _owner;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final buyer = BuyerScope.of(context);
    final owner = buyer.user?['id'] as String?;
    if (_owner != owner) {
      _owner = owner;
      _photo = owner == null ? null : buyer.api.proof(widget.shipment);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: BuyerAppBar(title: const Text('Private delivery proof')),
    body: !BuyerScope.of(context).signedIn
        ? const SignInRequired()
        : FutureBuilder<Uint8List>(
            key: ValueKey(_owner),
            future: _photo,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return Center(
                  child: EmptyMessage(
                    icon: Icons.image_not_supported_outlined,
                    title: 'Photo unavailable',
                    message: 'Check your session and connection.',
                    action: 'Retry',
                    onAction: () => setState(
                      () =>
                          _photo = BuyerScope.of(context).api
                              .proof(widget.shipment),
                    ),
                  ),
                );
              }
              if (!snapshot.hasData) {
                return const Center(child: CircularProgressIndicator());
              }
              return Center(
                child: InteractiveViewer(
                  child: Image.memory(
                    snapshot.data!,
                    errorBuilder: (_, _, _) =>
                        const Text('This photo could not be displayed.'),
                  ),
                ),
              );
            },
          ),
  );
}

class DeleteAccountScreen extends StatefulWidget {
  const DeleteAccountScreen({super.key});
  @override
  State<DeleteAccountScreen> createState() => _DeleteAccountScreenState();
}

class _DeleteAccountScreenState extends State<DeleteAccountScreen> {
  final _password = TextEditingController(),
      _confirmation = TextEditingController();
  bool _busy = false, _done = false;
  String? _error;
  @override
  void dispose() {
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_confirmation.text != 'DELETE' || _password.text.isEmpty) {
      setState(() => _error = 'Enter your password and type DELETE.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    final buyer = BuyerScope.of(context);
    try {
      final reauth = await buyer.api.data(
        'POST',
        'auth/reauthenticate',
        body: {'password': _password.text},
      );
      _password.clear();
      await buyer.api.call(
        'POST',
        'account/deletion-requests',
        body: {
          'confirmation': 'DELETE',
          'reauth_token': reauth['reauth_token'],
        },
      );
      if (mounted) setState(() => _done = true);
    } on ApiFailure catch (e) {
      if (mounted) setState(() => _error = e.display);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: BuyerAppBar(title: const Text('Account deletion request')),
    body: !BuyerScope.of(context).signedIn
        ? const SignInRequired()
        : ListView(
            padding: const EdgeInsets.all(24),
            children: [
              const Notice(
                'This sends a request for review. Order, financial and dispute records may need to be retained. It does not immediately delete your account.',
              ),
              const SizedBox(height: 20),
              if (_done)
                const Text(
                  'Your account deletion request has been submitted for review.',
                )
              else ...[
                if (_error != null) Notice(_error!),
                TextField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Current password',
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _confirmation,
                  decoration: const InputDecoration(labelText: 'Type DELETE'),
                ),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: Text(
                    _busy ? 'Submitting…' : 'Submit deletion request',
                  ),
                ),
              ],
            ],
          ),
  );
}
