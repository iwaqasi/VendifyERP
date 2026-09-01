import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';

class CreditPaymentScreen extends ConsumerStatefulWidget {
  final int? contactId;
  final String customerName;
  final double dueAmount;
  
  const CreditPaymentScreen({
    super.key,
    this.contactId,
    required this.customerName,
    required this.dueAmount,
  });

  @override
  ConsumerState<CreditPaymentScreen> createState() => _CreditPaymentScreenState();
}

class _CreditPaymentScreenState extends ConsumerState<CreditPaymentScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  final _amountController = TextEditingController();
  final _referenceController = TextEditingController();
  String _paymentMethod = 'cash';
  bool _isProcessing = false;
  double? _remainingCredit;

  @override
  void initState() {
    super.initState();
    _amountController.text = widget.dueAmount.toStringAsFixed(3);
  }

  @override
  void dispose() {
    _amountController.dispose();
    _referenceController.dispose();
    super.dispose();
  }

  Future<void> _processPayment() async {
    final amount = double.tryParse(_amountController.text);
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a valid amount'), backgroundColor: AppTheme.error),
      );
      return;
    }

    if (widget.contactId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No customer selected'), backgroundColor: AppTheme.error),
      );
      return;
    }

    setState(() => _isProcessing = true);

    try {
      final result = await _posService.payCustomerCredit(
        contactId: widget.contactId!,
        amount: amount,
        method: _paymentMethod,
        reference: _referenceController.text.isNotEmpty ? _referenceController.text : null,
      );

      final data = result['data'];
      setState(() {
        _remainingCredit = (num.tryParse(data['remaining_credit'].toString()) ?? 0).toDouble();
      });

      if (mounted) {
        final paidAmount = (num.tryParse(data['paid_amount'].toString()) ?? 0).toDouble();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Payment recorded: KD ${paidAmount.toStringAsFixed(3)}'),
            backgroundColor: AppTheme.success,
          ),
        );
        
        if ((num.tryParse(data['remaining_credit'].toString()) ?? 0).toDouble() <= 0) {
          // Fully paid
          showDialog(
            context: context,
            builder: (ctx) => AlertDialog(
              backgroundColor: AppTheme.surface,
              title: Row(
                children: [
                  const Icon(Icons.check_circle, color: AppTheme.success, size: 28),
                  const SizedBox(width: 10),
                  const Text('Fully Paid!', style: TextStyle(color: AppTheme.textPrimary)),
                ],
              ),
              content: Text('Customer ${widget.customerName} has no remaining balance.', style: const TextStyle(color: AppTheme.textSecondary)),
              actions: [
                TextButton(
                  onPressed: () { Navigator.pop(ctx); Navigator.pop(context); },
                  child: const Text('Done'),
                ),
              ],
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.error),
        );
      }
    }

    setState(() => _isProcessing = false);
  }

  @override
  Widget build(BuildContext context) {
    final remaining = _remainingCredit ?? widget.dueAmount;

    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        backgroundColor: AppTheme.surface,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppTheme.textPrimary),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text('Collect Credit Payment', style: TextStyle(color: AppTheme.textPrimary, fontSize: 16, fontWeight: FontWeight.bold)),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Customer info
            Card(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 40, height: 40,
                          decoration: BoxDecoration(
                            color: remaining > 0 ? Colors.orange.withValues(alpha: 0.1) : AppTheme.success.withValues(alpha: 0.1),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(Icons.person, color: remaining > 0 ? Colors.orange : AppTheme.success, size: 20),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(widget.customerName, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                              const SizedBox(height: 2),
                              Text(
                                remaining > 0 ? 'Outstanding Balance' : 'Fully Paid',
                                style: TextStyle(fontSize: 12, color: remaining > 0 ? AppTheme.warning : AppTheme.success),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: remaining > 0 ? Colors.orange.shade50 : AppTheme.success.withValues(alpha: 0.05),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: remaining > 0 ? Colors.orange.shade200 : AppTheme.success.withValues(alpha: 0.2)),
                      ),
                      child: Column(
                        children: [
                          Text('KD ${remaining.toStringAsFixed(3)}', style: TextStyle(
                            fontSize: 24,
                            fontWeight: FontWeight.bold,
                            color: remaining > 0 ? Colors.orange.shade800 : AppTheme.success,
                          )),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Amount input
            const Text('Payment Amount', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
            const SizedBox(height: 6),
            TextField(
              controller: _amountController,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,3}'))],
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
              decoration: InputDecoration(
                prefixText: 'KD ',
                prefixStyle: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textSecondary),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                filled: true,
                fillColor: AppTheme.surface,
              ),
            ),
            // Quick amount buttons
            const SizedBox(height: 8),
            Row(
              children: [
                _buildQuickAmount('Full', remaining),
                const SizedBox(width: 8),
                _buildQuickAmount('Half', remaining / 2),
                const SizedBox(width: 8),
                _buildQuickAmount('5 KD', 5.0),
                const SizedBox(width: 8),
                _buildQuickAmount('10 KD', 10.0),
              ],
            ),
            const SizedBox(height: 20),

            // Payment method
            const Text('Payment Method', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
            const SizedBox(height: 8),
            Row(
              children: [
                _buildMethodChip('Cash', 'cash', Icons.money),
                const SizedBox(width: 8),
                _buildMethodChip('Card', 'card', Icons.credit_card),
                const SizedBox(width: 8),
                _buildMethodChip('Bank Transfer', 'bank_transfer', Icons.account_balance),
                const SizedBox(width: 8),
                _buildMethodChip('Other', 'other', Icons.more_horiz),
              ],
            ),
            const SizedBox(height: 16),

            // Reference number (for card/transfer)
            if (_paymentMethod != 'cash') ...[
              const Text('Reference Number', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
              const SizedBox(height: 6),
              TextField(
                controller: _referenceController,
                style: const TextStyle(fontSize: 13, color: AppTheme.textPrimary),
                decoration: InputDecoration(
                  hintText: 'Enter reference/authorization number',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                  filled: true,
                  fillColor: AppTheme.surface,
                  isDense: true,
                ),
              ),
              const SizedBox(height: 16),
            ],

            // Process button
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              height: 52,
              child: ElevatedButton(
                onPressed: _isProcessing ? null : _processPayment,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.success,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: _isProcessing
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : Text(
                        'Collect KD ${double.tryParse(_amountController.text)?.toStringAsFixed(3) ?? '0.000'}',
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickAmount(String label, double amount) {
    return Expanded(
      child: InkWell(
        onTap: () => setState(() => _amountController.text = amount.toStringAsFixed(3)),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: AppTheme.surfaceLight,
            borderRadius: BorderRadius.circular(6),
            border: Border.all(color: AppTheme.border),
          ),
          child: Center(
            child: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
          ),
        ),
      ),
    );
  }

  Widget _buildMethodChip(String label, String value, IconData icon) {
    final selected = _paymentMethod == value;
    return Expanded(
      child: InkWell(
        onTap: () => setState(() => _paymentMethod = value),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: selected ? AppTheme.primary.withValues(alpha: 0.1) : AppTheme.surface,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: selected ? AppTheme.primary : AppTheme.border),
          ),
          child: Column(
            children: [
              Icon(icon, size: 18, color: selected ? AppTheme.primary : AppTheme.textSecondary),
              const SizedBox(height: 4),
              Text(label, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: selected ? AppTheme.primary : AppTheme.textSecondary)),
            ],
          ),
        ),
      ),
    );
  }
}
