import 'package:google_sign_in/google_sign_in.dart';

import 'buyer_api.dart';

class BuyerGoogle {
  static const serverClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
  );
  static bool get configured => serverClientId.isNotEmpty;
  static Future<void>? _initialization;
  static Future<String> token() async {
    if (!configured) {
      throw const ApiFailure(
        'Google sign-in is not configured for this mobile build. Use email and password.',
      );
    }
    try {
      await (_initialization ??= GoogleSignIn.instance.initialize(
        serverClientId: serverClientId,
      ));
      final account = await GoogleSignIn.instance.authenticate();
      final token = account.authentication.idToken;
      if (token == null) {
        throw const ApiFailure('Google could not provide a sign-in proof.');
      }
      return token;
    } on ApiFailure {
      rethrow;
    } catch (_) {
      throw const ApiFailure(
        'Google sign-in was cancelled or could not complete. Please retry.',
      );
    }
  }
}
