import 'package:flutter/material.dart';
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
  List<Contact> _searchResults = [];
  bool _isSearchingCustomers = false;

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
    if (query.length < 2) {
      setState(() => _searchResults = []);
      return;
    }
    setState(() => _isSearchingCustomers = true);
    try {
      final results = await widget.posService.getContacts(
        search: query,
        type: 'customer',
        perPage: 20,
      );
      if (mounted) {
        setState(() {
          _searchResults = results;
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
      _customerSearchController.clear();
    });
  }

  void _switchToNewCustomer() {
    setState(() {
      _selectedContactId = null;
      _isNewCustomer = true;
      _nameController.clear();
      _phoneController.clear();
      _searchResults = [];
    });
  }

  // ========== Service Management ==========

  void _addService(Map<String, dynamic> service) {
    final name = (service['name'] ?? service['service_name'] ?? '').toString();
    final price = (service['price'] ?? service['sell_price_inc_tax'] ?? 0).toDouble();
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

    setState(() {
      _selectedServices.add(BookingService(
        name: name,
        price: price,
        durationMinutes: duration is int ? duration : (duration as double).toInt(),
      ));
    });
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
    if (!_formKey.currentState!.validate()) return;
    if (_selectedServices.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please add at least one service'), backgroundColor: AppTheme.error),
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

      final customerName = _nameController.text.trim();

      // If new customer, create them first
      int? contactId = _selectedContactId;
      if (_isNewCustomer && customerName.isNotEmpty) {
        try {
          final newContact = await widget.posService.createContact(
            name: customerName,
            type: 'customer',
            mobile: _phoneController.text.trim().isNotEmpty ? _phoneController.text.trim() : null,
          );
          contactId = newContact.id;
        } catch (_) {
          // Continue without contact if creation fails
        }
      }

      // Create appointment with multiple services
      final serviceNames = _selectedServices.map((s) => s.name).join(', ');
      final servicePrices = _selectedServices.map((s) => s.price).toList();

      await widget.posService.createSaloonAppointment({
        'customer_name': customerName,
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
            content: Text('✅ Booked ${_selectedServices.length} service(s) for $customerName'),
            backgroundColor: AppTheme.success,
          ),
        );
        widget.onBookingCreated();
        Navigator.of(context).pop();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('❌ Failed: ${e.toString()}'), backgroundColor: AppTheme.error),
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

              // Selected customer display or switch to new
              if (_selectedContactId != null)
                Container(
                  margin: const EdgeInsets.only(top: 8),
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppTheme.success.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppTheme.success.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.check_circle, color: AppTheme.success, size: 18),
                      const SizedBox(width: 8),
                      Expanded(child: Text(_nameController.text, style: const TextStyle(fontWeight: FontWeight.w600, color: AppTheme.textPrimary, fontSize: 13))),
                      TextButton(
                        onPressed: () { setState(() { _selectedContactId = null; _nameController.clear(); _phoneController.clear(); }); },
                        child: const Text('Change', style: TextStyle(fontSize: 11)),
                      ),
                    ],
                  ),
                ),

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

            // Add service dropdown
            Container(
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
                                  Text('$duration min', style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                                ],
                              ),
                            ),
                            Text('KD ${price.toStringAsFixed(3)}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppTheme.primary)),
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
                      Text('KD ${s.price.toStringAsFixed(3)}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.primary)),
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
