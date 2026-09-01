import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/screens/pos/pos_layout_router.dart';
import 'package:vendify_pos/screens/login/pin_screen.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController(text: '');
  final _passwordController = TextEditingController(text: '');
  bool _obscurePassword = true;
  bool _isLoading = false;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final auth = ref.read(authServiceProvider);
      final response = await auth.login(
        email: _emailController.text.trim(),
        password: _passwordController.text,
      );

      // Save all user & business info for POS screen
      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt('business_id', response.user.businessId);
      await prefs.setInt('location_id', response.user.defaultLocationId ?? 1);
      await prefs.setString('business_name', response.user.businessName ?? '');
      await prefs.setString('business_slug', response.user.businessSlug ?? '');
      await prefs.setString('business_type', response.user.businessType ?? 'retail');
      await prefs.setString('user_name', response.user.name.isNotEmpty ? response.user.name : 'Admin');
      await prefs.setBool('has_assigned_location', response.user.hasAssignedLocation);
      if (response.user.defaultLocationName != null) {
        await prefs.setString('assigned_location_name', response.user.defaultLocationName!);
      }

      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const PosLayoutRouter()),
        );
      }
    } catch (e) {
      setState(() {
        _error = e.toString().replaceAll('Exception: ', '');
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppTheme.lg),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // Logo
                Container(
                  width: 80,
                  height: 80,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppTheme.surface,
                    border: Border.all(color: AppTheme.border),
                  ),
                  child: ClipOval(
                    child: Image.asset(
                      'assets/images/vendify_logo.png',
                      width: 70,
                      height: 70,
                      fit: BoxFit.cover,
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                const Text(
                  'Welcome to Vendify',
                  style: TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Sign in to your account',
                  style: TextStyle(
                    fontSize: 14,
                    color: AppTheme.textSecondary,
                  ),
                ),
                const SizedBox(height: 32),

                // Form
                Form(
                  key: _formKey,
                  child: Column(
                    children: [
                      // Email
                      TextField(
                        controller: _emailController,
                        style: const TextStyle(color: AppTheme.textPrimary),
                        decoration: InputDecoration(
                          hintText: 'Email address',
                          hintStyle: TextStyle(color: AppTheme.textMuted),
                          prefixIcon: const Icon(Icons.email_outlined, color: AppTheme.textMuted),
                          filled: true,
                          fillColor: AppTheme.surface,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                            borderSide: const BorderSide(color: AppTheme.border),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                            borderSide: const BorderSide(color: AppTheme.border),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                            borderSide: const BorderSide(color: AppTheme.primary, width: 2),
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Password
                      TextField(
                        controller: _passwordController,
                        obscureText: _obscurePassword,
                        style: const TextStyle(color: AppTheme.textPrimary),
                        decoration: InputDecoration(
                          hintText: 'Password',
                          hintStyle: TextStyle(color: AppTheme.textMuted),
                          prefixIcon: const Icon(Icons.lock_outlined, color: AppTheme.textMuted),
                          suffixIcon: IconButton(
                            icon: Icon(
                              _obscurePassword ? Icons.visibility_off : Icons.visibility,
                              color: AppTheme.textMuted,
                            ),
                            onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                          ),
                          filled: true,
                          fillColor: AppTheme.surface,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                            borderSide: const BorderSide(color: AppTheme.border),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                            borderSide: const BorderSide(color: AppTheme.border),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                            borderSide: const BorderSide(color: AppTheme.primary, width: 2),
                          ),
                        ),
                      ),

                    ],
                  ),
                ),

                const SizedBox(height: 12),

                // Error
                if (_error != null)
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppTheme.error.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                      border: Border.all(color: AppTheme.error.withValues(alpha: 0.3)),
                    ),
                    child: Text(
                      _error!,
                      style: const TextStyle(color: AppTheme.error, fontSize: 13),
                      textAlign: TextAlign.center,
                    ),
                  ),

                const SizedBox(height: 12),

                // Forgot password
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: () {},
                    child: const Text(
                      'Forgot Password?',
                      style: TextStyle(color: AppTheme.primary, fontSize: 13),
                    ),
                  ),
                ),

                const SizedBox(height: 8),

                // Login button
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: _isLoading ? null : _handleLogin,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      foregroundColor: Colors.black,
                    ),
                    child: _isLoading
                        ? const SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black),
                          )
                        : const Text(
                            'SIGN IN',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              letterSpacing: 1,
                            ),
                          ),
                  ),
                ),

                const SizedBox(height: 16),

                // Switch to PIN
                TextButton(
                  onPressed: () {
                    Navigator.pushReplacement(
                      context,
                      MaterialPageRoute(builder: (_) => const PinScreen()),
                    );
                  },
                  child: const Text(
                    'Use PIN instead',
                    style: TextStyle(color: AppTheme.textMuted, fontSize: 13),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
