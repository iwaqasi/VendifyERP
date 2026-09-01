import 'dart:async';
import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/screens/pos/widgets/hold_recall_service.dart';
import 'package:vendify_pos/screens/pos/invoice_history_screen.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/product.dart';
import 'package:vendify_pos/models/cart_item.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/screens/pos/widgets/product_card.dart';
import 'package:vendify_pos/screens/pos/widgets/cart_panel.dart';
import 'package:vendify_pos/screens/pos/payment_screen.dart';
import 'package:vendify_pos/screens/login/pin_screen.dart';
import 'package:vendify_pos/screens/pos/customer_selection_sheet.dart';
import 'package:vendify_pos/screens/pos/daily_register_screen.dart';
import 'package:vendify_pos/screens/pos/widgets/booking_dialog.dart';
import 'package:vendify_pos/screens/pos/widgets/appointment_detail_dialog.dart';

class SaloonPosScreen extends ConsumerStatefulWidget {
  final String businessType;
  final Map<String, dynamic> features;

  const SaloonPosScreen({
    super.key,
    required this.businessType,
    required this.features,
  });

  @override
  ConsumerState<SaloonPosScreen> createState() => _SaloonPosScreenState();
}

class _SaloonPosScreenState extends ConsumerState<SaloonPosScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  final TextEditingController _searchController = TextEditingController();
  final List<CartItem> _cartItems = [];

  List<Product> _products = [];
  List<Category> _categories = [];
  List<Product> _services = [];
  bool _isLoading = true;
  String _searchQuery = '';
  String _customerName = 'Walk-in Customer';
  int? _contactId;
  int _locationId = 1;
  String _userName = 'Admin';

  // Appointment sidebar
  List<Map<String, dynamic>> _todayAppointments = [];
  bool _showAppointmentSidebar = true;
  DateTime _selectedAppointmentDate = DateTime.now();

  // Category filter
  int? _selectedCategoryId;

  // Staff selection
  List<Map<String, dynamic>> _staff = [];
  int? _selectedStaffId;
  String _selectedStaffName = 'Any Available';

  // Receipt-level discount
  double _receiptDiscount = 0;
  String _receiptDiscountType = 'fixed';

  // Customer reward points
  int _customerRewardPoints = 0;
  double _rpRedeemAmount = 0.0;
  String _rpName = 'Reward Points';
  double _rpDiscount = 0.0; // Applied RP discount for this sale

  // Credit Customer Info
  double _customerCreditLimit = 0;
  double _customerSellDue = 0;
  int _customerPayTermNumber = 0;
  String _customerPayTermType = 'days';

  // Appointment notifications
  final List<Map<String, dynamic>> _pendingNotifications = [];
  Timer? _appointmentCheckTimer;
  final Set<int> _notifiedAppointmentIds = {};

  late Map<String, dynamic> _features;

  double get _subtotal => _cartItems.fold(0, (sum, item) => sum + item.lineTotal);
  double get _totalTax => _cartItems.fold(0, (sum, item) => sum + item.lineTax);
  double get _receiptDiscountAmount => _receiptDiscountType == 'percentage'
      ? _subtotal * _receiptDiscount / 100
      : _receiptDiscount;
  double get _grandTotal => (_subtotal + _totalTax - _receiptDiscountAmount - _rpDiscount).clamp(0, double.infinity).toDouble();
  int get _distinctItemCount => _cartItems.length;
  int get _totalQuantity => _cartItems.fold(0, (sum, item) => sum + item.quantity.toInt());

  @override
  void initState() {
    super.initState();
    _features = widget.features;
    _loadData();
    _startAppointmentChecker();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _appointmentCheckTimer?.cancel();
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
        final posSettings = (settings['pos_settings'] as Map<String, dynamic>?) ?? {};
        // Merge: keep business type features (like appointment_sidebar) and add POS settings features
        final posFeatures = Map<String, dynamic>.from(settings['features'] ?? {});
        _features = {...posFeatures, ..._features};
        
        // Save receipt prefix to SharedPreferences for invoice generation
        final receiptPrefix = posSettings['pos_receipt_prefix'] ?? 'INV-POS-';
        prefs.setString('pos_receipt_prefix', receiptPrefix);
        
        // Filter services (products that are services)
        _services = _products.where((p) => 
          p.type == 'service' || p.isServiceProduct
        ).toList();
        
        _isLoading = false;
      });
      
      // Load staff and appointments
      _loadStaff();
      _loadAppointments();
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _loadStaff() async {
    try {
      final staffData = await _posService.getSaloonStaff();
      setState(() {
        _staff = staffData.map((s) => {
          'id': s['id'],
          'name': s['name'],
          'color': Color(int.parse((s['color'] ?? '#00BCD4').replaceFirst('#', '0xFF'))),
          'available': s['is_active'] == true,
          'specialization': s['specialization'],
        }).toList();
      });
    } catch (e) {
      // Fallback to empty staff list
      setState(() => _staff = []);
    }
  }

  Future<void> _loadAppointments() async {
    try {
      final dateStr = '${_selectedAppointmentDate.year}-${_selectedAppointmentDate.month.toString().padLeft(2, '0')}-${_selectedAppointmentDate.day.toString().padLeft(2, '0')}';
      final appts = await _posService.getSaloonAppointments(date: dateStr);
      
      // Build staff lookup map
      final staffMap = <int, String>{};
      for (final s in _staff) {
        staffMap[s['id']] = s['name'];
      }
      
      setState(() {
        _todayAppointments = appts.map((a) {
          final staffId = a['staff_id'];
          return {
            'id': a['id'],
            'contactId': a['contact_id'],
            'customerName': a['customer_name'] ?? '',
            'service': a['service_name'] ?? '',
            'time': a['appointment_start'] != null
                ? (a['appointment_start'] as String).substring(11, 16)
                : '',
            'staff': staffMap[staffId] ?? 'Any Available',
            'status': a['status'] ?? 'scheduled',
            'duration': a['service_duration_minutes'] ?? 30,
            'price': a['service_price'] ?? 0,
            'notes': a['notes'] ?? '',
          };
        }).toList();
        // Filter out completed appointments from the main view
        _todayAppointments = _todayAppointments.where((a) => a['status'] != 'completed').toList();
      });
    } catch (e) {
      setState(() => _todayAppointments = []);
    }
  }

  Future<void> _loadCustomerRewardPoints(int? contactId) async {
    if (contactId == null) {
      setState(() {
        _customerRewardPoints = 0;
        _rpRedeemAmount = 0.0;
        _rpDiscount = 0.0;
        _customerCreditLimit = 0;
        _customerSellDue = 0;
        _customerPayTermNumber = 0;
        _customerPayTermType = 'days';
      });
      return;
    }
    try {
      final rpData = await _posService.getCustomerRewardPoints(contactId);
      setState(() {
        _customerRewardPoints = rpData['available_points'] ?? 0;
        _rpRedeemAmount = (num.tryParse(rpData['equivalent_amount'].toString()) ?? 0).toDouble();
        _rpName = rpData['rp_name'] ?? 'Reward Points';
      });
      // Load credit info
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
    } catch (e) {
      setState(() {
        _customerRewardPoints = 0;
        _rpRedeemAmount = 0.0;
      });
    }
  }

  void _onRedeemPoints(double amount) {
    setState(() {
      _rpDiscount = amount;
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('KD ${amount.toStringAsFixed(3)} loyalty discount applied!'), backgroundColor: Colors.green),
    );
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
      _selectedStaffId = null;
      _selectedStaffName = 'Any Available';
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

  void _openNewBooking() {
    BookingDialog.show(
      context: context,
      posService: _posService,
      locationId: _locationId,
      staff: _staff,
      services: _products.map((p) => {
        'name': p.name,
        'price': p.sellPriceIncTax,
        'duration': 30,
      }).toList(),
      onBookingCreated: _loadAppointments,
    );
  }

  void _navigateAppointmentDate(int days) {
    setState(() {
      _selectedAppointmentDate = _selectedAppointmentDate.add(Duration(days: days));
    });
    _loadAppointments();
  }

  // ========== Appointment Notifications ==========

  void _startAppointmentChecker() {
    // Check every 30 seconds for upcoming appointments
    _appointmentCheckTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      _checkUpcomingAppointments();
    });
    // Also check immediately after a short delay (wait for data to load)
    Future.delayed(const Duration(seconds: 3), _checkUpcomingAppointments);
  }

  void _checkUpcomingAppointments() {
    final now = DateTime.now();

    for (final appt in _todayAppointments) {
      final id = appt['id'];
      if (id == null || _notifiedAppointmentIds.contains(id)) continue;

      final timeStr = appt['time'] ?? '';
      if (timeStr.isEmpty) continue;

      // Parse the appointment time (format: HH:mm)
      final parts = timeStr.split(':');
      if (parts.length != 2) continue;

      final apptTime = DateTime(
        now.year, now.month, now.day,
        int.parse(parts[0]), int.parse(parts[1]),
      );

      // Notify if appointment is within the next 5 minutes or up to 5 minutes past
      final diff = apptTime.difference(now).inMinutes;
      if (diff >= -5 && diff <= 5) {
        _notifiedAppointmentIds.add(id);
        _showAppointmentNotification(appt, diff);
      }
    }
  }

  void _showAppointmentNotification(Map<String, dynamic> appointment, int minutesDiff) {
    final customerName = appointment['customerName'] ?? 'Unknown';
    final service = appointment['service'] ?? '';
    final time = appointment['time'] ?? '';
    final id = appointment['id'];

    String message;
    if (minutesDiff > 0) {
      message = '$customerName has $service in $minutesDiff min ($time)';
    } else if (minutesDiff == 0) {
      message = "$customerName's $service is starting NOW ($time)";
    } else {
      message = "$customerName's $service started ${-minutesDiff} min ago ($time)";
    }

    setState(() {
      _pendingNotifications.add({
        'id': id,
        'message': message,
        'customerName': customerName,
        'service': service,
        'time': time,
        'appointment': appointment,
        'timestamp': DateTime.now(),
      });
    });

    // Auto-dismiss after 30 seconds
    Future.delayed(const Duration(seconds: 30), () {
      if (mounted) {
        setState(() {
          _pendingNotifications.removeWhere((n) => n['id'] == id);
        });
      }
    });
  }

  void _dismissNotification(int id) {
    setState(() {
      _pendingNotifications.removeWhere((n) => n['id'] == id);
    });
  }

  void _startServiceFromNotification(Map<String, dynamic> appointment) {
    // Dismiss notification
    _dismissNotification(appointment['id']);
    // Open detail dialog
    _openAppointmentDetail(appointment);
  }

  void _openAppointmentDetail(Map<String, dynamic> appointment) {
    AppointmentDetailDialog.show(
      context: context,
      appointment: appointment,
      posService: _posService,
      staff: _staff,
      onComplete: (result) {
        // Add completed services to the cart (skip duplicates)
        int addedCount = 0;
        setState(() {
          for (final service in result.services) {
            // Check if already in cart
            final existingIndex = _cartItems.indexWhere(
              (i) => i.productName.toLowerCase() == service.name.toLowerCase(),
            );

            if (existingIndex >= 0) {
              // Already in cart — skip
              continue;
            }
            addedCount++;
            // Find product by name to get tax rate
            final idx = _products.indexWhere(
              (p) => p.name.toLowerCase() == service.name.toLowerCase(),
            );
            final product = idx >= 0 ? _products[idx] : Product(
              id: 0,
              name: service.name,
              sellPriceIncTax: service.price,
              type: 'service',
            );

            _cartItems.add(CartItem(
              productId: product.id,
              variationId: product.variationId ?? product.id,
              productName: service.name,
              sku: product.sku,
              image: product.image,
              unitPrice: service.price,
              quantity: 1,
              taxRate: product.taxRate,
            ));
          }

          // Set customer name from appointment
          if (result.customerName.isNotEmpty && result.customerName != 'Walk-in Customer') {
            _customerName = result.customerName;
            _contactId = result.contactId;
          }
        });

        // Load reward points for the customer
        _loadCustomerRewardPoints(_contactId);

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(addedCount > 0
                  ? '✅ $addedCount service(s) added to cart for ${result.customerName}'
                  : 'ℹ️ Services already in cart for ${result.customerName}'),
              backgroundColor: AppTheme.success,
              duration: const Duration(seconds: 2),
            ),
          );
        }
      },
      onStatusChanged: _loadAppointments,
    );
  }

  void _openPayment() {
    if (_cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cart is empty')),
      );
      return;
    }
    // Credit limit check
    if (_customerCreditLimit > 0 && (_customerSellDue + _grandTotal) > _customerCreditLimit) {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Credit Limit Exceeded'),
          content: Text('Customer due: KD ${_customerSellDue.toStringAsFixed(3)} + Current: KD ${_grandTotal.toStringAsFixed(3)} = KD ${(_customerSellDue + _grandTotal).toStringAsFixed(3)}\nCredit Limit: KD ${_customerCreditLimit.toStringAsFixed(3)}'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            ElevatedButton(
              onPressed: () { Navigator.pop(ctx); _proceedToPayment(); },
              child: const Text('Proceed Anyway'),
            ),
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

  void _openAddCustomer() {
    showAddCustomerSheet(
      context,
      onSaved: (result) {
        setState(() {
          _customerName = result.customerName;
          _contactId = result.contactId;
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
      body: Stack(
        children: [
          Column(
        children: [
          _buildTopBar(),
          Expanded(
            child: Row(
              children: [
                // Left: Appointment Sidebar (if enabled)
                if (_features['appointment_sidebar'] == true && _showAppointmentSidebar)
                  _buildAppointmentSidebar(),
                
                // Center: Services/Products
                Expanded(
                  child: Column(
                    children: [
                      _buildStaffSelector(),
                      Expanded(child: _buildProductGrid()),
                    ],
                  ),
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
                    onUpdateItem: (i, item) => setState(() => _cartItems[i] = item),
                    onClearCart: _clearCart,
                    onPayment: _openPayment,
                    onCustomerTap: _openCustomerSelection,
                    onAddNewCustomer: _openAddCustomer,
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

          // Appointment notification overlay
          if (_pendingNotifications.isNotEmpty)
            Positioned(
              top: 64,
              right: 16,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: _pendingNotifications.map((n) => _buildNotificationCard(n)).toList(),
              ),
            ),
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
              color: Color(0xFFE91E63).withValues(alpha: 0.1),
            ),
            child: const Icon(Icons.spa, color: Color(0xFFE91E63), size: 20),
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
              color: Color(0xFFE91E63).withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(4),
            ),
            child: const Text(
              'SALOON & SPA',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: Color(0xFFE91E63),
              ),
            ),
          ),
          const Spacer(),
          IconButton(
            icon: const Icon(Icons.receipt_long, color: AppTheme.textSecondary, size: 22),
            onPressed: () {
              Navigator.push(context, MaterialPageRoute(builder: (_) => const InvoiceHistoryScreen()));
            },
            tooltip: 'Invoices',
          ),
          IconButton(
            icon: const Icon(Icons.account_balance_wallet, color: AppTheme.textSecondary, size: 22),
            onPressed: () {
              Navigator.push(context, MaterialPageRoute(builder: (_) => const DailyRegisterScreen()));
            },
            tooltip: 'Daily Register',
          ),
          IconButton(
            icon: Icon(
              _showAppointmentSidebar ? Icons.calendar_view_day : Icons.calendar_today,
              color: AppTheme.textSecondary,
              size: 22,
            ),
            onPressed: () => setState(() => _showAppointmentSidebar = !_showAppointmentSidebar),
            tooltip: 'Toggle Appointments',
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

  Widget _buildAppointmentSidebar() {
    final isToday = _selectedAppointmentDate.year == DateTime.now().year &&
        _selectedAppointmentDate.month == DateTime.now().month &&
        _selectedAppointmentDate.day == DateTime.now().day;
    final dayLabel = isToday
        ? 'Today'
        : '${_selectedAppointmentDate.day}/${_selectedAppointmentDate.month}/${_selectedAppointmentDate.year}';

    return Container(
      width: 300,
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(right: BorderSide(color: AppTheme.border)),
      ),
      child: Column(
        children: [
          // Header with date navigation
          Container(
            padding: const EdgeInsets.all(12),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppTheme.border)),
            ),
            child: Column(
              children: [
                Row(
                  children: [
                    const Icon(Icons.calendar_today, size: 18, color: Color(0xFFE91E63)),
                    const SizedBox(width: 8),
                    const Text(
                      'Appointments',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    const Spacer(),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: const Color(0xFFE91E63).withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        '${_todayAppointments.length}',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFFE91E63),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                // Date navigator
                Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.chevron_left, size: 20),
                      onPressed: () => _navigateAppointmentDate(-1),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                      color: AppTheme.textSecondary,
                    ),
                    Expanded(
                      child: GestureDetector(
                        onTap: () async {
                          final picked = await showDatePicker(
                            context: context,
                            initialDate: _selectedAppointmentDate,
                            firstDate: DateTime.now().subtract(const Duration(days: 365)),
                            lastDate: DateTime.now().add(const Duration(days: 90)),
                          );
                          if (picked != null) {
                            setState(() => _selectedAppointmentDate = picked);
                            _loadAppointments();
                          }
                        },
                        child: Container(
                          padding: const EdgeInsets.symmetric(vertical: 6),
                          decoration: BoxDecoration(
                            color: AppTheme.background,
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              if (!isToday) ...[
                                const Icon(Icons.circle, size: 6, color: Color(0xFFE91E63)),
                                const SizedBox(width: 6),
                              ],
                              Text(
                                dayLabel,
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: isToday ? FontWeight.bold : FontWeight.w500,
                                  color: isToday ? const Color(0xFFE91E63) : AppTheme.textPrimary,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.chevron_right, size: 20),
                      onPressed: () => _navigateAppointmentDate(1),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                      color: AppTheme.textSecondary,
                    ),
                  ],
                ),
              ],
            ),
          ),
          
          // Appointments list
          Expanded(
            child: _todayAppointments.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.event_available, size: 40, color: AppTheme.textMuted.withValues(alpha: 0.4)),
                          const SizedBox(height: 12),
                          Text(
                            isToday ? 'No appointments today' : 'No appointments on this day',
                            style: const TextStyle(color: AppTheme.textMuted, fontSize: 13),
                          ),
                          const SizedBox(height: 4),
                          const Text(
                            'Tap + to book one',
                            style: TextStyle(color: AppTheme.textMuted, fontSize: 11),
                          ),
                        ],
                      ),
                    ),
                  )
                : ListView.builder(
                    padding: const EdgeInsets.all(8),
                    itemCount: _todayAppointments.length,
                    itemBuilder: (context, index) {
                      final appointment = _todayAppointments[index];
                      return _buildAppointmentCard(appointment);
                    },
                  ),
          ),
          
          // New Appointment button
          Padding(
            padding: const EdgeInsets.all(8),
            child: SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _openNewBooking,
                icon: const Icon(Icons.add, size: 18),
                label: const Text('New Appointment'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFE91E63),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAppointmentCard(Map<String, dynamic> appointment) {
    final statusColors = {
      'in_progress': AppTheme.warning,
      'completed': AppTheme.success,
      'upcoming': AppTheme.primary,
      'cancelled': AppTheme.error,
    };
    
    final status = appointment['status'] ?? 'upcoming';
    final statusColor = statusColors[status] ?? AppTheme.textMuted;
    
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 4),
      color: AppTheme.background,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: BorderSide(
          color: status == 'in_progress' ? statusColor : AppTheme.border,
          width: status == 'in_progress' ? 2 : 1,
        ),
      ),
      child: InkWell(
        onTap: () => _openAppointmentDetail(appointment),
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    appointment['time'] ?? '',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: statusColor,
                      fontSize: 12,
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
                appointment['customerName'] ?? '',
                style: const TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                  color: AppTheme.textPrimary,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                appointment['service'] ?? '',
                style: const TextStyle(
                  fontSize: 12,
                  color: AppTheme.textSecondary,
                ),
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(Icons.person, size: 12, color: AppTheme.textMuted),
                  const SizedBox(width: 4),
                  Text(
                    appointment['staff'] ?? '',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppTheme.textMuted,
                    ),
                  ),
                  const Spacer(),
                  Icon(Icons.access_time, size: 12, color: AppTheme.textMuted),
                  const SizedBox(width: 4),
                  Text(
                    '${appointment['duration']} min',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppTheme.textMuted,
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

  Widget _buildStaffSelector() {
    return Container(
      constraints: const BoxConstraints(maxHeight: 90),
      padding: const EdgeInsets.symmetric(horizontal: AppTheme.md, vertical: 8),
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(bottom: BorderSide(color: AppTheme.border)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.only(top: 6),
            child: Text(
              'Assign Staff:',
              style: TextStyle(
                fontWeight: FontWeight.w600,
                color: AppTheme.textSecondary,
                fontSize: 13,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: SingleChildScrollView(
              scrollDirection: Axis.vertical,
              child: Wrap(
                spacing: 8,
                runSpacing: 4,
                children: [
                  _buildStaffChip(0, 'Any Available', AppTheme.textMuted, true),
                  ..._staff.map((s) => _buildStaffChip(
                    s['id'],
                    s['name'],
                    s['color'],
                    s['available'],
                  )),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStaffChip(int id, String name, Color color, bool available) {
    final isSelected = (_selectedStaffId == null && id == 0) || _selectedStaffId == id;
    
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(
          name,
          style: TextStyle(
            color: isSelected ? Colors.white : (available ? AppTheme.textPrimary : AppTheme.textMuted),
            fontSize: 12,
          ),
        ),
        selected: isSelected,
        selectedColor: color,
        backgroundColor: AppTheme.background,
        disabledColor: AppTheme.background,
        onSelected: available ? (selected) {
          setState(() {
            _selectedStaffId = id == 0 ? null : id;
            _selectedStaffName = name;
          });
        } : null,
        avatar: !available 
          ? const Icon(Icons.block, size: 14, color: AppTheme.error)
          : null,
      ),
    );
  }

  List<Product> get _filteredProducts {
    var list = _products;
    if (_selectedCategoryId != null) {
      list = list.where((p) => p.categoryId == _selectedCategoryId).toList();
    }
    if (_searchQuery.isNotEmpty) {
      final q = _searchQuery.toLowerCase();
      list = list.where((p) =>
        p.name.toLowerCase().contains(q) ||
        (p.sku?.toLowerCase().contains(q) ?? false) ||
        (p.barcode?.toLowerCase().contains(q) ?? false)
      ).toList();
    }
    return list;
  }

  Widget _buildCategoryBar() {
    return Container(
      height: 50,
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(bottom: BorderSide(color: AppTheme.border)),
      ),
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: AppTheme.md, vertical: 8),
        children: [
          // All category
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ChoiceChip(
              label: Text(
                'All (${_products.length})',
                style: TextStyle(
                  color: _selectedCategoryId == null ? Colors.white : AppTheme.textPrimary,
                  fontSize: 12,
                ),
              ),
              selected: _selectedCategoryId == null,
              selectedColor: const Color(0xFFE91E63),
              backgroundColor: AppTheme.background,
              onSelected: (_) => setState(() => _selectedCategoryId = null),
            ),
          ),
          // Category chips
          ..._categories.map((cat) {
            final count = _products.where((p) => p.categoryId == cat.id).length;
            return Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                label: Text(
                  '${cat.name} ($count)',
                  style: TextStyle(
                    color: _selectedCategoryId == cat.id ? Colors.white : AppTheme.textPrimary,
                    fontSize: 12,
                  ),
                ),
                selected: _selectedCategoryId == cat.id,
                selectedColor: const Color(0xFFE91E63),
                backgroundColor: AppTheme.background,
                onSelected: (_) => setState(() {
                  _selectedCategoryId = _selectedCategoryId == cat.id ? null : cat.id;
                }),
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildProductGrid() {
    final displayProducts = _filteredProducts;
    return Column(
      children: [
        _buildCategoryBar(),
        Container(
          padding: const EdgeInsets.all(AppTheme.md),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _searchController,
                  style: const TextStyle(color: AppTheme.textPrimary),
                  decoration: InputDecoration(
                    hintText: 'Search services & products...',
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
                      borderSide: const BorderSide(color: Color(0xFFE91E63), width: 2),
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  ),
                  onChanged: (value) => setState(() => _searchQuery = value),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: _isLoading
              ? const Center(child: CircularProgressIndicator(color: Color(0xFFE91E63)))
              : displayProducts.isEmpty
                  ? Center(
                      child: Text(
                        _searchQuery.isNotEmpty ? 'No matching items' : 'No services found',
                        style: const TextStyle(color: AppTheme.textSecondary, fontSize: 16),
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
            'User: $_userName • Staff: $_selectedStaffName',
            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
          ),
          const Spacer(),
          Text(
            'Services: ${_services.length} • Products: ${_products.length}',
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

  // ========== Appointment Notification Card ==========

  Widget _buildNotificationCard(Map<String, dynamic> notification) {
    final appointment = (notification['appointment'] as Map<String, dynamic>?) ?? {};
    return Container(
      width: 360,
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFF9800), width: 2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFFF9800).withValues(alpha: 0.3),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            // Header row
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFF9800).withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(Icons.notifications_active, color: Color(0xFFFF9800), size: 18),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Appointment Alert',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                      ),
                      Text(
                        notification['message'] ?? '',
                        style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                GestureDetector(
                  onTap: () => _dismissNotification(notification['id']),
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: AppTheme.background,
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: const Icon(Icons.close, size: 14, color: AppTheme.textMuted),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            // Action buttons
            Row(
              children: [
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: () => _startServiceFromNotification(appointment),
                    icon: const Icon(Icons.play_arrow, size: 16),
                    label: const Text('Start Service', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFFF9800),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => _dismissNotification(notification['id']),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: AppTheme.border),
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    child: const Text('Dismiss', style: TextStyle(fontSize: 12, color: AppTheme.textMuted)),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
