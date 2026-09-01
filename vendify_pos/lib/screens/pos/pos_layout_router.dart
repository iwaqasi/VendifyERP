import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';

// Import all POS layouts
import 'package:vendify_pos/screens/pos/layouts/retail_pos_screen.dart';
import 'package:vendify_pos/screens/pos/layouts/saloon_pos_screen.dart';
import 'package:vendify_pos/screens/pos/layouts/repair_pos_screen.dart';
import 'package:vendify_pos/screens/pos/layouts/restaurant_pos_screen.dart';
import 'package:vendify_pos/screens/pos/layouts/wholesale_pos_screen.dart';
import 'package:vendify_pos/screens/pos/layouts/clinic_pos_screen.dart';

class PosLayoutRouter extends ConsumerStatefulWidget {
  const PosLayoutRouter({super.key});

  @override
  ConsumerState<PosLayoutRouter> createState() => _PosLayoutRouterState();
}

class _PosLayoutRouterState extends ConsumerState<PosLayoutRouter> {
  late final PosService _posService = ref.read(posServiceProvider);
  String _businessType = 'retail'; // Default
  String _posLayout = 'retail';
  Map<String, dynamic> _features = {};
  bool _isLoading = true;
  List<Map<String, dynamic>> _locations = [];
  int? _selectedLocationId;

  @override
  void initState() {
    super.initState();
    _loadBusinessType();
  }

  Future<void> _loadBusinessType() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      
      // Fetch from API
      final businessData = await _posService.getBusinessType();
      
      if (businessData.isNotEmpty) {
        final type = businessData['business_type'] ?? 'retail';
        final layout = businessData['pos_layout'] ?? 'retail';
        final features = businessData['features'] ?? {};
        
        // Save to local cache
        await prefs.setString('business_type', type);
        await prefs.setString('pos_layout', layout);
        
        setState(() {
          _businessType = type;
          _posLayout = layout;
          _features = Map<String, dynamic>.from(features);
        });
      }

      // Load locations
      final locationsData = await _posService.getLocations();
      final savedLocationId = prefs.getInt('location_id');
      
      setState(() {
        _locations = locationsData;
        _selectedLocationId = savedLocationId;
        _isLoading = false;
      });
    } catch (e) {
      debugPrint('Error loading business type: $e');
      setState(() {
        _businessType = 'retail';
        _posLayout = 'retail';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        backgroundColor: AppTheme.background,
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircularProgressIndicator(color: AppTheme.primary),
              SizedBox(height: 16),
              Text(
                'Loading POS...',
                style: TextStyle(color: AppTheme.textSecondary),
              ),
            ],
          ),
        ),
      );
    }

    // If user has an assigned location AND a location is saved, skip the picker
    // This is the primary flow for cashiers — they log in and go directly to their assigned shop
    if (_selectedLocationId != null) {
      return _getPosScreen();
    }

    // Show location selector only when:
    // - Multiple locations exist, AND
    // - No location was saved (admin with no assignment, or first login)
    if (_locations.length > 1) {
      return _buildLocationSelector();
    }

    // Single location — go directly to POS
    return _getPosScreen();
  }

  Widget _buildLocationSelector() {
    // Get the saved user name for a personalized greeting
    String userName = '';
    String locationHint = '';
    SharedPreferences.getInstance().then((p) {
      userName = p.getString('user_name') ?? '';
    });

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: Center(
        child: Container(
          width: 400,
          padding: const EdgeInsets.all(32),
          decoration: BoxDecoration(
            color: AppTheme.surface,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppTheme.border),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.store, size: 48, color: AppTheme.primary),
              const SizedBox(height: 16),
              const Text(
                'Select Location',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
              ),
              const SizedBox(height: 8),
              Text(
                locationHint.isNotEmpty ? locationHint : 'Choose which location to operate from',
                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
              ),
              const SizedBox(height: 24),
              ..._locations.map((loc) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () async {
                      final prefs = await SharedPreferences.getInstance();
                      await prefs.setInt('location_id', loc['id']);
                      setState(() => _selectedLocationId = loc['id']);
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.surfaceLight,
                      foregroundColor: AppTheme.textPrimary,
                      side: const BorderSide(color: AppTheme.border),
                      padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.location_on, size: 20),
                        const SizedBox(width: 12),
                        Text(loc['name'] ?? 'Location'),
                        const Spacer(),
                        const Icon(Icons.chevron_right),
                      ],
                    ),
                  ),
                ),
              )),
            ],
          ),
        ),
      ),
    );
  }

  Widget _getPosScreen() {
    switch (_posLayout) {
      case 'saloon':
        return SaloonPosScreen(
          businessType: _businessType,
          features: _features,
        );
      case 'repair':
        return RepairPosScreen(
          businessType: _businessType,
          features: _features,
        );
      case 'restaurant':
        return RestaurantPosScreen(
          businessType: _businessType,
          features: _features,
        );
      case 'wholesale':
        return WholesalePosScreen(
          businessType: _businessType,
          features: _features,
        );
      case 'clinic':
        return ClinicPosScreen(
          businessType: _businessType,
          features: _features,
        );
      case 'retail':
      default:
        return RetailPosScreen(
          businessType: _businessType,
          features: _features,
        );
    }
  }
}
