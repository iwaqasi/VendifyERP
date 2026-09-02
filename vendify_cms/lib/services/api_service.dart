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

  // ============ Home ============
  Future<Map<String, dynamic>> getHome() async {
    final response = await _dio.get('/v1/cms/home', queryParameters: {
      'business_id': ApiConfig.businessId,
    });
    return response.data['data'];
  }

  // ============ Pages ============
  Future<Map<String, dynamic>> getPage(String slug) async {
    final response = await _dio.get('/v1/cms/pages/$slug', queryParameters: {
      'business_id': ApiConfig.businessId,
    });
    return response.data['data'];
  }

  // ============ Posts ============
  Future<Map<String, dynamic>> getPosts({String? category, String? search, int page = 1}) async {
    final params = <String, dynamic>{
      'business_id': ApiConfig.businessId,
      'page': page,
    };
    if (category != null) params['category'] = category;
    if (search != null) params['search'] = search;
    final response = await _dio.get('/v1/cms/posts', queryParameters: params);
    return response.data['data'];
  }

  Future<Map<String, dynamic>> getPost(String slug) async {
    final response = await _dio.get('/v1/cms/posts/$slug', queryParameters: {
      'business_id': ApiConfig.businessId,
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
      'business_id': ApiConfig.businessId,
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
      'business_id': ApiConfig.businessId,
    });
    return response.data['data'];
  }

  // ============ Categories ============
  Future<List<dynamic>> getCategories() async {
    final response = await _dio.get('/v1/cms/categories', queryParameters: {
      'business_id': ApiConfig.businessId,
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
      'business_id': ApiConfig.businessId,
      'name': name,
      'email': email,
      'message': message,
    });
    return response.data;
  }
}
