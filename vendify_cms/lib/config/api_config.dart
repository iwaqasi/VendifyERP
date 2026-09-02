class ApiConfig {
  // Tenant configuration for the Flutter CMS.
  //
  // Multi-tenant resolution - NO hardcoded business IDs in source:
  //   1. Build-time slug:  --dart-define=CMS_BUSINESS_SLUG=acme
  //   2. At startup the app fetches GET /v1/cms/config?slug=<slug> and the
  //      server confirms the business id + branding (CmsApiService.fetchConfig).
  //   3. If the API is unreachable, the build-time CMS_BUSINESS_ID fallback
  //      keeps the site renderable for offline development/preview.

  static const String baseUrl = String.fromEnvironment(
    'CMS_API_BASE_URL',
    defaultValue: 'http://localhost/api',
  );

  /// Build-time tenant slug (the preferred way to publish a tenant site).
  static const String businessSlug = String.fromEnvironment(
    'CMS_BUSINESS_SLUG',
    defaultValue: '',
  );

  /// Legacy build-time tenant ID - only a fallback when the runtime config
  /// endpoint cannot be reached.
  static const int fallbackBusinessId = int.fromEnvironment(
    'CMS_BUSINESS_ID',
    defaultValue: 1,
  );

  // Runtime-resolved values (filled by CmsApiService.fetchConfig()).
  static int? _resolvedBusinessId;
  static String? businessName;
  static String? logoUrl;
  static String? primaryColor;

  /// Tenant ID used by every API call: server-confirmed once the runtime
  /// config has loaded, build-time fallback otherwise.
  static int get activeBusinessId => _resolvedBusinessId ?? fallbackBusinessId;

  static set resolvedBusinessId(int? value) => _resolvedBusinessId = value;

  static bool get hasRuntimeConfig => _resolvedBusinessId != null;
}