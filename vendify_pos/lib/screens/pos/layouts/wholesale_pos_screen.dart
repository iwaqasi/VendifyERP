import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/screens/pos/widgets/hold_recall_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/product.dart';
import 'package:vendify_pos/models/cart_item.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/screens/pos/widgets/product_card.dart';
import 'package:vendify_pos/screens/pos/widgets/inventory_dialog.dart';
import 'package:vendify_pos/screens/pos/widgets/cart_panel.dart';
import 'package:vendify_pos/screens/pos/payment_screen.dart';
import 'package:vendify_pos/screens/login/pin_screen.dart';
import 'package:vendify_pos/screens/pos/customer_selection_sheet.dart';

class WholesalePosScreen extends ConsumerStatefulWidget {
  final String businessType;
  final Map<String, dynamic> features;

  const WholesalePosScreen({
    super.key,
    required this.businessType,
    required this.features,
  });

  @override
  ConsumerState<WholesalePosScreen> createState() => _WholesalePosScreenState();
}

class _WholesalePosScreenState extends ConsumerState<WholesalePosScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _poNumberController = TextEditingController();
  final List<CartItem> _cartItems = [];

  List<Product> _products = [];
  bool _isLoading = true;
  String _searchQuery = '';
  String _customerName = 'Select Customer';
  int? _contactId;
  int _locationId = 1;
  String _userName = 'Admin';

  // Wholesale specific
  String _selectedPriceTier = 'retail'; // retail, wholesale, bulk
  String _poNumber = '';
  String _paymentTerms = 'net_30';
  double _creditLimit = 0;
  double _currentBalance = 0;

  // Receipt-level discount
  double _receiptDiscount = 0;
  String _receiptDiscountType = 'fixed';

  // Loyalty Points
  int _customerRewardPoints = 0;
  String _rpName = 'Reward Points';
  double _rpRedeemAmount = 0.0;
  double _rpDiscount = 0.0;

  // Credit Customer Info
  double _customerCreditLimit = 0;
  double _customerSellDue = 0;
  int _customerPayTermNumber = 0;
  String _customerPayTermType = 'days';

  late Map<String, dynamic> _features;

  double get _subtotal => _cartItems.fold(0, (sum, item) => sum + item.lineTotal);
  double get _totalTax => _cartItems.fold(0, (sum, item) => sum + item.lineTax);
  double get _receiptDiscountAmount => _receiptDiscountType == 'percentage'
      ? _subtotal * _receiptDiscount / 100
      : _receiptDiscount;
  double get _grandTotal => (_subtotal + _totalTax - _receiptDiscountAmount - _rpDiscount).clamp(0, double.infinity);
  int get _distinctItemCount => _cartItems.length;
  int get _totalQuantity => _cartItems.fold(0, (sum, item) => sum + item.quantity.toInt());
  int get _totalItems => _cartItems.fold(0, (sum, item) => sum + item.quantity.toInt());

  @override
  void initState() {
    super.initState();
    _features = widget.features;
    _loadData();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _poNumberController.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    try {
      final prefs = await SharedPreferences.getInstance();
      _locationId = prefs.getInt('location_id') ?? 1;
      _userName = prefs.getString('user_name') ?? 'Admin';
      
      // If no saved location, fetch from API
      if (_locationId == 1 && !prefs.containsKey('location_id')) {
        try {
          final locations = await _posService.getLocations();
          if (locations.isNotEmpty) {
            _locationId = locations.first['id'];
            await prefs.setInt('location_id', _locationId);
          }
        } catch (_) {
          // Intentional fallback
        }
      }
      
      final results = await Future.wait([
        _posService.getProducts(locationId: _locationId),
        _posService.getCategories(),
        _posService.getPosSettings(),
      ]);
      final settings = results[2] as Map<String, dynamic>;
      
      setState(() {
        _products = results[0] as List<Product>;
        _features = settings['features'] ?? _features;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  void _addToCart(Product product) {
    final existingIndex = _cartItems.indexWhere(
      (i) => i.variationId == product.id,
    );

    // Calculate price based on tier
    double price = product.sellPriceIncTax;
    if (_selectedPriceTier == 'wholesale') {
      price = product.sellPriceIncTax * 0.85; // 15% discount
    } else if (_selectedPriceTier == 'bulk') {
      price = product.sellPriceIncTax * 0.75; // 25% discount
    }

    setState(() {
      if (existingIndex >= 0) {
        final existing = _cartItems[existingIndex];
        _cartItems[existingIndex] = existing.copyWith(
          quantity: existing.quantity + 1,
          unitPrice: price,
        );
      } else {
        _cartItems.add(CartItem(
          productId: product.id,
          variationId: product.variationId ?? product.id,
          productName: product.name,
          sku: product.sku,
          image: product.image,
          unitPrice: price,
          quantity: 1,
          taxRate: product.taxRate,
          isFlexiblePrice: product.isFlexiblePrice,
          enableStock: product.enableStock,
          qtyAvailable: product.qtyAvailable,
        ));
      }
    });
  }

  void _removeFromCart(int index) {
    setState(() => _cartItems.removeAt(index));
  }

  void _updateQuantity(int index, double qty) {
    setState(() {
      if (qty <= 0) {
        _cartItems.removeAt(index);
      } else {
        _cartItems[index] = _cartItems[index].copyWith(quantity: qty);
      }
    });
  }

  void _clearCart() {
    setState(() {
      _cartItems.clear();
      _receiptDiscount = 0;
      _receiptDiscountType = 'fixed';
      _customerName = 'Select Customer';
      _contactId = null;
      _poNumber = '';
      _poNumberController.clear();
      _customerRewardPoints = 0;
      _rpRedeemAmount = 0.0;
      _rpDiscount = 0.0;
      _customerCreditLimit = 0;
      _customerSellDue = 0;
      _customerPayTermNumber = 0;
      _customerPayTermType = 'days';
    });
  }

  Future<void> _holdCart() async {
    await HoldRecallService.holdCart(
      context: context,
      cartItems: _cartItems,
      customerName: _customerName,
      contactId: _contactId,
      receiptDiscount: _receiptDiscount,
      receiptDiscountType: _receiptDiscountType,
      subtotal: _subtotal,
    );
    _clearCart();
  }

  Future<void> _recallCart() async {
    final selectedCart = await HoldRecallService.showRecallDialog(context);
    if (selectedCart != null) {
      final items = HoldRecallService.restoreCartItems(selectedCart);
      setState(() {
        _cartItems.clear();
        _cartItems.addAll(items);
        _customerName = selectedCart['customerName'] ?? 'Select Customer';
        _contactId = selectedCart['contactId'];
        _receiptDiscount = (selectedCart['receiptDiscount'] as num?)?.toDouble() ?? 0;
        _receiptDiscountType = selectedCart['receiptDiscountType'] ?? 'fixed';
      });
      HoldRecallService.removeHeldCart(selectedCart['id']);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Cart recalled (${items.length} items)'), backgroundColor: AppTheme.success, duration: const Duration(seconds: 2)),
        );
      }
    }
  }

  void _onReceiptDiscountChanged(double value, String type) {
    setState(() {
      _receiptDiscount = value;
      _receiptDiscountType = type;
    });
  }

  void _setPriceTier(String tier) {
    setState(() {
      _selectedPriceTier = tier;
      // Update prices in cart
      for (int i = 0; i < _cartItems.length; i++) {
        final item = _cartItems[i];
        // Note: This is simplified - in real app, need original price
        _updateQuantity(i, item.quantity); // Trigger rebuild
      }
    });
  }

  void _openPayment() {
    if (_cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cart is empty')),
      );
      return;
    }
    
    // Check credit limit for credit sales
    if (_paymentTerms != 'cash' && _currentBalance + _grandTotal > _creditLimit) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Credit limit exceeded! Limit: KD ${_creditLimit.toStringAsFixed(3)}'),
          backgroundColor: AppTheme.error,
        ),
      );
      return;
    }
    
    Navigator.push(context,
        MaterialPageRoute(builder: (_) => PaymentScreen(
          cartItems: _cartItems,
          subtotal: _subtotal,
          tax: _totalTax,
          discount: _receiptDiscountAmount,
          grandTotal: _grandTotal,
          customerName: _customerName,
          contactId: _contactId,
          locationId: _locationId,
          rpDiscount: _rpDiscount,
          rpPointsRedeemed: _rpDiscount > 0 ? _customerRewardPoints : 0,
        )));
  }

  void _loadCustomerRewardPoints(int? contactId) async {
    if (contactId == null) {
      setState(() { _customerRewardPoints = 0; _rpRedeemAmount = 0.0; _rpDiscount = 0.0; _customerCreditLimit = 0; _customerSellDue = 0; _customerPayTermNumber = 0; _customerPayTermType = 'days'; });
      return;
    }
    try {
      final rpData = await _posService.getCustomerRewardPoints(contactId);
      if (rpData['enabled'] == true) {
        setState(() {
          _rpName = rpData['rp_name'] ?? 'Reward Points';
          _customerRewardPoints = rpData['available_points'] ?? 0;
          _rpRedeemAmount = (num.tryParse(rpData['equivalent_amount'].toString()) ?? 0).toDouble();
          _rpDiscount = 0.0;
        });
      } else {
        setState(() { _customerRewardPoints = 0; _rpRedeemAmount = 0.0; _rpDiscount = 0.0; });
      }
      try {
        final details = await _posService.getCustomerDetails(contactId);
        setState(() {
          _customerCreditLimit = (num.tryParse(details['credit_limit'].toString()) ?? 0).toDouble();
          _customerSellDue = (num.tryParse(details['sell_due'].toString()) ?? 0).toDouble();
          _customerPayTermNumber = int.tryParse(details['pay_term_number'].toString()) ?? 0;
          _customerPayTermType = details['pay_term_type'] ?? 'days';
        });
      } catch (_) {
        // Intentional fallback
      }
    } catch (_) {
      setState(() { _customerRewardPoints = 0; _rpRedeemAmount = 0.0; _rpDiscount = 0.0; });
    }
  }

  void _onRedeemPoints(double amount) {
    setState(() { _rpDiscount = amount; });
    if (amount > 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('KD ${amount.toStringAsFixed(3)} loyalty discount applied!'), backgroundColor: Colors.green),
      );
    }
  }

  void _openCustomerSelection() {
    showCustomerSelectionSheet(
      context,
      onSelected: (result) {
        setState(() {
          _customerName = result.customerName;
          _contactId = result.contactId;
          _rpDiscount = 0.0;
          // Mock credit info
          _creditLimit = 5000.0;
          _currentBalance = 1250.0;
        });
        _loadCustomerRewardPoints(result.contactId);
      },
    );
  }

  void _logout() {
    Navigator.pushReplacement(
        context, MaterialPageRoute(builder: (_) => const PinScreen()));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: Column(
        children: [
          _buildTopBar(),
          Expanded(
            child: Row(
              children: [
                // Left: Order Info Panel
                _buildOrderInfoPanel(),
                
                // Center: Products
                Expanded(child: _buildProductGrid()),
                
                // Right: Cart
                SizedBox(
                  width: 400,
                  child: CartPanel(
                    cartItems: _cartItems,
                    customerName: _customerName,
                    itemCount: _distinctItemCount,
                    totalQuantity: _totalQuantity,
                    subtotal: _subtotal,
                    tax: _totalTax,
                    grandTotal: _grandTotal,
                    receiptDiscount: _receiptDiscount,
                    receiptDiscountType: _receiptDiscountType,
                    onRemove: _removeFromCart,
                    onUpdateQuantity: _updateQuantity,
                    onUpdateItem: (i, item) => setState(() => _cartItems[i] = item),
                    onClearCart: _clearCart,
                    onPayment: _openPayment,
                    onCustomerTap: _openCustomerSelection,
                    onAddNewCustomer: () {},
                    onHoldCart: _holdCart,
                    onRecall: _recallCart,
                    onBarcodeSearch: (q) {},
                    onReceiptDiscountChanged: _onReceiptDiscountChanged,
                    features: _features,
                    customerRewardPoints: _customerRewardPoints,
                    rpName: _rpName,
                    rpRedeemAmount: _rpRedeemAmount,
                    onRedeemPoints: _onRedeemPoints,
                    rpDiscount: _rpDiscount,
                    rpPointsRedeemed: _rpDiscount > 0 ? _customerRewardPoints : 0,
                    customerCreditLimit: _customerCreditLimit,
                    customerSellDue: _customerSellDue,
                    customerPayTermNumber: _customerPayTermNumber,
                    customerPayTermType: _customerPayTermType,
                  ),
                ),
              ],
            ),
          ),
          _buildStatusBar(),
        ],
      ),
    );
  }

  Widget _buildTopBar() {
    return Container(
      height: 56,
      padding: const EdgeInsets.symmetric(horizontal: AppTheme.md),
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(bottom: BorderSide(color: AppTheme.border)),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Color(0xFF795548).withValues(alpha: 0.1),
            ),
            child: const Icon(Icons.warehouse, color: Color(0xFF795548), size: 20),
          ),
          const SizedBox(width: 12),
          const Text(
            'VendifyERP',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: AppTheme.textPrimary,
            ),
          ),
          const SizedBox(width: 12),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: Color(0xFF795548).withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(4),
            ),
            child: const Text(
              'WHOLESALE',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: Color(0xFF795548),
              ),
            ),
          ),
          const Spacer(),
          
          // Price tier selector
          _buildPriceTierButton('retail', 'Retail'),
          const SizedBox(width: 4),
          _buildPriceTierButton('wholesale', 'Wholesale'),
          const SizedBox(width: 4),
          _buildPriceTierButton('bulk', 'Bulk'),
          
          const SizedBox(width: 16),
          
          IconButton(
            icon: const Icon(Icons.inventory_2_outlined, color: AppTheme.textSecondary, size: 22),
            onPressed: _openInventory,
            tooltip: 'Inventory',
          ),
          IconButton(
            icon: const Icon(Icons.logout, color: AppTheme.textSecondary, size: 22),
            onPressed: _logout,
            tooltip: 'Logout',
          ),
        ],
      ),
    );
  }

  void _openInventory() {
    InventoryDialog.show(context);
  }

  Widget _buildPriceTierButton(String tier, String label) {
    final isSelected = _selectedPriceTier == tier;
    final colors = {
      'retail': AppTheme.textSecondary,
      'wholesale': AppTheme.primary,
      'bulk': Color(0xFF795548),
    };
    
    return Container(
      decoration: BoxDecoration(
        color: isSelected ? colors[tier] : AppTheme.surfaceLight,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(
          color: isSelected ? colors[tier]! : AppTheme.border,
        ),
      ),
      child: InkWell(
        onTap: () => _setPriceTier(tier),
        borderRadius: BorderRadius.circular(6),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Text(
            label,
            style: TextStyle(
              color: isSelected ? Colors.white : AppTheme.textSecondary,
              fontWeight: FontWeight.w600,
              fontSize: 12,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildOrderInfoPanel() {
    return Container(
      width: 280,
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(right: BorderSide(color: AppTheme.border)),
      ),
      child: Column(
        children: [
          // Customer info
          Container(
            padding: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppTheme.border)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Customer',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 12,
                    color: AppTheme.textSecondary,
                  ),
                ),
                const SizedBox(height: 8),
                InkWell(
                  onTap: _openCustomerSelection,
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      border: Border.all(color: AppTheme.border),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.person,
                          color: _contactId != null ? AppTheme.primary : AppTheme.textMuted,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _customerName,
                                style: TextStyle(
                                  fontWeight: FontWeight.w600,
                                  color: _contactId != null ? AppTheme.textPrimary : AppTheme.textMuted,
                                ),
                              ),
                              if (_creditLimit > 0)
                                Text(
                                  'Credit: KD ${_currentBalance.toStringAsFixed(3)} / KD ${_creditLimit.toStringAsFixed(3)}',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: AppTheme.textSecondary,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        const Icon(Icons.chevron_right),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          
          // PO Number
          Container(
            padding: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppTheme.border)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Purchase Order',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 12,
                    color: AppTheme.textSecondary,
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _poNumberController,
                  decoration: InputDecoration(
                    hintText: 'PO Number',
                    prefixIcon: const Icon(Icons.receipt, size: 18),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    isDense: true,
                  ),
                  onChanged: (v) => setState(() => _poNumber = v),
                ),
              ],
            ),
          ),
          
          // Payment terms
          Container(
            padding: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppTheme.border)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Payment Terms',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 12,
                    color: AppTheme.textSecondary,
                  ),
                ),
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  initialValue: _paymentTerms,
                  isDense: true,
                  decoration: InputDecoration(
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'cash', child: Text('Cash')),
                    DropdownMenuItem(value: 'net_7', child: Text('Net 7 Days')),
                    DropdownMenuItem(value: 'net_15', child: Text('Net 15 Days')),
                    DropdownMenuItem(value: 'net_30', child: Text('Net 30 Days')),
                    DropdownMenuItem(value: 'net_60', child: Text('Net 60 Days')),
                    DropdownMenuItem(value: 'credit', child: Text('Credit Sale')),
                  ],
                  onChanged: (v) => setState(() => _paymentTerms = v ?? 'net_30'),
                ),
              ],
            ),
          ),
          
          // Order summary
          Container(
            padding: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppTheme.border)),
            ),
            child: Column(
              children: [
                _buildSummaryRow('Total Items', '$_totalItems'),
                _buildSummaryRow('Distinct Products', '$_distinctItemCount'),
                _buildSummaryRow('Subtotal', 'KD ${_subtotal.toStringAsFixed(3)}'),
                if (_receiptDiscountAmount > 0)
                  _buildSummaryRow('Discount', '-KD ${_receiptDiscountAmount.toStringAsFixed(3)}', color: AppTheme.success),
                _buildSummaryRow('Tax', 'KD ${_totalTax.toStringAsFixed(3)}'),
                const Divider(),
                _buildSummaryRow('Grand Total', 'KD ${_grandTotal.toStringAsFixed(3)}', bold: true),
              ],
            ),
          ),
          
          // Credit info
          if (_creditLimit > 0)
            Container(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  LinearProgressIndicator(
                    value: _currentBalance / _creditLimit,
                    backgroundColor: AppTheme.border,
                    valueColor: AlwaysStoppedAnimation(
                      _currentBalance / _creditLimit > 0.8 ? AppTheme.error : AppTheme.primary,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Credit Used: ${(_currentBalance / _creditLimit * 100).toStringAsFixed(0)}%',
                    style: TextStyle(
                      fontSize: 11,
                      color: _currentBalance / _creditLimit > 0.8 ? AppTheme.error : AppTheme.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildSummaryRow(String label, String value, {bool bold = false, Color? color}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: bold ? 14 : 12,
              fontWeight: bold ? FontWeight.bold : FontWeight.normal,
              color: AppTheme.textSecondary,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: bold ? 16 : 13,
              fontWeight: bold ? FontWeight.bold : FontWeight.w600,
              color: color ?? (bold ? AppTheme.textPrimary : AppTheme.textPrimary),
            ),
          ),
        ],
      ),
    );
  }

  List<Product> get _filteredProducts {
    if (_searchQuery.isEmpty) return _products;
    final q = _searchQuery.toLowerCase();
    return _products.where((p) =>
      p.name.toLowerCase().contains(q) ||
      (p.sku?.toLowerCase().contains(q) ?? false) ||
      (p.barcode?.toLowerCase().contains(q) ?? false)
    ).toList();
  }

  Widget _buildProductGrid() {
    final displayProducts = _filteredProducts;
    return Column(
      children: [
        Container(
          padding: const EdgeInsets.all(AppTheme.md),
          child: TextField(
            controller: _searchController,
            style: const TextStyle(color: AppTheme.textPrimary),
            decoration: InputDecoration(
              hintText: 'Search products by name, SKU, or barcode...',
              prefixIcon: const Icon(Icons.search, color: AppTheme.textMuted),
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
                borderSide: const BorderSide(color: Color(0xFF795548), width: 2),
              ),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            ),
            onChanged: (value) => setState(() => _searchQuery = value),
          ),
        ),
        Expanded(
          child: _isLoading
              ? const Center(child: CircularProgressIndicator(color: Color(0xFF795548)))
              : displayProducts.isEmpty
                  ? const Center(
                      child: Text(
                        'No products found',
                        style: TextStyle(color: AppTheme.textSecondary, fontSize: 16),
                      ),
                    )
                  : GridView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: AppTheme.md),
                      gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                        maxCrossAxisExtent: 200,
                        childAspectRatio: 0.9,
                        crossAxisSpacing: 12,
                        mainAxisSpacing: 12,
                      ),
                      itemCount: displayProducts.length,
                      itemBuilder: (context, index) {
                        return ProductCard(
                          product: displayProducts[index],
                          onTap: () => _addToCart(displayProducts[index]),
                        );
                      },
                    ),
        ),
      ],
    );
  }

  Widget _buildStatusBar() {
    return Container(
      height: 32,
      padding: const EdgeInsets.symmetric(horizontal: AppTheme.md),
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(top: BorderSide(color: AppTheme.border)),
      ),
      child: Row(
        children: [
          Text(
            'User: $_userName',
            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
          ),
          const Spacer(),
          Text(
            'Price Tier: ${_selectedPriceTier.toUpperCase()}',
            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
          ),
          const SizedBox(width: 16),
          if (_poNumber.isNotEmpty)
            Text(
              'PO: $_poNumber',
              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
            ),
          const Spacer(),
          Text(
            'Payment: ${_paymentTerms.replaceAll('_', ' ').toUpperCase()}',
            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
          ),
          const Spacer(),
          Row(
            children: [
              Container(
                width: 8,
                height: 8,
                decoration: const BoxDecoration(
                  color: AppTheme.success,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 6),
              const Text(
                'Online',
                style: TextStyle(color: AppTheme.success, fontSize: 12),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
