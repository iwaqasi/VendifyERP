import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/services/print_service.dart';

class OrderHistoryScreen extends ConsumerStatefulWidget {
  const OrderHistoryScreen({super.key});

  @override
  ConsumerState<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends ConsumerState<OrderHistoryScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  List<dynamic> _orders = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadOrders();
  }

  Future<void> _loadOrders() async {
    setState(() => _isLoading = true);
    try {
      final orders = await _posService.getSales(perPage: 50);
      setState(() {
        _orders = orders;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
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
          'Order History & Returns',
          style: TextStyle(
            color: AppTheme.textPrimary,
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_list, color: AppTheme.textSecondary),
            onPressed: () {},
            tooltip: 'Filter',
          ),
          TextButton.icon(
            onPressed: () {},
            icon: const Icon(Icons.swap_horiz, size: 16, color: AppTheme.primary),
            label: const Text(
              'Exchange Item',
              style: TextStyle(color: AppTheme.primary, fontSize: 13),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.refresh, color: AppTheme.textSecondary),
            onPressed: _loadOrders,
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : _orders.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.receipt_long, size: 64, color: AppTheme.textMuted),
                      const SizedBox(height: 16),
                      Text(
                        'No orders found',
                        style: TextStyle(color: AppTheme.textSecondary, fontSize: 16),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadOrders,
                  color: AppTheme.primary,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(AppTheme.md),
                    itemCount: _orders.length,
                    itemBuilder: (context, index) {
                      final order = _orders[index];
                      return _buildOrderCard(order);
                    },
                  ),
                ),
    );
  }

  Widget _buildOrderCard(dynamic order) {
    final invoiceNo = order['invoice_no'] ?? '#${order['id']}';
    final contactName = order['contact'] != null
        ? order['contact']['name']
        : 'Walk-in Customer';
    final total = (order['final_total'] ?? 0).toDouble();
    final date = order['transaction_date'] ?? '';
    final paymentStatus = order['payment_status'] ?? 'due';
    final cashier = order['created_by'] != null ? 'Admin' : 'Unknown';

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(AppTheme.radiusMd),
        border: Border.all(color: AppTheme.border),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppTheme.radiusMd),
        onTap: () {
          // TODO: Open order details
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              // Order info
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '#$invoiceNo',
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '$contactName • Vendify POS • ${_formatDate(date)}',
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppTheme.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Cashier: $cashier',
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppTheme.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),

              // Print button + Amount & Status
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  // Reprint button
                  InkWell(
                    onTap: () => _reprintReceipt(order),
                    borderRadius: BorderRadius.circular(6),
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: AppTheme.primary.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Icon(Icons.print, size: 18, color: AppTheme.primary),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'KD ${total.toStringAsFixed(3)}',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.priceColor,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: (paymentStatus == 'paid' ? AppTheme.success : AppTheme.warning).withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      paymentStatus.toUpperCase(),
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        color: paymentStatus == 'paid' ? AppTheme.success : AppTheme.warning,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatDate(String dateStr) {
    try {
      final date = DateTime.parse(dateStr);
      final months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                       'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return '${months[date.month - 1]} ${date.day}, ${date.hour}:${date.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return dateStr;
    }
  }

  void _reprintReceipt(dynamic order) async {
    final printService = PrintService();
    final invoiceNo = order['invoice_no'] ?? '#${order['id']}';
    final contactName = order['contact'] != null
        ? order['contact']['name']
        : 'Walk-in Customer';
    final total = (order['final_total'] ?? 0).toDouble();
    final date = order['transaction_date'] ?? '';
    final payments = (order['payment_lines'] as List?) ?? [];

    // Build items from sell_lines if available
    final sellLines = (order['sell_lines'] as List?) ?? [];
    final items = sellLines.map<Map<String, dynamic>>((line) {
      final product = line['product'] ?? {};
      return {
        'name': product['name'] ?? 'Item',
        'quantity': '${(line['quantity'] ?? 0)}',
        'unit_price': (line['unit_price'] ?? 0).toStringAsFixed(3),
        'line_total': ((line['unit_price'] ?? 0) * (line['quantity'] ?? 0)).toStringAsFixed(3),
        'discount': null,
      };
    }).toList();

    // Fallback if no sell lines: show single total line
    if (items.isEmpty) {
      items.add({
        'name': 'Sale #$invoiceNo',
        'quantity': '1',
        'unit_price': total.toStringAsFixed(3),
        'line_total': total.toStringAsFixed(3),
        'discount': null,
      });
    }

    final receiptHtml = printService.buildReceiptHtml(
      businessName: 'Vendify POS',
      invoiceNumber: '$invoiceNo',
      invoicePrefix: '',
      dateTime: date,
      customerName: contactName,
      items: items,
      subtotal: total.toStringAsFixed(3),
      grandTotal: total.toStringAsFixed(3),
      payments: payments.map<Map<String, dynamic>>((p) => {
        'method': (p['method'] ?? 'cash').toString().toUpperCase(),
        'amount': (p['amount'] ?? 0).toStringAsFixed(3),
      }).toList(),
      footer: 'Thank you for your purchase!',
      currencySymbol: 'KD',
    );

    await printService.printHtml(receiptHtml);

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Printing receipt #$invoiceNo'),
          backgroundColor: AppTheme.primary,
        ),
      );
    }
  }
}
