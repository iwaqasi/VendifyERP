import 'package:flutter/foundation.dart';

class ApiConfig {
  // The API base URL can be supplied at build time via --dart-define, e.g.:
  //   flutter build --dart-define=API_BASE_URL=https://erp.example.com/api
  // When running on web, it automatically detects the current browser host origin.
  static String get baseUrl {
    const envUrl = String.fromEnvironment('API_BASE_URL');
    if (envUrl.isNotEmpty) {
      return envUrl;
    }
    if (kIsWeb) {
      final origin = Uri.base.origin;
      if (origin.isNotEmpty && !origin.startsWith('file://')) {
        return '$origin/api';
      }
    }
    return 'http://10.0.2.2:8000/api';
  }

  static const String appVersion = '1.1.0';
  static const String appBuild = '2';

  static String get downloadApkUrl {
    if (kIsWeb) {
      final origin = Uri.base.origin;
      if (origin.isNotEmpty && !origin.startsWith('file://')) {
        return '$origin/download/apk';
      }
    }
    return 'https://erp.arksoftsolutions.com/download/apk';
  }

  static String get downloadWindowsUrl {
    if (kIsWeb) {
      final origin = Uri.base.origin;
      if (origin.isNotEmpty && !origin.startsWith('file://')) {
        return '$origin/download/windows';
      }
    }
    return 'https://erp.arksoftsolutions.com/download/windows';
  }

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 15);

  static const Map<String, String> defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
}
