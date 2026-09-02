class ApiConfig {
  // Build-time configuration for the CMS web bundles:
  //   flutter build web --dart-define=CMS_API_BASE_URL=https://example.com/api
  //                    --dart-define=CMS_BUSINESS_ID=3
  static const String baseUrl = String.fromEnvironment(
    'CMS_API_BASE_URL',
    defaultValue: 'http://localhost/api',
  );

  // Business (tenant) the website is being published for. Must be supplied
  // per client build; the default is a dev placeholder.
  static const int businessId = int.fromEnvironment(
    'CMS_BUSINESS_ID',
    defaultValue: 1,
  );
}
