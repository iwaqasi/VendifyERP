import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/contact.dart';
import 'package:vendify_pos/services/pos_service.dart';

/// Selected service item in the booking
class BookingService {
  final String name;
  final double price;
  final int durationMinutes;

  BookingService({required this.name, required this.price, required this.durationMinutes});
}

/// Dialog for creating a new appointment/booking from the POS screen.
/// Supports customer search/creation and multiple services per booking.
class BookingDialog extends StatefulWidget {
  final PosService posService;
  final int locationId;
  final List<Map<String, dynamic>> staff;
  final List<Map<String, dynamic>> services;
  final VoidCallback onBookingCreated;

  const BookingDialog({
    super.key,
    required this.posService,
    required this.locationId,
    this.staff = const [],
    this.services = const [],
    required this.onBookingCreated,
  });

  static Future<void> show({
    required BuildContext context,
    required PosService posService,
    required int locationId,
    List<Map<String, dynamic>> staff = const [],
    List<Map<String, dynamic>> services = const [],
    required VoidCallback onBookingCreated,
  }) {
    return showDialog(
      context: context,
      builder: (_) => BookingDialog(
        posService: posService,
        locationId: locationId,
        staff: staff,
        services: services,
        onBookingCreated: onBookingCreated,
      ),
    );
  }

  @override
  State<BookingDialog> createState() => _BookingDialogState();
}

class _BookingDialogState extends State<BookingDialog> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _notesController = TextEditingController();
  final _customerSearchController = TextEditingController();

  // Customer
  int? _selectedContactId;
  bool _isNewCustomer = false;

  // Services
  final List<BookingService> _selectedServices = [];
  String? _pendingServiceName;

  // Staff & time
  int? _selectedStaffId;
  DateTime _selectedDate = DateTime.now();
  TimeOfDay _selectedTime = TimeOfDay.now();
  bool _isSaving = false;

  // Customer search
  List<Contact> _allCustomers = [];
  List<Contact> _searchResults = [];
  bool _isSearchingCustomers = false;

  @override
  void initState() {
    super.initState();
    _loadInitialCustomers();
  }

  Future<void> _loadInitialCustomers() async {
    try {
      final list = await widget.posService.getContacts(type: 'customer', perPage: 200);
      if (mounted) {
        setState(() {
          _allCustomers = list;
        });
      }
    } catch (_) {}
  }

  List<Map<String, dynamic>> get _serviceList => widget.services.isNotEmpty
      ? widget.services
      : [
          {'name': 'Haircut', 'price': 35.0, 'duration': 30},
          {'name': 'Haircut Long Hair', 'price': 55.0, 'duration': 45},
          {'name': 'Hair Color', 'price': 80.0, 'duration': 90},
          {'name': 'Manicure', 'price': 25.0, 'duration': 30},
          {'name': 'Pedicure', 'price': 30.0, 'duration': 45},
          {'name': 'Facial', 'price': 45.0, 'duration': 60},
          {'name': 'Massage', 'price': 60.0, 'duration': 60},
        ];

  double get _totalPrice => _selectedServices.fold(0, (sum, s) => sum + s.price);
  int get _totalDuration => _selectedServices.fold(0, (sum, s) => sum + s.durationMinutes);

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _notesController.dispose();
    _customerSearchController.dispose();
    super.dispose();
  }

  // ========== Customer Search ==========

  Future<void> _searchCustomers(String query) async {
    final q = query.trim().toLowerCase();
    if (q.length < 2) {
      setState(() => _searchResults = []);
      return;
    }

    // 1. Instant local search from _allCustomers
    final cleanDigits = q.replaceAll(RegExp(r'[^0-9]'), '');
    final localMatches = _allCustomers.where((c) {
      final nameMatches = c.name.toLowerCase().contains(q);
      final phone = (c.mobile ?? '').replaceAll(RegExp(r'[^0-9]'), '');
      final phoneMatches = cleanDigits.length >= 3 && phone.contains(cleanDigits);
      return nameMatches || phoneMatches;
    }).toList();

    setState(() {
      _searchResults = localMatches;
      _isSearchingCustomers = true;
    });

    // 2. Fetch from backend API
    try {
      final results = await widget.posService.getContacts(
        search: query.trim(),
        type: 'customer',
        perPage: 20,
      );
      if (mounted) {
        final merged = List<Contact>.from(results);
        for (final loc in localMatches) {
          if (!merged.any((m) => m.id == loc.id)) {
            merged.add(loc);
          }
        }
        setState(() {
          _searchResults = merged;
          _isSearchingCustomers = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isSearchingCustomers = false);
    }
  }

  void _selectExistingCustomer(Contact customer) {
    setState(() {
      _selectedContactId = customer.id;
      _nameController.text = customer.name;
      _phoneController.text = customer.mobile ?? '';
      _isNewCustomer = false;
      _searchResults = [];
      _customerSearchController.text = customer.name;
    });
  }

  void _switchToNewCustomer() {
    setState(() {
      _selectedContactId = null;
      _isNewCustomer = true;
      _nameController.clear();
      _phoneController.clear();
      _customerSearchController.clear();
      _searchResults = [];
    });
  }

  // ========== Service Management ==========

  void _addService(Map<String, dynamic> service) async {
    final name = (service['name'] ?? service['service_name'] ?? '').toString();
    double price = (service['price'] ?? service['sell_price_inc_tax'] ?? 0).toDouble();
    final duration = service['duration'] ?? service['service_duration_minutes'] ?? 30;

    // Prevent duplicate services
    if (_selectedServices.any((s) => s.name == name)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('$name is already added'),
          duration: const Duration(seconds: 1),
          backgroundColor: AppTheme.warning,
        ),
      );
      return;
    }

    if (price <= 0) {
      final enteredPrice = await _showPriceDialog(
        context: context,
        title: 'Set Price for $name',
        initialPrice: 0,
      );
      if (enteredPrice == null) return;
      price = enteredPrice;
    }

    setState(() {
      _selectedServices.add(BookingService(
        name: name,
        price: price,
        durationMinutes: duration is int ? duration : (duration as double).toInt(),
      ));
    });
  }

  void _editServicePrice(int index) async {
    final item = _selectedServices[index];
    final newPrice = await _showPriceDialog(
      context: context,
      title: 'Update Price for ${item.name}',
      initialPrice: item.price,
    );
    if (newPrice != null) {
      setState(() {
        _selectedServices[index] = BookingService(
          name: item.name,
          price: newPrice,
          durationMinutes: item.durationMinutes,
        );
      });
    }
  }

  Widget _denomChip(String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(6),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
        decoration: BoxDecoration(
          color: AppTheme.primary.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: AppTheme.primary.withValues(alpha: 0.3)),
        ),
        child: Text(
          label,
          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.primary),
        ),
      ),
    );
  }

  Widget _presetChip(String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(6),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
        decoration: BoxDecoration(
          color: const Color(0xFFE91E63).withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: const Color(0xFFE91E63).withValues(alpha: 0.25)),
        ),
        child: Text(
          label,
          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFFE91E63)),
        ),
      ),
    );
  }

  Future<double?> _showPriceDialog({
    required BuildContext context,
    required String title,
    required double initialPrice,
  }) async {
    double currentPrice = initialPrice > 0 ? initialPrice : 0.0;
    final controller = TextEditingController(
      text: currentPrice > 0 ? currentPrice.toStringAsFixed(3) : '',
    );

    return showDialog<double>(
      context: context,
      builder: (dlgCtx) {
        return StatefulBuilder(
          builder: (context, setDlgState) {
            void addAmount(double amt) {
              final val = double.tryParse(controller.text) ?? 0.0;
              final newVal = val + amt;
              controller.text = newVal.toStringAsFixed(3);
              setDlgState(() {});
            }

            void setAmount(double amt) {
              controller.text = amt.toStringAsFixed(3);
              setDlgState(() {});
            }

            return AlertDialog(
              backgroundColor: AppTheme.surface,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE91E63).withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(Icons.monetization_on, color: Color(0xFFE91E63), size: 20),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      title,
                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                    ),
                  ),
                ],
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Service Price (KD):', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
                    const SizedBox(height: 6),
                    TextField(
                      controller: controller,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      autofocus: true,
                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: AppTheme.primary),
                      decoration: InputDecoration(
                        prefixText: 'KD ',
                        prefixStyle: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.primary),
                        suffixIcon: IconButton(
                          icon: const Icon(Icons.clear, size: 18),
                          onPressed: () {
                            controller.clear();
                            setDlgState(() {});
                          },
                        ),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      ),
                    ),
                    const SizedBox(height: 12),
                    const Text('Quick Denominations (+KD):', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppTheme.textMuted)),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        _denomChip('+0.500', () => addAmount(0.5)),
                        _denomChip('+1.000', () => addAmount(1.0)),
                        _denomChip('+2.000', () => addAmount(2.0)),
                        _denomChip('+5.000', () => addAmount(5.0)),
                        _denomChip('+10.00', () => addAmount(10.0)),
                        _denomChip('+20.00', () => addAmount(20.0)),
                        _denomChip('+50.00', () => addAmount(50.0)),
                      ],
                    ),
                    const SizedBox(height: 8),
                    const Text('Presets:', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppTheme.textMuted)),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        _presetChip('KD 5', () => setAmount(5.0)),
                        _presetChip('KD 10', () => setAmount(10.0)),
                        _presetChip('KD 15', () => setAmount(15.0)),
                        _presetChip('KD 25', () => setAmount(25.0)),
                        _presetChip('KD 35', () => setAmount(35.0)),
                        _presetChip('KD 50', () => setAmount(50.0)),
                      ],
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dlgCtx, null),
                  child: const Text('Cancel', style: TextStyle(color: AppTheme.textMuted)),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  onPressed: () {
                    final price = double.tryParse(controller.text) ?? 0.0;
                    Navigator.pop(dlgCtx, price);
                  },
                  child: const Text('Confirm', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  void _showAddCustomServiceDialog() async {
    final nameCtrl = TextEditingController();
    double price = 0.0;
    int duration = 30;

    await showDialog(
      context: context,
      builder: (dlgCtx) {
        return StatefulBuilder(
          builder: (context, setDlgState) {
            void addAmt(double amt) {
              price += amt;
              setDlgState(() {});
            }
            void setAmt(double amt) {
              price = amt;
              setDlgState(() {});
            }

            return AlertDialog(
              backgroundColor: AppTheme.surface,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: const Row(
                children: [
                  Icon(Icons.add_circle, color: Color(0xFFE91E63)),
                  SizedBox(width: 8),
                  Text('Add Custom Service', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                ],
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Service Name:*', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 4),
                    TextField(
                      controller: nameCtrl,
                      autofocus: true,
                      decoration: InputDecoration(
                        hintText: 'e.g. Special Beard Trim, Face Bleach',
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text('Price: KD ${price.toStringAsFixed(3)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.primary)),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        _denomChip('+0.500', () => addAmt(0.5)),
                        _denomChip('+1.000', () => addAmt(1.0)),
                        _denomChip('+2.000', () => addAmt(2.0)),
                        _denomChip('+5.000', () => addAmt(5.0)),
                        _denomChip('+10.00', () => addAmt(10.0)),
                        _denomChip('+20.00', () => addAmt(20.0)),
                        _presetChip('Clear', () => setAmt(0.0)),
                      ],
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(onPressed: () => Navigator.pop(dlgCtx), child: const Text('Cancel')),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
                  onPressed: () {
                    final name = nameCtrl.text.trim();
                    if (name.isEmpty) return;
                    Navigator.pop(dlgCtx);
                    _addService({
                      'name': name,
                      'price': price,
                      'duration': duration,
                    });
                  },
                  child: const Text('Add Service', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  void _removeService(int index) {
    setState(() => _selectedServices.removeAt(index));
  }

  // ========== Date/Time ==========

  Future<void> _selectDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 90)),
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.dark(
            primary: Color(0xFFE91E63), onPrimary: Colors.white,
            surface: AppTheme.surface, onSurface: AppTheme.textPrimary,
          ),
        ),
        child: child!,
      ),
    );
    if (picked != null) setState(() => _selectedDate = picked);
  }

  Future<void> _selectTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _selectedTime,
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.dark(
            primary: Color(0xFFE91E63), onPrimary: Colors.white,
            surface: AppTheme.surface, onSurface: AppTheme.textPrimary,
          ),
        ),
        child: child!,
      ),
    );
    if (picked != null) setState(() => _selectedTime = picked);
  }

  // ========== Save ==========

  Future<void> _saveBooking() async {
    if (_formKey.currentState?.validate() != true) return;
    if (_selectedServices.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please add at least one service'), backgroundColor: AppTheme.error),
      );
      return;
    }

    // Auto-resolve customer if user typed in search box but didn't tap dropdown item
    if (!_isNewCustomer && _selectedContactId == null) {
      if (_searchResults.isNotEmpty) {
        final query = _customerSearchController.text.trim().toLowerCase();
        final match = _searchResults.firstWhere(
          (c) => (c.mobile != null && c.mobile!.contains(query)) || c.name.toLowerCase().contains(query),
          orElse: () => _searchResults.first,
        );
        _selectExistingCustomer(match);
      } else if (_customerSearchController.text.trim().isNotEmpty) {
        final query = _customerSearchController.text.trim();
        final cleanDigits = query.replaceAll(RegExp(r'[^0-9]'), '');
        Contact? found;
        if (cleanDigits.length >= 4) {
          try {
            found = _allCustomers.firstWhere(
              (c) => (c.mobile ?? '').replaceAll(RegExp(r'[^0-9]'), '').contains(cleanDigits),
            );
          } catch (_) {}
        }
        if (found == null) {
          try {
            found = _allCustomers.firstWhere(
              (c) => c.name.toLowerCase() == query.toLowerCase(),
            );
          } catch (_) {}
        }

        if (found != null) {
          _selectExistingCustomer(found);
        } else {
          _nameController.text = query;
          if (RegExp(r'^[0-9+\s\-]+$').hasMatch(query)) {
            _phoneController.text = query;
          }
        }
      }
    }

    final customerName = _nameController.text.trim();
    if (customerName.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please select a customer or switch to "Add New Customer"'),
          backgroundColor: AppTheme.error,
        ),
      );
      return;
    }

    setState(() => _isSaving = true);

    // Save form fields (triggers onSaved callbacks for last name etc.)
    _formKey.currentState?.save();

    try {
      final startDateTime = DateTime(
        _selectedDate.year, _selectedDate.month, _selectedDate.day,
        _selectedTime.hour, _selectedTime.minute,
      );

      final resolvedCustomerName = _nameController.text.trim();

      // If contactId is null, attempt to create customer automatically
      int? contactId = _selectedContactId;
      if (contactId == null && resolvedCustomerName.isNotEmpty) {
        try {
          final newContact = await widget.posService.createContact(
            name: resolvedCustomerName,
            type: 'customer',
            mobile: _phoneController.text.trim().isNotEmpty ? _phoneController.text.trim() : null,
          );
          contactId = newContact.id;
        } catch (_) {
          // If creation fails, continue without contact (backend storeAppointment will resolve by phone/name)
        }
      }

      // Create appointment with multiple services
      final serviceNames = _selectedServices.map((s) => s.name).join(', ');
      final servicePrices = _selectedServices.map((s) => s.price).toList();

      await widget.posService.createSaloonAppointment({
        'customer_name': resolvedCustomerName,
        'customer_phone': _phoneController.text.trim().isNotEmpty ? _phoneController.text.trim() : null,
        'contact_id': contactId,
        'service_name': serviceNames,
        'service_price': _totalPrice,
        'service_duration_minutes': _totalDuration,
        'services_detail': servicePrices, // individual prices for each service
        'appointment_start': startDateTime.toIso8601String(),
        'staff_id': _selectedStaffId,
        'location_id': widget.locationId,
        'notes': _notesController.text.trim().isNotEmpty ? _notesController.text.trim() : null,
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('✅ Booked ${_selectedServices.length} service(s) for $resolvedCustomerName'),
            backgroundColor: AppTheme.success,
          ),
        );
        widget.onBookingCreated();
        Navigator.of(context).pop();
      }
    } catch (e) {
      if (mounted) {
        String errorMessage = 'Failed to create appointment';
        if (e is DioException) {
          dynamic resData = e.response?.data;
          if (resData is String) {
            try {
              resData = jsonDecode(resData);
            } catch (_) {}
          }
          if (resData is Map) {
            if (resData['message'] != null) {
              errorMessage = resData['message'].toString();
            } else if (resData['error'] != null) {
              errorMessage = resData['error'].toString();
            }
            if (resData['errors'] != null && resData['errors'] is Map) {
              final errMap = resData['errors'] as Map;
              final firstErr = errMap.values.first;
              if (firstErr is List && firstErr.isNotEmpty) {
                errorMessage += ': ${firstErr.first}';
              } else if (firstErr != null) {
                errorMessage += ': $firstErr';
              }
            }
          } else {
            errorMessage = e.message ?? e.toString();
          }
        } else {
          errorMessage = e.toString();
        }
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('❌ $errorMessage'), backgroundColor: AppTheme.error),
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        width: 540,
        constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.85),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Header
            _buildHeader(),
            // Form
            Flexible(child: _buildForm()),
            // Footer
            _buildFooter(),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: AppTheme.border))),
      child: Row(
        children: [
          Container(
            width: 40, height: 40,
            decoration: BoxDecoration(color: const Color(0xFFE91E63).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
            child: const Icon(Icons.calendar_month, color: Color(0xFFE91E63), size: 22),
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('New Appointment', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                Text('Book services for a customer', style: TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close, color: AppTheme.textMuted),
            onPressed: () => Navigator.of(context).pop(),
          ),
        ],
      ),
    );
  }

  Widget _buildForm() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ========== CUSTOMER SECTION ==========
            _buildLabel('Customer *'),
            const SizedBox(height: 6),

            // Customer search or new customer fields
            if (!_isNewCustomer) ...[
              if (_selectedContactId == null) ...[
                // Search existing customers
                TextField(
                  controller: _customerSearchController,
                  style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                  decoration: InputDecoration(
                    hintText: 'Search customer by name, phone...',
                    prefixIcon: const Icon(Icons.search, size: 18, color: AppTheme.textMuted),
                    suffixIcon: _isSearchingCustomers
                        ? const Padding(padding: EdgeInsets.all(12), child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)))
                        : _customerSearchController.text.isNotEmpty
                            ? IconButton(icon: const Icon(Icons.clear, size: 16), onPressed: () { _customerSearchController.clear(); setState(() => _searchResults = []); })
                            : null,
                    filled: true, fillColor: AppTheme.background,
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10), isDense: true,
                  ),
                  onChanged: _searchCustomers,
                ),

                // Search results dropdown
                if (_searchResults.isNotEmpty)
                  Container(
                    margin: const EdgeInsets.only(top: 4),
                    constraints: const BoxConstraints(maxHeight: 160),
                    decoration: BoxDecoration(
                      color: AppTheme.background, borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: AppTheme.border),
                    ),
                    child: ListView.builder(
                      shrinkWrap: true,
                      itemCount: _searchResults.length,
                      itemBuilder: (context, index) {
                        final c = _searchResults[index];
                        return ListTile(
                          dense: true, visualDensity: VisualDensity.compact,
                          leading: CircleAvatar(
                            radius: 16, backgroundColor: AppTheme.primary.withValues(alpha: 0.1),
                            child: Text(c.name.isNotEmpty ? c.name[0].toUpperCase() : '?', style: TextStyle(color: AppTheme.primary, fontSize: 12, fontWeight: FontWeight.bold)),
                          ),
                          title: Text(c.name, style: const TextStyle(fontSize: 13, color: AppTheme.textPrimary)),
                          subtitle: c.mobile != null ? Text(c.mobile!, style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)) : null,
                          onTap: () => _selectExistingCustomer(c),
                        );
                      },
                    ),
                  ),

                // Helpful hint if search returned nothing
                if (!_isSearchingCustomers && _customerSearchController.text.trim().length >= 2 && _searchResults.isEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 6, left: 4),
                    child: Row(
                      children: [
                        const Icon(Icons.info_outline, size: 14, color: AppTheme.textMuted),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            'No customer found for "${_customerSearchController.text}".',
                            style: const TextStyle(fontSize: 11, color: AppTheme.textMuted),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        InkWell(
                          onTap: () {
                            final query = _customerSearchController.text.trim();
                            _switchToNewCustomer();
                            if (RegExp(r'^[0-9+\s\-]+$').hasMatch(query)) {
                              _phoneController.text = query;
                            } else {
                              _nameController.text = query;
                            }
                          },
                          child: const Text(
                            ' Add as new?',
                            style: TextStyle(fontSize: 11, color: Color(0xFFE91E63), fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ),
                  ),
              ] else ...[
                // Selected customer display
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.success.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppTheme.success.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.check_circle, color: AppTheme.success, size: 20),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(_nameController.text, style: const TextStyle(fontWeight: FontWeight.w600, color: AppTheme.textPrimary, fontSize: 13)),
                            if (_phoneController.text.isNotEmpty)
                              Text(_phoneController.text, style: const TextStyle(color: AppTheme.textMuted, fontSize: 11)),
                          ],
                        ),
                      ),
                      TextButton(
                        onPressed: () {
                          setState(() {
                            _selectedContactId = null;
                            _nameController.clear();
                            _phoneController.clear();
                            _customerSearchController.clear();
                            _searchResults = [];
                          });
                        },
                        child: const Text('Change', style: TextStyle(fontSize: 11)),
                      ),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 6),
              TextButton.icon(
                onPressed: _switchToNewCustomer,
                icon: const Icon(Icons.person_add, size: 16),
                label: const Text('Add New Customer'),
              ),
            ] else ...[
              // New customer fields — matching the full Add Customer form
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _nameController,
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      decoration: _inputDecoration('First Name *', Icons.person_outline),
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      decoration: _inputDecoration('Last Name', Icons.person_outline),
                      onSaved: (v) {
                        // Append last name to full name on save
                        if (v != null && v.trim().isNotEmpty) {
                          _nameController.text = '${_nameController.text} ${v.trim()}';
                        }
                      },
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _phoneController,
                style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                keyboardType: TextInputType.phone,
                decoration: _inputDecoration('Mobile Number', Icons.phone_outlined),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      keyboardType: TextInputType.emailAddress,
                      decoration: _inputDecoration('Email Address', Icons.email_outlined),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      decoration: _inputDecoration('Tax Number', Icons.receipt_outlined),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      decoration: _inputDecoration('Address', Icons.location_on_outlined),
                    ),
                  ),
                  const SizedBox(width: 8),
                  TextButton.icon(
                    onPressed: () { setState(() { _isNewCustomer = false; _selectedContactId = null; }); },
                    icon: const Icon(Icons.search, size: 16),
                    label: const Text('Search Existing'),
                  ),
                ],
              ),
            ],
            const SizedBox(height: 20),

            // ========== SERVICES SECTION ==========
            _buildLabel('Services *'),
            const SizedBox(height: 6),

            // Add service dropdown + Custom Service button
            Row(
              children: [
                Expanded(
                  child: Container(
                    decoration: BoxDecoration(color: AppTheme.background, borderRadius: BorderRadius.circular(8), border: Border.all(color: AppTheme.border)),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String>(
                        isExpanded: true, value: _pendingServiceName,
                        dropdownColor: AppTheme.surface, style: const TextStyle(color: AppTheme.textPrimary),
                        hint: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          child: Text(_selectedServices.isEmpty ? 'Select a service to add' : 'Add another service', style: TextStyle(color: AppTheme.textMuted, fontSize: 13)),
                        ),
                        items: _serviceList.map<DropdownMenuItem<String>>((s) {
                          final name = (s['name'] ?? s['service_name'] ?? '').toString();
                          final price = (s['price'] ?? s['sell_price_inc_tax'] ?? 0).toDouble();
                          final duration = s['duration'] ?? s['service_duration_minutes'] ?? 30;
                          return DropdownMenuItem<String>(
                            value: name,
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(name, style: const TextStyle(fontSize: 13)),
                                        Text(
                                          price <= 0 ? '$duration min • Flexible Price' : '$duration min',
                                          style: TextStyle(fontSize: 11, color: price <= 0 ? const Color(0xFFE91E63) : AppTheme.textMuted),
                                        ),
                                      ],
                                    ),
                                  ),
                                  Text(
                                    price > 0 ? 'KD ${price.toStringAsFixed(3)}' : 'Set Price',
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.bold,
                                      color: price > 0 ? AppTheme.primary : const Color(0xFFE91E63),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        }).toList(),
                        onChanged: (val) {
                          if (val != null) {
                            final idx = _serviceList.indexWhere(
                              (s) => (s['name'] ?? s['service_name'] ?? '').toString() == val,
                            );
                            if (idx >= 0) _addService(_serviceList[idx]);
                          }
                          setState(() => _pendingServiceName = null);
                        },
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                ElevatedButton.icon(
                  icon: const Icon(Icons.add, size: 14, color: Colors.white),
                  label: const Text('Custom', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFE91E63),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  onPressed: _showAddCustomServiceDialog,
                ),
              ],
            ),

            // Selected services list
            if (_selectedServices.isNotEmpty) ...[
              const SizedBox(height: 10),
              ...List.generate(_selectedServices.length, (i) {
                final s = _selectedServices[i];
                return Container(
                  margin: const EdgeInsets.only(bottom: 6),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: AppTheme.background, borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppTheme.border),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 28, height: 28,
                        decoration: BoxDecoration(color: const Color(0xFFE91E63).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                        child: Center(child: Text('${i + 1}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFFE91E63)))),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(s.name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppTheme.textPrimary)),
                            Text('${s.durationMinutes} min', style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                          ],
                        ),
                      ),
                      InkWell(
                        onTap: () => _editServicePrice(i),
                        borderRadius: BorderRadius.circular(6),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: AppTheme.primary.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(4),
                            border: Border.all(color: AppTheme.primary.withValues(alpha: 0.2)),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text('KD ${s.price.toStringAsFixed(3)}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.primary)),
                              const SizedBox(width: 4),
                              const Icon(Icons.edit, size: 12, color: AppTheme.primary),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      GestureDetector(
                        onTap: () => _removeService(i),
                        child: Container(
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(color: AppTheme.error.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)),
                          child: const Icon(Icons.close, size: 14, color: AppTheme.error),
                        ),
                      ),
                    ],
                  ),
                );
              }),

              // Running total
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(8)),
                child: Row(
                  children: [
                    const Icon(Icons.receipt_long, size: 16, color: AppTheme.primary),
                    const SizedBox(width: 8),
                    Text('${_selectedServices.length} service(s)', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                    const Spacer(),
                    Text('$_totalDuration min total', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                    const SizedBox(width: 12),
                    Text('KD ${_totalPrice.toStringAsFixed(3)}', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppTheme.primary)),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 16),

            // ========== DATE & TIME ==========
            Row(
              children: [
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  _buildLabel('Date *'), const SizedBox(height: 6),
                  InkWell(onTap: _selectDate, child: _buildDateTimeChip(Icons.calendar_today, '${_selectedDate.day}/${_selectedDate.month}/${_selectedDate.year}')),
                ])),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  _buildLabel('Time *'), const SizedBox(height: 6),
                  InkWell(onTap: _selectTime, child: _buildDateTimeChip(Icons.access_time, _selectedTime.format(context))),
                ])),
              ],
            ),
            const SizedBox(height: 16),

            // ========== STAFF ==========
            if (widget.staff.isNotEmpty) ...[
              _buildLabel('Assign Staff'), const SizedBox(height: 6),
              Wrap(spacing: 8, runSpacing: 8, children: [
                _buildStaffChoice(0, 'Any Available', AppTheme.textMuted),
                ...widget.staff.map((s) {
                  final c = s['color'];
                  final color = c is Color ? c : Color(int.parse((c?.toString().replaceFirst('#', '0xFF') ?? '0xFF00BCD4')));
                  return _buildStaffChoice(s['id'] ?? 0, s['name'] ?? '', color);
                }),
              ]),
              const SizedBox(height: 16),
            ],

            // ========== NOTES ==========
            _buildLabel('Notes'), const SizedBox(height: 6),
            TextFormField(
              controller: _notesController,
              style: const TextStyle(color: AppTheme.textPrimary),
              maxLines: 2,
              decoration: _inputDecoration('Any special requests...', Icons.note_outlined),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFooter() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppTheme.border))),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_selectedServices.isNotEmpty)
                  Text(
                    '${_selectedServices.length} service(s) • $_totalDuration min',
                    style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                  ),
                if (_totalPrice > 0)
                  Text('KD ${_totalPrice.toStringAsFixed(3)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.primary)),
              ],
            ),
          ),
          TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel', style: TextStyle(color: AppTheme.textMuted))),
          const SizedBox(width: 8),
          ElevatedButton(
            onPressed: _isSaving ? null : _saveBooking,
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFE91E63), foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12)),
            child: _isSaving
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Text('Book Appointment', style: TextStyle(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  // ========== Helpers ==========

  Widget _buildLabel(String text) {
    return Text(text, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textSecondary));
  }

  Widget _buildDateTimeChip(IconData icon, String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      decoration: BoxDecoration(color: AppTheme.background, borderRadius: BorderRadius.circular(8), border: Border.all(color: AppTheme.border)),
      child: Row(children: [Icon(icon, size: 16, color: AppTheme.textMuted), const SizedBox(width: 8), Text(text, style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13))]),
    );
  }

  Widget _buildStaffChoice(int id, String name, Color color) {
    final isSelected = (_selectedStaffId == null && id == 0) || _selectedStaffId == id;
    return ChoiceChip(
      label: Text(name, style: TextStyle(fontSize: 12, color: isSelected ? Colors.white : AppTheme.textPrimary)),
      selected: isSelected, selectedColor: color, backgroundColor: AppTheme.background,
      onSelected: (_) { setState(() { _selectedStaffId = id == 0 ? null : id; }); },
    );
  }

  InputDecoration _inputDecoration(String hint, IconData icon) {
    return InputDecoration(
      hintText: hint, hintStyle: TextStyle(color: AppTheme.textMuted, fontSize: 13),
      prefixIcon: Icon(icon, size: 18, color: AppTheme.textMuted),
      filled: true, fillColor: AppTheme.background,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppTheme.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppTheme.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFFE91E63), width: 2)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    );
  }
}
