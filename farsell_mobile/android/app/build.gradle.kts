import java.util.Base64

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

val mobileDefines = (project.findProperty("dart-defines") as String? ?: "")
    .split(",").filter { it.isNotBlank() }.mapNotNull {
        val parts = String(Base64.getDecoder().decode(it)).split("=", limit = 2)
        if (parts.size == 2) parts[0] to parts[1] else null
    }.toMap()
val releaseSecrets = listOf("FARSELL_KEYSTORE", "FARSELL_STORE_PASSWORD", "FARSELL_KEY_ALIAS", "FARSELL_KEY_PASSWORD")
val releaseConfigured = releaseSecrets.all { !System.getenv(it).isNullOrBlank() }
if (gradle.startParameter.taskNames.any { it.contains("Release", ignoreCase = true) } && !releaseConfigured) {
    throw GradleException("Release signing is not configured. Supply the four FARSELL signing environment variables; debug signing is never used for release.")
}

android {
    namespace = "com.example.farsell_mobile"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = mobileDefines["APPLICATION_ID"] ?: "com.example.farsell_mobile"
        manifestPlaceholders["buyerAppLinkHost"] = mobileDefines["APP_LINK_HOST"] ?: "links.invalid"
        // Native Firebase initialization must work before the background Dart isolate.
        if (listOf("FIREBASE_APP_ID", "FIREBASE_API_KEY", "FIREBASE_MESSAGING_SENDER_ID", "FIREBASE_PROJECT_ID").all { !mobileDefines[it].isNullOrBlank() }) {
            resValue("string", "google_app_id", mobileDefines.getValue("FIREBASE_APP_ID"))
            resValue("string", "google_api_key", mobileDefines.getValue("FIREBASE_API_KEY"))
            resValue("string", "gcm_defaultSenderId", mobileDefines.getValue("FIREBASE_MESSAGING_SENDER_ID"))
            resValue("string", "project_id", mobileDefines.getValue("FIREBASE_PROJECT_ID"))
        }
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (releaseConfigured) {
            create("release") {
                storeFile = file(System.getenv("FARSELL_KEYSTORE"))
                storePassword = System.getenv("FARSELL_STORE_PASSWORD")
                keyAlias = System.getenv("FARSELL_KEY_ALIAS")
                keyPassword = System.getenv("FARSELL_KEY_PASSWORD")
            }
        }
    }
    buildTypes {
        release {
            if (releaseConfigured) signingConfig = signingConfigs.getByName("release")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
