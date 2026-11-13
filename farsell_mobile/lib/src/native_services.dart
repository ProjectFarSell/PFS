import 'dart:async';
import 'dart:convert';

import 'package:app_links/app_links.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:web_socket_channel/web_socket_channel.dart';
import 'package:web_socket_channel/io.dart';

import 'auth_screen.dart';
import 'buyer_api.dart';
import 'buyer_screens.dart';
import 'buyer_state.dart';
import 'catalog.dart';
import 'chat_screen.dart';
import 'firebase_setup.dart';
import 'product_screen.dart';

@pragma('vm:entry-point')
Future<void> buyerBackgroundMessage(RemoteMessage message) async {
  // The OS displays a generic notification. No account data or credentials are
  // read, logged or fetched by the background isolate.
  if (MobileFirebase.configured) {
    await MobileFirebase.initialize();
  }
}

bool opaqueId(dynamic value) =>
    value is String &&
    RegExp(
      r'^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$',
    ).hasMatch(value);
const buyerEventTypes = [
  'order.placed',
  'order.status_changed',
  'shipment.status_changed',
  'shipment.assigned',
  'shipment.cancelled',
  'chat.message_created',
];

Json? safeBuyerEvent(Json data) {
  if (!opaqueId(data['event_id']) || !buyerEventTypes.contains(data['type'])) {
    return null;
  }
  final expires = DateTime.tryParse('${data['expires_at'] ?? ''}');
  if (data.containsKey('expires_at') && expires == null) return null;
  if (expires != null && expires.isBefore(DateTime.now().toUtc())) return null;
  return {
    'event_id': data['event_id'],
    'type': data['type'],
    for (final key in ['order_id', 'shipment_id', 'conversation_id'])
      if (opaqueId(data[key])) key: data[key],
  };
}

Json? safeBuyerLink(Uri uri, String host) {
  if (host.isEmpty ||
      uri.scheme != 'https' ||
      uri.host != host ||
      uri.userInfo.isNotEmpty ||
      uri.query.isNotEmpty ||
      uri.fragment.isNotEmpty) {
    return null;
  }
  final parts = uri.pathSegments;
  if (parts.length != 3 ||
      parts.first != 'app' ||
      !['orders', 'products'].contains(parts[1]) ||
      !opaqueId(parts[2])) {
    return null;
  }
  return {parts[1] == 'orders' ? 'order_id' : 'product_id': parts[2]};
}

class BuyerNativeServices with WidgetsBindingObserver {
  BuyerNativeServices({
    required this.buyer,
    required this.catalog,
    required this.navigator,
  });
  final BuyerState buyer;
  final BuyerCatalog catalog;
  final GlobalKey<NavigatorState> navigator;
  static const linkHost = String.fromEnvironment('APP_LINK_HOST');
  static const socketUrl = String.fromEnvironment('REVERB_URL');
  static const socketKey = String.fromEnvironment('REVERB_APP_KEY');
  final _preferences = SharedPreferencesAsync();
  final _seen = <String>{};
  final List<StreamSubscription<dynamic>> _subscriptions = [];
  WebSocketChannel? _socket;
  Timer? _reconnect, _ping;
  String? _boundToken, _installation;
  Json? _target;
  bool _foreground = true,
      _navigating = false,
      _push = false,
      _disposed = false;
  int _generation = 0, _failures = 0;

  Future<void> start() async {
    WidgetsBinding.instance.addObserver(this);
    buyer.addListener(_identityChanged);
    buyer.enablePush = enableNotifications;
    final links = AppLinks();
    _subscriptions.add(links.uriLinkStream.listen(_link, onError: (_) {}));
    try {
      final initial = await links.getInitialLink();
      if (initial != null) _link(initial);
    } catch (_) {
      /* App links remain optional. */
    }
    try {
      _installation = await _preferences.getString(
        'farsell.buyer.installation',
      );
      _installation ??= requestId();
      await _preferences.setString(
        'farsell.buyer.installation',
        _installation!,
      );
      _push = await _preferences.getBool('farsell.buyer.push') ?? false;
    } catch (_) {
      _push = false;
    }
    if (MobileFirebase.configured) {
      try {
        await MobileFirebase.initialize();
        FirebaseMessaging.onBackgroundMessage(buyerBackgroundMessage);
        _subscriptions.add(
          FirebaseMessaging.onMessage.listen(
            (m) => _event(m.data, tapped: false),
          ),
        );
        _subscriptions.add(
          FirebaseMessaging.onMessageOpenedApp.listen(
            (m) => _event(m.data, tapped: true),
          ),
        );
        _subscriptions.add(
          FirebaseMessaging.instance.onTokenRefresh.listen((_) {
            if (_push && buyer.signedIn) _registerPush();
          }, onError: (_) {}),
        );
        final initial = await FirebaseMessaging.instance.getInitialMessage();
        if (initial != null) _event(initial.data, tapped: true);
      } catch (_) {
        buyer.liveMessage(
          'Notifications are unavailable. Pull to refresh your orders.',
        );
      }
    }
    _identityChanged();
  }

  void _identityChanged() {
    if (_disposed) return;
    final token = buyer.signedIn ? buyer.api.token : null;
    if (token == _boundToken) {
      _drainTarget();
      return;
    }
    if (_boundToken != null && token == null) {
      _target = null;
      _seen.clear();
    }
    _boundToken = token;
    _generation++;
    _failures = 0;
    _disconnect();
    if (token != null) {
      if (_foreground) _connect();
      if (_push) _registerPush();
      _drainTarget();
    }
  }

  Future<void> enableNotifications() async {
    if (!MobileFirebase.configured) {
      throw const ApiFailure(
        'Notifications are not available for this app build yet. Order updates are available in My orders.',
      );
    }
    if (!buyer.signedIn) {
      throw const ApiFailure('Sign in to enable account notifications.');
    }
    await MobileFirebase.initialize();
    final settings = await FirebaseMessaging.instance.requestPermission();
    if (settings.authorizationStatus == AuthorizationStatus.denied) {
      throw const ApiFailure(
        'Notifications are disabled in your phone settings. You can still check My orders.',
      );
    }
    _push = true;
    await _preferences.setBool('farsell.buyer.push', true);
    await _registerPush(throwErrors: true);
  }

  Future<void> _registerPush({bool throwErrors = false}) async {
    final token = buyer.api.token;
    if (!buyer.signedIn || token == null || !MobileFirebase.configured) return;
    try {
      await FirebaseMessaging.instance.setAutoInitEnabled(true);
      final fcm = await FirebaseMessaging.instance.getToken();
      if (buyer.api.token != token) return;
      if (fcm == null) {
        throw const ApiFailure(
          'Notifications could not be initialized. Please retry.',
        );
      }
      _installation ??= requestId();
      await _preferences.setString(
        'farsell.buyer.installation',
        _installation!,
      );
      await buyer.api.call(
        'PUT',
        'devices/$_installation',
        body: {
          'installation_id': _installation,
          'platform': 'android',
          'app': 'buyer',
          'fcm_token': fcm,
          'enabled_events': buyerEventTypes,
        },
      );
      buyer.liveMessage('Notifications enabled.');
    } catch (_) {
      if (throwErrors) {
        throw const ApiFailure(
          'Notifications could not be enabled. Please retry.',
        );
      }
    }
  }

  void _event(Json raw, {required bool tapped}) {
    if (_disposed) return;
    final event = safeBuyerEvent(raw);
    if (event == null) return;
    final id = event['event_id'] as String;
    if (_seen.add(id)) {
      if (_seen.length > 200) _seen.remove(_seen.first);
      if (buyer.signedIn) {
        buyer.invalidate();
        final context = navigator.currentContext;
        if (!tapped && _foreground && context != null) {
          ScaffoldMessenger.maybeOf(context)?.showSnackBar(
            SnackBar(
              content: const Text('You have a FarSell update.'),
              duration: const Duration(seconds: 3),
              action:
                  event['order_id'] == null && event['conversation_id'] == null
                  ? null
                  : SnackBarAction(
                      label: 'View',
                      onPressed: () {
                        _target = event;
                        _drainTarget();
                      },
                    ),
            ),
          );
        }
      }
    }
    if (tapped) {
      _target = event;
      _drainTarget();
    }
  }

  void _link(Uri uri) {
    final target = safeBuyerLink(uri, linkHost);
    if (target == null) return;
    _target = target;
    _drainTarget();
  }

  Future<void> _drainTarget() async {
    final nav = navigator.currentState;
    if (_navigating ||
        _target == null ||
        nav == null ||
        buyer.restoring ||
        _disposed) {
      return;
    }
    _navigating = true;
    final target = _target!;
    _target = null;
    try {
      if (target['product_id'] != null) {
        final product = Product.fromMobile(
          await buyer.api.data(
            'GET',
            'products/${target['product_id']}',
            authenticated: false,
          ),
        );
        if (!_disposed && nav.mounted) {
          await nav.push(
            MaterialPageRoute<void>(
              builder: (_) => ProductScreen(
                product: product,
                repository: catalog,
                state: buyer.guest,
              ),
            ),
          );
        }
      } else {
        if (!buyer.signedIn && nav.context.mounted) {
          if (!await signInBuyer(nav.context)) return;
        }
        if (!buyer.signedIn || !nav.mounted) return;
        if (target['conversation_id'] != null) {
          await nav.push(
            MaterialPageRoute<void>(
              builder: (_) => ChatScreen(
                id: target['conversation_id'] as String,
                title: 'Seller chat',
              ),
            ),
          );
        } else if (target['order_id'] != null) {
          await nav.push(
            MaterialPageRoute<void>(
              builder: (_) => OrderScreen(id: target['order_id'] as String),
            ),
          );
        }
      }
    } on ApiFailure {
      if (nav.mounted) {
        buyerMessage(
          nav.context,
          'This update is unavailable for your current account.',
        );
      }
    } finally {
      _navigating = false;
    }
  }

  Future<void> _connect() async {
    if (_disposed ||
        !_foreground ||
        _boundToken == null ||
        socketUrl.isEmpty ||
        socketKey.isEmpty) {
      return;
    }
    final url = Uri.tryParse(socketUrl);
    if (url == null ||
        !['ws', 'wss'].contains(url.scheme) ||
        (!kDebugMode && url.scheme != 'wss') ||
        url.userInfo.isNotEmpty) {
      return;
    }
    final generation = _generation;
    try {
      final socket = IOWebSocketChannel.connect(
        url.replace(
          path: '/app/$socketKey',
          queryParameters: {
            'protocol': '7',
            'client': 'farsell-buyer',
            'version': '1.0',
            'flash': 'false',
          },
        ),
        headers: {'Origin': buyer.api.base.origin},
        connectTimeout: const Duration(seconds: 12),
      );
      _socket = socket;
      await socket.ready;
      if (generation != _generation || _disposed) {
        await socket.sink.close();
        return;
      }
      socket.stream.listen(
        (raw) async {
          if (_disposed || generation != _generation || _socket != socket) {
            return;
          }
          try {
            final message = object(jsonDecode('$raw'));
            var data = message['data'];
            if (data is String) data = jsonDecode(data);
            if (message['event'] == 'pusher:connection_established') {
              final channel = 'private-user.${buyer.user!['id']}';
              final auth = await buyer.api.call(
                'POST',
                'broadcasting/auth',
                body: {
                  'socket_id': object(data)['socket_id'],
                  'channel_name': channel,
                },
              );
              if (generation != _generation || _socket != socket) return;
              socket.sink.add(
                jsonEncode({
                  'event': 'pusher:subscribe',
                  'data': {'channel': channel, 'auth': auth['auth']},
                }),
              );
              _failures = 0;
              buyer.invalidate();
              _ping?.cancel();
              _ping = Timer.periodic(const Duration(seconds: 25), (_) {
                if (_socket == socket) {
                  socket.sink.add('{"event":"pusher:ping","data":{}}');
                }
              });
            } else if (message['event'] == 'pusher:ping') {
              socket.sink.add('{"event":"pusher:pong","data":{}}');
            } else if (data is Map) {
              _event(object(data), tapped: false);
            }
          } catch (_) {
            /* Never log private socket/auth data. Reconnect refetches state. */
          }
        },
        onDone: () => _retry(generation),
        onError: (_) => _retry(generation),
        cancelOnError: true,
      );
    } catch (_) {
      _retry(generation);
    }
  }

  void _retry(int generation) {
    if (_disposed ||
        generation != _generation ||
        !_foreground ||
        _boundToken == null ||
        _reconnect?.isActive == true) {
      return;
    }
    _ping?.cancel();
    final delay = Duration(seconds: [2, 4, 8, 15, 30][_failures.clamp(0, 4)]);
    _failures++;
    _reconnect = Timer(delay, () {
      if (generation == _generation) _connect();
    });
  }

  void _disconnect() {
    _reconnect?.cancel();
    _ping?.cancel();
    _socket?.sink.close();
    _socket = null;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    if (!_foreground) {
      _generation++;
      _disconnect();
    } else {
      buyer.invalidate();
      _connect();
      if (_push) _registerPush();
    }
  }

  void dispose() {
    _disposed = true;
    _generation++;
    buyer.removeListener(_identityChanged);
    buyer.enablePush = null;
    WidgetsBinding.instance.removeObserver(this);
    _disconnect();
    for (final subscription in _subscriptions) {
      subscription.cancel();
    }
  }
}
