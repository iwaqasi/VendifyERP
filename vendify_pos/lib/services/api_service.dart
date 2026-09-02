import 'package:dio/dio.dart';
import 'package:pretty_dio_logger/pretty_dio_logger.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:vendify_pos/config/api_config.dart';

class ApiService {
  ApiService();

  Dio? _dio;
  String? _baseUrl;
  String? _token;
  bool _initialized = false;

  /// Initialize the API service with a base URL
  void init({String? baseUrl}) {
    _baseUrl = baseUrl ?? ApiConfig.baseUrl;

    _dio = Dio(BaseOptions(
      baseUrl: _baseUrl!,
      connectTimeout: ApiConfig.connectTimeout,
      receiveTimeout: ApiConfig.receiveTimeout,
      headers: Map<String, String>.from(ApiConfig.defaultHeaders),
    ));

    // HTTP request/response logging is DEBUG-ONLY. The auth token is never
    // written to logs, and response bodies may contain customer PII, so we
    // only log request metadata (method + URL + status) in release builds.
    if (kDebugMode) {
      _dio!.interceptors.add(PrettyDioLogger(
        requestHeader: true,
        requestBody: true,
        responseBody: true,
        error: true,
        compact: true,
      ));
    } else {
      _dio!.interceptors.add(InterceptorsWrapper(
        onRequest: (options, handler) {
          debugPrint('Req ${options.method} ${options.uri}');
          handler.next(options);
        },
        onResponse: (response, handler) {
          debugPrint('Res ${response.statusCode} ${response.requestOptions.uri}');
          handler.next(response);
        },
      ));
    }

    _dio!.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        if (_token != null) {
          options.headers['Authorization'] = 'Bearer $_token';
        }
        handler.next(options);
      },
      onError: (error, handler) {
        if (error.response?.statusCode == 401) {
          clearToken();
        }
        handler.next(error);
      },
    ));

    _initialized = true;
  }

  /// Re-initialize with a new base URL
  void reInit(String newUrl) {
    _baseUrl = newUrl;
    _initialized = false;
    init(baseUrl: newUrl);
  }

  void _ensureInitialized() {
    if (!_initialized || _dio == null) {
      init(baseUrl: _baseUrl);
    }
  }

  void setBaseUrl(String url) {
    _baseUrl = url;
    _dio?.options.baseUrl = url;
  }

  void setToken(String token) {
    _token = token;
  }

  void clearToken() {
    _token = null;
    const storage = FlutterSecureStorage();
    storage.delete(key: 'api_token');
  }

  String? get token => _token;
  String? get baseUrl => _baseUrl;
  Dio get dio {
    _ensureInitialized();
    return _dio!;
  }

  // ============ HTTP Methods ============

  Future<Response> get(String path, {Map<String, dynamic>? queryParameters}) {
    _ensureInitialized();
    return _dio!.get(path, queryParameters: queryParameters);
  }

  Future<Response> post(String path, {dynamic data}) {
    _ensureInitialized();
    return _dio!.post(path, data: data);
  }

  Future<Response> put(String path, {dynamic data}) {
    _ensureInitialized();
    return _dio!.put(path, data: data);
  }

  Future<Response> delete(String path) {
    _ensureInitialized();
    return _dio!.delete(path);
  }

  // ============ Token Persistence ============

  Future<void> saveToken(String token) async {
    _token = token;
    const storage = FlutterSecureStorage();
    await storage.write(key: 'api_token', value: token);
  }

  Future<void> loadToken() async {
    const storage = FlutterSecureStorage();
    _token = await storage.read(key: 'api_token');
  }

  Future<void> saveBaseUrl(String url) async {
    _baseUrl = url;
    _dio?.options.baseUrl = url;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('base_url', url);
  }

  Future<String?> loadBaseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    _baseUrl = prefs.getString('base_url') ?? ApiConfig.baseUrl;

    // Auto-fix: ensure URL ends with /api
    if (_baseUrl != null && !_baseUrl!.endsWith('/api')) {
      if (_baseUrl!.endsWith('/')) {
        _baseUrl = '${_baseUrl}api';
      } else {
        _baseUrl = '$_baseUrl/api';
      }
      // Save the corrected URL
      await prefs.setString('base_url', _baseUrl!);
    }

    return _baseUrl;
  }

  Future<void> clearAll() async {
    _token = null;
    const storage = FlutterSecureStorage();
    await storage.delete(key: 'api_token');
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('base_url');
  }
}
