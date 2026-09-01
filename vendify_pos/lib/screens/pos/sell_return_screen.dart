import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';

class SellReturnScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> invoice;
  const SellReturnScreen({super.key, required this.invoice});

  @override
  ConsumerState<SellReturnScreen> createState() => _SellReturnScreenState();
}

class _SellReturnScreenState extends ConsumerState<SellReturnScreen> {

  static double _vd(dynamic v) { if (v == null) return 0; if (v is num) return v.toDouble(); return (num.tryParse(v.toString()) ?? 0).toDouble(); }
  late final PosService _posService = ref.read(posServiceProvider);
  bool _isProcessing = false;
  String _refundMethod = 'cash';
  
  // Selected items for return
  final Map<int, double> _returnQuantities = {};
  final Map<int, TextEditingController> _reasonControllers = {};
  final Map<int, String> _returnReasons = {};

  @override
  void initState() {
    super.initState();
    // Initialize return quantities to 0
    final items = widget.invoice['sell_lines'] as List? ?? [];
    for (final item in items) {
      final id = item['id'] as int;
      _returnQuantities[id] = 0;
      _reasonControllers[id] = TextEditingController();
    }
  }

  @override
  void dispose() {
    for (final c in _reasonControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  double get _totalReturnAmount {
    final items = widget.invoice['sell_lines'] as List? ?? [];
    double total = 0;
    for (final item in items) {
      final id = item['id'] as int;
      final qty = _returnQuantities[id] ?? 0;
      if (qty > 0) {
        final unitPrice = _vd(item['unit_price']);
        total += qty * unitPrice;
      }
    }
    return total;
  }

  List<Map<String, dynamic>> get _selectedReturnItems {
    final items = widget.invoice['sell_lines'] as List? ?? [];
    final result = <Map<String, dynamic>>[];
    for (final item in items) {
      final id = item['id'] as int;
      final qty = _returnQuantities[id] ?? 0;
      if (qty > 0) {
        result.add({
          'sell_line_id': id,
          'quantity': qty,
          'return_reason': _returnReasons[id],
        });
      }
    }
    return result;
  }

  Future<void> _processReturn() async {
    if (_selectedReturnItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select at least one item to return'), backgroundColor: AppTheme.error),
      );
      return;
    }

    setState(() => _isProcessing = true);

    try {
      await _posService.returnSellItems(
        sellId: widget.invoice['id'],
        returnItems: _selectedReturnItems,
        refundMethod: _refundMethod,
      );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Return processed! Refund: KD ${_totalReturnAmount.toStringAsFixed(3)}'),
            backgroundColor: AppTheme.success,
          ),
        );
        Navigator.pop(context);
        Navigator.pop(context); // Go back to invoice list
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
    final items = widget.invoice['sell_lines'] as List? ?? [];
    final invoiceNo = widget.invoice['invoice_no'] ?? 'N/A';

    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        backgroundColor: AppTheme.surface,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppTheme.textPrimary),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text('Return / Exchange', style: TextStyle(color: AppTheme.textPrimary, fontSize: 16, fontWeight: FontWeight.bold)),
      ),
      body: Column(
        children: [
          // Invoice info
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            color: AppTheme.surface,
            child: Text('Invoice: $invoiceNo', style: TextStyle(fontSize: 13, color: AppTheme.textMuted)),
          ),
          
          // Items list
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: items.length,
              itemBuilder: (context, index) => _buildReturnItemCard(items[index]),
            ),
          ),
          
          // Summary and submit
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppTheme.surface,
              border: Border(top: BorderSide(color: AppTheme.border)),
            ),
            child: Column(
              children: [
                // Refund method
                Row(
                  children: [
                    const Text('Refund via:', style: TextStyle(fontSize: 13, color: AppTheme.textSecondary)),
                    const SizedBox(width: 12),
                    ChoiceChip(
                      label: const Text('Cash', style: TextStyle(fontSize: 12)),
                      selected: _refundMethod == 'cash',
                      selectedColor: AppTheme.primary,
                      onSelected: (_) => setState(() => _refundMethod = 'cash'),
                    ),
                    const SizedBox(width: 8),
                    ChoiceChip(
                      label: const Text('Card', style: TextStyle(fontSize: 12)),
                      selected: _refundMethod == 'card',
                      selectedColor: AppTheme.primary,
                      onSelected: (_) => setState(() => _refundMethod = 'card'),
                    ),
                    const SizedBox(width: 8),
                    ChoiceChip(
                      label: const Text('Credit', style: TextStyle(fontSize: 12)),
                      selected: _refundMethod == 'bank_transfer',
                      selectedColor: AppTheme.primary,
                      onSelected: (_) => setState(() => _refundMethod = 'bank_transfer'),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Return Total', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                    Text('KD ${_totalReturnAmount.toStringAsFixed(3)}', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: _totalReturnAmount > 0 ? AppTheme.warning : AppTheme.textPrimary)),
                  ],
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: _totalReturnAmount > 0 && !_isProcessing ? _processReturn : null,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _totalReturnAmount > 0 ? AppTheme.warning : AppTheme.surfaceLight,
                      foregroundColor: _totalReturnAmount > 0 ? Colors.black : AppTheme.textMuted,
                    ),
                    child: _isProcessing
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                        : Text(
                            _totalReturnAmount > 0 ? 'Process Return (KD ${_totalReturnAmount.toStringAsFixed(3)})' : 'Select items to return',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildReturnItemCard(Map<String, dynamic> item) {
    final id = item['id'] as int;
    final product = item['product'];
    final productName = product != null ? (product['name'] ?? 'Unknown') : 'Unknown';
    final qty = _vd(item['quantity']);
    final unitPrice = _vd(item['unit_price']);
    final returned = _vd(item['quantity_returned']);
    final returnable = qty - returned;
    final selectedQty = _returnQuantities[id] ?? 0;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(productName, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppTheme.textPrimary)),
                      const SizedBox(height: 2),
                      Text(
                        'Purchased: ${qty.toInt()} × KD ${unitPrice.toStringAsFixed(3)}',
                        style: TextStyle(fontSize: 12, color: AppTheme.textMuted),
                      ),
                      if (returned > 0)
                        Text('Already returned: ${returned.toInt()}', style: TextStyle(fontSize: 11, color: AppTheme.warning)),
                    ],
                  ),
                ),
                Text('Returnable: ${returnable.toInt()}', style: TextStyle(fontSize: 12, color: AppTheme.success, fontWeight: FontWeight.w600)),
              ],
            ),
            if (returnable > 0) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  const Text('Return Qty:', style: TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                  const SizedBox(width: 8),
                  // Decrement button
                  IconButton(
                    onPressed: selectedQty > 0 ? () {
                      setState(() { _returnQuantities[id] = selectedQty - 1; });
                    } : null,
                    icon: const Icon(Icons.remove_circle_outline, size: 24),
                    color: AppTheme.error,
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    child: Text('${selectedQty.toInt()}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  ),
                  // Increment button
                  IconButton(
                    onPressed: selectedQty < returnable ? () {
                      setState(() { _returnQuantities[id] = selectedQty + 1; });
                    } : null,
                    icon: const Icon(Icons.add_circle_outline, size: 24),
                    color: AppTheme.success,
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints(),
                  ),
                  const Spacer(),
                  if (selectedQty > 0)
                    Text(
                      'KD ${(selectedQty * unitPrice).toStringAsFixed(3)}',
                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: AppTheme.warning),
                    ),
                ],
              ),
              // Reason field
              if (selectedQty > 0)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: TextField(
                    controller: _reasonControllers[id],
                    onChanged: (v) => _returnReasons[id] = v,
                    style: const TextStyle(fontSize: 12, color: AppTheme.textPrimary),
                    decoration: InputDecoration(
                      hintText: 'Return reason (optional)',
                      hintStyle: TextStyle(fontSize: 12, color: AppTheme.textMuted),
                      isDense: true,
                      contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(6), borderSide: BorderSide.none),
                      filled: true,
                      fillColor: AppTheme.background,
                    ),
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}
