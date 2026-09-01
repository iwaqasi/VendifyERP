class ApiConfig {
   static const String baseUrl = 'https://erp.arksoftsolutions.com/api';
  // static const String baseUrl = 'http://ultimatepos7.0.test/api';
  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 15);

  static const Map<String, String> defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
}
