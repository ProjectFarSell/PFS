import 'buyer_navigation.dart';

import 'package:material_ui/material_ui.dart';

import 'buyer_api.dart';
import 'buyer_records.dart';
import 'buyer_screens.dart';
import 'buyer_state.dart';
import 'catalog.dart';
import 'design.dart';
import 'review_screens.dart';

class BuyerNotificationsScreen extends StatelessWidget {
  const BuyerNotificationsScreen({super.key});
  @override
  Widget build(BuildContext context) => BuyerRecords(
    title: 'Notifications',
    path: 'buyer/notifications',
    empty: 'Your order updates will appear here.',
    record: (context, record) => Card(
      child: ListTile(
        leading: Icon(
          record['is_read'] == true
              ? Icons.notifications_none
              : Icons.notifications_active_outlined,
          color: brand,
        ),
        title: Text(record['title'] as String),
        subtitle: Text('${record['body']}\n${localTime(record['created_at'])}'),
        onTap: () => buyerAction(context, () async {
          final buyer = BuyerScope.of(context);
          await buyer.api.data(
            'POST',
            'buyer/notifications/${record['id']}/read',
          );
          if (!context.mounted) return;
          await Navigator.push(
            context,
            MaterialPageRoute<void>(
              builder: (_) => OrderScreen(id: record['order_id'] as String),
            ),
          );
          buyer.invalidate();
        }),
      ),
    ),
  );
}

class BuyerReturnsScreen extends StatelessWidget {
  const BuyerReturnsScreen({super.key});
  @override
  Widget build(BuildContext context) => BuyerRecords(
    title: 'Returns and refunds',
    path: 'buyer/returns',
    empty: 'No requests yet. You can request a return from a delivered item in My orders.',
    record: (context, record) => Card(
      child: ListTile(
        title: Text(record['item_name'] as String),
        subtitle: Text('${record['label']}\nOrder ${record['order_number']}'),
        trailing: Text(pesos(record['requested_amount'] as int)),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute<void>(
            builder: (_) => ReturnCaseScreen(
              initial: record,
              owner: BuyerScope.of(context).user!['id'] as String,
            ),
          ),
        ),
      ),
    ),
  );
}

class ReturnCaseScreen extends StatefulWidget {
  const ReturnCaseScreen({
    super.key,
    required this.initial,
    required this.owner,
  });
  final Json initial;
  final String owner;
  @override
  State<ReturnCaseScreen> createState() => _ReturnCaseScreenState();
}

class _ReturnCaseScreenState extends State<ReturnCaseScreen> {
  late Json _record = widget.initial;
  bool _busy = false;
  Future<void> _refresh() async {
    if (_busy) return;
    final buyer = BuyerScope.of(context);
    setState(() => _busy = true);
    await buyerAction(context, () async {
      String? cursor;
      do {
        final response = await buyer.api.call(
          'GET',
          'buyer/returns',
          query: {'limit': '50', 'cursor': ?cursor},
        );
        for (final record in objects(response['data'])) {
          if (record['id'] == _record['id']) {
            if (mounted) setState(() => _record = record);
            return;
          }
        }
        cursor = response['next_cursor'] as String?;
      } while (cursor != null);
      throw const ApiFailure('This request is no longer available.');
    });
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: BuyerAppBar(
      title: const Text('Return / refund request'),
      actions: [
        IconButton(
          tooltip: 'Refresh request',
          onPressed: _busy ? null : _refresh,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: BuyerScope.of(context).user?['id'] != widget.owner
        ? const SignInRequired()
        : ListView(
            padding: const EdgeInsets.all(20),
            children: [
              if (_busy) const LinearProgressIndicator(),
              Text(
                _record['item_name'] as String,
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                ),
              ),
              Text('Order ${_record['order_number']}'),
              const SizedBox(height: 12),
              Notice(_record['label'] as String),
              const SizedBox(height: 12),
              Text(
                'Quantity: ${_record['quantity']} · Reason: ${_record['reason']}',
              ),
              Text(_record['details'] as String),
              const SizedBox(height: 12),
              Text('Requested: ${pesos(_record['requested_amount'] as int)}'),
              if (_record['approved_amount'] != null)
                Text('Approved: ${pesos(_record['approved_amount'] as int)}'),
              if (_record['return_required'] == true)
                const Notice(
                  'A physical return is required. Follow the seller/admin instructions before reimbursement.',
                ),
              if (_record['refunded_at'] != null)
                Text(
                  'Reimbursement recorded ${localTime(_record['refunded_at'])}\nReference: ${_record['refund_reference']}',
                ),
              if (_record['has_evidence'] == true)
                TextButton(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(
                      builder: (_) =>
                          ReturnEvidenceScreen(id: _record['id'] as String),
                    ),
                  ),
                  child: const Text('View private evidence'),
                ),
              const Divider(),
              for (final event in objects(_record['events']))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(statusLabel(event['status'])),
                  subtitle: Text(
                    '${event['note'] ?? ''}\n${localTime(event['occurred_at'])}',
                  ),
                ),
              const Notice(
                'Refunds are reviewed and recorded by the admin. Sending this request does not automatically transfer money.',
              ),
            ],
          ),
  );
}

class ReturnEvidenceScreen extends StatefulWidget {
  const ReturnEvidenceScreen({super.key, required this.id});
  final String id;
  @override
  State<ReturnEvidenceScreen> createState() => _ReturnEvidenceScreenState();
}

class _ReturnEvidenceScreenState extends State<ReturnEvidenceScreen> {
  Future<dynamic>? _future;
  String? _owner;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final buyer = BuyerScope.of(context);
    final owner = buyer.user?['id'] as String?;
    if (_owner != owner) {
      _owner = owner;
      _future = owner == null
          ? null
          : buyer.api.privateBytes('buyer/returns/${widget.id}/evidence');
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: BuyerAppBar(title: const Text('Private return evidence')),
    body: !BuyerScope.of(context).signedIn
        ? const SignInRequired()
        : FutureBuilder(
            key: ValueKey(_owner),
            future: _future,
            builder: (context, snapshot) => snapshot.hasError
                ? const Center(
                    child: Text(
                      'Evidence unavailable. Check your session and connection.',
                    ),
                  )
                : snapshot.hasData
                ? Center(
                    child: InteractiveViewer(
                      child: Image.memory(snapshot.data!),
                    ),
                  )
                : const Center(child: CircularProgressIndicator()),
          ),
  );
}

class RequestReturnScreen extends StatefulWidget {
  const RequestReturnScreen({super.key, required this.item});
  final Json item;
  @override
  State<RequestReturnScreen> createState() => _RequestReturnScreenState();
}

class _RequestReturnScreenState extends State<RequestReturnScreen> {
  final _form = GlobalKey<FormState>();
  final _details = TextEditingController();
  int _quantity = 1;
  String _reason = 'damaged', _key = requestId();
  String? _error;
  bool _busy = false, _uncertain = false;
  List<ChosenMedia> _evidence = [];
  Map<String, String>? _pending;
  List<BuyerUpload>? _files;
  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_uncertain && !_form.currentState!.validate()) return;
    if (!_uncertain) {
      _key = requestId();
      _pending = {
        'quantity': '$_quantity',
        'reason': _reason,
        'details': _details.text.trim(),
      };
      _files = [
        for (final file in _evidence)
          BuyerUpload('evidence', file.name, file.bytes),
      ];
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await BuyerScope.of(context).api.multipart(
        'buyer/order-items/${widget.item['id']}/returns',
        _pending!,
        _files!,
        _key,
      );
      if (mounted) {
        buyerMessage(context, 'Your return/refund request was submitted.');
        Navigator.pop(context);
      }
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(() {
          _uncertain = e.uncertain;
          _error = e.uncertain
              ? 'The result is unclear. Retry the same request, or check Returns and refunds.'
              : e.display;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: BuyerAppBar(title: const Text('Request return / refund')),
    body: !BuyerScope.of(context).signedIn
        ? const SignInRequired()
        : Form(
            key: _form,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Text(
                  widget.item['name'] as String,
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text('SKU: ${widget.item['sku'] ?? '—'}'),
                const SizedBox(height: 16),
                if (_error != null) Notice(_error!),
                DropdownButtonFormField<int>(
                  initialValue: 1,
                  decoration: const InputDecoration(labelText: 'Quantity'),
                  items: [
                    for (var i = 1; i <= (widget.item['qty'] as int); i++)
                      DropdownMenuItem(value: i, child: Text('$i')),
                  ],
                  onChanged: _busy || _uncertain ? null : (v) => _quantity = v!,
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  initialValue: _reason,
                  decoration: const InputDecoration(labelText: 'Reason'),
                  items: const [
                    DropdownMenuItem(
                      value: 'damaged',
                      child: Text('Damaged item'),
                    ),
                    DropdownMenuItem(
                      value: 'incorrect',
                      child: Text('Incorrect item'),
                    ),
                    DropdownMenuItem(
                      value: 'missing',
                      child: Text('Missing item'),
                    ),
                    DropdownMenuItem(value: 'other', child: Text('Other')),
                  ],
                  onChanged: _busy || _uncertain ? null : (v) => _reason = v!,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _details,
                  enabled: !_busy && !_uncertain,
                  maxLength: 2000,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'What happened?',
                  ),
                  validator: (v) => (v ?? '').trim().length < 10
                      ? 'Describe the issue in at least 10 characters.'
                      : null,
                ),
                AttachmentPicker(
                  singlePhoto: true,
                  disabled: _busy || _uncertain,
                  onChanged: (files) => _evidence = files,
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: Text(
                    _busy
                        ? 'Submitting…'
                        : _uncertain
                        ? 'Check request result'
                        : 'Submit request',
                  ),
                ),
              ],
            ),
          ),
  );
}
