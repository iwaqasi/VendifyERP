import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:vendify_pos/models/user.dart';
import 'package:vendify_pos/services/auth_service.dart';
import 'package:vendify_pos/services/api_service.dart';
import 'package:vendify_pos/providers/api_provider.dart';

enum AuthStatus { initial, loading, authenticated, unauthenticated, error }

class AuthState {
  final AuthStatus status;
  final User? user;
  final String? error;
  final bool hasPosLicense;

  AuthState({
    this.status = AuthStatus.initial,
    this.user,
    this.error,
    this.hasPosLicense = false,
  });
}

class AuthNotifier extends StateNotifier<AuthState> {
  final AuthService _authService;
  final ApiService _api;

  AuthNotifier(this._authService, this._api) : super(AuthState()) {
    _init();
  }

  Future<void> _init() async {
    // Load saved base URL and token
    await _api.loadBaseUrl();
    await _api.loadToken();

    if (_api.token != null) {
      try {
        final user = await _authService.getUserProfile();
        state = AuthState(
          status: AuthStatus.authenticated,
          user: user,
          hasPosLicense: true,
        );
      } catch (_) {
        // Token invalid
        _api.clearToken();
        state = AuthState(status: AuthStatus.unauthenticated);
      }
    } else {
      state = AuthState(status: AuthStatus.unauthenticated);
    }
  }

  Future<void> login({
    required String email,
    required String password,
    String? baseUrl,
  }) async {
    state = AuthState(status: AuthStatus.loading);

    try {
      if (baseUrl != null && baseUrl.isNotEmpty) {
        await _api.saveBaseUrl(baseUrl);
      }

      final response = await _authService.login(
        email: email,
        password: password,
      );

      // Check license
      try {
        final license = await _authService.checkLicense();
        state = AuthState(
          status: AuthStatus.authenticated,
          user: response.user,
          hasPosLicense: license['has_pos_license'] ?? false,
        );
      } catch (_) {
        state = AuthState(
          status: AuthStatus.authenticated,
          user: response.user,
          hasPosLicense: true, // Assume valid if check fails
        );
      }
    } catch (e) {
      state = AuthState(
        status: AuthStatus.error,
        error: e.toString().replaceAll('Exception: ', ''),
      );
    }
  }

  Future<void> logout() async {
    await _authService.logout();
    state = AuthState(status: AuthStatus.unauthenticated);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final api = ref.read(apiProvider);
  final authService = AuthService(api);
  return AuthNotifier(authService, api);
});
