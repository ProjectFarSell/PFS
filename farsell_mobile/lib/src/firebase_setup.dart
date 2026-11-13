import 'package:firebase_core/firebase_core.dart';

class MobileFirebase {
  static const apiKey = String.fromEnvironment('FIREBASE_API_KEY');
  static const appId = String.fromEnvironment('FIREBASE_APP_ID');
  static const projectId = String.fromEnvironment('FIREBASE_PROJECT_ID');
  static const senderId = String.fromEnvironment(
    'FIREBASE_MESSAGING_SENDER_ID',
  );
  static bool get configured =>
      [apiKey, appId, projectId, senderId].every((s) => s.isNotEmpty);
  static Future<FirebaseApp>? _initialization;
  static Future<FirebaseApp> initialize() {
    return _initialization ??= Firebase.initializeApp(
      options: const FirebaseOptions(
        apiKey: apiKey,
        appId: appId,
        projectId: projectId,
        messagingSenderId: senderId,
      ),
    );
  }
}
