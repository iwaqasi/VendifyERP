import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/cart_item.dart';

class EditCartItemDialog extends StatefulWidget {
  final CartItem item;
  final Function(CartItem) onSave;

  const EditCartItemDialog({
    super.key,
    required this.item,
    required this.onSave,
  });

  @override
  State<EditCartItemDialog> createState() => _EditCartItemDialogState();
}

class _EditCartItemDialogState extends State<EditCartItemDialog> {
  late int _quantity;
  late TextEditingController _discountPercentController;
  late TextEditingController _discountAmountController;
  bool _updatingFromPercent = false;
  bool _updatingFromAmount = false;

  double get _lineTotal => widget.item.unitPrice * _quantity;
  double get _discountPercent => double.tryParse(_discountPercentController.text) ?? 0;
  double get _discountAmount => _updatingFromAmount
      ? (double.tryParse(_discountAmountController.text) ?? 0)
      : _lineTotal * _discountPercent / 100;
  double get _total => _lineTotal - _discountAmount;

  @override
  void initState() {
    super.initState();
    _quantity = widget.item.quantity.toInt();
    _discountPercentController = TextEditingController(
      text: widget.item.discountPercent > 0 ? widget.item.discountPercent.toStringAsFixed(3) : '0.000',
    );
    _discountAmountController = TextEditingController(
      text: widget.item.discount > 0 ? widget.item.discount.toStringAsFixed(3) : '0.000',
    );
  }

  @override
  void dispose() {
    _discountPercentController.dispose();
    _discountAmountController.dispose();
    super.dispose();
  }

  void _onPercentChanged(String value) {
    if (_updatingFromAmount) return;
    _updatingFromPercent = true;
    final percent = double.tryParse(value) ?? 0;
    final amount = _lineTotal * percent / 100;
    _discountAmountController.text = amount.toStringAsFixed(3);
    setState(() {});
    _updatingFromPercent = false;
  }

  void _onAmountChanged(String value) {
    if (_updatingFromPercent) return;
    _updatingFromAmount = true;
    final amount = double.tryParse(value) ?? 0;
    final percent = _lineTotal > 0 ? (amount / _lineTotal * 100) : 0;
    _discountPercentController.text = percent.toStringAsFixed(3);
    setState(() {});
    _updatingFromAmount = false;
  }

  void _save() {
    final updated = widget.item.copyWith(
      quantity: _quantity.toDouble(),
      discountPercent: _discountPercent,
      discount: _discountAmount,
    );
    widget.onSave(updated);
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 400),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Header
                const Text(
                  'Edit Cart Item',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const SizedBox(height: 16),

                // Product info
                Row(
                  children: [
                    // Product image
                    if (widget.item.image != null)
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: Image.network(
                          widget.item.image!,
                          width: 56,
                          height: 56,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => Container(
                            width: 56,
                            height: 56,
                            decoration: BoxDecoration(
                              color: AppTheme.surfaceLight,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Icon(Icons.inventory_2, color: AppTheme.textMuted),
                          ),
                        ),
                      )
                    else
                      Container(
                        width: 56,
                        height: 56,
                        decoration: BoxDecoration(
                          color: AppTheme.surfaceLight,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Icon(Icons.inventory_2, color: AppTheme.textMuted),
                      ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.item.productName,
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.textPrimary,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Price: KD${widget.item.unitPrice.toStringAsFixed(3)}',
                            style: const TextStyle(
                              fontSize: 13,
                              color: AppTheme.priceColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 20),

                // Quantity
                Row(
                  children: [
                    const Text(
                      'Quantity:',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    const SizedBox(width: 12),
                    // Minus button
                    _buildCircleButton(
                      icon: Icons.remove,
                      onTap: _quantity > 1
                          ? () => setState(() => _quantity--)
                          : null,
                    ),
                    const SizedBox(width: 12),
                    Text(
                      '$_quantity',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    const SizedBox(width: 12),
                    // Plus button
                    _buildCircleButton(
                      icon: Icons.add,
                      onTap: () => setState(() => _quantity++),
                    ),
                  ],
                ),

                const SizedBox(height: 20),

                // Discount Percent
                const Text(
                  'Discount Percent (%)',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppTheme.textSecondary,
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  controller: _discountPercentController,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  style: const TextStyle(color: AppTheme.textPrimary),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,3}')),
                  ],
                  onChanged: _onPercentChanged,
                  decoration: InputDecoration(
                    filled: true,
                    fillColor: AppTheme.surfaceLight,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: const BorderSide(color: AppTheme.border),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: const BorderSide(color: AppTheme.border),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: const BorderSide(color: AppTheme.primary, width: 2),
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  ),
                ),

                const SizedBox(height: 14),

                // Discount Amount
                const Text(
                  'Discount Amount (KD)',
                  style: TextStyle(
                    fontSize: 13,
                    color: AppTheme.textSecondary,
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  controller: _discountAmountController,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  style: const TextStyle(color: AppTheme.textPrimary),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,3}')),
                  ],
                  onChanged: _onAmountChanged,
                  decoration: InputDecoration(
                    filled: true,
                    fillColor: AppTheme.surfaceLight,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: const BorderSide(color: AppTheme.border),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: const BorderSide(color: AppTheme.border),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: const BorderSide(color: AppTheme.primary, width: 2),
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  ),
                ),

                const SizedBox(height: 20),

                // Total
                Align(
                  alignment: Alignment.centerRight,
                  child: Text(
                    'Total: KD${_total.toStringAsFixed(3)}',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.priceColor,
                    ),
                  ),
                ),

                const SizedBox(height: 20),

                // Action buttons
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.of(context).pop(),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppTheme.textSecondary,
                          side: const BorderSide(color: AppTheme.border),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        child: const Text('Cancel'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: _save,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppTheme.primary,
                          foregroundColor: Colors.black,
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        child: const Text(
                          'Save Changes',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCircleButton({required IconData icon, VoidCallback? onTap}) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: onTap != null ? AppTheme.surfaceLight : AppTheme.surface,
          shape: BoxShape.circle,
          border: Border.all(color: AppTheme.border),
        ),
        child: Icon(
          icon,
          size: 18,
          color: onTap != null ? AppTheme.textPrimary : AppTheme.textMuted,
        ),
      ),
    );
  }
}
