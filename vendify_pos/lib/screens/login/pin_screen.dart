import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/screens/login/login_screen.dart';
import 'package:vendify_pos/screens/pos/pos_layout_router.dart';

class PinScreen extends ConsumerStatefulWidget {
  const PinScreen({super.key});

  @override
  ConsumerState<PinScreen> createState() => _PinScreenState();
}

class _PinScreenState extends ConsumerState<PinScreen> {
  String _pin = '';
  bool _isLoading = false;
  String? _error;
  final int _maxPinLength = 4;

  void _onKeyPress(String value) {
    if (_pin.length < _maxPinLength) {
      setState(() {
        _pin += value;
        _error = null;
      });
      HapticFeedback.lightImpact();

      if (_pin.length == _maxPinLength) {
        _validatePin();
      }
    }
  }

  void _onBackspace() {
    if (_pin.isNotEmpty) {
      setState(() {
        _pin = _pin.substring(0, _pin.length - 1);
        _error = null;
      });
      HapticFeedback.lightImpact();
    }
  }

  Future<void> _validatePin() async {
    setState(() => _isLoading = true);

    try {
      final api = ref.read(apiProvider);
      
      final response = await api.post('/v1/auth/login-by-pin', data: {
        'pin': _pin,
      });

      if (response.data['success'] == true) {
        final data = response.data['data'];
        await api.saveToken(data['access_token']);
        
        // Save business_id and location_id for POS screen
        final userData = data['user'] ?? {};
        final savedPrefs = await SharedPreferences.getInstance();
        await savedPrefs.setInt('business_id', userData['business_id'] ?? 1);
        await savedPrefs.setInt('location_id', userData['default_location_id'] ?? 1);
        await savedPrefs.setString('business_name', userData['business_name'] ?? '');
        await savedPrefs.setString('business_slug', userData['business_slug'] ?? '');
        await savedPrefs.setString('business_type', userData['business_type'] ?? 'retail');
        await savedPrefs.setString('user_name', userData['name'] ?? 'Admin');
        
        // Always go to POS layout router — it handles business type detection
        if (mounted) {
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(builder: (_) => const PosLayoutRouter()),
          );
        }
      } else {
        setState(() {
          _error = response.data['message'] ?? 'Invalid PIN. Please try again.';
          _pin = '';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = e.toString().replaceAll('Exception: ', '');
        _pin = '';
        _isLoading = false;
      });
    }
  }

  void _openFullLogin() {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 320),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Logo
                  Container(
                    width: 64,
                    height: 64,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppTheme.surface,
                      border: Border.all(color: AppTheme.border),
                    ),
                    child: ClipOval(
                      child: Image.asset(
                        'assets/images/vendify_logo.png',
                        width: 56,
                        height: 56,
                        fit: BoxFit.cover,
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Title
                  const Text(
                    'Enter Cashier PIN',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 4),

                  const SizedBox(height: 20),

                  // PIN Dots
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(_maxPinLength, (index) {
                      final isFilled = index < _pin.length;
                      return Container(
                        width: 12,
                        height: 12,
                        margin: const EdgeInsets.symmetric(horizontal: 5),
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: _error != null
                              ? AppTheme.error
                              : isFilled
                                  ? AppTheme.primary
                                  : AppTheme.surface,
                          border: Border.all(
                            color: _error != null
                                ? AppTheme.error
                                : isFilled
                                    ? AppTheme.primary
                                    : AppTheme.borderLight,
                            width: 2,
                          ),
                        ),
                      );
                    }),
                  ),

                  // Error
                  if (_error != null) ...[
                    const SizedBox(height: 10),
                    Text(
                      _error!,
                      style: const TextStyle(color: AppTheme.error, fontSize: 12),
                      textAlign: TextAlign.center,
                    ),
                  ],

                  // Loading
                  if (_isLoading) ...[
                    const SizedBox(height: 16),
                    const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        color: AppTheme.primary,
                        strokeWidth: 2,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Verifying PIN...',
                      style: TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                    ),
                  ],

                  const SizedBox(height: 20),

                  // Keypad
                  if (!_isLoading)
                    SizedBox(
                      width: 240,
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          _buildKeypadRow(['1', '2', '3']),
                          const SizedBox(height: 8),
                          _buildKeypadRow(['4', '5', '6']),
                          const SizedBox(height: 8),
                          _buildKeypadRow(['7', '8', '9']),
                          const SizedBox(height: 8),
                          _buildKeypadRow(['0', '⌫']),
                        ],
                      ),
                    ),

                  const SizedBox(height: 16),

                  // Switch to full login
                  TextButton(
                    onPressed: _openFullLogin,
                    child: const Text(
                      'Use email & password instead',
                      style: TextStyle(color: AppTheme.textMuted, fontSize: 12),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildKeypadRow(List<String> keys) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: keys.map((key) {
        final isBackspace = key == '⌫';
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 6),
          child: SizedBox(
            width: 64,
            height: 64,
            child: ElevatedButton(
              onPressed: isBackspace ? _onBackspace : () => _onKeyPress(key),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.surface,
                foregroundColor: AppTheme.textPrimary,
                elevation: 0,
                padding: EdgeInsets.zero,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(32),
                  side: const BorderSide(color: AppTheme.border, width: 1),
                ),
              ),
              child: isBackspace
                  ? const Icon(Icons.backspace_outlined, size: 20)
                  : Text(
                      key,
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
            ),
          ),
        );
      }).toList(),
    );
  }

  @override
  void dispose() {
    super.dispose();
  }
}
