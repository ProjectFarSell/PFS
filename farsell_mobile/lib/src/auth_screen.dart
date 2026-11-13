import 'buyer_navigation.dart';

import 'dart:async';

import 'package:firebase_auth/firebase_auth.dart';
import 'package:material_ui/material_ui.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import 'buyer_api.dart';
import 'buyer_state.dart';
import 'design.dart';
import 'firebase_setup.dart';
import 'google_login.dart';

Future<void> openExternalTracking(Uri uri) async {
  if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
    throw const ApiFailure('Could not open the tracking link.');
  }
}

Future<void> openSite(BuildContext context, BuyerApi api, String path) async {
  final url = api.base.replace(path: path, query: '', fragment: '');
  bool opened;
  try {
    opened = await launchUrl(url, mode: LaunchMode.externalApplication);
  } catch (_) {
    opened = false;
  }
  if (!opened && context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Could not open your browser.')),
    );
  }
}

class PhoneOtp {
  static const apiKey = String.fromEnvironment('FIREBASE_API_KEY');
  static const appId = String.fromEnvironment('FIREBASE_APP_ID');
  static const projectId = String.fromEnvironment('FIREBASE_PROJECT_ID');
  static const senderId = String.fromEnvironment(
    'FIREBASE_MESSAGING_SENDER_ID',
  );
  static bool get configured =>
      [apiKey, appId, projectId, senderId].every((s) => s.isNotEmpty);
  FirebaseAuth? _auth;
  String? verificationId;
  Future<FirebaseAuth> _get() async {
    if (!configured) {
      throw const ApiFailure(
        'Phone sign-in is not configured for this mobile build. Choose email instead.',
      );
    }
    if (_auth != null) return _auth!;
    final app = await MobileFirebase.initialize();
    return _auth = FirebaseAuth.instanceFor(app: app);
  }

  Future<String?> send(String phone) async {
    final auth = await _get();
    final completer = Completer<String?>();
    await auth.verifyPhoneNumber(
      phoneNumber: phone,
      verificationCompleted: (credential) async {
        if (completer.isCompleted) return;
        try {
          final token = await _proof(auth, credential);
          if (!completer.isCompleted) completer.complete(token);
        } catch (_) {
          if (!completer.isCompleted) {
            completer.completeError(
              const ApiFailure('Phone verification failed. Please retry.'),
            );
          }
        }
      },
      verificationFailed: (_) {
        if (!completer.isCompleted) {
          completer.completeError(
            const ApiFailure(
              'SMS could not be sent. Check the number and mobile Firebase configuration, or use email.',
            ),
          );
        }
      },
      codeSent: (id, _) {
        verificationId = id;
        if (!completer.isCompleted) completer.complete(null);
      },
      codeAutoRetrievalTimeout: (id) {
        verificationId = id;
        if (!completer.isCompleted) completer.complete(null);
      },
    );
    return completer.future.timeout(
      const Duration(seconds: 75),
      onTimeout: () =>
          throw const ApiFailure('SMS verification timed out. Please retry.'),
    );
  }

  Future<String> verify(String code) async {
    if (verificationId == null) {
      throw const ApiFailure('Request a new SMS code first.');
    }
    try {
      return await _proof(
        await _get(),
        PhoneAuthProvider.credential(
          verificationId: verificationId!,
          smsCode: code,
        ),
      );
    } catch (_) {
      throw const ApiFailure(
        'The SMS code is invalid or expired. Please try again.',
      );
    }
  }

  Future<String> _proof(
    FirebaseAuth auth,
    PhoneAuthCredential credential,
  ) async {
    try {
      final result = await auth.signInWithCredential(credential);
      final token = await result.user?.getIdToken(true);
      if (token == null) throw const ApiFailure('Phone verification failed.');
      return token;
    } finally {
      await auth.signOut();
    }
  }
}

Future<bool> signInBuyer(BuildContext context) async =>
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        settings: const RouteSettings(name: '/buyer-sign-in'),
        builder: (_) => const AuthScreen(),
      ),
    ) ??
    false;

class AuthScreen extends StatefulWidget {
  const AuthScreen({super.key});
  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController(),
      _password = TextEditingController(),
      _name = TextEditingController(),
      _confirm = TextEditingController(),
      _code = TextEditingController();
  final _phone = PhoneOtp();
  Json? _challenge;
  bool _register = false,
      _consent = false,
      _busy = false,
      _sent = false,
      _obscure = true;
  String _channel = 'email';
  String? _error;
  DateTime? _resendAt;
  Timer? _timer;
  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted && _resendAt != null) setState(() {});
    });
  }

  int get _seconds => _resendAt == null
      ? 0
      : _resendAt!.difference(DateTime.now().toUtc()).inSeconds.clamp(0, 600);
  Future<void> _run(Future<void> Function() work) async {
    if (_busy) return;
    FocusScope.of(context).unfocus();
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
            _resendAt = DateTime.now().toUtc().add(
              Duration(seconds: e.retryAfter!),
            );
          }
        });
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Sign-in could not be completed. Please retry.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _accept(Json data) async {
    if (data['access_token'] != null) {
      await BuyerScope.of(context).finishLogin(data);
      if (mounted) Navigator.pop(context, true);
    } else {
      setState(() {
        _challenge = data;
        _sent = false;
        _channel = 'email';
        _code.clear();
      });
    }
  }

  void _login() {
    if (!_form.currentState!.validate()) return;
    if (_register && !_consent) {
      setState(
        () =>
            _error = 'Read and accept the privacy notice to create an account.',
      );
      return;
    }
    _run(() async {
      final result = await BuyerScope.of(context).api.data(
        'POST',
        _register ? 'auth/register' : 'auth/login',
        authenticated: false,
        body: {
          'email': _email.text.trim(),
          'password': _password.text,
          if (!_register) 'app': 'buyer',
          if (_register) ...{
            'name': _name.text.trim(),
            'password_confirmation': _confirm.text,
            'privacy_consent': _consent,
            'privacy_notice_version': '2026-11-03',
          },
        },
      );
      _password.clear();
      _confirm.clear();
      if (mounted) await _accept(result);
    });
  }

  void _google() {
    if (!BuyerGoogle.configured) {
      setState(
        () => _error = 'Google sign-in is temporarily unavailable. Use email and password.',
      );
      return;
    }
    if (!_consent) {
      setState(
        () => _error =
            'Read and accept the privacy notice before Google sign-in.',
      );
      return;
    }
    _run(() async {
      final token = await BuyerGoogle.token();
      if (!mounted) return;
      final result = await BuyerScope.of(context).api.data(
        'POST',
        'auth/google',
        authenticated: false,
        body: {
          'id_token': token,
          'privacy_consent': true,
          'privacy_notice_version': '2026-11-03',
        },
      );
      if (mounted) await _accept(result);
    });
  }

  void _send() => _run(() async {
    final api = BuyerScope.of(context).api;
    final sent = await api.data(
      'POST',
      'auth/challenges/send',
      authenticated: false,
      body: {
        'challenge_token': _challenge!['challenge_token'],
        'channel': _channel,
      },
    );
    if (!mounted) return;
    setState(() {
      _resendAt = DateTime.tryParse(sent['resend_after'] as String? ?? '');
      _sent = false;
    });
    if (_channel == 'phone') {
      final proof = await _phone.send(sent['phone'] as String);
      if (proof != null && mounted) {
        await _verifyPhone(proof);
        return;
      }
    }
    if (mounted) setState(() => _sent = true);
  });
  Future<void> _verifyPhone(String token) async {
    final result = await BuyerScope.of(context).api.data(
      'POST',
      'auth/challenges/verify',
      authenticated: false,
      body: {
        'challenge_token': _challenge!['challenge_token'],
        'channel': 'phone',
        'id_token': token,
      },
    );
    if (mounted) await _accept(result);
  }

  void _verify() {
    if (!RegExp(r'^\d{6}$').hasMatch(_code.text)) {
      setState(() => _error = 'Enter the six-digit code.');
      return;
    }
    _run(() async {
      if (_channel == 'phone') {
        final proof = await _phone.verify(_code.text);
        if (mounted) await _verifyPhone(proof);
      } else {
        final result = await BuyerScope.of(context).api.data(
          'POST',
          'auth/challenges/verify',
          authenticated: false,
          body: {
            'challenge_token': _challenge!['challenge_token'],
            'channel': 'email',
            'code': _code.text,
          },
        );
        if (mounted) await _accept(result);
      }
    });
  }

  @override
  void dispose() {
    for (final c in [_email, _password, _name, _confirm, _code]) {
      c.dispose();
    }
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final api = BuyerScope.of(context).api;
    return Scaffold(
      appBar: BuyerAppBar(
        title: Text(
          _challenge != null
              ? 'Verify your sign-in'
              : _register
              ? 'Create buyer account'
              : 'Sign in',
        ),
      ),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            const Icon(Icons.storefront, color: brand, size: 48),
            const SizedBox(height: 20),
            if (_error != null) ...[
              Notice(_error!),
              const SizedBox(height: 16),
            ],
            if (_challenge == null) ...[
              if (_register) ...[
                TextFormField(
                  controller: _name,
                  decoration: const InputDecoration(labelText: 'Full name'),
                  maxLength: 120,
                  validator: (v) =>
                      (v ?? '').trim().isEmpty ? 'Enter your name.' : null,
                ),
                const SizedBox(height: 12),
              ],
              TextFormField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.email],
                decoration: const InputDecoration(labelText: 'Email'),
                validator: (v) =>
                    !(v ?? '').contains('@') ? 'Enter your email.' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _password,
                obscureText: _obscure,
                autofillHints: [
                  _register
                      ? AutofillHints.newPassword
                      : AutofillHints.password,
                ],
                decoration: InputDecoration(
                  labelText: 'Password',
                  suffixIcon: IconButton(
                    tooltip: 'Show or hide password',
                    onPressed: () => setState(() => _obscure = !_obscure),
                    icon: Icon(
                      _obscure
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                    ),
                  ),
                ),
                validator: (v) => (v ?? '').isEmpty
                    ? 'Enter your password.'
                    : _register && v!.length < 12
                    ? 'Use at least 12 characters.'
                    : null,
              ),
              if (_register) ...[
                const SizedBox(height: 8),
                const Text(
                  'Use at least 12 characters with upper/lowercase letters, a number and a symbol.',
                  style: TextStyle(color: muted),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _confirm,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Confirm password',
                  ),
                  validator: (v) =>
                      v != _password.text ? 'Passwords must match.' : null,
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('I accept the privacy notice.'),
                  value: _consent,
                  onChanged: _busy
                      ? null
                      : (v) => setState(() => _consent = v ?? false),
                ),
                TextButton(
                  onPressed: () => openSite(context, api, '/privacy'),
                  child: const Text('Read privacy notice'),
                ),
              ] else
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: () => openSite(context, api, '/forgot-password'),
                    child: const Text('Forgot password?'),
                  ),
                ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _busy ? null : _login,
                child: Text(
                  _busy
                      ? 'Please wait…'
                      : _register
                      ? 'Create account'
                      : 'Sign in',
                ),
              ),
              if (BuyerGoogle.configured) ...[
                if (!_register)
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text(
                      'I accept the privacy notice for Google sign-in.',
                    ),
                    value: _consent,
                    onChanged: _busy
                        ? null
                        : (v) => setState(() => _consent = v ?? false),
                  ),
                TextButton(
                  onPressed: () => openSite(context, api, '/privacy'),
                  child: const Text('Privacy notice'),
                ),
              ],
              OutlinedButton.icon(
                onPressed: _busy ? null : _google,
                icon: const Icon(Icons.login),
                label: const Text('Sign in with Google'),
              ),
              TextButton(
                onPressed: _busy
                    ? null
                    : () => setState(() {
                        _register = !_register;
                        _error = null;
                      }),
                child: Text(
                  _register
                      ? 'Already have an account? Sign in'
                      : 'Create buyer account',
                ),
              ),
            ] else ...[
              Text(
                'Send a verification code to ${_channel == 'email' ? _challenge!['email_hint'] : _challenge!['phone_hint']}.',
                style: const TextStyle(height: 1.6),
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                children: [
                  for (final method in _challenge!['methods'] as List)
                    ChoiceChip(
                      label: Text(method == 'email' ? 'Email' : 'Phone'),
                      selected: _channel == method,
                      onSelected:
                          _busy || method == 'phone' && !PhoneOtp.configured
                          ? null
                          : (_) => setState(() {
                              _channel = method as String;
                              _sent = false;
                              _code.clear();
                              _error = null;
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
                  autofillHints: const [AutofillHints.oneTimeCode],
                  maxLength: 6,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'Six-digit code',
                  ),
                ),
                FilledButton(
                  onPressed: _busy ? null : _verify,
                  child: Text(_busy ? 'Verifying…' : 'Verify and sign in'),
                ),
              ],
              TextButton(
                onPressed: _busy
                    ? null
                    : () => setState(() {
                        _challenge = null;
                        _error = null;
                        _resendAt = null;
                      }),
                child: const Text('Back to sign in'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
