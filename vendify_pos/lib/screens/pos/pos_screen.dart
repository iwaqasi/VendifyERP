import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/product.dart';
import 'package:vendify_pos/models/cart_item.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/screens/pos/widgets/product_card.dart';
import 'package:vendify_pos/screens/pos/widgets/cart_panel.dart';
import 'package:vendify_pos/screens/pos/widgets/category_sidebar.dart';
import 'package:vendify_pos/screens/pos/payment_screen.dart';
import 'package:vendify_pos/screens/pos/order_history_screen.dart';
import 'package:vendify_pos/screens/login/pin_screen.dart';
import 'package:vendify_pos/screens/pos/customer_selection_sheet.dart';
import 'package:vendify_pos/screens/pos/edit_cart_item_dialog.dart';

class PosScreen extends ConsumerStatefulWidget {
  const PosScreen({super.key});

  @override
  ConsumerState<PosScreen> createState() => _PosScreenState();
}

class _PosScreenState extends ConsumerState<PosScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  final TextEditingController _searchController = TextEditingController();
  final List<CartItem> _cartItems = [];

  List<Product> _products = [];
  List<Category> _categories = [];
  bool _isLoading = true;
  int? _selectedCategoryId;
  String _searchQuery = '';
  String _customerName = 'Walk-in Customer';
  int? _contactId;
  int _locationId = 1;
  String _userName = 'Admin';

  // Receipt-level discount
  double _receiptDiscount = 0;
  String _receiptDiscountType = 'fixed'; // 'fixed' or 'percentage'

  // POS Features from backend
  Map<String, dynamic> _features = {};

  double get _subtotal => _cartItems.fold(0, (sum, item) => sum + item.lineTotal);
  double get _totalTax => _cartItems.fold(0, (sum, item) => sum + item.lineTax);
  double get _receiptDiscountAmount => _receiptDiscountType == 'percentage'
      ? _subtotal * _receiptDiscount / 100
      : _receiptDiscount;
  double get _grandTotal => _subtotal + _totalTax - _receiptDiscountAmount;
  int get _distinctItemCount => _cartItems.length; // Number of distinct products
  int get _totalQuantity => _cartItems.fold(0, (sum, item) => sum + item.quantity.toInt()); // Total quantity
  int get _totalProductCount => _categories.fold(0, (sum, cat) => sum + cat.productCount);

  @override
  void initState() {
    super.initState();
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
        _categories = results[1] as List<Category>;
        _features = settings['features'] ?? {};
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
    });
  }

  void _onReceiptDiscountChanged(double value, String type) {
    setState(() {
      _receiptDiscount = value;
      _receiptDiscountType = type;
    });
  }

  Future<void> _barcodeSearch(String query) async {
    try {
      final results = await _posService.getProducts(
        search: query,
        locationId: _locationId,
        perPage: 10,
      );

      if (results.isNotEmpty) {
        final product = results.first;
        _addToCart(product);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${product.name} added to cart'),
              backgroundColor: AppTheme.success,
              duration: const Duration(seconds: 1),
            ),
          );
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('No product found for "$query"'),
            backgroundColor: AppTheme.error,
            duration: const Duration(seconds: 2),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Search error: $e'),
            backgroundColor: AppTheme.error,
          ),
        );
      }
    }
  }

  // ==================== HOLD CART ====================
  Future<void> _holdCart() async {
    if (_cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cart is empty')),
      );
      return;
    }

    final prefs = await SharedPreferences.getInstance();
    final heldCarts = prefs.getStringList('held_carts') ?? [];

    // Create held cart data
    final heldCartData = {
      'id': DateTime.now().millisecondsSinceEpoch.toString(),
      'timestamp': DateTime.now().toIso8601String(),
      'customerName': _customerName,
      'contactId': _contactId,
      'receiptDiscount': _receiptDiscount,
      'receiptDiscountType': _receiptDiscountType,
      'subtotal': _subtotal,
      'items': _cartItems.map((item) => {
        'productId': item.productId,
        'variationId': item.variationId,
        'productName': item.productName,
        'sku': item.sku,
        'image': item.image,
        'unitPrice': item.unitPrice,
        'quantity': item.quantity,
        'discount': item.discount,
        'discountPercent': item.discountPercent,
        'taxId': item.taxId,
        'taxName': item.taxName,
        'taxRate': item.taxRate,
        'isFlexiblePrice': item.isFlexiblePrice,
        'enableStock': item.enableStock,
        'qtyAvailable': item.qtyAvailable,
      }).toList(),
    };

    heldCarts.add(jsonEncode(heldCartData));
    await prefs.setStringList('held_carts', heldCarts);

    // Clear current cart
    _clearCart();

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Cart held successfully (${heldCarts.length} held)'),
          backgroundColor: AppTheme.warning,
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  // ==================== RECALL CART ====================
  Future<void> _recallCart() async {
    final prefs = await SharedPreferences.getInstance();
    final heldCarts = prefs.getStringList('held_carts') ?? [];

    if (!mounted) return;
    if (heldCarts.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No held carts to recall'),
          backgroundColor: AppTheme.textMuted,
        ),
      );
      return;
    }

    // Parse held carts
    final List<Map<String, dynamic>> parsedCarts = [];
    for (final cartJson in heldCarts) {
      try {
        final cartData = jsonDecode(cartJson) as Map<String, dynamic>;
        parsedCarts.add(cartData);
      } catch (e) {
        // Skip invalid carts
      }
    }

    if (!mounted) return;
    // Show recall dialog
    final selectedCart = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (context) => _RecallDialog(carts: parsedCarts),
    );

    if (selectedCart != null) {
      _restoreCart(selectedCart);
    }
  }

  void _restoreCart(Map<String, dynamic> cartData) {
    final items = (cartData['items'] as List).map((itemData) {
      return CartItem(
        productId: itemData['productId'],
        variationId: itemData['variationId'],
        productName: itemData['productName'],
        sku: itemData['sku'],
        image: itemData['image'],
        unitPrice: (itemData['unitPrice'] as num).toDouble(),
        quantity: (itemData['quantity'] as num).toDouble(),
        discount: (itemData['discount'] as num?)?.toDouble() ?? 0,
        discountPercent: (itemData['discountPercent'] as num?)?.toDouble() ?? 0,
        taxId: itemData['taxId'],
        taxName: itemData['taxName'],
        taxRate: (itemData['taxRate'] as num?)?.toDouble() ?? 0,
        isFlexiblePrice: itemData['isFlexiblePrice'] ?? false,
        enableStock: itemData['enableStock'] ?? false,
        qtyAvailable: (itemData['qtyAvailable'] as num?)?.toDouble() ?? 0,
      );
    }).toList();

    setState(() {
      _cartItems.clear();
      _cartItems.addAll(items);
      _customerName = cartData['customerName'] ?? 'Walk-in Customer';
      _contactId = cartData['contactId'];
      _receiptDiscount = (cartData['receiptDiscount'] as num?)?.toDouble() ?? 0;
      _receiptDiscountType = cartData['receiptDiscountType'] ?? 'fixed';
    });

    // Remove from held carts
    _removeHeldCart(cartData['id']);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Cart recalled (${items.length} items)'),
        backgroundColor: AppTheme.success,
        duration: const Duration(seconds: 2),
      ),
    );
  }

  Future<void> _removeHeldCart(String? cartId) async {
    if (cartId == null) return;
    final prefs = await SharedPreferences.getInstance();
    final heldCarts = prefs.getStringList('held_carts') ?? [];
    
    heldCarts.removeWhere((cartJson) {
      try {
        final data = jsonDecode(cartJson) as Map<String, dynamic>;
        return data['id'] == cartId;
      } catch (e) {
        return false;
      }
    });
    
    await prefs.setStringList('held_carts', heldCarts);
  }

  void _openPayment() {
    if (_cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cart is empty')),
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
        )));
  }

  void _openCustomerSelection() {
    showCustomerSelectionSheet(
      context,
      onSelected: (result) {
        setState(() {
          _customerName = result.customerName;
          _contactId = result.contactId;
        });
      },
    );
  }

  void _openAddCustomer() {
    showAddCustomerSheet(
      context,
      onSaved: (result) {
        setState(() {
          _customerName = result.customerName;
          _contactId = result.contactId;
        });
      },
    );
  }

  void _openOrderHistory() {
    Navigator.push(context,
        MaterialPageRoute(builder: (_) => const OrderHistoryScreen()));
  }

  void _logout() {
    Navigator.pushReplacement(
        context, MaterialPageRoute(builder: (_) => const PinScreen()));
  }

  void _openEditCartItem(int index, CartItem item) {
    showDialog(
      context: context,
      builder: (_) => EditCartItemDialog(
        item: item,
        onSave: (updatedItem) {
          setState(() {
            _cartItems[index] = updatedItem;
          });
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      body: Column(
        children: [
          // Top Bar
          _buildTopBar(),

          // Main Content
          Expanded(
            child: Row(
              children: [
                // Left: Categories
                CategorySidebar(
                  categories: _categories,
                  selectedId: _selectedCategoryId,
                  totalProductCount: _totalProductCount,
                  onCategorySelected: (id) {
                    setState(() => _selectedCategoryId = id);
                    _loadProducts();
                  },
                ),

                // Center: Products
                Expanded(
                  child: _buildProductGrid(),
                ),

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
                    onUpdateItem: _openEditCartItem,
                    onClearCart: _clearCart,
                    onPayment: _openPayment,
                    onCustomerTap: _openCustomerSelection,
                    onAddNewCustomer: _openAddCustomer,
                    onHoldCart: _holdCart,
                    onRecall: _recallCart,
                    onBarcodeSearch: _barcodeSearch,
                    onReceiptDiscountChanged: _onReceiptDiscountChanged,
                    features: _features,
                  ),
                ),
              ],
            ),
          ),

          // Bottom Status Bar
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
          // Logo + Business Name
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppTheme.primary.withValues(alpha: 0.1),
            ),
            child: const Icon(Icons.storefront, color: AppTheme.primary, size: 20),
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

          const Spacer(),

          // Action buttons
          _buildTopBarButton(Icons.receipt_long, 'Orders', _openOrderHistory),
          _buildTopBarButton(Icons.inventory_2_outlined, 'Inventory', null),
          _buildTopBarButton(Icons.close, 'X-Out', null),
          const SizedBox(width: 8),
          IconButton(
            icon: const Icon(Icons.sync, color: AppTheme.textSecondary, size: 22),
            onPressed: _loadData,
            tooltip: 'Sync',
          ),
          IconButton(
            icon: const Icon(Icons.settings, color: AppTheme.textSecondary, size: 22),
            onPressed: null,
            tooltip: 'Settings',
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

  Widget _buildTopBarButton(IconData icon, String label, VoidCallback? onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppTheme.radiusSm),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: AppTheme.textSecondary, size: 20),
            const SizedBox(width: 6),
            Text(
              label,
              style: const TextStyle(
                color: AppTheme.textSecondary,
                fontSize: 13,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProductGrid() {
    return Column(
      children: [
        // Search bar
        Container(
          padding: const EdgeInsets.all(AppTheme.md),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _searchController,
                  style: const TextStyle(color: AppTheme.textPrimary),
                  decoration: InputDecoration(
                    hintText: 'Search all products...',
                    prefixIcon: const Icon(Icons.search, color: AppTheme.textMuted),
                    suffixIcon: _searchController.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, color: AppTheme.textMuted),
                            onPressed: () {
                              _searchController.clear();
                              setState(() => _searchQuery = '');
                              _loadData();
                            },
                          )
                        : null,
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
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  ),
                  onChanged: (value) {
                    setState(() => _searchQuery = value);
                    _searchProducts(value);
                  },
                ),
              ),
            ],
          ),
        ),

        // Product grid
        Expanded(
          child: _isLoading
              ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
              : _products.isEmpty
                  ? Center(
                      child: Text(
                        'No products found',
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
                      itemCount: _products.length,
                      itemBuilder: (context, index) {
                        return ProductCard(
                          product: _products[index],
                          onTap: () => _addToCart(_products[index]),
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
          const Spacer(),
          Text(
            DateTime.now().toString().substring(0, 19),
            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
          ),
        ],
      ),
    );
  }

  Future<void> _searchProducts(String query) async {
    try {
      final results = await _posService.getProducts(
        search: query.isNotEmpty ? query : null,
        categoryId: _selectedCategoryId,
        locationId: _locationId,
      );
      setState(() => _products = results);
    } catch (e) {
      // Ignore search errors
    }
  }

  Future<void> _loadProducts() async {
    setState(() => _isLoading = true);
    try {
      final products = await _posService.getProducts(
        search: _searchQuery.isNotEmpty ? _searchQuery : null,
        categoryId: _selectedCategoryId,
        locationId: _locationId,
      );
      setState(() {
        _products = products;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }
}

// ==================== RECALL DIALOG ====================
class _RecallDialog extends ConsumerWidget {
  final List<Map<String, dynamic>> carts;

  const _RecallDialog({required this.carts});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Container(
        width: 500,
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.7,
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
                  const Icon(Icons.history, color: AppTheme.primary, size: 20),
                  const SizedBox(width: 10),
                  const Text(
                    'Recall Held Carts',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    '${carts.length} held',
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppTheme.textSecondary,
                    ),
                  ),
                ],
              ),
            ),

            // Cart list
            Flexible(
              child: carts.isEmpty
                  ? const Center(
                      child: Padding(
                        padding: EdgeInsets.all(32),
                        child: Text(
                          'No held carts',
                          style: TextStyle(color: AppTheme.textMuted, fontSize: 14),
                        ),
                      ),
                    )
                  : ListView.builder(
                      shrinkWrap: true,
                      padding: const EdgeInsets.all(8),
                      itemCount: carts.length,
                      itemBuilder: (context, index) {
                        final cart = carts[index];
                        final items = cart['items'] as List? ?? [];
                        final timestamp = cart['timestamp'] ?? '';
                        final customerName = cart['customerName'] ?? 'Walk-in';
                        final subtotal = (cart['subtotal'] as num?)?.toDouble() ?? 0;
                        
                        // Format timestamp
                        String timeStr = '';
                        try {
                          final dt = DateTime.parse(timestamp);
                          timeStr = '${dt.day}/${dt.month}/${dt.year} ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
                        } catch (e) {
                          timeStr = timestamp;
                        }

                        return Card(
                          margin: const EdgeInsets.symmetric(vertical: 4),
                          color: AppTheme.background,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                            side: const BorderSide(color: AppTheme.border),
                          ),
                          child: InkWell(
                            onTap: () => Navigator.pop(context, cart),
                            borderRadius: BorderRadius.circular(8),
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Row(
                                children: [
                                  // Cart icon
                                  Container(
                                    width: 48,
                                    height: 48,
                                    decoration: BoxDecoration(
                                      color: AppTheme.primary.withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: const Icon(
                                      Icons.shopping_cart,
                                      color: AppTheme.primary,
                                      size: 24,
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  // Cart details
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          customerName,
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w600,
                                            color: AppTheme.textPrimary,
                                            fontSize: 14,
                                          ),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          '${items.length} item${items.length != 1 ? 's' : ''} • KD ${subtotal.toStringAsFixed(3)}',
                                          style: const TextStyle(
                                            color: AppTheme.textSecondary,
                                            fontSize: 12,
                                          ),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          timeStr,
                                          style: const TextStyle(
                                            color: AppTheme.textMuted,
                                            fontSize: 11,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  // Restore button
                                  const Icon(
                                    Icons.restore,
                                    color: AppTheme.primary,
                                    size: 20,
                                  ),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),
            ),

            // Close button
            Container(
              padding: const EdgeInsets.all(12),
              decoration: const BoxDecoration(
                border: Border(top: BorderSide(color: AppTheme.border)),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text(
                      'Cancel',
                      style: TextStyle(color: AppTheme.textSecondary),
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
}
