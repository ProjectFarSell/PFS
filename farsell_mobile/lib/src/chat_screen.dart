import 'buyer_navigation.dart';

import 'dart:async';

import 'package:material_ui/material_ui.dart';

import 'buyer_api.dart';
import 'buyer_records.dart';
import 'buyer_screens.dart';
import 'buyer_state.dart';
import 'design.dart';

class ConversationsScreen extends StatelessWidget {
  const ConversationsScreen({super.key});
  @override
  Widget build(BuildContext context) => BuyerRecords(
    title: 'Messages',
    path: 'buyer/conversations',
    empty: 'Start a chat from a product to contact its seller.',
    record: (context, record) => Card(
      child: ListTile(
        leading: Badge(
          isLabelVisible: (record['unread'] as int) > 0,
          label: Text('${record['unread']}'),
          child: const Icon(Icons.chat_bubble_outline, color: brand),
        ),
        title: Text(record['shop_name'] as String),
        subtitle: Text(
          record['last_message'] == null
              ? record['product_name'] as String? ?? 'Start chatting'
              : object(record['last_message'])['body'] as String,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        onTap: () async {
          await Navigator.push(
            context,
            MaterialPageRoute<void>(
              builder: (_) => ChatScreen(
                id: record['id'] as String,
                title: record['shop_name'] as String,
              ),
            ),
          );
          if (context.mounted) BuyerScope.of(context).invalidate();
        },
      ),
    ),
  );
}

class ChatScreen extends StatefulWidget {
  const ChatScreen({super.key, required this.id, required this.title});
  final String id, title;
  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> with WidgetsBindingObserver {
  final _text = TextEditingController(), _scroll = ScrollController();
  final Map<String, Json> _messages = {};
  final List<String> _ordered = [];
  Timer? _poll;
  String? _owner, _error, _older, _sendKey, _pendingBody;
  int _revision = -1, _generation = 0;
  bool _busy = false, _sending = false, _foreground = true;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _poll = Timer.periodic(const Duration(seconds: 6), (_) {
      if (mounted && _foreground && ModalRoute.of(context)?.isCurrent == true) {
        _load();
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    if (_foreground && mounted) _load();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final buyer = BuyerScope.of(context);
    final owner = buyer.user?['id'] as String?;
    if (_owner != owner) {
      _owner = owner;
      _messages.clear();
      _ordered.clear();
      _text.clear();
      _older = null;
      _generation++;
      _busy = false;
    }
    if (_owner != null && _revision != buyer.revision) {
      _revision = buyer.revision;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _load();
      });
    }
  }

  Future<void> _load({bool older = false}) async {
    if (_busy ||
        _owner == null ||
        !_foreground ||
        ModalRoute.of(context)?.isCurrent != true) {
      return;
    }
    final generation = ++_generation;
    final initiallyEmpty = _messages.isEmpty;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await BuyerScope.of(context).api.call(
        'GET',
        'buyer/conversations/${widget.id}/messages',
        query: {if (older && _older != null) 'cursor': _older!},
      );
      if (!mounted || generation != _generation) return;
      setState(() {
        final incoming = objects(result['data']).reversed.toList();
        final incomingIds = incoming.map((m) => m['id'] as String).toList();
        // If a long disconnection left a gap, restart from the latest page so
        // its cursor can retrieve every missed message, rather than skipping it.
        if (!older &&
            _messages.isNotEmpty &&
            !incomingIds.any(_messages.containsKey)) {
          _messages.clear();
          _ordered.clear();
          _older = result['next_cursor'] as String?;
        }
        if (older) {
          _ordered.insertAll(
            0,
            incomingIds.where((id) => !_ordered.contains(id)).toList(),
          );
        } else {
          _ordered.removeWhere(incomingIds.contains);
          _ordered.addAll(incomingIds);
        }
        for (final message in objects(result['data'])) {
          _messages[message['id'] as String] = message;
        }
        if (initiallyEmpty || older) _older = result['next_cursor'] as String?;
      });
      if (initiallyEmpty || _sending) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted && _scroll.hasClients) {
            _scroll.jumpTo(_scroll.position.maxScrollExtent);
          }
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

  Future<void> _send() async {
    if (_sending || _text.text.trim().isEmpty) return;
    FocusScope.of(context).unfocus();
    final body = _text.text.trim();
    if (_pendingBody != body) {
      _pendingBody = body;
      _sendKey = requestId();
    }
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final result = await BuyerScope.of(context).api.data(
        'POST',
        'buyer/conversations/${widget.id}/messages',
        body: {'body': body},
        key: _sendKey!,
      );
      if (!mounted) return;
      setState(() {
        _messages[result['id'] as String] = result;
        if (!_ordered.contains(result['id'])) {
          _ordered.add(result['id'] as String);
        }
        _text.clear();
        _pendingBody = null;
        _sendKey = null;
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _scroll.hasClients) {
          _scroll.jumpTo(_scroll.position.maxScrollExtent);
        }
      });
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(
          () => _error = e.uncertain
              ? 'Message result unclear. Tap send again to retry safely.'
              : e.display,
        );
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  void dispose() {
    _poll?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    _text.dispose();
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final messages = _ordered.map((id) => _messages[id]!).toList();
    return Scaffold(
      appBar: BuyerAppBar(title: Text(widget.title)),
      body: !BuyerScope.of(context).signedIn
          ? const SignInRequired()
          : Column(
              children: [
                if (_busy) const LinearProgressIndicator(),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.all(12),
                    child: Notice(_error!),
                  ),
                Expanded(
                  child: ListView(
                    controller: _scroll,
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (_older != null)
                        TextButton(
                          onPressed: _busy ? null : () => _load(older: true),
                          child: const Text('Load earlier messages'),
                        ),
                      for (final message in messages)
                        Align(
                          alignment: message['mine'] == true
                              ? Alignment.centerRight
                              : Alignment.centerLeft,
                          child: Container(
                            constraints: const BoxConstraints(maxWidth: 320),
                            margin: const EdgeInsets.symmetric(vertical: 5),
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: message['mine'] == true
                                  ? lavender
                                  : Colors.white,
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                SelectableText(message['body'] as String),
                                const SizedBox(height: 6),
                                Text(
                                  localTime(message['created_at']),
                                  style: const TextStyle(
                                    fontSize: 10,
                                    color: muted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      if (messages.isEmpty && !_busy)
                        const Text('Say hello to the seller.'),
                    ],
                  ),
                ),
                SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _text,
                            enabled: !_sending,
                            maxLength: 4000,
                            minLines: 1,
                            maxLines: 5,
                            decoration: const InputDecoration(
                              hintText: 'Message the seller…',
                            ),
                          ),
                        ),
                        IconButton(
                          tooltip: 'Send message',
                          onPressed: _sending ? null : _send,
                          icon: const Icon(Icons.send, color: brand),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}
