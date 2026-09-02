class ApiConfig {
  // The API base URL is supplied at build time via --dart-define, e.g.:
  //   flutter build --dart-define=API_BASE_URL=https://erp.example.com/api
  // Never commit a production URL as the default for a source drop; the
  // default is for local development only.
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost/api',
  );

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 15);

  static const Map<String, String> defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
}
