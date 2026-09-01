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
import 'package:vendify_pos/screens/pos/daily_register_screen.dart';

class RepairPosScreen extends ConsumerStatefulWidget {
  final String businessType;
  final Map<String, dynamic> features;

  const RepairPosScreen({
    super.key,
    required this.businessType,
    required this.features,
  });

  @override
  ConsumerState<RepairPosScreen> createState() => _RepairPosScreenState();
}

class _RepairPosScreenState extends ConsumerState<RepairPosScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  final TextEditingController _searchController = TextEditingController();
  final List<CartItem> _cartItems = [];

  List<Product> _products = [];
  bool _isLoading = true;
  String _searchQuery = '';
  String _customerName = 'Walk-in Customer';
  int? _contactId;
  int _locationId = 1;
  String _userName = 'Admin';

  // Repair ticket
  List<Map<String, dynamic>> _activeRepairs = [];
  int? _selectedRepairId;

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
      
      _loadActiveRepairs();
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _loadActiveRepairs() async {
    try {
      final repairs = await _posService.getRepairTickets();
      setState(() {
        _activeRepairs = repairs.map((r) => {
          'id': r['ticket_number'] ?? '',
          'customerName': r['customer_name'] ?? '',
          'deviceType': r['device_type'] ?? '',
          'deviceBrand': r['device_brand'] ?? '',
          'issue': r['reported_issue'] ?? '',
          'status': r['status'] ?? 'received',
          'estimatedCost': (r['estimated_cost'] ?? 0).toDouble(),
          'receivedDate': r['received_date'] ?? '',
          'estimatedCompletion': r['estimated_completion'] ?? '',
          'technician': '',
        }).toList();
      });
    } catch (e) {
      setState(() => _activeRepairs = []);
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
      _customerName = 'Walk-in Customer';
      _contactId = null;
      _selectedRepairId = null;
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
        _customerName = selectedCart['customerName'] ?? 'Walk-in Customer';
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

  void _selectRepair(Map<String, dynamic> repair) {
    setState(() {
      _selectedRepairId = repair['id'];
      _customerName = repair['customerName'];
    });
    
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Selected repair: ${repair['id']}'),
        backgroundColor: AppTheme.primary,
      ),
    );
  }

  void _openNewRepairDialog() {
    showDialog(
      context: context,
      builder: (context) => _NewRepairDialog(
        onSaved: (repairData) {
          setState(() {
            _activeRepairs.add(repairData);
          });
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Repair ticket ${repairData['id']} created'),
              backgroundColor: AppTheme.success,
            ),
          );
        },
      ),
    );
  }

  void _openPayment() {
    if (_cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cart is empty')),
      );
      return;
    }
    if (_customerCreditLimit > 0 && (_customerSellDue + _grandTotal) > _customerCreditLimit) {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Credit Limit Exceeded'),
          content: Text('Due: KD ${_customerSellDue.toStringAsFixed(3)} + Now: KD ${_grandTotal.toStringAsFixed(3)}\nLimit: KD ${_customerCreditLimit.toStringAsFixed(3)}'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            ElevatedButton(onPressed: () { Navigator.pop(ctx); _proceedToPayment(); }, child: const Text('Proceed Anyway')),
          ],
        ),
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
        });
        _loadCustomerRewardPoints(result.contactId);
      },
    );
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
                // Left: Active Repairs Panel
                _buildRepairsPanel(),
                
                // Center: Products/Parts
                Expanded(child: _buildProductGrid()),
                
                // Right: Cart
                SizedBox(
                  width: 380,
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
              color: Color(0xFFFF9800).withValues(alpha: 0.1),
            ),
            child: const Icon(Icons.build, color: Color(0xFFFF9800), size: 20),
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
              color: Color(0xFFFF9800).withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(4),
            ),
            child: const Text(
              'REPAIR SHOP',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: Color(0xFFFF9800),
              ),
            ),
          ),
          const Spacer(),
          ElevatedButton.icon(
            onPressed: _openNewRepairDialog,
            icon: const Icon(Icons.add, size: 18),
            label: const Text('New Repair'),
            style: ElevatedButton.styleFrom(
              backgroundColor: Color(0xFFFF9800),
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
          ),          IconButton(
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




  Widget _buildRepairsPanel() {
    final statusColors = {
      'received': AppTheme.textMuted,
      'diagnosed': AppTheme.primary,
      'waiting_parts': AppTheme.warning,
      'in_repair': AppTheme.info,
      'completed': AppTheme.success,
      'ready_pickup': Color(0xFF9C27B0),
      'delivered': AppTheme.textMuted,
    };

    return Container(
      width: 320,
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(right: BorderSide(color: AppTheme.border)),
      ),
      child: Column(
        children: [
          // Header
          Container(
            padding: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppTheme.border)),
            ),
            child: Row(
              children: [
                const Icon(Icons.build_circle, size: 18, color: Color(0xFFFF9800)),
                const SizedBox(width: 8),
                const Text(
                  'Active Repairs',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: Color(0xFFFF9800).withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    '${_activeRepairs.length}',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFFFF9800),
                    ),
                  ),
                ),
              ],
            ),
          ),
          
          // Repairs list
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(8),
              itemCount: _activeRepairs.length,
              itemBuilder: (context, index) {
                final repair = _activeRepairs[index];
                final status = repair['status'] ?? 'received';
                final statusColor = statusColors[status] ?? AppTheme.textMuted;
                
                return Card(
                  margin: const EdgeInsets.symmetric(vertical: 4),
                  color: _selectedRepairId == repair['id'] 
                      ? Color(0xFFFF9800).withValues(alpha: 0.05)
                      : AppTheme.background,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                    side: BorderSide(
                      color: _selectedRepairId == repair['id'] 
                          ? Color(0xFFFF9800)
                          : AppTheme.border,
                      width: _selectedRepairId == repair['id'] ? 2 : 1,
                    ),
                  ),
                  child: InkWell(
                    onTap: () => _selectRepair(repair),
                    borderRadius: BorderRadius.circular(8),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Text(
                                repair['id'],
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  color: AppTheme.textPrimary,
                                  fontSize: 13,
                                ),
                              ),
                              const Spacer(),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: statusColor.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(
                                  status.toUpperCase().replaceAll('_', ' '),
                                  style: TextStyle(
                                    fontSize: 9,
                                    fontWeight: FontWeight.bold,
                                    color: statusColor,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            repair['customerName'],
                            style: const TextStyle(
                              fontWeight: FontWeight.w600,
                              fontSize: 13,
                              color: AppTheme.textPrimary,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Row(
                            children: [
                              Icon(Icons.smartphone, size: 12, color: AppTheme.textMuted),
                              const SizedBox(width: 4),
                              Expanded(
                                child: Text(
                                  '${repair['deviceBrand']} ${repair['deviceType']}',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: AppTheme.textSecondary,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            repair['issue'],
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppTheme.textMuted,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 8),
                          Row(
                            children: [
                              Icon(Icons.person, size: 12, color: AppTheme.textMuted),
                              const SizedBox(width: 4),
                              Text(
                                repair['technician'],
                                style: const TextStyle(
                                  fontSize: 11,
                                  color: AppTheme.textMuted,
                                ),
                              ),
                              const Spacer(),
                              Text(
                                'KD ${repair['estimatedCost']}',
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                  color: Color(0xFFFF9800),
                                ),
                              ),
                            ],
                          ),
                        ],
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
              hintText: 'Search parts, repair services, accessories...',
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
                borderSide: const BorderSide(color: Color(0xFFFF9800), width: 2),
              ),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            ),
            onChanged: (value) => setState(() => _searchQuery = value),
          ),
        ),
        Expanded(
          child: _isLoading
              ? const Center(child: CircularProgressIndicator(color: Color(0xFFFF9800)))
              : displayProducts.isEmpty
                  ? const Center(
                      child: Text(
                        'No parts found',
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
    final waitingParts = _activeRepairs.where((r) => r['status'] == 'waiting_parts').length;
    final inRepair = _activeRepairs.where((r) => r['status'] == 'in_repair').length;
    final readyPickup = _activeRepairs.where((r) => r['status'] == 'ready_pickup').length;
    
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
          if (waitingParts > 0)
            Text(
              '⚠ Waiting Parts: $waitingParts',
              style: const TextStyle(color: AppTheme.warning, fontSize: 12),
            ),
          const SizedBox(width: 16),
          if (inRepair > 0)
            Text(
              '🔧 In Repair: $inRepair',
              style: const TextStyle(color: AppTheme.info, fontSize: 12),
            ),
          const SizedBox(width: 16),
          if (readyPickup > 0)
            Text(
              '✅ Ready: $readyPickup',
              style: const TextStyle(color: AppTheme.success, fontSize: 12),
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

// ==================== NEW REPAIR DIALOG ====================
class _NewRepairDialog extends ConsumerStatefulWidget {
  final Function(Map<String, dynamic>) onSaved;

  const _NewRepairDialog({required this.onSaved});

  @override
  ConsumerState<_NewRepairDialog> createState() => _NewRepairDialogState();
}

class _NewRepairDialogState extends ConsumerState<_NewRepairDialog> {
  final _formKey = GlobalKey<FormState>();
  final _customerNameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _deviceTypeController = TextEditingController();
  final _deviceBrandController = TextEditingController();
  final _deviceModelController = TextEditingController();
  final _serialController = TextEditingController();
  final _issueController = TextEditingController();
  final _estimatedCostController = TextEditingController();
  
  String _selectedPriority = 'normal';

  @override
  void dispose() {
    _customerNameController.dispose();
    _phoneController.dispose();
    _deviceTypeController.dispose();
    _deviceBrandController.dispose();
    _deviceModelController.dispose();
    _serialController.dispose();
    _issueController.dispose();
    _estimatedCostController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Container(
        width: 600,
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.85,
        ),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Header
              Container(
                padding: const EdgeInsets.all(16),
                decoration: const BoxDecoration(
                  color: Color(0xFFFF9800),
                  borderRadius: BorderRadius.only(
                    topLeft: Radius.circular(12),
                    topRight: Radius.circular(12),
                  ),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.build_circle, color: Colors.white, size: 24),
                    const SizedBox(width: 12),
                    const Text(
                      'New Repair Ticket',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                    const Spacer(),
                    IconButton(
                      icon: const Icon(Icons.close, color: Colors.white),
                      onPressed: () => Navigator.pop(context),
                    ),
                  ],
                ),
              ),
              
              // Form
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Customer Info
                      const Text(
                        'Customer Information',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: _customerNameController,
                              decoration: const InputDecoration(
                                labelText: 'Customer Name *',
                                border: OutlineInputBorder(),
                              ),
                              validator: (v) => v?.isEmpty ?? true ? 'Required' : null,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextFormField(
                              controller: _phoneController,
                              decoration: const InputDecoration(
                                labelText: 'Phone Number',
                                border: OutlineInputBorder(),
                              ),
                            ),
                          ),
                        ],
                      ),
                      
                      const SizedBox(height: 20),
                      
                      // Device Info
                      const Text(
                        'Device Information',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: _deviceTypeController,
                              decoration: const InputDecoration(
                                labelText: 'Device Type *',
                                hintText: 'e.g., iPhone, Laptop, Tablet',
                                border: OutlineInputBorder(),
                              ),
                              validator: (v) => v?.isEmpty ?? true ? 'Required' : null,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextFormField(
                              controller: _deviceBrandController,
                              decoration: const InputDecoration(
                                labelText: 'Brand *',
                                hintText: 'e.g., Apple, Samsung',
                                border: OutlineInputBorder(),
                              ),
                              validator: (v) => v?.isEmpty ?? true ? 'Required' : null,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: _deviceModelController,
                              decoration: const InputDecoration(
                                labelText: 'Model',
                                hintText: 'e.g., iPhone 15 Pro Max',
                                border: OutlineInputBorder(),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextFormField(
                              controller: _serialController,
                              decoration: const InputDecoration(
                                labelText: 'Serial Number',
                                border: OutlineInputBorder(),
                              ),
                            ),
                          ),
                        ],
                      ),
                      
                      const SizedBox(height: 20),
                      
                      // Issue & Cost
                      TextFormField(
                        controller: _issueController,
                        maxLines: 3,
                        decoration: const InputDecoration(
                          labelText: 'Reported Issue *',
                          hintText: 'Describe the problem...',
                          border: OutlineInputBorder(),
                        ),
                        validator: (v) => v?.isEmpty ?? true ? 'Required' : null,
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: _estimatedCostController,
                              keyboardType: TextInputType.number,
                              decoration: const InputDecoration(
                                labelText: 'Estimated Cost (KD)',
                                border: OutlineInputBorder(),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: DropdownButtonFormField<String>(
                              initialValue: _selectedPriority,
                              decoration: const InputDecoration(
                                labelText: 'Priority',
                                border: OutlineInputBorder(),
                              ),
                              items: const [
                                DropdownMenuItem(value: 'low', child: Text('Low')),
                                DropdownMenuItem(value: 'normal', child: Text('Normal')),
                                DropdownMenuItem(value: 'high', child: Text('High')),
                                DropdownMenuItem(value: 'urgent', child: Text('Urgent')),
                              ],
                              onChanged: (v) => setState(() => _selectedPriority = v ?? 'normal'),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              
              // Actions
              Container(
                padding: const EdgeInsets.all(16),
                decoration: const BoxDecoration(
                  border: Border(top: BorderSide(color: AppTheme.border)),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TextButton(
                      onPressed: () => Navigator.pop(context),
                      child: const Text('Cancel'),
                    ),
                    const SizedBox(width: 12),
                    ElevatedButton.icon(
                      onPressed: () {
                        if (_formKey.currentState!.validate()) {
                          final repairId = 'RPR-${(_activeRepairsCount + 1).toString().padLeft(3, '0')}';
                          widget.onSaved({
                            'id': repairId,
                            'customerName': _customerNameController.text,
                            'phone': _phoneController.text,
                            'deviceType': _deviceTypeController.text,
                            'deviceBrand': _deviceBrandController.text,
                            'deviceModel': _deviceModelController.text,
                            'serialNumber': _serialController.text,
                            'issue': _issueController.text,
                            'estimatedCost': double.tryParse(_estimatedCostController.text) ?? 0,
                            'priority': _selectedPriority,
                            'status': 'received',
                            'receivedDate': DateTime.now().toIso8601String().substring(0, 10),
                            'estimatedCompletion': DateTime.now().add(const Duration(days: 3)).toIso8601String().substring(0, 10),
                            'technician': 'Unassigned',
                          });
                          Navigator.pop(context);
                        }
                      },
                      icon: const Icon(Icons.save, size: 18),
                      label: const Text('Create Repair Ticket'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Color(0xFFFF9800),
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
  
  int get _activeRepairsCount => 3; // Mock count
}
