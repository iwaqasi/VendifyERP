import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/screens/pos/sell_return_screen.dart';
import 'package:vendify_pos/screens/pos/credit_payment_screen.dart';
import 'package:barcode_widget/barcode_widget.dart';
import 'package:shared_preferences/shared_preferences.dart';

class InvoiceDetailScreen extends ConsumerStatefulWidget {
  final int sellId;
  const InvoiceDetailScreen({super.key, required this.sellId});

  @override
  ConsumerState<InvoiceDetailScreen> createState() => _InvoiceDetailScreenState();
}

class _InvoiceDetailScreenState extends ConsumerState<InvoiceDetailScreen> {

  static double _vd(dynamic v) { if (v == null) return 0; if (v is num) return v.toDouble(); return (num.tryParse(v.toString()) ?? 0).toDouble(); }
  late final PosService _posService = ref.read(posServiceProvider);
  Map<String, dynamic>? _invoice;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadInvoice();
  }

  Future<void> _loadInvoice() async {
    setState(() => _isLoading = true);
    try {
      final data = await _posService.getSellDetail(widget.sellId);
      setState(() { _invoice = data; _isLoading = false; });
    } catch (e) {
      setState(() => _isLoading = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.error),
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
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppTheme.textPrimary),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          _invoice != null ? '${_invoice!['invoice_no'] ?? 'Invoice'}' : 'Invoice Detail',
          style: const TextStyle(color: AppTheme.textPrimary, fontSize: 16, fontWeight: FontWeight.bold),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : _invoice == null
              ? const Center(child: Text('Invoice not found'))
              : _buildDetail(),
    );
  }

  Widget _buildDetail() {
    final inv = _invoice!;
    final contact = inv['contact'];
    final customerName = contact != null ? (contact['name'] ?? 'Walk-in') : 'Walk-in';
    final customerMobile = contact != null ? (contact['mobile'] ?? '') : '';
    final customerEmail = contact != null ? (contact['email'] ?? '') : '';
    final items = inv['sell_lines'] as List? ?? [];
    final payments = inv['payment_lines'] as List? ?? [];
    final total = _vd(inv['final_total']);
    final amountPaid = _vd(inv['amount_paid']);
    final due = total - amountPaid;
    final discount = _vd(inv['discount']);
    final tax = _vd(inv['tax']);
    final subTotal = _vd(inv['sub_total']);
    final paymentStatus = inv['payment_status'] ?? 'pending';
    final isReturn = inv['is_return'] == 1;
    final location = inv['location'];
    final locationName = location != null ? (location['name'] ?? '') : '';

    String dateStr = '';
    try {
      dateStr = DateFormat('dd MMMM yyyy, hh:mm a').format(DateTime.parse(inv['transaction_date']));
    } catch (_) {
      dateStr = inv['transaction_date'] ?? '';
    }

    // Calculate returnable quantities
    bool hasReturnableItems = false;
    for (final item in items) {
      final qty = _vd(item['quantity']);
      final returned = _vd(item['quantity_returned']);
      if (returned < qty) { hasReturnableItems = true; break; }
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Status banner
          if (isReturn)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: AppTheme.warning.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: AppTheme.warning.withValues(alpha: 0.3)),
              ),
              child: const Text('RETURN INVOICE', style: TextStyle(color: AppTheme.warning, fontWeight: FontWeight.bold, fontSize: 13)),
            ),

          // Invoice header card
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
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)),
                        child: Text('${inv['invoice_no']}', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppTheme.primary)),
                      ),
                      const Spacer(),
                      _buildStatusBadge(paymentStatus),
                    ],
                  ),
                  const SizedBox(height: 10),
                  _buildInfoRow(Icons.access_time, dateStr),
                  if (locationName.isNotEmpty) _buildInfoRow(Icons.location_on, locationName),
                ],
              ),
            ),
          ),
          const SizedBox(height: 10),

          // Customer card
          Card(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Customer', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
                  const SizedBox(height: 8),
                  _buildInfoRow(Icons.person, customerName),
                  if (customerMobile.isNotEmpty) _buildInfoRow(Icons.phone, customerMobile),
                  if (customerEmail.isNotEmpty) _buildInfoRow(Icons.email, customerEmail),
                ],
              ),
            ),
          ),
          const SizedBox(height: 10),

          // Items card
          Card(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Items (${items.length})', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
                  const SizedBox(height: 8),
                  ...items.map((item) => _buildItemRow(item)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 10),

          // Totals card
          Card(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                children: [
                  _buildTotalRow('Sub Total', subTotal),
                  if (discount > 0) _buildTotalRow('Discount', -discount, isNegative: true),
                  if (tax > 0) _buildTotalRow('Tax', tax),
                  const Divider(),
                  _buildTotalRow('Grand Total', total, isBold: true),
                  _buildTotalRow('Amount Paid', amountPaid, valueColor: AppTheme.success),
                  if (due > 0)
                    _buildTotalRow('Balance Due', due, valueColor: AppTheme.error, isBold: true),
                ],
              ),
            ),
          ),
          const SizedBox(height: 10),

          // Payments card
          if (payments.isNotEmpty)
            Card(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Payments', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
                    const SizedBox(height: 8),
                    ...payments.map((p) => _buildPaymentRow(p)),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 16),

          const SizedBox(height: 16),

          // Reprint button
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => _showReprintDialog(inv),
              icon: const Icon(Icons.print, size: 18),
              label: const Text('Reprint Invoice'),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppTheme.primary,
                side: const BorderSide(color: AppTheme.primary),
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          ),
          const SizedBox(height: 12),

          // Action buttons
          Row(
            children: [
              if (hasReturnableItems && !isReturn)
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => SellReturnScreen(invoice: inv)),
                    ),
                    icon: const Icon(Icons.undo, size: 18),
                    label: const Text('Return/Exchange'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppTheme.warning,
                      side: BorderSide(color: AppTheme.warning),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
              if (due > 0) ...[
                if (hasReturnableItems && !isReturn) const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: () async {
                      await Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => CreditPaymentScreen(
                          contactId: inv['contact_id'],
                          customerName: customerName,
                          dueAmount: due,
                        )),
                      );
                      _loadInvoice();
                    },
                    icon: const Icon(Icons.payment, size: 18),
                    label: const Text('Collect Payment'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.success,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStatusBadge(String status) {
    Color color;
    switch (status) {
      case 'paid': color = AppTheme.success; break;
      case 'partial': color = AppTheme.warning; break;
      default: color = AppTheme.error;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)),
      child: Text(status.toUpperCase(), style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: color)),
    );
  }

  Widget _buildInfoRow(IconData icon, String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Icon(icon, size: 14, color: AppTheme.textMuted),
          const SizedBox(width: 6),
          Text(text, style: const TextStyle(fontSize: 13, color: AppTheme.textPrimary)),
        ],
      ),
    );
  }

  Widget _buildItemRow(Map<String, dynamic> item) {
    final product = item['product'];
    final productName = product != null ? (product['name'] ?? 'Unknown') : 'Unknown';
    final qty = _vd(item['quantity']);
    final unitPrice = _vd(item['unit_price']);
    final discount = _vd(item['discount']);
    final returned = _vd(item['quantity_returned']);
    final lineTotal = qty * unitPrice - discount;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(productName, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textPrimary)),
              ),
              Text('KD ${lineTotal.toStringAsFixed(3)}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textPrimary)),
            ],
          ),
          const SizedBox(height: 2),
          Row(
            children: [
              Text('Qty: ${qty.toInt()} × KD ${unitPrice.toStringAsFixed(3)}', style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
              if (discount > 0) Text(' - KD ${discount.toStringAsFixed(3)}', style: TextStyle(fontSize: 11, color: AppTheme.error)),
              if (returned > 0) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                  decoration: BoxDecoration(color: AppTheme.warning.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(3)),
                  child: Text('Returned: ${returned.toInt()}', style: TextStyle(fontSize: 10, color: AppTheme.warning, fontWeight: FontWeight.w600)),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildTotalRow(String label, double value, {bool isBold = false, bool isNegative = false, Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: isBold ? 14 : 13, fontWeight: isBold ? FontWeight.bold : FontWeight.normal, color: AppTheme.textSecondary)),
          Text(
            'KD ${value.toStringAsFixed(3)}',
            style: TextStyle(
              fontSize: isBold ? 16 : 13,
              fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
              color: valueColor ?? (isNegative ? AppTheme.error : AppTheme.textPrimary),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentRow(Map<String, dynamic> payment) {
    final method = payment['method'] ?? 'cash';
    final amount = _vd(payment['amount']);
    final isReturn = payment['is_return'] == 1;
    final paidOn = payment['paid_on'] ?? '';
    String dateStr = '';
    try { dateStr = DateFormat('dd/MM/yy HH:mm').format(DateTime.parse(paidOn)); } catch (_) {

      // Intentional fallback

    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(method == 'cash' ? Icons.money : Icons.credit_card, size: 16, color: AppTheme.textSecondary),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(method.toUpperCase(), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textPrimary)),
                if (dateStr.isNotEmpty) Text(dateStr, style: TextStyle(fontSize: 10, color: AppTheme.textMuted)),
              ],
            ),
          ),
          Text(
            '${isReturn ? '-' : ''}KD ${amount.abs().toStringAsFixed(3)}',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: isReturn ? AppTheme.error : AppTheme.success),
          ),
        ],
      ),
    );
  }

  void _showReprintDialog(Map<String, dynamic> inv) async {
    final prefs = await SharedPreferences.getInstance();
    final businessName = prefs.getString('business_name') ?? 'VendifyERP';
    final contact = inv['contact'];
    final customerName = contact != null ? (contact['name'] ?? 'Walk-in') : 'Walk-in';
    final items = inv['sell_lines'] as List? ?? [];
    final total = _vd(inv['final_total']);
    final amountPaid = _vd(inv['amount_paid']);
    final due = total - amountPaid;
    final invoiceNo = inv['invoice_no'] ?? 'N/A';

    String dateStr = '';
    try {
      dateStr = DateFormat('dd MMM yyyy, hh:mm a').format(DateTime.parse(inv['transaction_date']));
    } catch (_) {
      dateStr = inv['transaction_date'] ?? '';
    }

    if (!mounted) return;
    showDialog(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: AppTheme.surface,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        child: Container(
          width: 340,
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(context).size.height * 0.85,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Header
              Container(
                padding: const EdgeInsets.all(12),
                decoration: const BoxDecoration(
                  color: AppTheme.surfaceLight,
                  borderRadius: BorderRadius.only(topLeft: Radius.circular(12), topRight: Radius.circular(12)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.print, color: AppTheme.primary, size: 20),
                    const SizedBox(width: 8),
                    const Text('Reprint Invoice', style: TextStyle(fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                    const Spacer(),
                    IconButton(
                      icon: const Icon(Icons.close, size: 20),
                      onPressed: () => Navigator.pop(ctx),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                    ),
                  ],
                ),
              ),

              // Receipt content
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Stack(
                    children: [
                      // REPRINT watermark
                      Positioned.fill(
                        child: Center(
                          child: Transform.rotate(
                            angle: -0.5,
                            child: Text(
                              'REPRINT',
                              style: TextStyle(
                                fontSize: 48,
                                fontWeight: FontWeight.w900,
                                color: Colors.red.withValues(alpha: 0.12),
                                letterSpacing: 8,
                              ),
                            ),
                          ),
                        ),
                      ),

                      // Receipt content
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          // Business name
                          Text(businessName, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                          const SizedBox(height: 8),
                          const Divider(height: 1),
                          const SizedBox(height: 8),

                          // Invoice info
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('Invoice:', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                              Text(invoiceNo, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('Date:', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                              Text(dateStr, style: const TextStyle(fontSize: 12, color: AppTheme.textPrimary)),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('Customer:', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                              Text(customerName, style: const TextStyle(fontSize: 12, color: AppTheme.textPrimary)),
                            ],
                          ),
                          const SizedBox(height: 8),
                          const Divider(height: 1),
                          const SizedBox(height: 6),

                          // Items header
                          Row(
                            children: [
                              Expanded(flex: 3, child: Text('Item', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppTheme.textSecondary))),
                              Expanded(flex: 1, child: Text('Qty', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppTheme.textSecondary), textAlign: TextAlign.center)),
                              Expanded(flex: 2, child: Text('Price', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppTheme.textSecondary), textAlign: TextAlign.right)),
                            ],
                          ),
                          const SizedBox(height: 4),

                          // Items
                          ...items.map((item) {
                            final product = item['product'];
                            final name = product != null ? (product['name'] ?? '') : '';
                            final qty = _vd(item['quantity']);
                            final price = _vd(item['unit_price']);
                            final lineTotal = qty * price;
                            return Padding(
                              padding: const EdgeInsets.symmetric(vertical: 3),
                              child: Row(
                                children: [
                                  Expanded(flex: 3, child: Text(name, style: const TextStyle(fontSize: 11, color: AppTheme.textPrimary), overflow: TextOverflow.ellipsis)),
                                  Expanded(flex: 1, child: Text('${qty.toInt()}', style: const TextStyle(fontSize: 11, color: AppTheme.textPrimary), textAlign: TextAlign.center)),
                                  Expanded(flex: 2, child: Text('KD ${lineTotal.toStringAsFixed(3)}', style: const TextStyle(fontSize: 11, color: AppTheme.textPrimary), textAlign: TextAlign.right)),
                                ],
                              ),
                            );
                          }),
                          const SizedBox(height: 6),
                          const Divider(height: 1),
                          const SizedBox(height: 6),

                          // Totals
                          _receiptRow('Total', 'KD ${total.toStringAsFixed(3)}', isBold: true),
                          if (due <= 0)
                            _receiptRow('Paid', 'KD ${amountPaid.toStringAsFixed(3)}', valueColor: AppTheme.success),
                          if (due > 0) ...[
                            _receiptRow('Paid', 'KD ${amountPaid.toStringAsFixed(3)}', valueColor: AppTheme.success),
                            _receiptRow('Due', 'KD ${due.toStringAsFixed(3)}', valueColor: AppTheme.error, isBold: true),
                          ],
                          const SizedBox(height: 8),
                          const Divider(height: 1),
                          const SizedBox(height: 10),

                          // Barcode of invoice number
                          BarcodeWidget(
                            barcode: Barcode.code128(),
                            data: invoiceNo,
                            width: 200,
                            height: 50,
                            drawText: false,
                          ),
                          const SizedBox(height: 4),
                          Text(invoiceNo, style: TextStyle(fontSize: 10, color: AppTheme.textMuted, letterSpacing: 1)),
                          const SizedBox(height: 12),

                          // Footer
                          Text('Thank you for your visit!', style: TextStyle(fontSize: 10, color: AppTheme.textMuted)),
                          const SizedBox(height: 2),
                          Text('REPRINTED', style: TextStyle(fontSize: 9, color: Colors.red.shade400, fontWeight: FontWeight.bold)),
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              // Close button
              Padding(
                padding: const EdgeInsets.all(12),
                child: SizedBox(
                  width: double.infinity,
                  height: 42,
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(ctx),
                    child: const Text('Close'),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _receiptRow(String label, String value, {bool isBold = false, Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: 12, fontWeight: isBold ? FontWeight.bold : FontWeight.normal, color: AppTheme.textSecondary)),
          Text(value, style: TextStyle(fontSize: 12, fontWeight: isBold ? FontWeight.bold : FontWeight.w600, color: valueColor ?? AppTheme.textPrimary)),
        ],
      ),
    );
  }
}
