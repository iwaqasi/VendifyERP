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

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 15);

  static const Map<String, String> defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
}
