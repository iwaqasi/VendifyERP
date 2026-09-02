import 'package:dio/dio.dart';
import 'package:vendify_cms/config/api_config.dart';

class CmsApiService {
  late final Dio _dio;

  /// [adapter] allows tests to inject an in-memory HttpClientAdapter so
  /// widget tests never perform real network I/O.
  CmsApiService({HttpClientAdapter? adapter}) {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));
    if (adapter != null) {
      _dio.httpClientAdapter = adapter;
    }
  }

  // ============ Runtime tenant config ============

  /// Resolves the tenant at runtime and stores the server-confirmed values.
  ///
  /// Priority: build-time slug -> build-time fallback business id. Every
  /// subsequent API call uses [ApiConfig.activeBusinessId], which is the
  /// server-confirmed id once this succeeds.
  Future<Map<String, dynamic>> fetchConfig() async {
    final params = <String, dynamic>{};
    if (ApiConfig.businessSlug.isNotEmpty) {
      params['slug'] = ApiConfig.businessSlug;
    } else {
      params['business_id'] = ApiConfig.fallbackBusinessId;
    }

    final response = await _dio.get('/v1/cms/config', queryParameters: params);
    final data = Map<String, dynamic>.from(response.data['data'] as Map);

    ApiConfig.resolvedBusinessId = (data['business_id'] as num).toInt();
    ApiConfig.businessName =
        (data['business_name'] as String?)?.trim().isNotEmpty == true
            ? data['business_name'] as String
            : null;
    ApiConfig.logoUrl = data['logo'] as String?;
    ApiConfig.primaryColor = data['primary_color'] as String?;

    return data;
  }

  // ============ Home ============
  Future<Map<String, dynamic>> getHome() async {
    final response = await _dio.get('/v1/cms/home', queryParameters: {
      'business_id': ApiConfig.activeBusinessId,
    });
    return response.data['data'];
  }

  // ============ Pages ============
  Future<Map<String, dynamic>> getPage(String slug) async {
    final response = await _dio.get('/v1/cms/pages/$slug', queryParameters: {
      'business_id': ApiConfig.activeBusinessId,
    });
    return response.data['data'];
  }

  // ============ Posts ============
  Future<Map<String, dynamic>> getPosts({String? category, String? search, int page = 1}) async {
    final params = <String, dynamic>{
      'business_id': ApiConfig.activeBusinessId,
      'page': page,
    };
    if (category != null) params['category'] = category;
    if (search != null) params['search'] = search;
    final response = await _dio.get('/v1/cms/posts', queryParameters: params);
    return response.data['data'];
  }

  Future<Map<String, dynamic>> getPost(String slug) async {
    final response = await _dio.get('/v1/cms/posts/$slug', queryParameters: {
      'business_id': ApiConfig.activeBusinessId,
    });
    return response.data['data'];
  }

  // ============ Products ============
  Future<Map<String, dynamic>> getProducts({
    int? categoryId,
    String? search,
    String? sortBy,
    int page = 1,
    int perPage = 24,
  }) async {
    final params = <String, dynamic>{
      'business_id': ApiConfig.activeBusinessId,
      'page': page,
      'per_page': perPage,
    };
    if (categoryId != null) params['category_id'] = categoryId;
    if (search != null) params['search'] = search;
    if (sortBy != null) params['sort'] = sortBy;
    final response = await _dio.get('/v1/cms/products', queryParameters: params);
    return response.data['data'];
  }

  Future<Map<String, dynamic>> getProduct(String slug) async {
    final response = await _dio.get('/v1/cms/products/$slug', queryParameters: {
      'business_id': ApiConfig.activeBusinessId,
    });
    return response.data['data'];
  }

  // ============ Categories ============
  Future<List<dynamic>> getCategories() async {
    final response = await _dio.get('/v1/cms/categories', queryParameters: {
      'business_id': ApiConfig.activeBusinessId,
    });
    return response.data['data']['categories'];
  }

  // ============ Contact ============
  Future<Map<String, dynamic>> submitContact({
    required String name,
    required String email,
    required String message,
  }) async {
    final response = await _dio.post('/v1/cms/contact', data: {
      'business_id': ApiConfig.activeBusinessId,
      'name': name,
      'email': email,
      'message': message,
    });
    return response.data;
  }
}
