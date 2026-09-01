import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/contact.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'dart:async';

class CustomerSelectionResult {
  final int? contactId;
  final String customerName;

  CustomerSelectionResult({this.contactId, required this.customerName});
}

/// Shows customer selection as a centered dialog card
void showCustomerSelectionSheet(BuildContext context, {required Function(CustomerSelectionResult) onSelected}) {
  showDialog(
    context: context,
    builder: (_) => _CustomerSelectionDialog(onSelected: onSelected),
  );
}

/// Shows add customer as a centered dialog card
void showAddCustomerSheet(BuildContext context, {required Function(CustomerSelectionResult) onSaved}) {
  showDialog(
    context: context,
    builder: (_) => _AddCustomerDialog(onSaved: onSaved),
  );
}

// ============================================================
// CUSTOMER SELECTION DIALOG
// ============================================================

class _CustomerSelectionDialog extends ConsumerStatefulWidget {
  final Function(CustomerSelectionResult) onSelected;

  const _CustomerSelectionDialog({required this.onSelected});

  @override
  ConsumerState<_CustomerSelectionDialog> createState() => _CustomerSelectionDialogState();
}

class _CustomerSelectionDialogState extends ConsumerState<_CustomerSelectionDialog> {
  late final PosService _posService = ref.read(posServiceProvider);
  final TextEditingController _searchController = TextEditingController();
  List<Contact> _customers = [];
  List<Contact> _filteredCustomers = [];
  bool _isLoading = true;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _loadCustomers();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _loadCustomers() async {
    setState(() => _isLoading = true);
    try {
      final customers = await _posService.getContacts(type: 'customer', perPage: 500);
      if (mounted) {
        setState(() {
          _customers = customers;
          _filteredCustomers = List.from(customers);
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  void _onSearchChanged(String query) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () {
      if (!mounted) return;
      if (query.isEmpty) {
        setState(() => _filteredCustomers = List.from(_customers));
      } else {
        final q = query.toLowerCase();
        setState(() {
          _filteredCustomers = _customers.where((c) =>
            c.name.toLowerCase().contains(q) ||
            (c.mobile?.toLowerCase().contains(q) ?? false) ||
            (c.email?.toLowerCase().contains(q) ?? false)
          ).toList();
        });
      }
    });
  }

  void _selectWalkIn() {
    Navigator.pop(context);
    widget.onSelected(CustomerSelectionResult(
      contactId: null,
      customerName: 'Walk-in Customer',
    ));
  }

  void _selectCustomer(Contact customer) {
    Navigator.pop(context);
    widget.onSelected(CustomerSelectionResult(
      contactId: customer.id,
      customerName: customer.name,
    ));
  }

  void _clearCustomer() {
    Navigator.pop(context);
    widget.onSelected(CustomerSelectionResult(
      contactId: null,
      customerName: 'Walk-in Customer',
    ));
  }

  void _openAddCustomer() {
    Navigator.pop(context);
    showAddCustomerSheet(context, onSaved: widget.onSelected);
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        width: 480,
        height: 560,
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            // Header
            Row(
              children: [
                const Icon(Icons.people, color: AppTheme.primary, size: 20),
                const SizedBox(width: 10),
                const Expanded(
                  child: Text(
                    'Select Customer',
                    style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                  ),
                ),
                // Clear button
                TextButton.icon(
                  onPressed: _clearCustomer,
                  icon: const Icon(Icons.clear_all, size: 16, color: AppTheme.error),
                  label: const Text('Clear', style: TextStyle(color: AppTheme.error, fontSize: 12)),
                ),
                const SizedBox(width: 4),
                IconButton(
                  icon: const Icon(Icons.close, color: AppTheme.textMuted, size: 20),
                  onPressed: () => Navigator.pop(context),
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(),
                ),
              ],
            ),

            const SizedBox(height: 14),

            // Walk-in + Add New buttons
            Row(
              children: [
                Expanded(
                  child: _buildActionChip(
                    icon: Icons.person_outline,
                    label: 'Walk-in Customer',
                    onTap: _selectWalkIn,
                    isPrimary: true,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _buildActionChip(
                    icon: Icons.person_add,
                    label: 'Add New Customer',
                    onTap: _openAddCustomer,
                    isPrimary: false,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 14),

            // Search bar
            TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              autofocus: true,
              style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
              decoration: InputDecoration(
                hintText: 'Search by name, phone, or email...',
                prefixIcon: const Icon(Icons.search, color: AppTheme.textMuted, size: 18),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, color: AppTheme.textMuted, size: 16),
                        onPressed: () {
                          _searchController.clear();
                          _onSearchChanged('');
                        },
                      )
                    : null,
                filled: true,
                fillColor: AppTheme.background,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                isDense: true,
              ),
            ),

            const SizedBox(height: 10),

            // Customer count
            Row(
              children: [
                Text(
                  '${_filteredCustomers.length} customers',
                  style: const TextStyle(color: AppTheme.textMuted, fontSize: 11),
                ),
              ],
            ),

            const SizedBox(height: 6),

            // Customer list
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator(color: AppTheme.primary, strokeWidth: 2))
                  : _filteredCustomers.isEmpty
                      ? Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.people_outline, size: 40, color: AppTheme.textMuted.withValues(alpha: 0.4)),
                              const SizedBox(height: 8),
                              Text(
                                _searchController.text.isNotEmpty ? 'No customers match your search' : 'No customers yet',
                                style: const TextStyle(color: AppTheme.textMuted, fontSize: 13),
                              ),
                            ],
                          ),
                        )
                      : ListView.builder(
                          itemCount: _filteredCustomers.length,
                          itemBuilder: (context, index) {
                            final customer = _filteredCustomers[index];
                            return _buildCustomerTile(customer);
                          },
                        ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActionChip({required IconData icon, required String label, required VoidCallback onTap, required bool isPrimary}) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: isPrimary ? AppTheme.primary.withValues(alpha: 0.1) : AppTheme.background,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: isPrimary ? AppTheme.primary.withValues(alpha: 0.3) : AppTheme.border),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 16, color: isPrimary ? AppTheme.primary : AppTheme.textSecondary),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: isPrimary ? AppTheme.primary : AppTheme.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCustomerTile(Contact customer) {
    final hasBalance = customer.balance > 0;
    final hasDue = customer.sellDue > 0;
    final hasCreditLimit = customer.creditLimit > 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 4),
      child: ListTile(
        onTap: () => _selectCustomer(customer),
        dense: true,
        visualDensity: VisualDensity.compact,
        leading: Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            color: hasBalance || hasDue
                ? Colors.orange.withValues(alpha: 0.1)
                : AppTheme.primary.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Center(
            child: Text(
              customer.name.isNotEmpty ? customer.name[0].toUpperCase() : '?',
              style: TextStyle(
                color: hasBalance || hasDue ? Colors.orange.shade700 : AppTheme.primary,
                fontWeight: FontWeight.bold,
                fontSize: 14,
              ),
            ),
          ),
        ),
        title: Row(
          children: [
            Expanded(
              child: Text(
                customer.name,
                style: const TextStyle(color: AppTheme.textPrimary, fontWeight: FontWeight.w600, fontSize: 13),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            if (hasCreditLimit)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.indigo.shade50,
                  borderRadius: BorderRadius.circular(4),
                  border: Border.all(color: Colors.indigo.shade200),
                ),
                child: Text(
                  'Credit: KD ${customer.creditLimit.toStringAsFixed(3)}',
                  style: TextStyle(fontSize: 9, color: Colors.indigo.shade700, fontWeight: FontWeight.w600),
                ),
              ),
          ],
        ),
        subtitle: Row(
          children: [
            if (customer.mobile != null && customer.mobile!.isNotEmpty)
              Text(customer.mobile!, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 11)),
            if (hasDue) ...[
              const Text(' • ', style: TextStyle(color: AppTheme.textMuted, fontSize: 11)),
              Text(
                'Due: KD ${customer.sellDue.toStringAsFixed(3)}',
                style: TextStyle(color: Colors.orange.shade700, fontSize: 11, fontWeight: FontWeight.w600),
              ),
            ],
            if (customer.payTermNumber > 0) ...[
              const Text(' • ', style: TextStyle(color: AppTheme.textMuted, fontSize: 11)),
              Text(
                'Net ${customer.payTermNumber} ${customer.payTermType}',
                style: const TextStyle(color: AppTheme.textMuted, fontSize: 10),
              ),
            ],
          ],
        ),
        trailing: const Icon(Icons.chevron_right, color: AppTheme.textMuted, size: 18),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }
}

// ============================================================
// ADD CUSTOMER DIALOG
// ============================================================

class _AddCustomerDialog extends ConsumerStatefulWidget {
  final Function(CustomerSelectionResult) onSaved;

  const _AddCustomerDialog({required this.onSaved});

  @override
  ConsumerState<_AddCustomerDialog> createState() => _AddCustomerDialogState();
}

class _AddCustomerDialogState extends ConsumerState<_AddCustomerDialog> {
  final _formKey = GlobalKey<FormState>();
  late final PosService _posService = ref.read(posServiceProvider);
  bool _isSaving = false;
  String? _phoneError;

  String _firstName = '';
  String _lastName = '';
  String _mobile = '';
  String _email = '';
  String _taxNumber = '';
  String _address = '';
  double _creditLimit = 0;
  int _payTermNumber = 0;
  String _payTermType = 'days';

  Future<void> _saveCustomer() async {
    if (!_formKey.currentState!.validate()) return;
    _formKey.currentState!.save();

    setState(() {
      _isSaving = true;
      _phoneError = null;
    });

    try {
      final fullName = [_firstName, _lastName].where((s) => s.isNotEmpty).join(' ');
      final customer = await _posService.createContact(
        name: fullName,
        type: 'customer',
        mobile: _mobile.isNotEmpty ? _mobile : null,
        email: _email.isNotEmpty ? _email : null,
        taxNumber: _taxNumber.isNotEmpty ? _taxNumber : null,
        shippingAddress: _address.isNotEmpty ? _address : null,
        creditLimit: _creditLimit,
        payTermNumber: _payTermNumber > 0 ? _payTermNumber : null,
        payTermType: _payTermNumber > 0 ? _payTermType : null,
      );

      if (mounted) {
        Navigator.pop(context);
        widget.onSaved(CustomerSelectionResult(
          contactId: customer.id,
          customerName: customer.name,
        ));
      }
    } catch (e) {
      setState(() => _isSaving = false);
      // Check if it's a duplicate phone error
      final errorMsg = e.toString();
      if (errorMsg.contains('phone number already exists')) {
        setState(() => _phoneError = 'This phone number is already registered');
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: $e'), backgroundColor: AppTheme.error),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        width: 500,
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Header
              Row(
                children: [
                  const Icon(Icons.person_add, color: AppTheme.primary, size: 20),
                  const SizedBox(width: 10),
                  const Expanded(
                    child: Text('Add New Customer', style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: AppTheme.textMuted, size: 20),
                    onPressed: () => Navigator.pop(context),
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                  ),
                ],
              ),

              const SizedBox(height: 20),

              // Name row
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      decoration: _inputDecoration('First Name *'),
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                      onSaved: (v) => _firstName = v ?? '',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      decoration: _inputDecoration('Last Name'),
                      onSaved: (v) => _lastName = v ?? '',
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 14),

              // Phone with duplicate error
              TextFormField(
                style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                keyboardType: TextInputType.phone,
                decoration: _inputDecoration('Mobile Number').copyWith(
                  errorText: _phoneError,
                  errorStyle: const TextStyle(color: AppTheme.error, fontSize: 12),
                ),
                onChanged: (v) {
                  if (_phoneError != null) setState(() => _phoneError = null);
                },
                onSaved: (v) => _mobile = v ?? '',
              ),

              const SizedBox(height: 14),

              // Email
              TextFormField(
                style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                keyboardType: TextInputType.emailAddress,
                decoration: _inputDecoration('Email Address'),
                validator: (v) {
                  if (v != null && v.isNotEmpty && !v.contains('@')) return 'Invalid email';
                  return null;
                },
                onSaved: (v) => _email = v ?? '',
              ),

              const SizedBox(height: 14),

              // Tax + Address
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      decoration: _inputDecoration('Tax Number'),
                      onSaved: (v) => _taxNumber = v ?? '',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      decoration: _inputDecoration('Address'),
                      onSaved: (v) => _address = v ?? '',
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 14),

              // Credit Limit + Payment Terms
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: _inputDecoration('Credit Limit (KD)'),
                      onSaved: (v) => _creditLimit = double.tryParse(v ?? '') ?? 0,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Row(
                      children: [
                        Expanded(
                          flex: 2,
                          child: TextFormField(
                            style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                            keyboardType: TextInputType.number,
                            decoration: _inputDecoration('Pay Term #'),
                            onSaved: (v) => _payTermNumber = int.tryParse(v ?? '') ?? 0,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          flex: 2,
                          child: DropdownButtonFormField<String>(
                            initialValue: _payTermType,
                            style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                            decoration: _inputDecoration('Type'),
                            items: const [
                              DropdownMenuItem(value: 'days', child: Text('Days')),
                              DropdownMenuItem(value: 'months', child: Text('Months')),
                            ],
                            onChanged: (v) => setState(() => _payTermType = v ?? 'days'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 24),

              // Save button
              SizedBox(
                width: double.infinity,
                height: 44,
                child: ElevatedButton(
                  onPressed: _isSaving ? null : _saveCustomer,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.black,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  child: _isSaving
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                      : const Text('Save Customer', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: AppTheme.textMuted, fontSize: 12),
      filled: true,
      fillColor: AppTheme.background,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      isDense: true,
    );
  }
}
