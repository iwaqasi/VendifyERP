import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/cart_item.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/screens/pos/pos_layout_router.dart';
import 'package:vendify_pos/screens/pos/widgets/receipt_preview.dart';

class PaymentScreen extends ConsumerStatefulWidget {
  final List<CartItem> cartItems;
  final double subtotal;
  final double tax;
  final double discount;
  final double grandTotal;
  final String customerName;
  final int? contactId;
  final int locationId;
  final double rpDiscount;
  final int rpPointsRedeemed;

  const PaymentScreen({
    super.key,
    required this.cartItems,
    required this.subtotal,
    required this.tax,
    required this.discount,
    required this.grandTotal,
    required this.customerName,
    this.contactId,
    required this.locationId,
    this.rpDiscount = 0.0,
    this.rpPointsRedeemed = 0,
  });

  @override
  ConsumerState<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends ConsumerState<PaymentScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  bool _isProcessing = false;
  bool _isLoadingSettings = true;

  // Split payment
  final List<_PaymentEntry> _payments = [];
  String _selectedMethod = 'cash';
  final _amountController = TextEditingController();
  final _referenceController = TextEditingController();
  final _authCodeController = TextEditingController();

  // POS Settings from backend
  Map<String, dynamic> _posSettings = {};
  bool _enableAuthCode = true;
  String _defaultPaymentMethod = 'cash';
  String _currencySymbol = 'KD';

  // Payment methods loaded from backend
  List<Map<String, dynamic>> _paymentMethods = [];

  // All available payment methods with icons
  static const Map<String, Map<String, dynamic>> _allPaymentMethods = {
    'cash': {'label': 'Cash', 'icon': Icons.money, 'requiresAuth': false},
    'card': {'label': 'Debit Card', 'icon': Icons.credit_card, 'requiresAuth': true},
    'visa': {'label': 'Visa', 'icon': Icons.credit_card, 'requiresAuth': true},
    'mastercard': {'label': 'Mastercard', 'icon': Icons.credit_card, 'requiresAuth': true},
    'amex': {'label': 'Amex', 'icon': Icons.credit_card, 'requiresAuth': true},
    'bank_transfer': {'label': 'Bank Transfer', 'icon': Icons.account_balance, 'requiresAuth': false},
    'cheque': {'label': 'Cheque', 'icon': Icons.receipt, 'requiresAuth': false},
    'other': {'label': 'Other', 'icon': Icons.payment, 'requiresAuth': false},
    'custom_pay_1': {'label': 'Custom Pay 1', 'icon': Icons.payment, 'requiresAuth': false},
    'custom_pay_2': {'label': 'Custom Pay 2', 'icon': Icons.payment, 'requiresAuth': false},
    'custom_pay_3': {'label': 'Custom Pay 3', 'icon': Icons.payment, 'requiresAuth': false},
  };

  double get _totalPaid => _payments.fold(0, (sum, p) => sum + p.amount);
  double get _remaining => widget.grandTotal - _totalPaid;
  double get _change => _totalPaid > widget.grandTotal ? _totalPaid - widget.grandTotal : 0;
  bool get _isFullyPaid => _totalPaid >= widget.grandTotal - 0.001;
  bool get _selectedMethodRequiresAuth {
    final method = _paymentMethods.firstWhere(
      (m) => m['method'] == _selectedMethod,
      orElse: () => {'requiresAuth': false},
    );
    return method['requiresAuth'] == true && _enableAuthCode;
  }

  @override
  void initState() {
    super.initState();
    _amountController.text = widget.grandTotal.toStringAsFixed(3);
    _loadPosSettings();
  }

  @override
  void dispose() {
    _amountController.dispose();
    _referenceController.dispose();
    _authCodeController.dispose();
    super.dispose();
  }

  /// Fetch POS settings from Laravel backend
  Future<void> _loadPosSettings() async {
    setState(() => _isLoadingSettings = true);
    try {
      final settings = await _posService.getPosSettings();

      if (mounted) {
        setState(() {
          _posSettings = settings['pos_settings'] ?? {};
          _currencySymbol = settings['currency']?['symbol'] ?? 'KD';
          _enableAuthCode = _posSettings['pos_enable_auth_code'] ?? true;
          _defaultPaymentMethod = _posSettings['pos_default_payment_method'] ?? 'cash';
          _selectedMethod = _defaultPaymentMethod;

          // Build payment methods from backend-enabled list
          final enabledMethods = settings['enabled_payment_methods'] as List? ?? [];
          final customLabels = settings['custom_payment_labels'] as Map<String, dynamic>? ?? {};
          if (enabledMethods.isNotEmpty) {
            _paymentMethods = enabledMethods.map<Map<String, dynamic>>((m) {
              final methodId = m['method'];
              // Use custom label if available, otherwise use default label
              String label;
              if (customLabels.containsKey(methodId) && (customLabels[methodId] as String).isNotEmpty) {
                label = customLabels[methodId];
              } else {
                label = _allPaymentMethods[methodId]?['label'] ?? methodId;
              }
              // Skip custom payment methods with no label
              if (methodId.startsWith('custom_pay_') && label.isEmpty) {
                return {'method': methodId, 'label': '', 'icon': Icons.payment, 'requiresAuth': false, 'skip': true};
              }
              final info = _allPaymentMethods[methodId] ?? {'requiresAuth': false};
              return {
                'method': methodId,
                'label': label,
                'icon': info['icon'] ?? Icons.payment,
                'requiresAuth': info['requiresAuth'] ?? false,
              };
            }).where((m) => m['skip'] != true).toList();
          } else {
            // Fallback: show cash and card
            _paymentMethods = [
              {'method': 'cash', 'label': 'Cash', 'icon': Icons.money, 'requiresAuth': false},
              {'method': 'card', 'label': 'Debit Card', 'icon': Icons.credit_card, 'requiresAuth': true},
            ];
          }

          // Ensure selected method exists in list
          if (!_paymentMethods.any((m) => m['method'] == _selectedMethod)) {
            _selectedMethod = _paymentMethods.isNotEmpty ? _paymentMethods.first['method'] : 'cash';
          }

          _isLoadingSettings = false;
        });

        // Save settings locally for offline use
        await _posService.savePosSettings(settings);
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          // Use defaults on error
          _paymentMethods = [
            {'method': 'cash', 'label': 'Cash', 'icon': Icons.money, 'requiresAuth': false},
            {'method': 'card', 'label': 'Debit Card', 'icon': Icons.credit_card, 'requiresAuth': true},
          ];
          _isLoadingSettings = false;
        });
      }
    }
  }

  void _addPayment() {
    final amount = double.tryParse(_amountController.text) ?? 0;
    if (amount <= 0) {
      _showError('Please enter a valid amount');
      return;
    }

    // Allow overpayment for cash (customer gives larger note, gets change back)
    // Don't allow overpayment for card/other methods
    if (_selectedMethod != 'cash' && _totalPaid + amount > widget.grandTotal + 0.001) {
      _showError('Amount exceeds remaining balance');
      return;
    }

    if (_selectedMethodRequiresAuth && _authCodeController.text.isEmpty) {
      _showError('Auth Code is required for card payments');
      return;
    }

    final method = _paymentMethods.firstWhere(
      (m) => m['method'] == _selectedMethod,
      orElse: () => {'label': _selectedMethod},
    );

    setState(() {
      _payments.add(_PaymentEntry(
        method: _selectedMethod,
        label: method['label'],
        amount: amount,
        reference: _referenceController.text.isNotEmpty ? _referenceController.text : null,
        authCode: _authCodeController.text.isNotEmpty ? _authCodeController.text : null,
      ));

      _amountController.text = _remaining > 0 ? _remaining.toStringAsFixed(3) : '0.000';
      _referenceController.clear();
      _authCodeController.clear();
    });
  }

  void _removePayment(int index) {
    setState(() {
      _payments.removeAt(index);
      _amountController.text = _remaining > 0 ? _remaining.toStringAsFixed(3) : '0.000';
    });
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: AppTheme.error),
    );
  }

  void _quickAmount(double amount) {
    setState(() {
      _amountController.text = amount.toStringAsFixed(3);
    });
  }

  Future<void> _processPayment() async {
    if (!_isFullyPaid) {
      _showError('Payment is not complete. Remaining: $_currencySymbol ${_remaining.toStringAsFixed(3)}');
      return;
    }

    setState(() => _isProcessing = true);

    try {
      final prefs = await SharedPreferences.getInstance();
      final businessId = prefs.getInt('business_id') ?? 1;
      final userId = prefs.getInt('user_id') ?? 1;

      final paymentLines = _payments.map((p) => {
        'method': p.method,
        'amount': p.amount,
        'reference': p.reference,
        'auth_code': p.authCode,
      }).toList();

      final result = await _posService.createSell(
        businessId: businessId,
        locationId: widget.locationId,
        contactId: widget.contactId,
        userId: userId,
        cartItems: widget.cartItems,
        subtotal: widget.subtotal,
        tax: widget.tax,
        discount: widget.discount,
        grandTotal: widget.grandTotal,
        payments: paymentLines,
        rpRedeemAmount: widget.rpDiscount,
        rpRedeemed: widget.rpPointsRedeemed,
      );

      if (result['success'] == true) {
        if (mounted) {
          // Show receipt preview
          if (mounted) {
            // Get business type for receipt
            final businessType = prefs.getString('business_type') ?? 'retail';
            
            showDialog(
              context: context,
              barrierDismissible: false,
              builder: (ctx) => ReceiptPreview(
                invoiceNumber: result['invoice_number']?.toString() ?? DateTime.now().millisecondsSinceEpoch.toString(),
                cartItems: widget.cartItems,
                subtotal: widget.subtotal,
                tax: widget.tax,
                discount: widget.discount,
                grandTotal: widget.grandTotal,
                payments: paymentLines,
                change: _change,
                customerName: widget.customerName,
                businessType: businessType,
              ),
            ).then((_) {
              // After receipt is closed, go back to POS
              if (mounted) {
                Navigator.pushAndRemoveUntil(
                  context,
                  MaterialPageRoute(builder: (_) => const PosLayoutRouter()),
                  (route) => false,
                );
              }
            });
          }
        }
      } else {
        _showError(result['message'] ?? 'Payment failed');
      }
    } catch (e) {
      String errorMsg = 'An unexpected error occurred';
      
      if (e is DioException && e.response != null) {
        final data = e.response!.data;
        if (data is Map) {
          if (data['errors'] != null) {
            final errors = data['errors'] as Map;
            final messages = <String>[];
            errors.forEach((field, fieldErrors) {
              if (fieldErrors is List) {
                messages.addAll(fieldErrors.map((e) => '$field: $e'));
              }
            });
            errorMsg = messages.isNotEmpty ? messages.join('\n') : (data['message'] ?? 'Validation error');
          } else {
            errorMsg = data['message'] ?? 'Error ${e.response!.statusCode}';
          }
        } else {
          errorMsg = 'Server error: ${e.response!.statusCode}';
        }
      } else if (e is DioException) {
        errorMsg = 'Network error: ${e.message}';
      } else {
        errorMsg = e.toString();
      }
      
      _showError(errorMsg);
    } finally {
      setState(() => _isProcessing = false);
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
          'Payment',
          style: TextStyle(
            color: AppTheme.textPrimary,
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: Center(
              child: Text(
                '$_currencySymbol ${widget.grandTotal.toStringAsFixed(3)}',
                style: const TextStyle(
                  color: AppTheme.primary,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
        ],
      ),
      body: _isLoadingSettings
          ? const Center(child: CircularProgressIndicator())
          : Row(
              children: [
                // Left: Order Summary
                Expanded(
                  flex: 3,
                  child: _buildOrderSummary(),
                ),

                // Right: Payment Methods
                Expanded(
                  flex: 5,
                  child: _buildPaymentSection(),
                ),
              ],
            ),
    );
  }

  Widget _buildOrderSummary() {
    return Container(
      color: AppTheme.surface,
      padding: const EdgeInsets.all(AppTheme.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Order Summary',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
              ),
              Text('${widget.cartItems.length} items', style: TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
            ],
          ),
          const SizedBox(height: 8),
          Text('Customer: ${widget.customerName}', style: TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
          const SizedBox(height: 12),
          const Divider(color: AppTheme.border),

          // Cart items
          Expanded(
            child: ListView.builder(
              itemCount: widget.cartItems.length,
              itemBuilder: (context, index) {
                final item = widget.cartItems[index];
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    children: [
                      Expanded(
                        flex: 4,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(item.productName, style: const TextStyle(color: AppTheme.textPrimary, fontSize: 12, fontWeight: FontWeight.w500), maxLines: 1, overflow: TextOverflow.ellipsis),
                            Text('$_currencySymbol ${item.unitPrice.toStringAsFixed(3)} x ${item.quantity.toInt()}', style: TextStyle(color: AppTheme.textSecondary, fontSize: 11)),
                          ],
                        ),
                      ),
                      Expanded(
                        flex: 2,
                        child: Text('$_currencySymbol ${item.lineTotal.toStringAsFixed(3)}', style: const TextStyle(color: AppTheme.textPrimary, fontSize: 12, fontWeight: FontWeight.w600), textAlign: TextAlign.right),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),

          const Divider(color: AppTheme.border),
          _buildSummaryRow('Subtotal', '$_currencySymbol ${widget.subtotal.toStringAsFixed(3)}'),
          if (widget.tax > 0) _buildSummaryRow('Tax', '$_currencySymbol ${widget.tax.toStringAsFixed(3)}'),
          if (widget.discount > 0) _buildSummaryRow('Discount', '- $_currencySymbol ${widget.discount.toStringAsFixed(3)}', isNegative: true),
          const SizedBox(height: 8),
          const Divider(color: AppTheme.border),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('TOTAL', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
              Text('$_currencySymbol ${widget.grandTotal.toStringAsFixed(3)}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.primary)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentSection() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(AppTheme.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Section Title
          const Text('Payment Method', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
          const SizedBox(height: 12),

          // Payment Method Buttons
          _buildPaymentMethodGrid(),

          const SizedBox(height: 16),

          // Amount Input Row
          Row(
            children: [
              Expanded(
                flex: 3,
                child: TextField(
                  controller: _amountController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,3}'))],
                  style: const TextStyle(fontSize: 18, color: AppTheme.textPrimary),
                  decoration: InputDecoration(
                    prefixText: '$_currencySymbol ',
                    prefixStyle: TextStyle(color: AppTheme.textSecondary, fontSize: 18),
                    hintText: '0.000',
                    hintStyle: TextStyle(color: AppTheme.textMuted),
                    filled: true,
                    fillColor: AppTheme.background,
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(AppTheme.radiusMd), borderSide: const BorderSide(color: AppTheme.border)),
                    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(AppTheme.radiusMd), borderSide: const BorderSide(color: AppTheme.border)),
                    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(AppTheme.radiusMd), borderSide: const BorderSide(color: AppTheme.primary, width: 2)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              SizedBox(
                height: 52,
                width: 60,
                child: ElevatedButton(
                  onPressed: _addPayment,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.black,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppTheme.radiusMd)),
                  ),
                  child: const Icon(Icons.add, size: 24),
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          // Quick Amount Buttons
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _buildQuickAmount('Exact', widget.grandTotal),
              _buildQuickAmount('$_currencySymbol 5', 5),
              _buildQuickAmount('$_currencySymbol 10', 10),
              _buildQuickAmount('$_currencySymbol 20', 20),
              _buildQuickAmount('$_currencySymbol 50', 50),
              _buildQuickAmount('$_currencySymbol 100', 100),
            ],
          ),

          const SizedBox(height: 16),

          // Card Details Section (Reference + Auth Code)
          if (_selectedMethod != 'cash') ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.surface,
                borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                border: Border.all(color: AppTheme.primary.withValues(alpha: 0.3)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.credit_card, color: AppTheme.primary, size: 16),
                      const SizedBox(width: 8),
                      Text(
                        '${_paymentMethods.firstWhere((m) => m['method'] == _selectedMethod, orElse: () => {'label': 'Card'})['label']} Details',
                        style: TextStyle(color: AppTheme.primary, fontSize: 13, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _referenceController,
                          style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                          decoration: InputDecoration(
                            labelText: 'Reference / Transaction No.',
                            labelStyle: TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                            hintText: 'Enter reference number',
                            hintStyle: TextStyle(color: AppTheme.textMuted),
                            filled: true,
                            fillColor: AppTheme.background,
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(AppTheme.radiusSm), borderSide: const BorderSide(color: AppTheme.border)),
                            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(AppTheme.radiusSm), borderSide: const BorderSide(color: AppTheme.border)),
                            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(AppTheme.radiusSm), borderSide: const BorderSide(color: AppTheme.primary)),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                          ),
                        ),
                      ),
                      if (_selectedMethodRequiresAuth) ...[
                        const SizedBox(width: 12),
                        Expanded(
                          child: TextField(
                            controller: _authCodeController,
                            style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                            decoration: InputDecoration(
                              labelText: 'Auth / Approval Code *',
                              labelStyle: TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                              hintText: 'Required',
                              hintStyle: TextStyle(color: AppTheme.textMuted),
                              filled: true,
                              fillColor: AppTheme.background,
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(AppTheme.radiusSm), borderSide: const BorderSide(color: AppTheme.border)),
                              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(AppTheme.radiusSm), borderSide: const BorderSide(color: AppTheme.border)),
                              focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(AppTheme.radiusSm), borderSide: const BorderSide(color: AppTheme.primary)),
                              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],

          // Applied Payments
          if (_payments.isNotEmpty) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.surface,
                borderRadius: BorderRadius.circular(AppTheme.radiusMd),
                border: Border.all(color: AppTheme.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Applied Payments', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                      Text('${_payments.length} payment(s)', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  ..._payments.asMap().entries.map((entry) {
                    final idx = entry.key;
                    final payment = entry.value;
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 3),
                      child: Row(
                        children: [
                          Icon(_getPaymentIcon(payment.method), size: 14, color: AppTheme.primary),
                          const SizedBox(width: 8),
                          Text(payment.label, style: const TextStyle(fontSize: 12, color: AppTheme.textPrimary)),
                          if (payment.authCode != null) ...[
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)),
                              child: Text('Auth: ${payment.authCode}', style: TextStyle(fontSize: 10, color: AppTheme.primary)),
                            ),
                          ],
                          const Spacer(),
                          Text('$_currencySymbol ${payment.amount.toStringAsFixed(3)}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textPrimary)),
                          const SizedBox(width: 8),
                          GestureDetector(
                            onTap: () => _removePayment(idx),
                            child: Icon(Icons.close, size: 14, color: AppTheme.error),
                          ),
                        ],
                      ),
                    );
                  }),
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],

          // Summary Box
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppTheme.surface,
              borderRadius: BorderRadius.circular(AppTheme.radiusMd),
              border: Border.all(color: AppTheme.border),
            ),
            child: Column(
              children: [
                _buildSummaryRow('Total', '$_currencySymbol ${widget.grandTotal.toStringAsFixed(3)}'),
                _buildSummaryRow('Paid', '$_currencySymbol ${_totalPaid.toStringAsFixed(3)}'),
                _buildSummaryRow('Remaining', '$_currencySymbol ${_remaining.toStringAsFixed(3)}', isHighlight: _remaining > 0),
                if (_change > 0) _buildSummaryRow('Change', '$_currencySymbol ${_change.toStringAsFixed(3)}', isPositive: true),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // Process Payment Button
          SizedBox(
            height: 56,
            child: ElevatedButton(
              onPressed: (_isProcessing || !_isFullyPaid) ? null : _processPayment,
              style: ElevatedButton.styleFrom(
                backgroundColor: _isFullyPaid ? AppTheme.success : AppTheme.primary,
                foregroundColor: Colors.white,
                disabledBackgroundColor: AppTheme.surface,
                disabledForegroundColor: AppTheme.textMuted,
              ),
              child: _isProcessing
                  ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(
                      _isFullyPaid
                          ? 'Complete Payment - $_currencySymbol ${widget.grandTotal.toStringAsFixed(3)}'
                          : 'Remaining: $_currencySymbol ${_remaining.toStringAsFixed(3)}',
                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentMethodGrid() {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 4,
        childAspectRatio: 2.2,
        crossAxisSpacing: 8,
        mainAxisSpacing: 8,
      ),
      itemCount: _paymentMethods.length,
      itemBuilder: (context, index) {
        final method = _paymentMethods[index];
        final isSelected = _selectedMethod == method['method'];
        return GestureDetector(
          onTap: () {
            setState(() {
              _selectedMethod = method['method'];
              _amountController.text = _remaining > 0 ? _remaining.toStringAsFixed(3) : '0.000';
              _referenceController.clear();
              _authCodeController.clear();
            });
          },
          child: Container(
            decoration: BoxDecoration(
              color: isSelected ? AppTheme.primary.withValues(alpha: 0.15) : AppTheme.background,
              borderRadius: BorderRadius.circular(AppTheme.radiusSm),
              border: Border.all(
                color: isSelected ? AppTheme.primary : AppTheme.border,
                width: isSelected ? 2 : 1,
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(method['icon'], size: 18, color: isSelected ? AppTheme.primary : AppTheme.textSecondary),
                const SizedBox(height: 4),
                Text(
                  method['label'],
                  style: TextStyle(
                    fontSize: 11,
                    color: isSelected ? AppTheme.primary : AppTheme.textPrimary,
                    fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildSummaryRow(String label, String value, {bool isNegative = false, bool isHighlight = false, bool isPositive = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
          Text(
            value,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: isPositive ? AppTheme.success : isNegative ? AppTheme.error : isHighlight ? AppTheme.warning : AppTheme.textPrimary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildQuickAmount(String label, double amount) {
    return ActionChip(
      label: Text(label, style: TextStyle(color: AppTheme.textPrimary, fontSize: 12)),
      onPressed: () => _quickAmount(amount),
      backgroundColor: AppTheme.background,
      side: const BorderSide(color: AppTheme.border),
    );
  }

  IconData _getPaymentIcon(String method) {
    final info = _allPaymentMethods[method];
    return info?['icon'] ?? Icons.payment;
  }
}

class _PaymentEntry {
  final String method;
  final String label;
  final double amount;
  final String? reference;
  final String? authCode;

  _PaymentEntry({
    required this.method,
    required this.label,
    required this.amount,
    this.reference,
    this.authCode,
  });
}
