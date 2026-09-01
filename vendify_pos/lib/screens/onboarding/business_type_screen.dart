import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/screens/pos/pos_layout_router.dart';

class BusinessTypeScreen extends ConsumerStatefulWidget {
  final bool isFromLogin; // true if coming from login, false if changing type

  const BusinessTypeScreen({super.key, this.isFromLogin = true});

  @override
  ConsumerState<BusinessTypeScreen> createState() => _BusinessTypeScreenState();
}

class _BusinessTypeScreenState extends ConsumerState<BusinessTypeScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  List<Map<String, dynamic>> _businessTypes = [];
  String? _selectedType;
  bool _isLoading = true;
  bool _isSaving = false;

  // Icons for each business type
  static const Map<String, IconData> _typeIcons = {
    'saloon': Icons.spa,
    'repair': Icons.build,
    'restaurant': Icons.restaurant,
    'retail': Icons.store,
    'wholesale': Icons.warehouse,
    'clinic': Icons.local_hospital,
    'other': Icons.settings,
  };

  @override
  void initState() {
    super.initState();
    _loadBusinessTypes();
  }

  Future<void> _loadBusinessTypes() async {
    setState(() => _isLoading = true);
    try {
      // Check if business type is already set locally
      final localType = await _posService.loadBusinessType();
      if (localType != null && !widget.isFromLogin) {
        // Already configured, go to POS via layout router
        if (mounted) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (_) => const PosLayoutRouter()),
          );
        }
        return;
      }

      // Fetch available types from API
      final types = await _posService.getBusinessTypes();
      if (mounted) {
        setState(() {
          _businessTypes = types;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _selectBusinessType(String type) async {
    setState(() {
      _selectedType = type;
      _isSaving = true;
    });

    try {
      // Save to backend
      final success = await _posService.setBusinessType(type);

      if (success) {
        // Fetch the full config
        final config = await _posService.getBusinessType();

        // Save locally
        await _posService.saveBusinessType(config);

        if (mounted) {
          // Navigate to POS via layout router
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (_) => const PosLayoutRouter()),
          );
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Failed to save business type. Please try again.')),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: ${e.toString()}')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isSaving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: SafeArea(
        child: Column(
          children: [
            const SizedBox(height: 40),
            // Header
            Icon(
              Icons.business_center,
              size: 60,
              color: AppTheme.primary,
            ),
            const SizedBox(height: 16),
            Text(
              'Welcome to VendifyPOS',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
                color: AppTheme.textPrimary,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'What type of business do you have?',
              style: TextStyle(
                fontSize: 16,
                color: AppTheme.textSecondary,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'We\'ll customize your POS experience based on your business',
              style: TextStyle(
                fontSize: 13,
                color: AppTheme.textMuted,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 32),

            // Business Type Grid
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _buildTypeGrid(),
            ),

            // Skip button (for existing businesses)
            if (!widget.isFromLogin)
              Padding(
                padding: const EdgeInsets.all(16),
                child: TextButton(
                  onPressed: () {
                    Navigator.pushReplacement(
                      context,
                      MaterialPageRoute(builder: (_) => const PosLayoutRouter()),
                    );
                  },
                  child: Text(
                    'Skip for now',
                    style: TextStyle(color: AppTheme.textMuted),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildTypeGrid() {
    if (_businessTypes.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.wifi_off, size: 48, color: AppTheme.textMuted),
            const SizedBox(height: 16),
            Text(
              'Could not load business types',
              style: TextStyle(color: AppTheme.textSecondary),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadBusinessTypes,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: GridView.builder(
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 3,
          crossAxisSpacing: 16,
          mainAxisSpacing: 16,
          childAspectRatio: 1.4,
        ),
        itemCount: _businessTypes.length,
        itemBuilder: (context, index) {
          final type = _businessTypes[index];
          final isSelected = _selectedType == type['id'];
          final color = Color(int.parse(type['color'].replaceFirst('#', '0xFF')));

          return GestureDetector(
            onTap: _isSaving ? null : () => _selectBusinessType(type['id']),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              decoration: BoxDecoration(
                color: isSelected ? color.withValues(alpha: 0.1) : AppTheme.surface,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: isSelected ? color : AppTheme.border,
                  width: isSelected ? 2 : 1,
                ),
                boxShadow: isSelected
                    ? [
                        BoxShadow(
                          color: color.withValues(alpha: 0.2),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ]
                    : null,
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (isSelected && _isSaving)
                    SizedBox(
                      width: 32,
                      height: 32,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: color,
                      ),
                    )
                  else
                    Icon(
                      _typeIcons[type['id']] ?? Icons.business,
                      size: 36,
                      color: color,
                    ),
                  const SizedBox(height: 12),
                  Text(
                    type['label'],
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: AppTheme.textPrimary,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 4),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    child: Text(
                      type['description'],
                      style: TextStyle(
                        fontSize: 11,
                        color: AppTheme.textMuted,
                      ),
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
