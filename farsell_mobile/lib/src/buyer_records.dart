import 'buyer_navigation.dart';

import 'package:material_ui/material_ui.dart';

import 'buyer_api.dart';
import 'buyer_state.dart';
import 'buyer_screens.dart';
import 'design.dart';

typedef BuyerRecordBuilder = Widget Function(BuildContext context, Json record);

class BuyerRecords extends StatefulWidget {
  const BuyerRecords({
    super.key,
    required this.title,
    required this.path,
    required this.record,
    this.public = false,
    this.empty = 'Nothing here yet.',
  });
  final String title, path, empty;
  final bool public;
  final BuyerRecordBuilder record;
  @override
  State<BuyerRecords> createState() => _BuyerRecordsState();
}

class _BuyerRecordsState extends State<BuyerRecords>
    with WidgetsBindingObserver {
  final List<Json> _records = [];
  String? _cursor, _error, _owner;
  int _generation = 0, _revision = -1;
  bool _busy = false, _more = false;
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
        ModalRoute.of(context)?.isCurrent == true) {
      _load();
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final buyer = BuyerScope.of(context);
    final owner = widget.public ? 'public' : buyer.user?['id'] as String?;
    if (_owner != owner) {
      _owner = owner;
      _records.clear();
      _cursor = null;
      _generation++;
      _busy = false;
      _revision = -1;
    }
    if (owner != null && _revision != buyer.revision) {
      _revision = buyer.revision;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && ModalRoute.of(context)?.isCurrent == true) _load();
      });
    }
  }

  Future<void> _load({bool more = false}) async {
    if (_busy || _owner == null) return;
    final generation = ++_generation;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final data = await BuyerScope.of(context).api.call(
        'GET',
        widget.path,
        authenticated: !widget.public,
        query: {if (more && _cursor != null) 'cursor': _cursor!},
      );
      if (mounted && generation == _generation) {
        setState(() {
          if (!more) _records.clear();
          _records.addAll(objects(data['data']));
          _cursor = data['next_cursor'] as String?;
          _more = data['has_more'] == true;
        });
      }
    } on ApiFailure catch (e) {
      if (mounted && generation == _generation) {
        setState(() => _error = e.display);
      }
    } catch (_) {
      if (mounted && generation == _generation) {
        setState(() => _error = 'Could not load this page. Please retry.');
      }
    } finally {
      if (mounted && generation == _generation) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: BuyerAppBar(
      title: Text(widget.title),
      actions: [
        IconButton(
          tooltip: 'Refresh',
          onPressed: _busy ? null : _load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: !widget.public && !BuyerScope.of(context).signedIn
        ? const SignInRequired()
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              children: [
                if (_busy) const LinearProgressIndicator(),
                if (_error != null) ...[
                  Notice(_error!),
                  TextButton(onPressed: _load, child: const Text('Retry')),
                ],
                for (final record in _records) widget.record(context, record),
                if (_records.isEmpty && !_busy && _error == null)
                  Notice(widget.empty),
                if (_more)
                  TextButton(
                    onPressed: _busy ? null : () => _load(more: true),
                    child: const Text('Load more'),
                  ),
              ],
            ),
          ),
  );
}
