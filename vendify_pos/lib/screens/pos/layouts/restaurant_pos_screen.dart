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
import 'package:vendify_pos/screens/pos/daily_register_screen.dart';
import 'package:vendify_pos/screens/pos/customer_selection_sheet.dart';

class RestaurantPosScreen extends ConsumerStatefulWidget {
  final String businessType;
  final Map<String, dynamic> features;

  const RestaurantPosScreen({
    super.key,
    required this.businessType,
    required this.features,
  });

  @override
  ConsumerState<RestaurantPosScreen> createState() => _RestaurantPosScreenState();
}

class _RestaurantPosScreenState extends ConsumerState<RestaurantPosScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  final TextEditingController _searchController = TextEditingController();
  final List<CartItem> _cartItems = [];

  List<Product> _products = [];
  bool _isLoading = true;
  String _searchQuery = '';
  String _customerName = 'Dine-in';
  int? _contactId;
  int _locationId = 1;
  String _userName = 'Admin';

  // Order type
  String _orderType = 'dine_in'; // dine_in, takeaway, delivery

  // Table management
  String? _selectedTable;
  int _guestCount = 2;
  List<Map<String, dynamic>> _tables = [];
  
  // Active orders
  List<Map<String, dynamic>> _activeOrders = [];

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

  @override
  void initState() {
    super.initState();
    _features = widget.features;
    _loadData();
  }

  @override
  void dispose() {
    _searchController.dispose();
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
      
      _loadTables();
      _loadActiveOrders();
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _loadTables() async {
    try {
      final tablesData = await _posService.getRestaurantTables();
      setState(() {
        _tables = tablesData.map((t) => ({
          'id': t['id'],
          'name': t['name'] ?? 'Table ${t['id']}',
          'capacity': t['capacity'] ?? 4,
          'status': t['status'] ?? 'available',
          'currentOrder': null,
        })).toList();
      });
    } catch (e) {
      setState(() => _tables = []);
    }
  }

  Future<void> _loadActiveOrders() async {
    try {
      final ordersData = await _posService.getRestaurantOrders();
      final List<Map<String, dynamic>> mappedOrders = [];
      for (final o in ordersData) {
        final items = o['items'] as List? ?? [];
        mappedOrders.add({
          'id': o['order_number'] ?? '',
          'table': o['table_id'] != null ? 'Table ${o['table_id']}' : null,
          'type': o['order_type'] ?? 'dine_in',
          'items': items.length,
          'total': (o['grand_total'] ?? 0).toDouble(),
          'status': o['status'] ?? 'pending',
          'time': o['created_at'] != null
              ? (o['created_at'] as String).substring(11, 16)
              : '',
        });
      }
      setState(() => _activeOrders = mappedOrders);
    } catch (e) {
      setState(() => _activeOrders = []);
    }
  }

  void _addToCart(Product product) {
    final existingIndex = _cartItems.indexWhere(
      (i) => i.variationId == product.id,
    );

    setState(() {
      if (existingIndex >= 0) {
        final existing = _cartItems[existingIndex];
        _cartItems[existingIndex] = existing.copyWith(
          quantity: existing.quantity + 1,
        );
      } else {
        _cartItems.add(CartItem(
          productId: product.id,
          variationId: product.variationId ?? product.id,
          productName: product.name,
          sku: product.sku,
          image: product.image,
          unitPrice: product.sellPriceIncTax,
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
      _customerName = _orderType == 'dine_in' ? 'Dine-in' : (_orderType == 'takeaway' ? 'Takeaway' : 'Delivery');
      _contactId = null;
      _selectedTable = null;
      _guestCount = 2;
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
        _customerName = selectedCart['customerName'] ?? 'Dine-in';
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

  void _selectTable(Map<String, dynamic> table) {
    if (table['status'] != 'available') {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${table['name']} is ${table['status']}'),
          backgroundColor: AppTheme.warning,
        ),
      );
      return;
    }
    
    setState(() {
      _selectedTable = table['name'];
      _guestCount = table['capacity'] ~/ 2; // Default to half capacity
    });
  }

  void _setOrderType(String type) {
    setState(() {
      _orderType = type;
      _customerName = type == 'dine_in' ? 'Dine-in' : (type == 'takeaway' ? 'Takeaway' : 'Delivery');
      if (type != 'dine_in') {
        _selectedTable = null;
      }
    });
  }

  void _sendToKOT() {
    if (_cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cart is empty')),
      );
      return;
    }

    // TODO: Send KOT to kitchen display
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('KOT sent to kitchen! Items: ${_cartItems.length}'),
        backgroundColor: AppTheme.success,
      ),
    );
  }

  void _loadCustomerRewardPoints(int? contactId) async {
    if (contactId == null) {
      setState(() { _customerRewardPoints = 0; _rpRedeemAmount = 0.0; _rpDiscount = 0.0; });
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
        });
        _loadCustomerRewardPoints(result.contactId);
      },
    );
  }

  void _openPayment() {
    if (_cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cart is empty')),
      );
      return;
    }
    _proceedToPayment();
  }

  void _proceedToPayment() {
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

  void _openInventory() {
    InventoryDialog.show(context);
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
                // Left: Tables & Active Orders
                _buildTablePanel(),
                
                // Center: Menu
                Expanded(child: _buildProductGrid()),
                
                // Right: Cart
                SizedBox(
                  width: 380,
                  child: CartPanel(
                    cartItems: _cartItems,
                    customerName: _selectedTable != null ? '$_selectedTable • $_guestCount guests' : _customerName,
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
                    onAddNewCustomer: _openCustomerSelection,
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
              color: Color(0xFF4CAF50).withValues(alpha: 0.1),
            ),
            child: const Icon(Icons.restaurant, color: Color(0xFF4CAF50), size: 20),
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
              color: Color(0xFF4CAF50).withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(4),
            ),
            child: const Text(
              'RESTAURANT',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: Color(0xFF4CAF50),
              ),
            ),
          ),
          const Spacer(),
          
          // Order type selector
          _buildOrderTypeButton('dine_in', Icons.restaurant_menu, 'Dine-in'),
          const SizedBox(width: 8),
          _buildOrderTypeButton('takeaway', Icons.takeout_dining, 'Takeaway'),
          const SizedBox(width: 8),
          _buildOrderTypeButton('delivery', Icons.delivery_dining, 'Delivery'),
          
          const SizedBox(width: 16),
          
          // KOT button
          ElevatedButton.icon(
            onPressed: _sendToKOT,
            icon: const Icon(Icons.kitchen, size: 18),
            label: const Text('Send KOT'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.warning,
              foregroundColor: Colors.white,
            ),
          ),
          const SizedBox(width: 16),
          
          IconButton(
            icon: const Icon(Icons.account_balance_wallet, color: AppTheme.textSecondary, size: 22),
            onPressed: () {
              Navigator.push(context, MaterialPageRoute(builder: (_) => const DailyRegisterScreen()));
            },
            tooltip: 'Daily Register',
          ),
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

  Widget _buildOrderTypeButton(String type, IconData icon, String label) {
    final isSelected = _orderType == type;
    return Container(
      decoration: BoxDecoration(
        color: isSelected ? Color(0xFF4CAF50) : AppTheme.surfaceLight,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: isSelected ? Color(0xFF4CAF50) : AppTheme.border,
        ),
      ),
      child: InkWell(
        onTap: () => _setOrderType(type),
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                icon,
                size: 18,
                color: isSelected ? Colors.white : AppTheme.textSecondary,
              ),
              const SizedBox(width: 6),
              Text(
                label,
                style: TextStyle(
                  color: isSelected ? Colors.white : AppTheme.textSecondary,
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildTablePanel() {
    final statusColors = {
      'available': AppTheme.success,
      'occupied': AppTheme.error,
      'reserved': AppTheme.warning,
    };

    return Container(
      width: 300,
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(right: BorderSide(color: AppTheme.border)),
      ),
      child: Column(
        children: [
          // Tables header
          Container(
            padding: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppTheme.border)),
            ),
            child: Row(
              children: [
                const Icon(Icons.table_restaurant, size: 18, color: Color(0xFF4CAF50)),
                const SizedBox(width: 8),
                const Text(
                  'Tables',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const Spacer(),
                // Legend
                ...statusColors.entries.map((e) => Padding(
                  padding: const EdgeInsets.only(left: 8),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: BoxDecoration(
                          color: e.value,
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        e.key.substring(0, 3).toUpperCase(),
                        style: TextStyle(fontSize: 9, color: AppTheme.textMuted),
                      ),
                    ],
                  ),
                )),
              ],
            ),
          ),
          
          // Tables grid
          Expanded(
            flex: 3,
            child: GridView.builder(
              padding: const EdgeInsets.all(8),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 4,
                childAspectRatio: 1,
                crossAxisSpacing: 6,
                mainAxisSpacing: 6,
              ),
              itemCount: _tables.length,
              itemBuilder: (context, index) {
                final table = _tables[index];
                final statusColor = statusColors[table['status']] ?? AppTheme.textMuted;
                final isSelected = _selectedTable == table['name'];
                
                return InkWell(
                  onTap: () => _selectTable(table),
                  child: Container(
                    decoration: BoxDecoration(
                      color: isSelected 
                          ? Color(0xFF4CAF50).withValues(alpha: 0.2)
                          : statusColor.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: isSelected ? Color(0xFF4CAF50) : statusColor,
                        width: isSelected ? 2 : 1,
                      ),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.table_restaurant,
                          size: 24,
                          color: statusColor,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          table['id'].toString(),
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: statusColor,
                            fontSize: 12,
                          ),
                        ),
                        Text(
                          '${table['capacity']} seats',
                          style: TextStyle(
                            fontSize: 9,
                            color: AppTheme.textMuted,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
          
          // Active orders
          Container(
            padding: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
              border: Border(top: BorderSide(color: AppTheme.border)),
            ),
            child: Row(
              children: [
                const Icon(Icons.receipt_long, size: 16, color: Color(0xFF4CAF50)),
                const SizedBox(width: 8),
                const Text(
                  'Active Orders',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 12,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: Color(0xFF4CAF50).withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    '${_activeOrders.length}',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF4CAF50),
                    ),
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            flex: 2,
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 8),
              itemCount: _activeOrders.length,
              itemBuilder: (context, index) {
                final order = _activeOrders[index];
                final statusColors = {
                  'preparing': AppTheme.warning,
                  'ready': AppTheme.success,
                  'served': AppTheme.info,
                };
                final statusColor = statusColors[order['status']] ?? AppTheme.textMuted;
                
                return Card(
                  margin: const EdgeInsets.symmetric(vertical: 3),
                  color: AppTheme.background,
                  child: ListTile(
                    dense: true,
                    title: Text(
                      order['id'],
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                    subtitle: Text(
                      '${order['table'] ?? order['type']} • ${order['items']} items',
                      style: const TextStyle(fontSize: 10),
                    ),
                    trailing: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: statusColor.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        order['status'].toUpperCase(),
                        style: TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.bold,
                          color: statusColor,
                        ),
                      ),
                    ),
                  ),
                );
              },
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
              hintText: 'Search food, drinks, desserts...',
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
                borderSide: const BorderSide(color: Color(0xFF4CAF50), width: 2),
              ),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            ),
            onChanged: (value) => setState(() => _searchQuery = value),
          ),
        ),
        Expanded(
          child: _isLoading
              ? const Center(child: CircularProgressIndicator(color: Color(0xFF4CAF50)))
              : displayProducts.isEmpty
                  ? const Center(
                      child: Text(
                        'No menu items found',
                        style: TextStyle(color: AppTheme.textSecondary, fontSize: 16),
                      ),
                    )
                  : GridView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: AppTheme.md),
                      gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                        maxCrossAxisExtent: 220,
                        childAspectRatio: 0.82,
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
    final occupied = _tables.where((t) => t['status'] == 'occupied').length;
    final available = _tables.where((t) => t['status'] == 'available').length;
    
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
            'Tables: $occupied occupied / $available available',
            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
          ),
          const Spacer(),
          Text(
            'Active Orders: ${_activeOrders.length}',
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
