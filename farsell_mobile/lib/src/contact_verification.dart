import 'buyer_navigation.dart';

import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:flutter/services.dart';

import 'auth_screen.dart';
import 'buyer_api.dart';
import 'buyer_state.dart';
import 'buyer_screens.dart';
import 'design.dart';

class ContactVerificationScreen extends StatefulWidget {
  const ContactVerificationScreen({super.key});
  @override
  State<ContactVerificationScreen> createState() =>
      _ContactVerificationScreenState();
}

class _ContactVerificationScreenState extends State<ContactVerificationScreen> {
  final _code = TextEditingController(), _phone = PhoneOtp();
  String _channel = 'email';
  String? _error;
  bool _busy = false, _sent = false;
  DateTime? _resend;
  Timer? _timer;
  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted && _resend != null) setState(() {});
    });
  }

  int get _seconds =>
      _resend?.difference(DateTime.now().toUtc()).inSeconds.clamp(0, 600) ?? 0;
  Future<void> _run(Future<void> Function() work) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await work();
    } on ApiFailure catch (e) {
      if (mounted) {
        setState(() {
          _error = e.display;
          if (e.retryAfter != null) {
            _resend = DateTime.now().toUtc().add(
              Duration(seconds: e.retryAfter!),
            );
          }
        });
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Verification could not be completed. Please retry.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _finish(Json proof) async {
    final buyer = BuyerScope.of(context);
    final session = await buyer.api.data(
      'POST',
      'account/contact-verification/verify',
      body: {'channel': _channel, ...proof},
    );
    if (mounted) {
      buyer.updateSession(session);
      Navigator.pop(context);
    }
  }

  void _send() => _run(() async {
    final result = await BuyerScope.of(context).api.data(
      'POST',
      'account/contact-verification/send',
      body: {'channel': _channel},
    );
    if (!mounted) return;
    setState(() {
      _resend = DateTime.tryParse(result['resend_after'] as String);
      _sent = false;
    });
    if (_channel == 'phone') {
      final proof = await _phone.send(result['phone'] as String);
      if (proof != null && mounted) {
        await _finish({'id_token': proof});
        return;
      }
    }
    if (mounted) setState(() => _sent = true);
  });
  void _verify() => _run(() async {
    if (!RegExp(r'^\d{6}$').hasMatch(_code.text)) {
      throw const ApiFailure('Enter the six-digit code.');
    }
    final proof = _channel == 'phone'
        ? {'id_token': await _phone.verify(_code.text)}
        : {'code': _code.text};
    if (mounted) await _finish(proof);
  });
  @override
  void dispose() {
    _timer?.cancel();
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final buyer = BuyerScope.of(context);
    return Scaffold(
      appBar: BuyerAppBar(title: const Text('Verify your contact')),
      body: !buyer.signedIn
          ? const SignInRequired()
          : ListView(
              padding: const EdgeInsets.all(24),
              children: [
                const Text(
                  'Choose your saved email or phone to verify your account.',
                ),
                const SizedBox(height: 16),
                if (_error != null) Notice(_error!),
                Wrap(
                  spacing: 8,
                  children: [
                    for (final channel in [
                      'email',
                      if (buyer.user!['phone'] != null && PhoneOtp.configured)
                        'phone',
                    ])
                      ChoiceChip(
                        label: Text(channel == 'email' ? 'Email' : 'Phone'),
                        selected: _channel == channel,
                        onSelected: _busy
                            ? null
                            : (_) => setState(() {
                                _channel = channel;
                                _sent = false;
                                _code.clear();
                              }),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                OutlinedButton(
                  onPressed: _busy || _seconds > 0 ? null : _send,
                  child: Text(
                    _seconds > 0 ? 'Send again in ${_seconds}s' : 'Send code',
                  ),
                ),
                if (_sent) ...[
                  const SizedBox(height: 16),
                  TextField(
                    controller: _code,
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    autofillHints: const [AutofillHints.oneTimeCode],
                    decoration: const InputDecoration(
                      labelText: 'Six-digit code',
                    ),
                  ),
                  FilledButton(
                    onPressed: _busy ? null : _verify,
                    child: Text(_busy ? 'Verifying…' : 'Verify contact'),
                  ),
                ],
              ],
            ),
    );
  }
}
