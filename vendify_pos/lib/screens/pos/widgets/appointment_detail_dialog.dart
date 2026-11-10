import 'package:flutter/material.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';

/// Result returned when appointment is completed and services added to cart
class AppointmentCompleteResult {
  final List<CartItemData> services;
  final String customerName;
  final int? contactId;
  final int? staffId;
  final String? staffName;
  final int? appointmentId;

  AppointmentCompleteResult({
    required this.services,
    required this.customerName,
    this.contactId,
    this.staffId,
    this.staffName,
    this.appointmentId,
  });
}

class CartItemData {
  final String name;
  final double price;
  final int durationMinutes;

  CartItemData({required this.name, required this.price, required this.durationMinutes});
}

class _ServiceItem {
  String name;
  double price;
  int durationMinutes;

  _ServiceItem({required this.name, required this.price, required this.durationMinutes});
}

/// Shows appointment details with status and dynamic service management.
/// Supports adding/removing services mid-appointment (Scenario 1).
/// When completed, all services are loaded into the POS cart with staff attribution.
class AppointmentDetailDialog extends StatefulWidget {
  final Map<String, dynamic> appointment;
  final PosService posService;
  final List<Map<String, dynamic>> staff;
  final List<Map<String, dynamic>> availableServices;
  final Function(AppointmentCompleteResult) onComplete;
  final VoidCallback onStatusChanged;

  const AppointmentDetailDialog({
    super.key,
    required this.appointment,
    required this.posService,
    this.staff = const [],
    this.availableServices = const [],
    required this.onComplete,
    required this.onStatusChanged,
  });

  static Future<void> show({
    required BuildContext context,
    required Map<String, dynamic> appointment,
    required PosService posService,
    List<Map<String, dynamic>> staff = const [],
    List<Map<String, dynamic>> availableServices = const [],
    required Function(AppointmentCompleteResult) onComplete,
    required VoidCallback onStatusChanged,
  }) {
    return showDialog(
      context: context,
      builder: (_) => AppointmentDetailDialog(
        appointment: appointment,
        posService: posService,
        staff: staff,
        availableServices: availableServices,
        onComplete: onComplete,
        onStatusChanged: onStatusChanged,
      ),
    );
  }

  @override
  State<AppointmentDetailDialog> createState() => _AppointmentDetailDialogState();
}

class _AppointmentDetailDialogState extends State<AppointmentDetailDialog> {
  late String _status;
  bool _isUpdating = false;
  bool _isSavingServices = false;
  late List<_ServiceItem> _services;

  Map<String, dynamic> get _appt => widget.appointment;

  @override
  void initState() {
    super.initState();
    _status = _appt['status'] ?? 'scheduled';
    _initServices();
  }

  void _initServices() {
    final rawName = (_appt['service'] ?? '').toString();
    final names = rawName.split(',').map((s) => s.trim()).where((s) => s.isNotEmpty).toList();

    final totalPrice = _parsePrice(_appt['price']);
    final totalDuration = (_appt['duration'] is int) ? _appt['duration'] as int : int.tryParse('${_appt['duration']}') ?? 30;

    if (names.isEmpty) {
      _services = [
        _ServiceItem(
          name: 'General Service',
          price: totalPrice > 0 ? totalPrice : 0,
          durationMinutes: totalDuration > 0 ? totalDuration : 30,
        )
      ];
    } else {
      final unitPrice = names.isNotEmpty ? (totalPrice / names.length) : totalPrice;
      final unitDuration = names.isNotEmpty ? (totalDuration / names.length).round() : totalDuration;

      _services = names.map((n) => _ServiceItem(
        name: n,
        price: unitPrice,
        durationMinutes: unitDuration > 0 ? unitDuration : 30,
      )).toList();
    }
  }

  double _parsePrice(dynamic p) {
    if (p is double) return p;
    if (p is int) return p.toDouble();
    if (p is String) return double.tryParse(p) ?? 0;
    return 0;
  }

  Color get _statusColor {
    switch (_status) {
      case 'in_progress':
        return const Color(0xFFFF9800);
      case 'completed':
      case 'finished':
        return AppTheme.success;
      case 'cancelled':
        return AppTheme.error;
      default:
        return AppTheme.primary;
    }
  }

  String get _statusLabel {
    switch (_status) {
      case 'scheduled':
        return 'Scheduled';
      case 'confirmed':
        return 'Confirmed';
      case 'in_progress':
        return 'In Progress';
      case 'completed':
      case 'finished':
        return 'Completed';
      case 'cancelled':
        return 'Cancelled';
      default:
        return _status.toUpperCase();
    }
  }

  double get _totalPrice => _services.fold(0, (sum, s) => sum + s.price);
  int get _totalDuration => _services.fold(0, (sum, s) => sum + s.durationMinutes);

  Future<void> _syncServicesToBackend() async {
    final id = _appt['id'];
    if (id == null) return;

    setState(() => _isSavingServices = true);
    try {
      final payload = {
        'services': _services.map((s) => {
          'name': s.name,
          'price': s.price,
          'duration_minutes': s.durationMinutes,
        }).toList(),
      };
      await widget.posService.updateAppointmentServices(id, payload);
      widget.onStatusChanged();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to update services: $e'), backgroundColor: AppTheme.error),
        );
      }
    } finally {
      if (mounted) setState(() => _isSavingServices = false);
    }
  }

  void _addServiceToAppointment(Map<String, dynamic> svc) async {
    final name = (svc['name'] ?? svc['service_name'] ?? '').toString();
    double price = _parsePrice(svc['price'] ?? svc['sell_price_inc_tax'] ?? 0);
    final duration = (svc['duration'] ?? svc['service_duration_minutes'] ?? 30) as int;

    // Check duplicate
    if (_services.any((s) => s.name.toLowerCase() == name.toLowerCase())) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$name is already in this appointment'), backgroundColor: AppTheme.warning),
      );
      return;
    }

    // If price is 0 or flexible, prompt with denomination buttons
    if (price <= 0) {
      final enteredPrice = await _showPriceDialog(
        context: context,
        title: 'Set Price for $name',
        initialPrice: 0,
      );
      if (enteredPrice == null) return; // user cancelled
      price = enteredPrice;
    }

    setState(() {
      _services.add(_ServiceItem(
        name: name,
        price: price,
        durationMinutes: duration,
      ));
    });

    await _syncServicesToBackend();

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Added "$name" (KD ${price.toStringAsFixed(3)}) to appointment'),
          backgroundColor: AppTheme.success,
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  void _editServicePrice(int index) async {
    final item = _services[index];
    final newPrice = await _showPriceDialog(
      context: context,
      title: 'Update Price for ${item.name}',
      initialPrice: item.price,
    );
    if (newPrice != null) {
      setState(() {
        item.price = newPrice;
      });
      await _syncServicesToBackend();
    }
  }

  void _removeService(int index) async {
    if (_services.length <= 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Appointment must have at least one service'), backgroundColor: AppTheme.warning),
      );
      return;
    }

    final removed = _services[index].name;
    setState(() {
      _services.removeAt(index);
    });

    await _syncServicesToBackend();

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Removed "$removed"'), backgroundColor: AppTheme.info, duration: const Duration(seconds: 1)),
      );
    }
  }

  Widget _denomChip(String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(6),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
        decoration: BoxDecoration(
          color: AppTheme.primary.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: AppTheme.primary.withValues(alpha: 0.3)),
        ),
        child: Text(
          label,
          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.primary),
        ),
      ),
    );
  }

  Widget _presetChip(String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(6),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
        decoration: BoxDecoration(
          color: const Color(0xFFE91E63).withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: const Color(0xFFE91E63).withValues(alpha: 0.25)),
        ),
        child: Text(
          label,
          style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFFE91E63)),
        ),
      ),
    );
  }

  Future<double?> _showPriceDialog({
    required BuildContext context,
    required String title,
    required double initialPrice,
  }) async {
    double currentPrice = initialPrice > 0 ? initialPrice : 0.0;
    final controller = TextEditingController(
      text: currentPrice > 0 ? currentPrice.toStringAsFixed(3) : '',
    );

    return showDialog<double>(
      context: context,
      builder: (dlgCtx) {
        return StatefulBuilder(
          builder: (context, setDlgState) {
            void addAmount(double amt) {
              final val = double.tryParse(controller.text) ?? 0.0;
              final newVal = val + amt;
              controller.text = newVal.toStringAsFixed(3);
              setDlgState(() {});
            }

            void setAmount(double amt) {
              controller.text = amt.toStringAsFixed(3);
              setDlgState(() {});
            }

            return AlertDialog(
              backgroundColor: AppTheme.surface,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE91E63).withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(Icons.monetization_on, color: Color(0xFFE91E63), size: 20),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      title,
                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                    ),
                  ),
                ],
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Service Price (KD):', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
                    const SizedBox(height: 6),
                    TextField(
                      controller: controller,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      autofocus: true,
                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: AppTheme.primary),
                      decoration: InputDecoration(
                        prefixText: 'KD ',
                        prefixStyle: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.primary),
                        suffixIcon: IconButton(
                          icon: const Icon(Icons.clear, size: 18),
                          onPressed: () {
                            controller.clear();
                            setDlgState(() {});
                          },
                        ),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      ),
                    ),
                    const SizedBox(height: 12),
                    const Text('Quick Denominations (+KD):', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppTheme.textMuted)),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        _denomChip('+0.500', () => addAmount(0.5)),
                        _denomChip('+1.000', () => addAmount(1.0)),
                        _denomChip('+2.000', () => addAmount(2.0)),
                        _denomChip('+5.000', () => addAmount(5.0)),
                        _denomChip('+10.00', () => addAmount(10.0)),
                        _denomChip('+20.00', () => addAmount(20.0)),
                        _denomChip('+50.00', () => addAmount(50.0)),
                      ],
                    ),
                    const SizedBox(height: 8),
                    const Text('Presets:', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: AppTheme.textMuted)),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        _presetChip('KD 5', () => setAmount(5.0)),
                        _presetChip('KD 10', () => setAmount(10.0)),
                        _presetChip('KD 15', () => setAmount(15.0)),
                        _presetChip('KD 25', () => setAmount(25.0)),
                        _presetChip('KD 35', () => setAmount(35.0)),
                        _presetChip('KD 50', () => setAmount(50.0)),
                      ],
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dlgCtx, null),
                  child: const Text('Cancel', style: TextStyle(color: AppTheme.textMuted)),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  onPressed: () {
                    final price = double.tryParse(controller.text) ?? 0.0;
                    Navigator.pop(dlgCtx, price);
                  },
                  child: const Text('Confirm', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  void _showAddCustomServiceDialog() async {
    final nameCtrl = TextEditingController();
    double price = 0.0;
    int duration = 30;

    await showDialog(
      context: context,
      builder: (dlgCtx) {
        return StatefulBuilder(
          builder: (context, setDlgState) {
            void addAmt(double amt) {
              price += amt;
              setDlgState(() {});
            }
            void setAmt(double amt) {
              price = amt;
              setDlgState(() {});
            }

            return AlertDialog(
              backgroundColor: AppTheme.surface,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: const Row(
                children: [
                  Icon(Icons.add_circle, color: Color(0xFFE91E63)),
                  SizedBox(width: 8),
                  Text('Add Custom Service', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                ],
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Service Name:*', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 4),
                    TextField(
                      controller: nameCtrl,
                      autofocus: true,
                      decoration: InputDecoration(
                        hintText: 'e.g. Special Beard Trim, Face Bleach',
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text('Price: KD ${price.toStringAsFixed(3)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.primary)),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        _denomChip('+0.500', () => addAmt(0.5)),
                        _denomChip('+1.000', () => addAmt(1.0)),
                        _denomChip('+2.000', () => addAmt(2.0)),
                        _denomChip('+5.000', () => addAmt(5.0)),
                        _denomChip('+10.00', () => addAmt(10.0)),
                        _denomChip('+20.00', () => addAmt(20.0)),
                        _presetChip('Clear', () => setAmt(0.0)),
                      ],
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(onPressed: () => Navigator.pop(dlgCtx), child: const Text('Cancel')),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
                  onPressed: () {
                    final name = nameCtrl.text.trim();
                    if (name.isEmpty) return;
                    Navigator.pop(dlgCtx);
                    _addServiceToAppointment({
                      'name': name,
                      'price': price,
                      'duration': duration,
                    });
                  },
                  child: const Text('Add Service', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  void _showAddServiceSheet() {
    final available = widget.availableServices;
    if (available.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No catalog services available to add'), backgroundColor: AppTheme.warning),
      );
      return;
    }

    String searchQuery = '';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppTheme.surface,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (bottomSheetCtx, setSheetState) {
            final filtered = available.where((s) {
              if (searchQuery.trim().isEmpty) return true;
              final n = (s['name'] ?? '').toString().toLowerCase();
              return n.contains(searchQuery.trim().toLowerCase());
            }).toList();

            return Container(
              padding: const EdgeInsets.all(16),
              constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.75),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Select Add-on Service', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                      IconButton(icon: const Icon(Icons.close, size: 20), onPressed: () => Navigator.pop(ctx)),
                    ],
                  ),
                  const SizedBox(height: 8),

                  // Search Bar + Custom Service Button
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          decoration: InputDecoration(
                            hintText: 'Search service...',
                            prefixIcon: const Icon(Icons.search, size: 18),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                            isDense: true,
                          ),
                          onChanged: (val) {
                            setSheetState(() => searchQuery = val);
                          },
                        ),
                      ),
                      const SizedBox(width: 8),
                      ElevatedButton.icon(
                        icon: const Icon(Icons.add, size: 16, color: Colors.white),
                        label: const Text('Custom', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFE91E63),
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        onPressed: () {
                          Navigator.pop(ctx);
                          _showAddCustomServiceDialog();
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),

                  Expanded(
                    child: ListView.separated(
                      itemCount: filtered.length,
                      separatorBuilder: (_, __) => const Divider(height: 1, color: AppTheme.border),
                      itemBuilder: (ctx, i) {
                        final s = filtered[i];
                        final name = (s['name'] ?? '').toString();
                        final price = _parsePrice(s['price'] ?? s['sell_price_inc_tax'] ?? 0);
                        final dur = s['duration'] ?? 30;

                        final isAlreadyAdded = _services.any((item) => item.name.toLowerCase() == name.toLowerCase());

                        return ListTile(
                          dense: true,
                          leading: Container(
                            width: 34, height: 34,
                            decoration: BoxDecoration(color: const Color(0xFFE91E63).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
                            child: const Icon(Icons.spa, color: Color(0xFFE91E63), size: 18),
                          ),
                          title: Text(name, style: TextStyle(fontWeight: FontWeight.w600, color: isAlreadyAdded ? AppTheme.textMuted : AppTheme.textPrimary)),
                          subtitle: Text(
                            price <= 0 ? '$dur min  •  Flexible Price (Tap to enter)' : '$dur min',
                            style: TextStyle(fontSize: 11, color: price <= 0 ? const Color(0xFFE91E63) : AppTheme.textSecondary, fontWeight: price <= 0 ? FontWeight.bold : FontWeight.normal),
                          ),
                          trailing: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                price > 0 ? 'KD ${price.toStringAsFixed(3)}' : 'Set Price',
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  color: price > 0 ? AppTheme.primary : const Color(0xFFE91E63),
                                  fontSize: 13,
                                ),
                              ),
                              const SizedBox(width: 8),
                              isAlreadyAdded
                                  ? const Icon(Icons.check_circle, color: AppTheme.success, size: 18)
                                  : const Icon(Icons.add_circle_outline, color: AppTheme.primary, size: 20),
                            ],
                          ),
                          onTap: isAlreadyAdded ? null : () {
                            Navigator.pop(ctx);
                            _addServiceToAppointment(s);
                          },
                        );
                      },
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _updateStatus(String newStatus) async {
    setState(() => _isUpdating = true);
    try {
      final id = _appt['id'];
      if (id != null) {
        await widget.posService.updateAppointmentStatus(id, newStatus);
      }
      setState(() {
        _status = newStatus;
        _isUpdating = false;
      });
      widget.onStatusChanged();
    } catch (e) {
      setState(() => _isUpdating = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: $e'), backgroundColor: AppTheme.error),
        );
      }
    }
  }

  void _completeAndAddToCart() {
    // Build cart items for all current services (including add-ons)
    final cartItems = _services.map((s) => CartItemData(
      name: s.name,
      price: s.price,
      durationMinutes: s.durationMinutes,
    )).toList();

    // Mark as completed
    _updateStatus('completed');

    // Extract staff info
    int? staffId = _appt['staff_id'];
    String? staffName = (_appt['staff'] ?? '').toString();

    // Return result with staff & appointment attribution
    widget.onComplete(AppointmentCompleteResult(
      services: cartItems,
      customerName: _appt['customerName'] ?? 'Walk-in Customer',
      contactId: _appt['contactId'],
      staffId: staffId,
      staffName: staffName.isNotEmpty ? staffName : null,
      appointmentId: _appt['id'],
    ));

    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        width: 480,
        constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.85),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _buildHeader(),
            Flexible(child: _buildContent()),
            _buildActions(),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: AppTheme.border))),
      child: Row(
        children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(color: _statusColor.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(12)),
            child: Icon(
              _status == 'in_progress' ? Icons.play_circle :
              _status == 'completed' || _status == 'finished' ? Icons.check_circle : Icons.calendar_today,
              color: _statusColor, size: 24,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _appt['customerName'] ?? 'Unknown Customer',
                  style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(color: _statusColor.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(4)),
                      child: Text(_statusLabel, style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: _statusColor)),
                    ),
                    if (_isSavingServices) ...[
                      const SizedBox(width: 8),
                      const SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 1.5)),
                      const SizedBox(width: 4),
                      const Text('Saving...', style: TextStyle(fontSize: 10, color: AppTheme.textMuted)),
                    ],
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close, color: AppTheme.textMuted, size: 22),
            onPressed: () => Navigator.of(context).pop(),
          ),
        ],
      ),
    );
  }

  Widget _buildContent() {
    final canModifyServices = _status != 'completed' && _status != 'finished' && _status != 'cancelled';

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Time & Total Duration
          _buildInfoRow(Icons.access_time, 'Time', '${_appt['time'] ?? 'N/A'}  •  $_totalDuration min total'),
          const SizedBox(height: 14),

          // Staff
          if (_appt['staff'] != null && (_appt['staff'] ?? '').toString().isNotEmpty) ...[
            _buildInfoRow(Icons.person, 'Stylist', (_appt['staff'] ?? '').toString()),
            const SizedBox(height: 14),
          ],

          // Services Section with Dynamic Add-ons (Scenario 1)
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              _buildSectionTitle('Services (${_services.length})'),
              if (canModifyServices && widget.availableServices.isNotEmpty)
                InkWell(
                  onTap: _showAddServiceSheet,
                  borderRadius: BorderRadius.circular(6),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE91E63).withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: const Color(0xFFE91E63).withValues(alpha: 0.3)),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.add, size: 14, color: Color(0xFFE91E63)),
                        SizedBox(width: 4),
                        Text('Add-on Service', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFFE91E63))),
                      ],
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),

          // Service Items List
          ...List.generate(_services.length, (i) {
            final s = _services[i];
            return Container(
              margin: const EdgeInsets.only(bottom: 6),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(color: AppTheme.background, borderRadius: BorderRadius.circular(8)),
              child: Row(
                children: [
                  Container(
                    width: 28, height: 28,
                    decoration: BoxDecoration(color: const Color(0xFFE91E63).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                    child: const Icon(Icons.spa, size: 14, color: Color(0xFFE91E63)),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(s.name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppTheme.textPrimary)),
                        Text('${s.durationMinutes} min', style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                      ],
                    ),
                  ),
                  InkWell(
                    onTap: canModifyServices ? () => _editServicePrice(i) : null,
                    borderRadius: BorderRadius.circular(6),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: AppTheme.primary.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: AppTheme.primary.withValues(alpha: 0.2)),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text('KD ${s.price.toStringAsFixed(3)}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.primary)),
                          if (canModifyServices) ...[
                            const SizedBox(width: 4),
                            const Icon(Icons.edit, size: 12, color: AppTheme.primary),
                          ],
                        ],
                      ),
                    ),
                  ),
                  if (canModifyServices && _services.length > 1) ...[
                    const SizedBox(width: 6),
                    IconButton(
                      icon: const Icon(Icons.close, size: 16, color: AppTheme.textMuted),
                      splashRadius: 16,
                      onPressed: () => _removeService(i),
                      tooltip: 'Remove add-on',
                    ),
                  ],
                ],
              ),
            );
          }),

          // Total Price Summary Box
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppTheme.primary.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AppTheme.primary.withValues(alpha: 0.2)),
            ),
            child: Row(
              children: [
                const Icon(Icons.receipt_long, size: 18, color: AppTheme.primary),
                const SizedBox(width: 8),
                Text('Total ($_totalDuration min)', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: AppTheme.textSecondary)),
                const Spacer(),
                Text('KD ${_totalPrice.toStringAsFixed(3)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.primary)),
              ],
            ),
          ),
          const SizedBox(height: 14),

          // Notes
          if (_appt['notes'] != null && (_appt['notes'] ?? '').toString().isNotEmpty) ...[
            _buildInfoRow(Icons.note, 'Notes', (_appt['notes'] ?? '').toString()),
            const SizedBox(height: 14),
          ],

          // Timer Banner (if in progress)
          if (_status == 'in_progress') ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFFF9800).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFFF9800).withValues(alpha: 0.3)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.timer, color: Color(0xFFFF9800), size: 20),
                  SizedBox(width: 10),
                  Text(
                    'Client is on the chair (Service in progress)',
                    style: TextStyle(fontWeight: FontWeight.w600, color: Color(0xFFFF9800), fontSize: 13),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildActions() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppTheme.border))),
      child: Row(
        children: [
          // Status action buttons based on current state
          if (_status == 'scheduled' || _status == 'confirmed') ...[
            Expanded(
              child: OutlinedButton.icon(
                onPressed: _isUpdating ? null : () => _updateStatus('cancelled'),
                icon: const Icon(Icons.cancel_outlined, size: 16),
                label: const Text('Cancel', style: TextStyle(fontSize: 12)),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.error,
                  side: BorderSide(color: AppTheme.error.withValues(alpha: 0.3)),
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: ElevatedButton.icon(
                onPressed: _isUpdating ? null : () => _updateStatus('in_progress'),
                icon: const Icon(Icons.play_arrow, size: 18),
                label: const Text('Start Service', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFFF9800),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ),
          ] else if (_status == 'in_progress') ...[
            Expanded(
              child: OutlinedButton.icon(
                onPressed: _isUpdating ? null : () => _updateStatus('cancelled'),
                icon: const Icon(Icons.cancel_outlined, size: 16),
                label: const Text('Cancel', style: TextStyle(fontSize: 12)),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.error,
                  side: BorderSide(color: AppTheme.error.withValues(alpha: 0.3)),
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: ElevatedButton.icon(
                onPressed: _isUpdating ? null : _completeAndAddToCart,
                icon: const Icon(Icons.shopping_cart, size: 16),
                label: const Text('Complete & Pay', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.success,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ),
          ] else if (_status == 'completed' || _status == 'finished') ...[
            Expanded(
              child: ElevatedButton.icon(
                onPressed: _completeAndAddToCart,
                icon: const Icon(Icons.shopping_cart, size: 16),
                label: const Text('Add to Cart for Payment', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ),
          ] else if (_status == 'cancelled') ...[
            const Expanded(
              child: Center(
                child: Text('This appointment was cancelled', style: TextStyle(color: AppTheme.textMuted, fontSize: 12)),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, size: 16, color: AppTheme.textMuted),
        const SizedBox(width: 8),
        Text('$label: ', style: const TextStyle(fontSize: 12, color: AppTheme.textMuted)),
        Expanded(child: Text(value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textPrimary))),
      ],
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(title, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.textSecondary));
  }
}
