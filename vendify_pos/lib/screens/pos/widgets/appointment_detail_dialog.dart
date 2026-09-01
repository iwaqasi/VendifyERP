import 'package:flutter/material.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';

/// Result returned when appointment is completed and services added to cart
class AppointmentCompleteResult {
  final List<CartItemData> services;
  final String customerName;
  final int? contactId;

  AppointmentCompleteResult({
    required this.services,
    required this.customerName,
    this.contactId,
  });
}

class CartItemData {
  final String name;
  final double price;
  final int durationMinutes;

  CartItemData({required this.name, required this.price, required this.durationMinutes});
}

/// Shows appointment details with status management.
/// When completed, services are added to the POS cart for payment.
class AppointmentDetailDialog extends StatefulWidget {
  final Map<String, dynamic> appointment;
  final PosService posService;
  final List<Map<String, dynamic>> staff;
  final Function(AppointmentCompleteResult) onComplete;
  final VoidCallback onStatusChanged;

  const AppointmentDetailDialog({
    super.key,
    required this.appointment,
    required this.posService,
    this.staff = const [],
    required this.onComplete,
    required this.onStatusChanged,
  });

  static Future<void> show({
    required BuildContext context,
    required Map<String, dynamic> appointment,
    required PosService posService,
    List<Map<String, dynamic>> staff = const [],
    required Function(AppointmentCompleteResult) onComplete,
    required VoidCallback onStatusChanged,
  }) {
    return showDialog(
      context: context,
      builder: (_) => AppointmentDetailDialog(
        appointment: appointment,
        posService: posService,
        staff: staff,
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

  Map<String, dynamic> get _appt => widget.appointment;

  @override
  void initState() {
    super.initState();
    _status = _appt['status'] ?? 'scheduled';
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

  List<String> get _serviceNames {
    final name = (_appt['service'] ?? '').toString();
    return name.split(',').map((s) => s.trim()).where((s) => s.isNotEmpty).toList();
  }

  double get _totalPrice {
    final p = _appt['price'] ?? 0;
    if (p is double) return p;
    if (p is int) return p.toDouble();
    if (p is String) return double.tryParse(p) ?? 0;
    return 0;
  }

  int get _totalDuration {
    return (_appt['duration'] ?? 30);
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
    // Build services list for the cart
    final services = _serviceNames.map((name) {
      // Distribute price equally among services
      final pricePerService = _serviceNames.isNotEmpty
          ? _totalPrice / _serviceNames.length
          : _totalPrice.toDouble();
      return CartItemData(
        name: name,
        price: pricePerService,
        durationMinutes: _serviceNames.isNotEmpty
            ? (_totalDuration / _serviceNames.length).round()
            : _totalDuration,
      );
    }).toList();

    // Mark as completed
    _updateStatus('completed');

    // Return result to add to cart
    widget.onComplete(AppointmentCompleteResult(
      services: services,
      customerName: _appt['customerName'] ?? 'Walk-in Customer',
      contactId: _appt['contactId'],
    ));

    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        width: 440,
        constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.8),
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
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(color: _statusColor.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(4)),
                  child: Text(_statusLabel, style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: _statusColor)),
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
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Time & Duration
          _buildInfoRow(Icons.access_time, 'Time', '${_appt['time'] ?? 'N/A'}  •  $_totalDuration min'),
          const SizedBox(height: 14),

          // Services
          _buildSectionTitle('Services'),
          const SizedBox(height: 8),
          ..._serviceNames.map((name) => Container(
            margin: const EdgeInsets.only(bottom: 6),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: AppTheme.background, borderRadius: BorderRadius.circular(8)),
            child: Row(
              children: [
                Container(
                  width: 28, height: 28,
                  decoration: BoxDecoration(color: const Color(0xFFE91E63).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                  child: const Icon(Icons.spa, size: 14, color: Color(0xFFE91E63)),
                ),
                const SizedBox(width: 10),
                Expanded(child: Text(name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppTheme.textPrimary))),
                if (_serviceNames.length > 1)
                  Text('KD ${(_totalPrice / _serviceNames.length).toStringAsFixed(3)}', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
              ],
            ),
          )),

          // Total price
          if (_totalPrice > 0) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: AppTheme.primary.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(8)),
              child: Row(
                children: [
                  const Icon(Icons.receipt_long, size: 16, color: AppTheme.primary),
                  const SizedBox(width: 8),
                  const Text('Total', style: TextStyle(fontSize: 13, color: AppTheme.textSecondary)),
                  const Spacer(),
                  Text('KD ${_totalPrice.toStringAsFixed(3)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.primary)),
                ],
              ),
            ),
          ],
          const SizedBox(height: 14),

          // Staff
          if (_appt['staff'] != null && (_appt['staff'] ?? '').toString().isNotEmpty)
            _buildInfoRow(Icons.person, 'Staff', (_appt['staff'] ?? '').toString()),

          // Notes
          if (_appt['notes'] != null && (_appt['notes'] ?? '').toString().isNotEmpty) ...[
            const SizedBox(height: 14),
            _buildInfoRow(Icons.note, 'Notes', (_appt['notes'] ?? '').toString()),
          ],

          // Timer (if in progress)
          if (_status == 'in_progress') ...[
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFFF9800).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFFF9800).withValues(alpha: 0.3)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.timer, color: Color(0xFFFF9800), size: 20),
                  const SizedBox(width: 10),
                  const Text(
                    'Service in progress',
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
