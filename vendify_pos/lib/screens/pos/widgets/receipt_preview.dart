import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/cart_item.dart';
import 'package:barcode_widget/barcode_widget.dart';
import 'package:vendify_pos/services/print_service.dart';
import 'package:vendify_pos/screens/pos/widgets/print_settings_dialog.dart';
// ignore: avoid_web_libraries_in_flutter
import 'dart:html' as html;

class ReceiptPreview extends StatefulWidget {
  final String invoiceNumber;
  final List<CartItem> cartItems;
  final double subtotal;
  final double tax;
  final double discount;
  final double grandTotal;
  final List<Map<String, dynamic>> payments;
  final double change;
  final String customerName;
  final String businessType;

  const ReceiptPreview({
    super.key,
    required this.invoiceNumber,
    required this.cartItems,
    required this.subtotal,
    required this.tax,
    required this.discount,
    required this.grandTotal,
    required this.payments,
    required this.change,
    required this.customerName,
    required this.businessType,
  });

  @override
  State<ReceiptPreview> createState() => _ReceiptPreviewState();
}

class _ReceiptPreviewState extends State<ReceiptPreview> {
  String _currencySymbol = 'KD';
  String _receiptPrefix = 'INV-POS-';
  String _receiptFooter = 'Thank you for your purchase!';
  String _businessName = 'VendifyERP';
  bool _isLoading = true;
  final PrintService _printService = PrintService();

  @override
  void initState() {
    super.initState();
    _loadSettings();
  }

  Future<void> _loadSettings() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      _currencySymbol = prefs.getString('pos_currency_symbol') ?? 'KD';
      _receiptPrefix = prefs.getString('pos_receipt_prefix') ?? 'INV-POS-';
      _receiptFooter = prefs.getString('pos_receipt_footer') ?? 'Thank you for your purchase!';
      _businessName = prefs.getString('business_name') ?? 'VendifyERP';
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Container(
        width: 400,
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.85,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Header
            Container(
              padding: const EdgeInsets.all(16),
              decoration: const BoxDecoration(
                color: AppTheme.surfaceLight,
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(12),
                  topRight: Radius.circular(12),
                ),
                border: Border(bottom: BorderSide(color: AppTheme.border)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.receipt_long, color: AppTheme.primary, size: 24),
                  const SizedBox(width: 12),
                  const Text(
                    'Receipt',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                  const Spacer(),
                  IconButton(
                    icon: const Icon(Icons.close, color: AppTheme.textSecondary),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
            ),

            // Receipt content
            Flexible(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Container(
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppTheme.border),
                  ),
                  child: _buildReceiptContent(),
                ),
              ),
            ),

            // Action buttons
            Container(
              padding: const EdgeInsets.all(16),
              decoration: const BoxDecoration(
                border: Border(top: BorderSide(color: AppTheme.border)),
              ),
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _copyToClipboard,
                          icon: const Icon(Icons.copy, size: 18),
                          label: const Text('Copy'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppTheme.textSecondary,
                            side: const BorderSide(color: AppTheme.border),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _shareReceipt,
                          icon: const Icon(Icons.share, size: 18),
                          label: const Text('Share'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppTheme.primary,
                            side: const BorderSide(color: AppTheme.primary),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: _printReceipt,
                          icon: const Icon(Icons.print, size: 18),
                          label: const Text('Print'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppTheme.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  // Print settings link
                  TextButton.icon(
                    onPressed: _openPrintSettings,
                    icon: const Icon(Icons.settings, size: 16),
                    label: const Text('Print Settings'),
                    style: TextButton.styleFrom(
                      foregroundColor: AppTheme.textMuted,
                      padding: const EdgeInsets.symmetric(vertical: 4),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildReceiptContent() {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Business Name
          Text(
            _businessName,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: Colors.black,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 4),
          Text(
            'Receipt',
            style: TextStyle(
              fontSize: 12,
              color: Colors.grey[600],
            ),
          ),
          const SizedBox(height: 12),
          
          // Invoice Number
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.grey[100],
              borderRadius: BorderRadius.circular(4),
            ),
            child: Text(
              '$_receiptPrefix${widget.invoiceNumber}',
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.bold,
                color: Colors.black,
                fontFamily: 'monospace',
              ),
            ),
          ),
          const SizedBox(height: 12),
          
          // Date & Time
          Text(
            DateTime.now().toString().substring(0, 19),
            style: TextStyle(fontSize: 11, color: Colors.grey[600]),
          ),
          const SizedBox(height: 4),
          
          // Customer
          Text(
            'Customer: ${widget.customerName}',
            style: TextStyle(fontSize: 11, color: Colors.grey[600]),
          ),
          
          const SizedBox(height: 16),
          
          // Divider
          Container(
            height: 1,
            color: Colors.black12,
          ),
          const SizedBox(height: 12),
          
          // Items header
          const Row(
            children: [
              Expanded(
                flex: 4,
                child: Text(
                  'Item',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.black),
                ),
              ),
              Expanded(
                flex: 1,
                child: Text(
                  'Qty',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.black),
                  textAlign: TextAlign.center,
                ),
              ),
              Expanded(
                flex: 2,
                child: Text(
                  'Price',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.black),
                  textAlign: TextAlign.right,
                ),
              ),
              Expanded(
                flex: 2,
                child: Text(
                  'Total',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.black),
                  textAlign: TextAlign.right,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          
          // Items
          ...widget.cartItems.map((item) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 3),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      flex: 4,
                      child: Text(
                        item.productName,
                        style: const TextStyle(fontSize: 11, color: Colors.black87),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Expanded(
                      flex: 1,
                      child: Text(
                        '${item.quantity.toInt()}',
                        style: const TextStyle(fontSize: 11, color: Colors.black87),
                        textAlign: TextAlign.center,
                      ),
                    ),
                    Expanded(
                      flex: 2,
                      child: Text(
                        '$_currencySymbol ${item.unitPrice.toStringAsFixed(3)}',
                        style: const TextStyle(fontSize: 11, color: Colors.black87),
                        textAlign: TextAlign.right,
                      ),
                    ),
                    Expanded(
                      flex: 2,
                      child: Text(
                        '$_currencySymbol ${item.lineTotal.toStringAsFixed(3)}',
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.black),
                        textAlign: TextAlign.right,
                      ),
                    ),
                  ],
                ),
                if (item.discountAmount > 0)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Row(
                      children: [
                        Expanded(
                          flex: 4,
                          child: Text(
                            '  Discount',
                            style: TextStyle(fontSize: 10, color: Colors.grey[600]),
                          ),
                        ),
                        const Expanded(flex: 1, child: SizedBox()),
                        const Expanded(flex: 2, child: SizedBox()),
                        Expanded(
                          flex: 2,
                          child: Text(
                            '- $_currencySymbol ${item.discountAmount.toStringAsFixed(3)}',
                            style: TextStyle(fontSize: 10, color: Colors.red[400]),
                            textAlign: TextAlign.right,
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          )),
          
          const SizedBox(height: 12),
          
          // Divider
          Container(
            height: 1,
            color: Colors.black12,
          ),
          const SizedBox(height: 8),
          
          // Totals
          _buildTotalRow('Subtotal', '$_currencySymbol ${widget.subtotal.toStringAsFixed(3)}'),
          if (widget.tax > 0)
            _buildTotalRow('Tax', '$_currencySymbol ${widget.tax.toStringAsFixed(3)}'),
          if (widget.discount > 0)
            _buildTotalRow('Discount', '- $_currencySymbol ${widget.discount.toStringAsFixed(3)}', isNegative: true),
          
          const SizedBox(height: 8),
          Container(
            height: 2,
            color: Colors.black,
          ),
          const SizedBox(height: 8),
          
          // Grand Total
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'TOTAL',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: Colors.black,
                ),
              ),
              Text(
                '$_currencySymbol ${widget.grandTotal.toStringAsFixed(3)}',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: Colors.black,
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 12),
          
          // Payment breakdown
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.grey[50],
              borderRadius: BorderRadius.circular(4),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Payment',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.black),
                ),
                const SizedBox(height: 4),
                ...widget.payments.map((p) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 1),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        _getPaymentMethodLabel(p['method'] ?? ''),
                        style: TextStyle(fontSize: 11, color: Colors.grey[700]),
                      ),
                      Text(
                        '$_currencySymbol ${(p['amount'] ?? 0).toStringAsFixed(3)}',
                        style: const TextStyle(fontSize: 11, color: Colors.black87),
                      ),
                    ],
                  ),
                )),
                if (widget.change > 0)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Change',
                          style: TextStyle(fontSize: 11, color: Colors.grey[700]),
                        ),
                        Text(
                          '$_currencySymbol ${widget.change.toStringAsFixed(3)}',
                          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.black87),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          
          const SizedBox(height: 16),
          
          // Footer
          Container(
            height: 1,
            color: Colors.black12,
          ),
          const SizedBox(height: 12),
          
          Text(
            _receiptFooter,
            style: TextStyle(
              fontSize: 11,
              fontStyle: FontStyle.italic,
              color: Colors.grey[600],
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),

          // Invoice barcode
          Center(
            child: BarcodeWidget(
              barcode: Barcode.code128(),
              data: widget.invoiceNumber,
              width: 180,
              height: 45,
              drawText: false,
            ),
          ),
          const SizedBox(height: 4),
          Center(
            child: Text(
              widget.invoiceNumber,
              style: TextStyle(fontSize: 9, color: Colors.grey[500], letterSpacing: 1),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTotalRow(String label, String value, {bool isNegative = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(fontSize: 12, color: Colors.grey[700]),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: isNegative ? Colors.red : Colors.black87,
            ),
          ),
        ],
      ),
    );
  }

  String _getPaymentMethodLabel(String method) {
    const labels = {
      'cash': 'Cash',
      'card': 'Debit Card',
      'visa': 'Visa',
      'mastercard': 'Mastercard',
      'amex': 'Amex',
      'bank_transfer': 'Bank Transfer',
      'cheque': 'Cheque',
      'other': 'Other',
    };
    return labels[method] ?? method.replaceAll('_', ' ').toUpperCase();
  }

  String _generateReceiptText() {
    final buffer = StringBuffer();
    buffer.writeln(_businessName);
    buffer.writeln('Receipt');
    buffer.writeln('$_receiptPrefix${widget.invoiceNumber}');
    buffer.writeln(DateTime.now().toString().substring(0, 19));
    buffer.writeln('Customer: ${widget.customerName}');
    buffer.writeln('─' * 30);
    buffer.writeln('');
    
    for (final item in widget.cartItems) {
      buffer.writeln(item.productName);
      buffer.writeln('  ${item.quantity.toInt()} x $_currencySymbol ${item.unitPrice.toStringAsFixed(3)}  =  $_currencySymbol ${item.lineTotal.toStringAsFixed(3)}');
    }
    
    buffer.writeln('');
    buffer.writeln('─' * 30);
    buffer.writeln('Subtotal:    $_currencySymbol ${widget.subtotal.toStringAsFixed(3)}');
    if (widget.tax > 0) buffer.writeln('Tax:         $_currencySymbol ${widget.tax.toStringAsFixed(3)}');
    if (widget.discount > 0) buffer.writeln('Discount:   -$_currencySymbol ${widget.discount.toStringAsFixed(3)}');
    buffer.writeln('═══════════════════════════════════');
    buffer.writeln('TOTAL:       $_currencySymbol ${widget.grandTotal.toStringAsFixed(3)}');
    buffer.writeln('');
    
    buffer.writeln('Payment:');
    for (final p in widget.payments) {
      buffer.writeln('  ${_getPaymentMethodLabel(p['method'] ?? '')}: $_currencySymbol ${(p['amount'] ?? 0).toStringAsFixed(3)}');
    }
    if (widget.change > 0) {
      buffer.writeln('  Change:    $_currencySymbol ${widget.change.toStringAsFixed(3)}');
    }
    
    buffer.writeln('');
    buffer.writeln('─' * 30);
    buffer.writeln(_receiptFooter);
    
    return buffer.toString();
  }

  void _copyToClipboard() {
    final text = _generateReceiptText();
    Clipboard.setData(ClipboardData(text: text));
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Receipt copied to clipboard'),
        backgroundColor: AppTheme.success,
      ),
    );
  }

  void _shareReceipt() async {
    final text = _generateReceiptText();
    
    // Try Web Share API first (works on mobile browsers)
    try {
      await html.window.navigator.share({
        'text': text,
        'title': 'Receipt $_receiptPrefix${widget.invoiceNumber}',
      });
      return;
    } catch (_) {
      // Web Share API not available or failed
    }
    
    // Fallback: copy to clipboard
    _copyToClipboard();
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Receipt copied to clipboard'),
        backgroundColor: AppTheme.success,
      ),
    );
  }

  void _printReceipt() async {
    // Build receipt HTML using PrintService
    final receiptHtml = _printService.buildReceiptHtml(
      businessName: _businessName,
      invoiceNumber: widget.invoiceNumber,
      invoicePrefix: _receiptPrefix,
      dateTime: DateTime.now().toString().substring(0, 19),
      customerName: widget.customerName,
      items: widget.cartItems.map((item) => {
        'name': item.productName,
        'quantity': '${item.quantity.toInt()}',
        'unit_price': item.unitPrice.toStringAsFixed(3),
        'line_total': item.lineTotal.toStringAsFixed(3),
        'discount': item.discountAmount > 0 ? item.discountAmount.toStringAsFixed(3) : null,
      }).toList(),
      subtotal: widget.subtotal.toStringAsFixed(3),
      tax: widget.tax > 0 ? widget.tax.toStringAsFixed(3) : null,
      discount: widget.discount > 0 ? widget.discount.toStringAsFixed(3) : null,
      grandTotal: widget.grandTotal.toStringAsFixed(3),
      payments: widget.payments.map((p) => {
        'method': _getPaymentMethodLabel(p['method'] ?? ''),
        'amount': (p['amount'] ?? 0).toStringAsFixed(3),
      }).toList(),
      change: widget.change > 0 ? widget.change.toStringAsFixed(3) : null,
      footer: _receiptFooter,
      currencySymbol: _currencySymbol,
    );
    
    await _printService.printHtml(receiptHtml);
    
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Print dialog opened'),
          backgroundColor: AppTheme.primary,
        ),
      );
    }
  }

  void _openPrintSettings() async {
    await PrintSettingsDialog.show(context);
  }
}
