import 'buyer_navigation.dart';

import 'package:material_ui/material_ui.dart';

import 'buyer_api.dart';
import 'buyer_state.dart';
import 'buyer_screens.dart';
import 'design.dart';

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key, this.choose = false});
  final bool choose;
  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  List<Json> _addresses = [];
  String? _error, _owner, _cursor;
  bool _busy = false, _more = false;
  int _generation = 0;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final owner = BuyerScope.of(context).user?['id'] as String?;
    if (_owner != owner) {
      _owner = owner;
      _generation++;
      _addresses = [];
      _busy = false;
      _cursor = null;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && owner != null) _load();
      });
    }
  }

  Future<void> _load({bool more = false}) async {
    if (_busy) return;
    final generation = ++_generation;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await BuyerScope.of(context).api.call(
        'GET',
        'buyer/addresses',
        query: {if (more && _cursor != null) 'cursor': _cursor!},
      );
      if (mounted && generation == _generation) {
        setState(() {
          _addresses = [
            ...(more ? _addresses : <Json>[]),
            ...objects(result['data']),
          ];
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

  Future<void> _edit([Json? address]) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => EditAddressScreen(address: address)),
    );
    if (mounted && changed == true) _load();
  }

  Future<void> _delete(Json address) async {
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Remove address?'),
        content: Text(address['formatted'] as String),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Remove'),
          ),
        ],
      ),
    );
    if (yes != true || !mounted) return;
    setState(() => _busy = true);
    await buyerAction(context, () async {
      await BuyerScope.of(context).api
          .call('DELETE', 'buyer/addresses/${address['id']}');
    });
    if (mounted) {
      setState(() => _busy = false);
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: BuyerAppBar(
      title: Text(
        widget.choose ? 'Choose delivery address' : 'Delivery addresses',
      ),
    ),
    floatingActionButton: BuyerScope.of(context).signedIn
        ? FloatingActionButton(
            onPressed: _busy ? null : _edit,
            tooltip: 'Add address',
            child: const Icon(Icons.add),
          )
        : null,
    body: !BuyerScope.of(context).signedIn
        ? const SignInRequired()
        : RefreshIndicator(
            onRefresh: _load,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 100),
              children: [
                if (_busy) const LinearProgressIndicator(),
                if (_error != null) ...[
                  Notice(_error!),
                  TextButton(
                    onPressed: _busy ? null : _load,
                    child: const Text('Retry'),
                  ),
                ],
                for (final address in _addresses)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${address['label'] ?? 'Address'}${address['is_default'] == true ? ' · Default' : ''}',
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          const SizedBox(height: 8),
                          Text(address['formatted'] as String),
                          if (address['phone'] != null)
                            Text(address['phone'] as String),
                          Wrap(
                            spacing: 8,
                            children: [
                              if (widget.choose)
                                FilledButton(
                                  onPressed: _busy
                                      ? null
                                      : () => Navigator.pop(context, address),
                                  child: const Text('Use this address'),
                                ),
                              TextButton(
                                onPressed: _busy ? null : () => _edit(address),
                                child: const Text('Edit'),
                              ),
                              TextButton(
                                onPressed: _busy
                                    ? null
                                    : () => _delete(address),
                                child: const Text('Remove'),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                if (!_busy && _addresses.isEmpty && _error == null)
                  const Notice('Add an address to arrange delivery.'),
                if (_more && !_busy)
                  TextButton(
                    onPressed: () => _load(more: true),
                    child: const Text('Load more addresses'),
                  ),
              ],
            ),
          ),
  );
}

class EditAddressScreen extends StatefulWidget {
  const EditAddressScreen({super.key, this.address});
  final Json? address;
  @override
  State<EditAddressScreen> createState() => _EditAddressScreenState();
}

class _EditAddressScreenState extends State<EditAddressScreen> {
  final _form = GlobalKey<FormState>();
  final _label = TextEditingController(),
      _line = TextEditingController(),
      _postal = TextEditingController(),
      _phone = TextEditingController();
  final List<List<Json>> _options = [[], [], [], []];
  final List<String?> _selected = [null, null, null, null];
  static const _levels = ['regions', 'provinces', 'cities', 'barangays'];
  static const _labels = [
    'Region',
    'Province',
    'City / municipality',
    'Barangay',
  ];
  static const _parents = ['', 'region_id', 'province_id', 'city_id'];
  String? _error;
  bool _busy = true, _default = false, _started = false;
  int _generation = 0;
  @override
  void initState() {
    super.initState();
    final a = widget.address;
    _label.text = a?['label'] as String? ?? 'Home';
    _line.text = a?['line1'] as String? ?? '';
    _postal.text = a?['postal_code'] as String? ?? '';
    _phone.text = a?['phone'] as String? ?? '';
    _default = a?['is_default'] == true;
    for (var i = 0; i < 4; i++) {
      _selected[i] =
          a?[['region_id', 'province_id', 'city_id', 'barangay_id'][i]]
              as String?;
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      _load(0, restore: true);
    }
  }

  Future<List<Json>> _directory(BuyerApi api, int level) async {
    final result = <Json>[];
    String? cursor;
    do {
      final data = await api.call(
        'GET',
        'locations/${_levels[level]}',
        authenticated: false,
        query: {
          'limit': '50',
          if (level > 0) _parents[level]: _selected[level - 1]!,
          'cursor': ?cursor,
        },
      );
      result.addAll(objects(data['data']));
      cursor = data['has_more'] == true ? data['next_cursor'] as String? : null;
      if (result.length > 5000) {
        throw const ApiFailure(
          'This directory is too large to load. Contact support.',
        );
      }
    } while (cursor != null);
    result.sort((a, b) => (a['name'] as String).compareTo(b['name'] as String));
    return result;
  }

  Future<void> _load(int level, {bool restore = false}) async {
    final generation = ++_generation;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final api = BuyerScope.of(context).api;
      for (var i = level; i < 4; i++) {
        if (i > 0 && _selected[i - 1] == null) break;
        final values = await _directory(api, i);
        if (!mounted || generation != _generation) return;
        setState(() {
          _options[i] = values;
          if (!values.any((v) => v['id'] == _selected[i])) {
            for (var j = i; j < 4; j++) {
              _selected[j] = null;
              if (j > i) _options[j] = [];
            }
          }
        });
        if (!restore || _selected[i] == null) break;
      }
    } on ApiFailure catch (e) {
      if (mounted && generation == _generation) {
        setState(() => _error = e.display);
      }
    } finally {
      if (mounted && generation == _generation) setState(() => _busy = false);
    }
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate() || _selected.any((v) => v == null)) {
      setState(
        () => _error = 'Complete the address and select all four locations.',
      );
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await BuyerScope.of(context).api.data(
        widget.address == null ? 'POST' : 'PUT',
        'buyer/addresses${widget.address == null ? '' : '/${widget.address!['id']}'}',
        body: {
          'label': _label.text.trim(),
          'line1': _line.text.trim(),
          'postal_code': _postal.text.trim(),
          'phone': _phone.text.trim(),
          'is_default': _default,
          for (var i = 0; i < 4; i++)
            ['region_id', 'province_id', 'city_id', 'barangay_id'][i]:
                _selected[i],
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiFailure catch (e) {
      if (mounted) setState(() => _error = e.display);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    for (final c in [_label, _line, _postal, _phone]) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: BuyerAppBar(
      title: Text(widget.address == null ? 'Add address' : 'Edit address'),
    ),
    body: !BuyerScope.of(context).signedIn
        ? const SignInRequired()
        : Form(
            key: _form,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                if (_busy) const LinearProgressIndicator(),
                if (_error != null) ...[
                  Notice(_error!),
                  TextButton(
                    onPressed: _busy ? null : () => _load(0, restore: true),
                    child: const Text('Reload locations'),
                  ),
                ],
                const SizedBox(height: 16),
                TextFormField(
                  controller: _label,
                  enabled: !_busy,
                  maxLength: 60,
                  decoration: const InputDecoration(
                    labelText: 'Label (Home, Work…)',
                  ),
                ),
                TextFormField(
                  controller: _line,
                  enabled: !_busy,
                  maxLength: 255,
                  decoration: const InputDecoration(
                    labelText: 'House / unit / street',
                  ),
                  validator: (v) => (v ?? '').trim().isEmpty
                      ? 'Enter your street address.'
                      : null,
                ),
                for (var i = 0; i < 4; i++) ...[
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    key: ValueKey(
                      'location-$i-${_selected[i]}-${_options[i].length}',
                    ),
                    initialValue:
                        _options[i].any((v) => v['id'] == _selected[i])
                        ? _selected[i]
                        : null,
                    isExpanded: true,
                    decoration: InputDecoration(labelText: _labels[i]),
                    items: [
                      for (final value in _options[i])
                        DropdownMenuItem(
                          value: value['id'] as String,
                          child: Text(
                            value['name'] as String,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                    ],
                    onChanged: _busy || _options[i].isEmpty
                        ? null
                        : (id) {
                            setState(() {
                              _selected[i] = id;
                              for (var j = i + 1; j < 4; j++) {
                                _selected[j] = null;
                                _options[j] = [];
                              }
                            });
                            if (id != null && i < 3) _load(i + 1);
                          },
                    validator: (v) => v == null
                        ? 'Select ${_labels[i].toLowerCase()}.'
                        : null,
                  ),
                ],
                const SizedBox(height: 16),
                TextFormField(
                  controller: _postal,
                  enabled: !_busy,
                  maxLength: 20,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Postal code (optional)',
                  ),
                ),
                TextFormField(
                  controller: _phone,
                  enabled: !_busy,
                  maxLength: 40,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Contact phone (optional)',
                  ),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Default address'),
                  value: _default,
                  onChanged: _busy ? null : (v) => setState(() => _default = v),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _busy ? null : _save,
                  child: Text(_busy ? 'Please wait…' : 'Save address'),
                ),
              ],
            ),
          ),
  );
}
