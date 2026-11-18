import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/screens/pos/widgets/hold_recall_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
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
import 'package:vendify_pos/screens/pos/widgets/sync_center_dialog.dart';
import 'package:vendify_pos/screens/pos/widgets/sync_status_badge.dart';
import 'package:vendify_pos/screens/pos/widgets/diagnostics_dialog.dart';
import 'package:vendify_pos/screens/pos/widgets/audit_trail_dialog.dart';
import 'package:provider/provider.dart' as p;
import 'package:vendify_pos/providers/locale_provider.dart';

class ClinicPosScreen extends ConsumerStatefulWidget {
  final String businessType;
  final Map<String, dynamic> features;

  const ClinicPosScreen({
    super.key,
    required this.businessType,
    required this.features,
  });

  @override
  ConsumerState<ClinicPosScreen> createState() => _ClinicPosScreenState();
}

class _ClinicPosScreenState extends ConsumerState<ClinicPosScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  final TextEditingController _searchController = TextEditingController();
  final List<CartItem> _cartItems = [];

  List<Product> _products = [];
  List<Category> _categories = [];
  int? _selectedCategoryId;
  bool _isLoading = true;
  String _searchQuery = '';
  String _customerName = 'Select Patient';
  int? _contactId;
  int _locationId = 1;
  String _userName = 'Admin';

  // Patient info
  String _insuranceProvider = '';
  bool _hasInsurance = false;

  // Appointment sidebar
  List<Map<String, dynamic>> _todayAppointments = [];
  bool _showAppointmentSidebar = true;

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
  num get _totalQuantity => _cartItems.fold(0.0, (sum, item) => sum + item.quantity);

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
      final settings = (results[2] is Map) ? Map<String, dynamic>.from(results[2] as Map) : <String, dynamic>{};
      
      setState(() {
        _products = (results[0] is List<Product>) ? results[0] as List<Product> : <Product>[];
        _categories = (results[1] is List<Category>) ? results[1] as List<Category> : <Category>[];
        if (settings['features'] is Map) {
          _features = Map<String, dynamic>.from(settings['features'] as Map);
        }
        _isLoading = false;
      });
      
      _loadTodayAppointments();
    } catch (e) {
      setState(() => _isLoading = false);
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

  void _loadTodayAppointments() {
    // Mock appointments - in real app, fetch from API
    setState(() {
      _todayAppointments = [
        {
          'id': 1,
          'patientName': 'Ahmed Al-Rashid',
          'doctor': 'Dr. Sarah',
          'time': '9:00 AM',
          'type': 'Follow-up',
          'status': 'checked_in',
          'insurance': 'Kuwait Insurance Co.',
        },
        {
          'id': 2,
          'patientName': 'Fatima Hassan',
          'doctor': 'Dr. Mohammed',
          'time': '9:30 AM',
          'type': 'Consultation',
          'status': 'waiting',
          'insurance': null,
        },
        {
          'id': 3,
          'patientName': 'Omar Al-Sabah',
          'doctor': 'Dr. Sarah',
          'time': '10:00 AM',
          'type': 'Check-up',
          'status': 'in_consultation',
          'insurance': 'Oman Insurance',
        },
        {
          'id': 4,
          'patientName': 'Sara Ibrahim',
          'doctor': 'Dr. Ali',
          'time': '10:30 AM',
          'type': 'Lab Results',
          'status': 'completed',
          'insurance': null,
        },
      ];
    });
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
      _customerName = 'Select Patient';
      _contactId = null;
      _insuranceProvider = '';
      _customerRewardPoints = 0;
      _rpRedeemAmount = 0.0;
      _rpDiscount = 0.0;
      _hasInsurance = false;
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
        _customerName = selectedCart['customerName'] ?? 'Select Patient';
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

  void _selectAppointment(Map<String, dynamic> appointment) {
    setState(() {
      _customerName = appointment['patientName'];
      _hasInsurance = appointment['insurance'] != null;
      _insuranceProvider = appointment['insurance'] ?? '';
    });
    
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Selected patient: ${appointment['patientName']}'),
        backgroundColor: AppTheme.primary,
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
                // Left: Appointments sidebar
                if (_features['appointment_sidebar'] == true && _showAppointmentSidebar)
                  _buildAppointmentSidebar(),
                
                // Center: Services/Products
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
              color: Color(0xFF00BCD4).withValues(alpha: 0.1),
            ),
            child: const Icon(Icons.local_hospital, color: Color(0xFF00BCD4), size: 20),
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
              color: Color(0xFF00BCD4).withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(4),
            ),
            child: const Text(
              'CLINIC',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.bold,
                color: Color(0xFF00BCD4),
              ),
            ),
          ),
          const Spacer(),
          
          // Insurance badge
          if (_hasInsurance)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: AppTheme.success.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: AppTheme.success),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.health_and_safety, size: 16, color: AppTheme.success),
                  const SizedBox(width: 6),
                  Text(
                    _insuranceProvider,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: AppTheme.success,
                    ),
                  ),
                ],
              ),
            ),
          
          const SizedBox(width: 16),
          
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
            icon: const Icon(Icons.inventory_2_outlined, color: AppTheme.textSecondary, size: 22),
            onPressed: _openInventory,
            tooltip: 'Inventory',
          ),
          const SizedBox(width: 8),
          const SyncStatusBadge(),
          const SizedBox(width: 6),
          IconButton(
            icon: const Icon(Icons.sync_alt, color: AppTheme.textSecondary, size: 20),
            onPressed: () => SyncCenterDialog.show(context),
            tooltip: 'Sync Center',
          ),
          IconButton(
            icon: const Icon(Icons.bug_report_outlined, color: AppTheme.textSecondary, size: 20),
            onPressed: () => DiagnosticsDialog.show(context),
            tooltip: 'Diagnostics & Logs',
          ),
          IconButton(
            icon: const Icon(Icons.shield_outlined, color: AppTheme.textSecondary, size: 20),
            onPressed: () => AuditTrailDialog.show(context, _posService),
            tooltip: 'Audit Trail & Activity Logs',
          ),
          const SizedBox(width: 4),
          p.Consumer<LocaleProvider>(
            builder: (ctx, loc, _) => InkWell(
              onTap: () => loc.toggleLocale(),
              borderRadius: BorderRadius.circular(6),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: AppTheme.surfaceLight,
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: AppTheme.border),
                ),
                child: Text(
                  loc.isRtl ? 'English' : 'عربي',
                  style: const TextStyle(
                    color: AppTheme.primary,
                    fontWeight: FontWeight.bold,
                    fontSize: 12,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 4),
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
    return Container(
      width: 300,
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
                const Icon(Icons.calendar_today, size: 18, color: Color(0xFF00BCD4)),
                const SizedBox(width: 8),
                const Text(
                  "Today's Patients",
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: Color(0xFF00BCD4).withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    '${_todayAppointments.length}',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF00BCD4),
                    ),
                  ),
                ),
              ],
            ),
          ),
          
          // Appointments list
          Expanded(
            child: ListView.builder(
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
                onPressed: () {
                  _openNewAppointmentDialog();
                },
                icon: const Icon(Icons.add, size: 18),
                label: const Text('New Appointment'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Color(0xFF00BCD4),
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
      'waiting': AppTheme.warning,
      'checked_in': AppTheme.info,
      'in_consultation': AppTheme.primary,
      'completed': AppTheme.success,
      'cancelled': AppTheme.error,
    };
    
    final status = appointment['status'] ?? 'waiting';
    final statusColor = statusColors[status] ?? AppTheme.textMuted;
    
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 4),
      color: AppTheme.background,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: BorderSide(
          color: status == 'in_consultation' ? statusColor : AppTheme.border,
          width: status == 'in_consultation' ? 2 : 1,
        ),
      ),
      child: InkWell(
        onTap: () => _selectAppointment(appointment),
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(12),
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
                appointment['patientName'] ?? '',
                style: const TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                  color: AppTheme.textPrimary,
                ),
              ),
              const SizedBox(height: 2),
              Row(
                children: [
                  Icon(Icons.person, size: 12, color: AppTheme.textMuted),
                  const SizedBox(width: 4),
                  Text(
                    appointment['doctor'] ?? '',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppTheme.textSecondary,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 2),
              Row(
                children: [
                  Icon(Icons.medical_services, size: 12, color: AppTheme.textMuted),
                  const SizedBox(width: 4),
                  Text(
                    appointment['type'] ?? '',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppTheme.textSecondary,
                    ),
                  ),
                ],
              ),
              if (appointment['insurance'] != null) ...[
                const SizedBox(height: 4),
                Row(
                  children: [
                    const Icon(Icons.health_and_safety, size: 12, color: AppTheme.success),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        appointment['insurance'],
                        style: const TextStyle(
                          fontSize: 10,
                          color: AppTheme.success,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  void _openNewAppointmentDialog() {
    final patientCtrl = TextEditingController();
    final doctorCtrl = TextEditingController(text: 'Dr. Sarah');
    final timeCtrl = TextEditingController(text: TimeOfDay.now().format(context));
    String appointmentType = 'Consultation';
    String? selectedInsurance;
    bool hasInsurance = false;

    final insuranceProviders = [
      'Kuwait Insurance Co.',
      'Gulf Insurance Group (GIG)',
      'Warba Insurance',
      'Ahleia Insurance',
      'Enaya Healthcare',
    ];

    showDialog(
      context: context,
      builder: (dlgCtx) => StatefulBuilder(
        builder: (context, setDlgState) => AlertDialog(
          backgroundColor: AppTheme.surface,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(
            children: [
              Icon(Icons.calendar_month, color: Color(0xFF00BCD4), size: 24),
              SizedBox(width: 10),
              Text('Schedule Clinic Appointment', style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
            ],
          ),
          content: SizedBox(
            width: 420,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Patient Name:*', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
                  const SizedBox(height: 4),
                  TextField(
                    controller: patientCtrl,
                    autofocus: true,
                    decoration: InputDecoration(
                      hintText: 'e.g. Abdullah Al-Kandari',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Doctor / Practitioner:*', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
                            const SizedBox(height: 4),
                            TextField(
                              controller: doctorCtrl,
                              decoration: InputDecoration(
                                hintText: 'Doctor Name',
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Time:*', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
                            const SizedBox(height: 4),
                            TextField(
                              controller: timeCtrl,
                              decoration: InputDecoration(
                                hintText: 'e.g. 11:30 AM',
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  const Text('Consultation Type:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
                  const SizedBox(height: 4),
                  DropdownButtonFormField<String>(
                    initialValue: appointmentType,
                    decoration: InputDecoration(
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    ),
                    items: const [
                      DropdownMenuItem(value: 'Consultation', child: Text('General Consultation')),
                      DropdownMenuItem(value: 'Follow-up', child: Text('Follow-up Review')),
                      DropdownMenuItem(value: 'Treatment', child: Text('Clinical Treatment / Procedure')),
                      DropdownMenuItem(value: 'Dental Checkup', child: Text('Dental Checkup')),
                      DropdownMenuItem(value: 'Physiotherapy', child: Text('Physiotherapy Session')),
                    ],
                    onChanged: (val) {
                      if (val != null) setDlgState(() => appointmentType = val);
                    },
                  ),
                  const SizedBox(height: 12),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    dense: true,
                    title: const Text('Covered by Health Insurance', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                    value: hasInsurance,
                    onChanged: (val) {
                      setDlgState(() {
                        hasInsurance = val ?? false;
                        if (hasInsurance && selectedInsurance == null) {
                          selectedInsurance = insuranceProviders.first;
                        }
                      });
                    },
                  ),
                  if (hasInsurance) ...[
                    const SizedBox(height: 4),
                    DropdownButtonFormField<String>(
                      initialValue: selectedInsurance,
                      decoration: InputDecoration(
                        labelText: 'Insurance Provider',
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      ),
                      items: insuranceProviders.map((p) => DropdownMenuItem(value: p, child: Text(p, style: const TextStyle(fontSize: 12)))).toList(),
                      onChanged: (val) => setDlgState(() => selectedInsurance = val),
                    ),
                  ],
                ],
              ),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dlgCtx), child: const Text('Cancel')),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF00BCD4)),
              onPressed: () {
                final patientName = patientCtrl.text.trim();
                if (patientName.isEmpty) return;

                Navigator.pop(dlgCtx);
                final newAppt = {
                  'id': DateTime.now().millisecondsSinceEpoch,
                  'patientName': patientName,
                  'doctor': doctorCtrl.text.trim().isNotEmpty ? doctorCtrl.text.trim() : 'Dr. Sarah',
                  'time': timeCtrl.text.trim().isNotEmpty ? timeCtrl.text.trim() : '10:00 AM',
                  'type': appointmentType,
                  'status': 'waiting',
                  'insurance': hasInsurance ? selectedInsurance : null,
                };

                setState(() {
                  _todayAppointments.insert(0, newAppt);
                });

                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text('Appointment scheduled for $patientName'),
                    backgroundColor: AppTheme.success,
                  ),
                );
              },
              child: const Text('Schedule', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ],
        ),
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
    if (_categories.isEmpty) return const SizedBox.shrink();

    return Container(
      height: 52,
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(bottom: BorderSide(color: AppTheme.border)),
      ),
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: AppTheme.md, vertical: 8),
        children: [
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ChoiceChip(
              avatar: Icon(
                Icons.grid_view_rounded,
                size: 15,
                color: _selectedCategoryId == null ? Colors.white : AppTheme.textSecondary,
              ),
              label: Text(
                'All (${_products.length})',
                style: TextStyle(
                  color: _selectedCategoryId == null ? Colors.white : AppTheme.textPrimary,
                  fontSize: 12,
                  fontWeight: _selectedCategoryId == null ? FontWeight.bold : FontWeight.w500,
                ),
              ),
              selected: _selectedCategoryId == null,
              selectedColor: const Color(0xFF00BCD4),
              backgroundColor: AppTheme.background,
              side: BorderSide(
                color: _selectedCategoryId == null ? const Color(0xFF00BCD4) : AppTheme.border,
              ),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              onSelected: (_) {
                if (_selectedCategoryId != null) {
                  setState(() => _selectedCategoryId = null);
                  _loadProducts();
                }
              },
            ),
          ),
          ..._categories.map((cat) {
            final isSelected = _selectedCategoryId == cat.id;
            final count = cat.productCount > 0 
                ? cat.productCount 
                : _products.where((p) => p.categoryId == cat.id).length;
            return Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                avatar: Icon(
                  Icons.medical_services_outlined,
                  size: 15,
                  color: isSelected ? Colors.white : const Color(0xFF00BCD4),
                ),
                label: Text(
                  count > 0 ? '${cat.name} ($count)' : cat.name,
                  style: TextStyle(
                    color: isSelected ? Colors.white : AppTheme.textPrimary,
                    fontSize: 12,
                    fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                  ),
                ),
                selected: isSelected,
                selectedColor: const Color(0xFF00BCD4),
                backgroundColor: AppTheme.background,
                side: BorderSide(
                  color: isSelected ? const Color(0xFF00BCD4) : AppTheme.border,
                ),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                onSelected: (_) {
                  setState(() {
                    _selectedCategoryId = isSelected ? null : cat.id;
                  });
                  _loadProducts();
                },
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
          child: TextField(
            controller: _searchController,
            style: const TextStyle(color: AppTheme.textPrimary),
            decoration: InputDecoration(
              hintText: 'Search medical services, consultations...',
              prefixIcon: const Icon(Icons.search, color: AppTheme.textMuted),
              suffixIcon: _searchQuery.isNotEmpty
                  ? IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      onPressed: () {
                        _searchController.clear();
                        setState(() => _searchQuery = '');
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
                borderSide: const BorderSide(color: Color(0xFF00BCD4), width: 2),
              ),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            ),
            onChanged: (value) => setState(() => _searchQuery = value),
          ),
        ),
        Expanded(
          child: _isLoading
              ? const Center(child: CircularProgressIndicator(color: Color(0xFF00BCD4)))
              : displayProducts.isEmpty
                  ? const Center(
                      child: Text(
                        'No services found',
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
            'Services: ${_products.length} • Appointments: ${_todayAppointments.length}',
            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
          ),
          const Spacer(),
          const SyncStatusBadge(isCompact: true),
        ],
      ),
    );
  }
}
