import 'package:vendify_pos/models/user.dart';
import 'package:vendify_pos/services/api_service.dart';

class AuthService {
  final ApiService _api;
  
  AuthService(this._api);

  Future<LoginResponse> login({
    required String email,
    required String password,
    String deviceName = 'pos-app',
  }) async {
    final response = await _api.post('/v1/auth/login', data: {
      'email': email,
      'password': password,
      'device_name': deviceName,
    });

    if (response.data['success'] == true) {
      final loginResponse = LoginResponse.fromJson(response.data);
      await _api.saveToken(loginResponse.accessToken);
      return loginResponse;
    }

    throw Exception(response.data['message'] ?? 'Login failed');
  }

  Future<void> logout() async {
    try {
      await _api.post('/v1/auth/logout');
    } catch (_) {
      // Ignore logout errors
    }
    _api.clearToken();
  }

  Future<User> getUserProfile() async {
    final response = await _api.get('/v1/auth/user');

    if (response.data['success'] == true) {
      return User.fromJson(response.data['data']);
    }

    throw Exception('Failed to load user profile');
  }

  Future<Map<String, dynamic>> checkLicense() async {
    final response = await _api.get('/v1/license/check');
    return response.data;
  }

  bool get isAuthenticated => _api.token != null;
}
