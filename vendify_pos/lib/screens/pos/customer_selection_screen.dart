import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/contact.dart';
import 'package:vendify_pos/services/pos_service.dart';

class CustomerSelectionResult {
  final int? contactId;
  final String customerName;

  CustomerSelectionResult({this.contactId, required this.customerName});
}

class CustomerSelectionScreen extends ConsumerStatefulWidget {
  const CustomerSelectionScreen({super.key});

  @override
  ConsumerState<CustomerSelectionScreen> createState() => _CustomerSelectionScreenState();
}

class _CustomerSelectionScreenState extends ConsumerState<CustomerSelectionScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  final TextEditingController _searchController = TextEditingController();
  List<Contact> _customers = [];
  List<Contact> _filteredCustomers = [];
  bool _isLoading = true;
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _loadCustomers();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadCustomers() async {
    setState(() => _isLoading = true);
    try {
      final customers = await _posService.getContacts(type: 'customer', perPage: 200);
      setState(() {
        _customers = customers;
        _filteredCustomers = customers;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  void _filterCustomers(String query) {
    setState(() {
      _searchQuery = query;
      if (query.isEmpty) {
        _filteredCustomers = _customers;
      } else {
        _filteredCustomers = _customers.where((c) =>
          c.name.toLowerCase().contains(query.toLowerCase()) ||
          (c.mobile?.toLowerCase().contains(query.toLowerCase()) ?? false) ||
          (c.email?.toLowerCase().contains(query.toLowerCase()) ?? false)
        ).toList();
      }
    });
  }

  void _selectWalkIn() {
    Navigator.pop(context, CustomerSelectionResult(
      contactId: null,
      customerName: 'Walk-in Customer',
    ));
  }

  void _selectCustomer(Contact customer) {
    Navigator.pop(context, CustomerSelectionResult(
      contactId: customer.id,
      customerName: customer.name,
    ));
  }

  void _openAddCustomer() async {
    final result = await Navigator.push<CustomerSelectionResult>(
      context,
      MaterialPageRoute(builder: (_) => const AddCustomerScreen()),
    );
    if (result != null && mounted) {
      Navigator.pop(context, result);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        backgroundColor: AppTheme.surface,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppTheme.textPrimary),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Select Customer',
          style: TextStyle(color: AppTheme.textPrimary, fontWeight: FontWeight.bold),
        ),
        actions: [
          TextButton.icon(
            onPressed: _openAddCustomer,
            icon: const Icon(Icons.person_add, color: AppTheme.primary, size: 18),
            label: const Text(
              'Add New',
              style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          // Walk-in Customer button
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            child: OutlinedButton.icon(
              onPressed: _selectWalkIn,
              icon: const Icon(Icons.person_outline, size: 20),
              label: const Text(
                'Walk-in Customer',
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
              ),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppTheme.textPrimary,
                side: const BorderSide(color: AppTheme.primary, width: 2),
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),

          const Divider(height: 1),

          // Search bar
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              controller: _searchController,
              style: const TextStyle(color: AppTheme.textPrimary),
              onChanged: _filterCustomers,
              decoration: InputDecoration(
                hintText: 'Search by name, phone, or email...',
                prefixIcon: const Icon(Icons.search, color: AppTheme.textMuted, size: 20),
                suffixIcon: _searchQuery.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, color: AppTheme.textMuted, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          _filterCustomers('');
                        },
                      )
                    : null,
                filled: true,
                fillColor: AppTheme.surface,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: AppTheme.border),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: AppTheme.border),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: AppTheme.primary),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              ),
            ),
          ),

          // Customer count
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                Text(
                  '${_filteredCustomers.length} customers',
                  style: const TextStyle(color: AppTheme.textMuted, fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),

          // Customer list
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
                : _filteredCustomers.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.people_outline, size: 48, color: AppTheme.textMuted.withValues(alpha: 0.4)),
                            const SizedBox(height: 12),
                            Text(
                              _searchQuery.isNotEmpty ? 'No customers found' : 'No customers yet',
                              style: const TextStyle(color: AppTheme.textMuted, fontSize: 14),
                            ),
                            if (_searchQuery.isEmpty) ...[
                              const SizedBox(height: 8),
                              TextButton.icon(
                                onPressed: _openAddCustomer,
                                icon: const Icon(Icons.person_add, size: 16),
                                label: const Text('Add First Customer'),
                              ),
                            ],
                          ],
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        itemCount: _filteredCustomers.length,
                        itemBuilder: (context, index) {
                          final customer = _filteredCustomers[index];
                          return _buildCustomerTile(customer);
                        },
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildCustomerTile(Contact customer) {
    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      child: ListTile(
        onTap: () => _selectCustomer(customer),
        leading: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: AppTheme.primary.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Center(
            child: Text(
              customer.name.isNotEmpty ? customer.name[0].toUpperCase() : '?',
              style: const TextStyle(
                color: AppTheme.primary,
                fontWeight: FontWeight.bold,
                fontSize: 16,
              ),
            ),
          ),
        ),
        title: Text(
          customer.name,
          style: const TextStyle(
            color: AppTheme.textPrimary,
            fontWeight: FontWeight.w600,
            fontSize: 14,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (customer.mobile != null && customer.mobile!.isNotEmpty)
              Text(
                customer.mobile!,
                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
              ),
            if (customer.email != null && customer.email!.isNotEmpty)
              Text(
                customer.email!,
                style: const TextStyle(color: AppTheme.textMuted, fontSize: 11),
              ),
          ],
        ),
        trailing: customer.balance > 0
            ? Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: AppTheme.error.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  'KD ${customer.balance.toStringAsFixed(3)}',
                  style: const TextStyle(
                    color: AppTheme.error,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              )
            : null,
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
          side: const BorderSide(color: AppTheme.border),
        ),
      ),
    );
  }
}

// ============================================================
// ADD NEW CUSTOMER SCREEN
// ============================================================

class AddCustomerScreen extends ConsumerStatefulWidget {
  const AddCustomerScreen({super.key});

  @override
  ConsumerState<AddCustomerScreen> createState() => _AddCustomerScreenState();
}

class _AddCustomerScreenState extends ConsumerState<AddCustomerScreen> {
  final _formKey = GlobalKey<FormState>();
  late final PosService _posService = ref.read(posServiceProvider);
  bool _isSaving = false;

  String _prefix = '';
  String _firstName = '';
  String _lastName = '';
  String _mobile = '';
  String _email = '';
  String _taxNumber = '';
  String _address = '';
  String _city = '';
  String _zipCode = '';

  Future<void> _saveCustomer() async {
    if (!_formKey.currentState!.validate()) return;
    _formKey.currentState!.save();

    setState(() => _isSaving = true);

    try {
      final fullName = [_prefix, _firstName, _lastName].where((s) => s.isNotEmpty).join(' ');
      final fullAddress = [_address, _city, _zipCode].where((s) => s.isNotEmpty).join(', ');
      final customer = await _posService.createContact(
        name: fullName.isNotEmpty ? fullName : 'New Customer',
        type: 'customer',
        mobile: _mobile.isNotEmpty ? _mobile : null,
        email: _email.isNotEmpty ? _email : null,
        taxNumber: _taxNumber.isNotEmpty ? _taxNumber : null,
        billingAddress: fullAddress.isNotEmpty ? fullAddress : null,
        shippingAddress: fullAddress.isNotEmpty ? fullAddress : null,
      );

      if (mounted) {
        Navigator.pop(context, CustomerSelectionResult(
          contactId: customer.id,
          customerName: customer.name,
        ));
      }
    } catch (e) {
      setState(() => _isSaving = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to create customer: $e'),
            backgroundColor: AppTheme.error,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        backgroundColor: AppTheme.surface,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppTheme.textPrimary),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Add New Customer',
          style: TextStyle(color: AppTheme.textPrimary, fontWeight: FontWeight.bold),
        ),
        actions: [
          TextButton(
            onPressed: _isSaving ? null : _saveCustomer,
            child: _isSaving
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.primary))
                : const Text('Save', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold, fontSize: 16)),
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Name Section
              _buildSectionHeader('Customer Name'),
              const SizedBox(height: 12),
              Row(
                children: [
                  // Prefix
                  SizedBox(
                    width: 80,
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary),
                      decoration: _inputDecoration('Prefix'),
                      onSaved: (v) => _prefix = v ?? '',
                    ),
                  ),
                  const SizedBox(width: 10),
                  // First Name
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary),
                      decoration: _inputDecoration('First Name *'),
                      validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                      onSaved: (v) => _firstName = v ?? '',
                    ),
                  ),
                  const SizedBox(width: 10),
                  // Last Name
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary),
                      decoration: _inputDecoration('Last Name'),
                      onSaved: (v) => _lastName = v ?? '',
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 24),

              // Contact Section
              _buildSectionHeader('Contact Information'),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary),
                      keyboardType: TextInputType.phone,
                      decoration: _inputDecoration('Mobile Number'),
                      onSaved: (v) => _mobile = v ?? '',
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary),
                      keyboardType: TextInputType.emailAddress,
                      decoration: _inputDecoration('Email Address'),
                      validator: (v) {
                        if (v != null && v.isNotEmpty && !v.contains('@')) {
                          return 'Invalid email';
                        }
                        return null;
                      },
                      onSaved: (v) => _email = v ?? '',
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 24),

              // Tax & Address Section
              _buildSectionHeader('Additional Details'),
              const SizedBox(height: 12),
              TextFormField(
                style: const TextStyle(color: AppTheme.textPrimary),
                decoration: _inputDecoration('Tax Number'),
                onSaved: (v) => _taxNumber = v ?? '',
              ),
              const SizedBox(height: 12),
              TextFormField(
                style: const TextStyle(color: AppTheme.textPrimary),
                maxLines: 2,
                decoration: _inputDecoration('Address'),
                onSaved: (v) => _address = v ?? '',
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary),
                      decoration: _inputDecoration('City'),
                      onSaved: (v) => _city = v ?? '',
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary),
                      decoration: _inputDecoration('Zip Code'),
                      onSaved: (v) => _zipCode = v ?? '',
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 32),

              // Save Button
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton(
                  onPressed: _isSaving ? null : _saveCustomer,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.black,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isSaving
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                      : const Text('Save Customer', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                ),
              ),

              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.bold,
        color: AppTheme.primary,
      ),
    );
  }

  InputDecoration _inputDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: AppTheme.textMuted, fontSize: 13),
      filled: true,
      fillColor: AppTheme.surface,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: AppTheme.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: AppTheme.border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: AppTheme.primary),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    );
  }
}
