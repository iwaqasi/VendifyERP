import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/cart_item.dart';

class CartPanel extends StatefulWidget {
  final List<CartItem> cartItems;
  final String customerName;
  final int itemCount; // Distinct items
  final int totalQuantity; // Total quantity
  final double subtotal;
  final double tax;
  final double grandTotal;
  final double receiptDiscount;
  final String receiptDiscountType;
  final Function(int) onRemove;
  final Function(int, double) onUpdateQuantity;
  final Function(int, CartItem) onUpdateItem;
  final VoidCallback onClearCart;
  final VoidCallback onPayment;
  final VoidCallback onCustomerTap;
  final VoidCallback onAddNewCustomer;
  final VoidCallback onHoldCart;
  final VoidCallback onRecall;
  final Function(String) onBarcodeSearch;
  final Function(double, String) onReceiptDiscountChanged;
  final Map<String, dynamic> features;
  final int customerRewardPoints;
  final String rpName;
  final double rpRedeemAmount;
  final Function(double)? onRedeemPoints;
  final double rpDiscount;
  final int rpPointsRedeemed;
  final double customerCreditLimit;
  final double customerSellDue;
  final int customerPayTermNumber;
  final String customerPayTermType;

  const CartPanel({
    super.key,
    required this.cartItems,
    required this.customerName,
    required this.itemCount,
    required this.totalQuantity,
    required this.subtotal,
    required this.tax,
    required this.grandTotal,
    required this.receiptDiscount,
    required this.receiptDiscountType,
    required this.onRemove,
    required this.onUpdateQuantity,
    required this.onUpdateItem,
    required this.onClearCart,
    required this.onPayment,
    required this.onCustomerTap,
    required this.onAddNewCustomer,
    required this.onHoldCart,
    required this.onRecall,
    required this.onBarcodeSearch,
    required this.onReceiptDiscountChanged,
    this.features = const {},
    this.customerRewardPoints = 0,
    this.rpName = 'Reward Points',
    this.rpRedeemAmount = 0.0,
    this.onRedeemPoints,
    this.rpDiscount = 0.0,
    this.rpPointsRedeemed = 0,
    this.customerCreditLimit = 0,
    this.customerSellDue = 0,
    this.customerPayTermNumber = 0,
    this.customerPayTermType = 'days',
  });

  @override
  State<CartPanel> createState() => _CartPanelState();
}

class _CartPanelState extends State<CartPanel> {
  final _discountPercentController = TextEditingController(text: '0');
  final _discountAmountController = TextEditingController(text: '0');
  bool _isPercentFocused = false;
  bool _isAmountFocused = false;

  @override
  void didUpdateWidget(CartPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    // Update controllers when receipt discount changes externally
    if (widget.receiptDiscount != oldWidget.receiptDiscount ||
        widget.receiptDiscountType != oldWidget.receiptDiscountType) {
      if (!_isPercentFocused && !_isAmountFocused) {
        if (widget.receiptDiscountType == 'percentage') {
          _discountPercentController.text = widget.receiptDiscount.toStringAsFixed(2);
          final amount = widget.subtotal * widget.receiptDiscount / 100;
          _discountAmountController.text = amount.toStringAsFixed(3);
        } else {
          _discountAmountController.text = widget.receiptDiscount.toStringAsFixed(3);
          final percent = widget.subtotal > 0 ? (widget.receiptDiscount / widget.subtotal * 100) : 0.0;
          _discountPercentController.text = percent.toStringAsFixed(2);
        }
      }
    }
  }

  @override
  void dispose() {
    _discountPercentController.dispose();
    _discountAmountController.dispose();
    super.dispose();
  }

  void _onPercentChanged(String value) {
    _isPercentFocused = true;
    _isAmountFocused = false;
    final percent = double.tryParse(value) ?? 0;
    final amount = widget.subtotal * percent / 100;
    _discountAmountController.text = amount.toStringAsFixed(3);
    widget.onReceiptDiscountChanged(percent, 'percentage');
    setState(() {});
  }

  void _onAmountChanged(String value) {
    _isAmountFocused = true;
    _isPercentFocused = false;
    final amount = double.tryParse(value) ?? 0;
    final percent = widget.subtotal > 0 ? (amount / widget.subtotal * 100) : 0.0;
    _discountPercentController.text = percent.toStringAsFixed(2);
    widget.onReceiptDiscountChanged(amount, 'fixed');
    setState(() {});
  }

  void _onPercentSubmitted(String value) {
    _isPercentFocused = false;
  }

  void _onAmountSubmitted(String value) {
    _isAmountFocused = false;
  }

  void _showRedeemDialog(BuildContext context) {
    final controller = TextEditingController(
      text: widget.rpRedeemAmount.toStringAsFixed(3),
    );
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Redeem Loyalty Points'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Available: ${widget.customerRewardPoints} ${widget.rpName}',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 4),
            Text(
              'Maximum redeemable: KD ${widget.rpRedeemAmount.toStringAsFixed(3)}',
              style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: controller,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                labelText: 'Redeem Amount (KD)',
                border: OutlineInputBorder(),
                prefixText: 'KD ',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              final amount = double.tryParse(controller.text) ?? 0;
              if (amount > 0 && amount <= widget.rpRedeemAmount) {
                Navigator.pop(ctx);
                widget.onRedeemPoints?.call(amount);
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
            child: const Text('Redeem', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final receiptDiscountAmount = widget.receiptDiscountType == 'percentage'
        ? widget.subtotal * widget.receiptDiscount / 100
        : widget.receiptDiscount;
    return Container(
      color: AppTheme.background,
      child: Column(
        children: [
          // Header
          Container(
            padding: const EdgeInsets.all(AppTheme.md),
            child: const Row(
              children: [
                Icon(Icons.shopping_cart, color: AppTheme.primary, size: 18),
                SizedBox(width: 8),
                Text(
                  'Current Sale',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textPrimary,
                  ),
                ),
              ],
            ),
          ),

          // Customer Selection
          Container(
            padding: const EdgeInsets.symmetric(horizontal: AppTheme.md),
            child: Column(
              children: [
                _buildCustomerRow(Icons.person, widget.customerName, widget.onCustomerTap, isHighlighted: widget.customerName != 'Walk-in Customer'),
                const SizedBox(height: 6),
                Row(
                  children: [
                    Expanded(
                      child: _buildSmallButton(Icons.search, 'Search Customer', widget.onCustomerTap),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _buildSmallButton(Icons.person_add, 'Add New', widget.onAddNewCustomer),
                    ),
                  ],
                ),
                // Credit Customer Info
                if (widget.customerName != 'Walk-in Customer' && (widget.customerCreditLimit > 0 || widget.customerSellDue > 0)) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: widget.customerSellDue > 0 ? Colors.red.shade50 : Colors.blue.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: widget.customerSellDue > 0 ? Colors.red.shade200 : Colors.blue.shade200),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(children: [
                          Icon(Icons.account_balance_wallet, size: 14, color: widget.customerSellDue > 0 ? Colors.red.shade700 : Colors.blue.shade700),
                          const SizedBox(width: 6),
                          Text('Due: KD ${widget.customerSellDue.toStringAsFixed(3)}', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: widget.customerSellDue > 0 ? Colors.red.shade800 : Colors.blue.shade800)),
                        ]),
                        if (widget.customerCreditLimit > 0)
                          Padding(padding: const EdgeInsets.only(top: 2), child: Text('Credit Limit: KD ${widget.customerCreditLimit.toStringAsFixed(3)}', style: TextStyle(fontSize: 10, color: Colors.blue.shade600))),
                        if (widget.customerPayTermNumber > 0)
                          Padding(padding: const EdgeInsets.only(top: 2), child: Text('Terms: Net ${widget.customerPayTermNumber} ${widget.customerPayTermType}', style: TextStyle(fontSize: 10, color: AppTheme.textMuted))),
                      ],
                    ),
                  ),
                ],
                // Loyalty Points Badge
                if (widget.customerName != 'Walk-in Customer' && widget.customerRewardPoints > 0) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                    decoration: BoxDecoration(
                      color: widget.rpDiscount > 0 ? Colors.green.shade50 : Colors.amber.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: widget.rpDiscount > 0 ? Colors.green.shade300 : Colors.amber.shade200,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          widget.rpDiscount > 0 ? Icons.check_circle : Icons.stars,
                          size: 18,
                          color: widget.rpDiscount > 0 ? Colors.green.shade700 : Colors.amber.shade700,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${widget.customerRewardPoints} ${widget.rpName}',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: widget.rpDiscount > 0 ? Colors.green.shade900 : Colors.amber.shade900,
                                ),
                              ),
                              if (widget.rpDiscount > 0)
                                Text(
                                  'Redeemed: -KD ${widget.rpDiscount.toStringAsFixed(3)}',
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600,
                                    color: Colors.green.shade800,
                                  ),
                                )
                              else
                                Text(
                                  'Worth: KD ${widget.rpRedeemAmount.toStringAsFixed(3)}',
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: Colors.amber.shade800,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        if (widget.rpDiscount > 0)
                          IconButton(
                            onPressed: () => widget.onRedeemPoints?.call(0),
                            icon: Icon(Icons.close, size: 16, color: Colors.red.shade600),
                            tooltip: 'Remove redemption',
                          )
                        else if (widget.onRedeemPoints != null)
                          TextButton.icon(
                            onPressed: () => _showRedeemDialog(context),
                            icon: Icon(Icons.redeem, size: 16, color: Colors.green.shade700),
                            label: Text('Redeem', style: TextStyle(fontSize: 12, color: Colors.green.shade700)),
                          ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),

          const SizedBox(height: 12),

          // Barcode input
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppTheme.md),
            child: _BarcodeInput(onSearch: widget.onBarcodeSearch),
          ),

          const SizedBox(height: 12),
          const Divider(height: 1, color: AppTheme.border),

          // Cart Items
          Expanded(
            child: widget.cartItems.isEmpty
                ? const Center(
                    child: Text(
                      'Cart is empty',
                      style: TextStyle(
                        color: AppTheme.textMuted,
                        fontSize: 14,
                      ),
                    ),
                  )
                : ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: AppTheme.sm),
                    itemCount: widget.cartItems.length,
                    itemBuilder: (context, index) {
                      final item = widget.cartItems[index];
                      return _CartItemTile(
                        item: item,
                        onRemove: () => widget.onRemove(index),
                        onQuantityChanged: (qty) => widget.onUpdateQuantity(index, qty),
                        onTap: () => widget.onUpdateItem(index, item),
                      );
                    },
                  ),
          ),

          const Divider(height: 1, color: AppTheme.border),

          // Tender Details (scrollable)
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: AppTheme.md, vertical: 8),
              child: Column(
                children: [
                const Text(
                  'Tender Details',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const SizedBox(height: 12),
                _buildDetailRow('Number of Items:', '${widget.itemCount}'),
                _buildDetailRow('Quantity:', '${widget.totalQuantity}'),
                _buildDetailRow('Actual Amount:', 'KD ${widget.subtotal.toStringAsFixed(3)}'),
                const SizedBox(height: 8),
                
                // Receipt Discount Row (controlled by disable_discount feature)
                if (widget.features['discount_enabled'] != false)
                Row(
                  children: [
                    const Text(
                      'Discount:',
                      style: TextStyle(
                        color: AppTheme.textSecondary,
                        fontSize: 13,
                      ),
                    ),
                    const Spacer(),
                    // Percentage field
                    SizedBox(
                      width: 60,
                      height: 32,
                      child: TextField(
                        controller: _discountPercentController,
                        style: const TextStyle(color: AppTheme.textPrimary, fontSize: 12),
                        textAlign: TextAlign.center,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        inputFormatters: [
                          FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,2}')),
                        ],
                        onChanged: _onPercentChanged,
                        onSubmitted: _onPercentSubmitted,
                        onTap: () {
                          _discountPercentController.selection = TextSelection(
                            baseOffset: 0,
                            extentOffset: _discountPercentController.text.length,
                          );
                        },
                        decoration: InputDecoration(
                          hintText: '0.00',
                          hintStyle: TextStyle(color: AppTheme.textMuted, fontSize: 12),
                          filled: true,
                          fillColor: _isPercentFocused ? AppTheme.surfaceLight : AppTheme.surface,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(4),
                            borderSide: BorderSide(
                              color: _isPercentFocused ? AppTheme.primary : AppTheme.border,
                            ),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(4),
                            borderSide: const BorderSide(color: AppTheme.border),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(4),
                            borderSide: const BorderSide(color: AppTheme.primary, width: 2),
                          ),
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                    ),
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 4),
                      child: Text('%', style: TextStyle(color: AppTheme.textMuted)),
                    ),
                    // Amount field
                    SizedBox(
                      width: 80,
                      height: 32,
                      child: TextField(
                        controller: _discountAmountController,
                        style: const TextStyle(color: AppTheme.textPrimary, fontSize: 12),
                        textAlign: TextAlign.center,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        inputFormatters: [
                          FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,3}')),
                        ],
                        onChanged: _onAmountChanged,
                        onSubmitted: _onAmountSubmitted,
                        onTap: () {
                          _discountAmountController.selection = TextSelection(
                            baseOffset: 0,
                            extentOffset: _discountAmountController.text.length,
                          );
                        },
                        decoration: InputDecoration(
                          hintText: 'KD0.000',
                          hintStyle: TextStyle(color: AppTheme.textMuted, fontSize: 12),
                          filled: true,
                          fillColor: _isAmountFocused ? AppTheme.surfaceLight : AppTheme.surface,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(4),
                            borderSide: BorderSide(
                              color: _isAmountFocused ? AppTheme.primary : AppTheme.border,
                            ),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(4),
                            borderSide: const BorderSide(color: AppTheme.border),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(4),
                            borderSide: const BorderSide(color: AppTheme.primary, width: 2),
                          ),
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 16),

                // Hold & Recall buttons (controlled by pos_enable_hold_recall feature)
                if (widget.features['hold_recall_cart'] != false)
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: widget.cartItems.isEmpty ? null : widget.onHoldCart,
                        icon: const Icon(Icons.pause_circle, size: 16),
                        label: const Text('Hold Cart'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: widget.cartItems.isEmpty ? AppTheme.textMuted : AppTheme.warning,
                          side: BorderSide(
                            color: widget.cartItems.isEmpty ? AppTheme.border : AppTheme.warning,
                          ),
                          padding: const EdgeInsets.symmetric(vertical: 10),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: widget.onRecall,
                        icon: const Icon(Icons.play_circle, size: 16),
                        label: const Text('Recall'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppTheme.textSecondary,
                          side: const BorderSide(color: AppTheme.border),
                          padding: const EdgeInsets.symmetric(vertical: 10),
                        ),
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 16),

                // Receipt Discount Row
                if (receiptDiscountAmount > 0) ...[
                  _buildDetailRow('Discount Amount:', '- KD ${receiptDiscountAmount.toStringAsFixed(3)}'),
                  const SizedBox(height: 4),
                ],

                // Loyalty Points Redemption Row
                if (widget.rpDiscount > 0) ...[
                  _buildDetailRow('Loyalty Points (${widget.rpPointsRedeemed} pts):', '- KD ${widget.rpDiscount.toStringAsFixed(3)}'),
                  const SizedBox(height: 4),
                ],
                
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Amount After Discount',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    Text(
                      'KD ${widget.grandTotal.toStringAsFixed(3)}',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.priceColor,
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 12),

                // Checkout button
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: widget.cartItems.isEmpty ? null : widget.onPayment,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: widget.cartItems.isEmpty
                          ? AppTheme.surfaceLight
                          : AppTheme.primary,
                      foregroundColor: widget.cartItems.isEmpty
                          ? AppTheme.textMuted
                          : Colors.black,
                      disabledBackgroundColor: AppTheme.surfaceLight,
                    ),
                    child: const Text(
                      'Checkout',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          ),
        ],
      ),
    );
  }

  Widget _buildCustomerRow(IconData icon, String label, VoidCallback onTap, {bool isHighlighted = false}) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppTheme.radiusSm),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: isHighlighted ? AppTheme.primary.withValues(alpha: 0.08) : AppTheme.surface,
          borderRadius: BorderRadius.circular(AppTheme.radiusSm),
          border: Border.all(
            color: isHighlighted ? AppTheme.primary.withValues(alpha: 0.3) : AppTheme.border,
          ),
        ),
        child: Row(
          children: [
            Icon(
              icon,
              color: isHighlighted ? AppTheme.primary : AppTheme.textSecondary,
              size: 18,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  color: isHighlighted ? AppTheme.textPrimary : AppTheme.textSecondary,
                  fontSize: 13,
                  fontWeight: isHighlighted ? FontWeight.w600 : FontWeight.normal,
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            Icon(
              Icons.chevron_right,
              color: AppTheme.textMuted,
              size: 16,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSmallButton(IconData icon, String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: AppTheme.surface,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: AppTheme.border),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: AppTheme.textSecondary, size: 14),
            const SizedBox(width: 6),
            Text(
              label,
              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: AppTheme.textSecondary,
              fontSize: 13,
            ),
          ),
          Text(
            value,
            style: const TextStyle(
              color: AppTheme.textPrimary,
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }
}

/// Barcode input field with search/add functionality
class _BarcodeInput extends StatefulWidget {
  final Function(String) onSearch;

  const _BarcodeInput({required this.onSearch});

  @override
  State<_BarcodeInput> createState() => _BarcodeInputState();
}

class _BarcodeInputState extends State<_BarcodeInput> {
  final _controller = TextEditingController();

  void _submit() {
    final text = _controller.text.trim();
    if (text.isNotEmpty) {
      widget.onSearch(text);
      _controller.clear();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: _controller,
      style: const TextStyle(color: AppTheme.textPrimary),
      decoration: InputDecoration(
        hintText: 'Scan or Enter Barcode',
        prefixIcon: const Icon(Icons.qr_code_scanner, color: AppTheme.textMuted, size: 20),
        suffixIcon: IconButton(
          icon: const Icon(Icons.add_circle, color: AppTheme.primary, size: 22),
          onPressed: _submit,
        ),
        filled: true,
        fillColor: AppTheme.surface,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppTheme.radiusMd),
          borderSide: const BorderSide(color: AppTheme.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppTheme.radiusMd),
          borderSide: const BorderSide(color: AppTheme.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppTheme.radiusMd),
          borderSide: const BorderSide(color: AppTheme.primary, width: 2),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      ),
      onSubmitted: (_) => _submit(),
    );
  }
}

/// Individual cart item tile — tappable to edit
class _CartItemTile extends StatelessWidget {
  final CartItem item;
  final VoidCallback onRemove;
  final Function(double) onQuantityChanged;
  final VoidCallback onTap;

  const _CartItemTile({
    required this.item,
    required this.onRemove,
    required this.onQuantityChanged,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppTheme.radiusSm),
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: AppTheme.surface,
          borderRadius: BorderRadius.circular(AppTheme.radiusSm),
          border: Border.all(color: AppTheme.border),
        ),
        child: Row(
          children: [
            // Product info
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.productName,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppTheme.textPrimary,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'KD ${item.unitPrice.toStringAsFixed(3)}',
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppTheme.priceColor,
                    ),
                  ),
                  if (item.discountAmount > 0) ...[
                    const SizedBox(height: 2),
                    Text(
                      'Discount: KD ${item.discountAmount.toStringAsFixed(3)}',
                      style: const TextStyle(
                        fontSize: 10,
                        color: AppTheme.success,
                      ),
                    ),
                  ],
                ],
              ),
            ),

            // Quantity controls
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                _buildQtyButton(
                  icon: Icons.remove,
                  onTap: item.quantity > 1
                      ? () => onQuantityChanged(item.quantity - 1)
                      : null,
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: Text(
                    '${item.quantity.toInt()}',
                    style: const TextStyle(
                      color: AppTheme.textPrimary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                _buildQtyButton(
                  icon: Icons.add,
                  onTap: () => onQuantityChanged(item.quantity + 1),
                ),
              ],
            ),

            const SizedBox(width: 8),

            // Line total
            Text(
              'KD ${item.lineTotalIncTax.toStringAsFixed(3)}',
              style: const TextStyle(
                color: AppTheme.textPrimary,
                fontWeight: FontWeight.bold,
                fontSize: 13,
              ),
            ),

            const SizedBox(width: 4),

            // Remove button
            IconButton(
              icon: const Icon(Icons.close, size: 16, color: AppTheme.textMuted),
              onPressed: onRemove,
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildQtyButton({required IconData icon, VoidCallback? onTap}) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(4),
      child: Container(
        width: 28,
        height: 28,
        decoration: BoxDecoration(
          color: onTap != null ? AppTheme.surfaceLight : AppTheme.surface,
          borderRadius: BorderRadius.circular(4),
          border: Border.all(color: AppTheme.border),
        ),
        child: Icon(
          icon,
          size: 14,
          color: onTap != null ? AppTheme.textPrimary : AppTheme.textMuted,
        ),
      ),
    );
  }
}
