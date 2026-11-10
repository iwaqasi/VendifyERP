import 'package:flutter/material.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/services/print_service.dart';

/// Dialog showing End of Day (EOD) Staff Performance & Scorecards.
/// Displays services count, revenue, commission, and tips per staff member (Scenario 3).
/// Allows one-click daily cash tip payouts and printing EOD thermal slips.
class StaffDailySummaryDialog extends StatefulWidget {
  final PosService posService;
  final int locationId;

  const StaffDailySummaryDialog({
    super.key,
    required this.posService,
    required this.locationId,
  });

  static Future<void> show({
    required BuildContext context,
    required PosService posService,
    required int locationId,
  }) {
    return showDialog(
      context: context,
      builder: (_) => StaffDailySummaryDialog(
        posService: posService,
        locationId: locationId,
      ),
    );
  }

  @override
  State<StaffDailySummaryDialog> createState() => _StaffDailySummaryDialogState();
}

class _StaffDailySummaryDialogState extends State<StaffDailySummaryDialog> {
  DateTime _selectedDate = DateTime.now();
  bool _isLoading = true;
  Map<String, dynamic> _summaryData = {};
  int? _expandedStaffId;

  @override
  void initState() {
    super.initState();
    _loadSummary();
  }

  Future<void> _loadSummary() async {
    setState(() => _isLoading = true);
    final dateStr = '${_selectedDate.year}-${_selectedDate.month.toString().padLeft(2, '0')}-${_selectedDate.day.toString().padLeft(2, '0')}';
    try {
      final res = await widget.posService.getStaffDailySummary(date: dateStr, locationId: widget.locationId);
      if (mounted) {
        setState(() {
          _summaryData = res;
          _isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _changeDate(int offsetDays) {
    setState(() {
      _selectedDate = _selectedDate.add(Duration(days: offsetDays));
    });
    _loadSummary();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.dark(
            primary: Color(0xFFE91E63), onPrimary: Colors.white,
            surface: AppTheme.surface, onSurface: AppTheme.textPrimary,
          ),
        ),
        child: child!,
      ),
    );
    if (picked != null) {
      setState(() => _selectedDate = picked);
      _loadSummary();
    }
  }

  Future<void> _payoutTipsForStaff(int staffId, String staffName, double pendingTips) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppTheme.surface,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        title: const Text('Confirm Tip Payout', style: TextStyle(color: AppTheme.textPrimary, fontSize: 16)),
        content: Text(
          'Pay out KD ${pendingTips.toStringAsFixed(3)} in cash tips to $staffName?',
          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.success, foregroundColor: Colors.white),
            child: const Text('Confirm Payout'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        final res = await widget.posService.payoutStaffTips(
          staffId: staffId,
          notes: 'Daily tip payout for date: ${_selectedDate.toIso8601String().substring(0, 10)}',
        );
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(res['message'] ?? 'Tips paid out successfully!'),
              backgroundColor: AppTheme.success,
            ),
          );
          _loadSummary();
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Failed: $e'), backgroundColor: AppTheme.error),
          );
        }
      }
    }
  }

  void _printStaffSlip(Map<String, dynamic> staff) async {
    final staffName = staff['staff_name'] ?? 'Staff';
    final servicesCount = staff['services_count'] ?? 0;
    final revenue = (staff['service_revenue'] as num?)?.toDouble() ?? 0;
    final commission = (staff['commission_earned'] as num?)?.toDouble() ?? 0;
    final rate = (staff['commission_percent'] as num?)?.toDouble() ?? 0;
    final tips = (staff['tips_total'] as num?)?.toDouble() ?? 0;
    final payout = (staff['total_payout'] as num?)?.toDouble() ?? 0;
    final dateStr = '${_selectedDate.day}/${_selectedDate.month}/${_selectedDate.year}';

    final text = '''
================================
     STAFF DAILY SHIFT SLIP     
================================
Date:       $dateStr
Staff:      $staffName
--------------------------------
Services Done:    $servicesCount
Service Revenue:  KD ${revenue.toStringAsFixed(3)}
Commission ($rate%): KD ${commission.toStringAsFixed(3)}
Tips Received:    KD ${tips.toStringAsFixed(3)}
--------------------------------
TOTAL PAYOUT:     KD ${payout.toStringAsFixed(3)}
================================
Staff Signature: _______________
Printed via Vendify POS
''';

    await PrintService().printSlip(title: 'Staff EOD Slip - $staffName', content: text);

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Printed EOD slip for $staffName'),
          backgroundColor: AppTheme.success,
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  void _printAllStaffSummary() async {
    final totalServices = _summaryData['total_services'] ?? 0;
    final totalRevenue = (_summaryData['total_revenue'] as num?)?.toDouble() ?? 0;
    final totalCommission = (_summaryData['total_commission'] as num?)?.toDouble() ?? 0;
    final totalTips = (_summaryData['total_tips'] as num?)?.toDouble() ?? 0;
    final totalPayout = (_summaryData['total_staff_payout'] as num?)?.toDouble() ?? 0;
    final dateStr = '${_selectedDate.day}/${_selectedDate.month}/${_selectedDate.year}';

    final staffList = (_summaryData['staff'] as List?) ?? [];

    final buffer = StringBuffer();
    buffer.writeln('================================');
    buffer.writeln('   SALON DAILY STAFF SUMMARY    ');
    buffer.writeln('================================');
    buffer.writeln('Date: $dateStr');
    buffer.writeln('--------------------------------');
    for (final st in staffList) {
      final name = st['staff_name'] ?? 'Staff';
      final cnt = st['services_count'] ?? 0;
      final rev = (st['service_revenue'] as num?)?.toDouble() ?? 0;
      final t = (st['tips_total'] as num?)?.toDouble() ?? 0;
      final p = (st['total_payout'] as num?)?.toDouble() ?? 0;
      buffer.writeln('$name: $cnt svc | KD ${rev.toStringAsFixed(3)} | Tip: KD ${t.toStringAsFixed(3)} | Pay: KD ${p.toStringAsFixed(3)}');
    }
    buffer.writeln('--------------------------------');
    buffer.writeln('Total Services:   $totalServices');
    buffer.writeln('Total Revenue:    KD ${totalRevenue.toStringAsFixed(3)}');
    buffer.writeln('Total Commission: KD ${totalCommission.toStringAsFixed(3)}');
    buffer.writeln('Total Tips:       KD ${totalTips.toStringAsFixed(3)}');
    buffer.writeln('GRAND PAYOUT:     KD ${totalPayout.toStringAsFixed(3)}');
    buffer.writeln('================================');

    await PrintService().printSlip(title: 'Salon Daily Staff Summary - $dateStr', content: buffer.toString());

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Printed Team Daily Summary'), backgroundColor: AppTheme.success),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final staffList = (_summaryData['staff'] as List?) ?? [];
    final isToday = _selectedDate.year == DateTime.now().year &&
        _selectedDate.month == DateTime.now().month &&
        _selectedDate.day == DateTime.now().day;

    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        width: 720,
        constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.9),
        child: Column(
          children: [
            // Dialog Header
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: AppTheme.border))),
              child: Row(
                children: [
                  Container(
                    width: 40, height: 40,
                    decoration: BoxDecoration(color: const Color(0xFFE91E63).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
                    child: const Icon(Icons.people_alt, color: Color(0xFFE91E63), size: 22),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Staff Performance & Daily Summary', style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                        Text('End of day scorecard, commission calculations & staff tips', style: TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: AppTheme.textMuted),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
            ),

            // Date Navigation Strip
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              color: AppTheme.background,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      IconButton(
                        icon: const Icon(Icons.chevron_left, size: 20),
                        onPressed: () => _changeDate(-1),
                        tooltip: 'Previous Day',
                      ),
                      InkWell(
                        onTap: _pickDate,
                        borderRadius: BorderRadius.circular(8),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          decoration: BoxDecoration(
                            color: AppTheme.surface,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: AppTheme.border),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.calendar_month, size: 16, color: Color(0xFFE91E63)),
                              const SizedBox(width: 8),
                              Text(
                                isToday ? 'Today (${_selectedDate.day}/${_selectedDate.month}/${_selectedDate.year})' : '${_selectedDate.day}/${_selectedDate.month}/${_selectedDate.year}',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: AppTheme.textPrimary),
                              ),
                            ],
                          ),
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.chevron_right, size: 20),
                        onPressed: () => _changeDate(1),
                        tooltip: 'Next Day',
                      ),
                    ],
                  ),
                  Row(
                    children: [
                      OutlinedButton.icon(
                        onPressed: _isLoading ? null : _printAllStaffSummary,
                        icon: const Icon(Icons.print, size: 16),
                        label: const Text('Print Team Summary', style: TextStyle(fontSize: 12)),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppTheme.textPrimary,
                          side: const BorderSide(color: AppTheme.border),
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton(
                        icon: const Icon(Icons.refresh, size: 20, color: AppTheme.textSecondary),
                        onPressed: _loadSummary,
                        tooltip: 'Refresh',
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // Top Metric Counters Row
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  _buildMetricCard('Total Services', '${_summaryData['total_services'] ?? 0}', Icons.spa, const Color(0xFFE91E63)),
                  const SizedBox(width: 10),
                  _buildMetricCard('Service Revenue', 'KD ${((_summaryData['total_revenue'] as num?)?.toDouble() ?? 0).toStringAsFixed(3)}', Icons.payments, Colors.blue),
                  const SizedBox(width: 10),
                  _buildMetricCard('Total Tips', 'KD ${((_summaryData['total_tips'] as num?)?.toDouble() ?? 0).toStringAsFixed(3)}', Icons.volunteer_activism, const Color(0xFFFF9800)),
                  const SizedBox(width: 10),
                  _buildMetricCard('Total Payout', 'KD ${((_summaryData['total_staff_payout'] as num?)?.toDouble() ?? 0).toStringAsFixed(3)}', Icons.account_balance_wallet, AppTheme.success),
                ],
              ),
            ),

            const Divider(height: 1, color: AppTheme.border),

            // Staff Scorecards List
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : staffList.isEmpty
                      ? const Center(
                          child: Text('No service staff configured for this business', style: TextStyle(color: AppTheme.textMuted)),
                        )
                      : ListView.separated(
                          padding: const EdgeInsets.all(16),
                          itemCount: staffList.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 12),
                          itemBuilder: (context, index) {
                            final staff = Map<String, dynamic>.from(staffList[index]);
                            return _buildStaffScorecard(staff);
                          },
                        ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMetricCard(String label, String value, IconData icon, Color color) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: color.withValues(alpha: 0.2)),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(8)),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
                  const SizedBox(height: 2),
                  Text(value, style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: color)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStaffScorecard(Map<String, dynamic> staff) {
    final staffId = staff['staff_id'];
    final name = staff['staff_name'] ?? 'Staff';
    final count = staff['services_count'] ?? 0;
    final revenue = (staff['service_revenue'] as num?)?.toDouble() ?? 0;
    final commission = (staff['commission_earned'] as num?)?.toDouble() ?? 0;
    final rate = (staff['commission_percent'] as num?)?.toDouble() ?? 0;
    final tipsTotal = (staff['tips_total'] as num?)?.toDouble() ?? 0;
    final tipsPending = (staff['tips_pending'] as num?)?.toDouble() ?? 0;
    final tipsPaid = (staff['tips_paid_out'] as num?)?.toDouble() ?? 0;
    final totalPayout = (staff['total_payout'] as num?)?.toDouble() ?? 0;
    final services = (staff['services'] as List?) ?? [];

    final isExpanded = _expandedStaffId == staffId;

    return Container(
      decoration: BoxDecoration(
        color: AppTheme.background,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: isExpanded ? const Color(0xFFE91E63).withValues(alpha: 0.5) : AppTheme.border),
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 20,
                  backgroundColor: const Color(0xFFE91E63).withValues(alpha: 0.15),
                  child: Text(
                    name.isNotEmpty ? name[0].toUpperCase() : 'S',
                    style: const TextStyle(color: Color(0xFFE91E63), fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: AppTheme.textPrimary)),
                      Text('$count service(s) performed today', style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
                    ],
                  ),
                ),

                // Revenue Pill
                _buildStatPill('Revenue', 'KD ${revenue.toStringAsFixed(3)}', Colors.blue),
                const SizedBox(width: 8),

                // Commission Pill
                _buildStatPill('Comm ($rate%)', 'KD ${commission.toStringAsFixed(3)}', Colors.purple),
                const SizedBox(width: 8),

                // Tips Pill
                _buildStatPill('Tips', 'KD ${tipsTotal.toStringAsFixed(3)}', const Color(0xFFFF9800)),
                const SizedBox(width: 8),

                // Total Payout Pill
                _buildStatPill('Total Due', 'KD ${totalPayout.toStringAsFixed(3)}', AppTheme.success, isBold: true),
                const SizedBox(width: 12),

                // Actions Menu
                IconButton(
                  icon: const Icon(Icons.print, size: 18, color: AppTheme.textSecondary),
                  tooltip: 'Print Staff Slip',
                  onPressed: () => _printStaffSlip(staff),
                ),

                if (tipsPending > 0)
                  ElevatedButton(
                    onPressed: () => _payoutTipsForStaff(staffId, name, tipsPending),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFFF9800),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      visualDensity: VisualDensity.compact,
                    ),
                    child: Text('Pay Tips (KD ${tipsPending.toStringAsFixed(3)})', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                  ),

                IconButton(
                  icon: Icon(isExpanded ? Icons.expand_less : Icons.expand_more, size: 20, color: AppTheme.textMuted),
                  onPressed: () {
                    setState(() {
                      _expandedStaffId = isExpanded ? null : staffId;
                    });
                  },
                ),
              ],
            ),
          ),

          // Expanded Ticket List
          if (isExpanded) ...[
            const Divider(height: 1, color: AppTheme.border),
            Container(
              padding: const EdgeInsets.all(12),
              color: AppTheme.surface,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Tickets & Services Performed ($count)', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: AppTheme.textSecondary)),
                      if (tipsPaid > 0)
                        Text('Paid out tips: KD ${tipsPaid.toStringAsFixed(3)}', style: const TextStyle(fontSize: 11, color: AppTheme.success)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  if (services.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 8),
                      child: Text('No completed tickets recorded today for this staff member.', style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                    )
                  else
                    ...services.map((item) {
                      final itemMap = Map<String, dynamic>.from(item);
                      return Padding(
                        padding: const EdgeInsets.symmetric(vertical: 4),
                        child: Row(
                          children: [
                            const Icon(Icons.check_circle_outline, size: 14, color: AppTheme.success),
                            const SizedBox(width: 8),
                            Text(itemMap['time'] ?? '', style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                '${itemMap['service_name']} (${itemMap['customer']})',
                                style: const TextStyle(fontSize: 12, color: AppTheme.textPrimary, fontWeight: FontWeight.w500),
                              ),
                            ),
                            Text('KD ${(itemMap['total'] as num?)?.toDouble().toStringAsFixed(3) ?? '0.000'}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.primary)),
                          ],
                        ),
                      );
                    }),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildStatPill(String title, String val, Color color, {bool isBold = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(title, style: TextStyle(fontSize: 9, color: color, fontWeight: FontWeight.w500)),
          Text(val, style: TextStyle(fontSize: 11, color: color, fontWeight: isBold ? FontWeight.bold : FontWeight.w600)),
        ],
      ),
    );
  }
}
