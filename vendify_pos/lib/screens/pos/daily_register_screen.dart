import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';

class DailyRegisterScreen extends ConsumerStatefulWidget {
  const DailyRegisterScreen({super.key});

  @override
  ConsumerState<DailyRegisterScreen> createState() => _DailyRegisterScreenState();
}

class _DailyRegisterScreenState extends ConsumerState<DailyRegisterScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  bool _isLoading = true;
  
  // Current shift data
  Map<String, dynamic>? _currentShift;
  bool _hasOpenShift = false;
  
  // Daily summary
  Map<String, dynamic> _dailySummary = {};
  
  // Opening cash input
  final _openingCashController = TextEditingController(text: '50.000');
  final _openingNotesController = TextEditingController();
  
  // Closing cash input
  final _countedCashController = TextEditingController();
  final _closingNotesController = TextEditingController();
  
  String _currencySymbol = 'KD';

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  @override
  void dispose() {
    _openingCashController.dispose();
    _openingNotesController.dispose();
    _countedCashController.dispose();
    _closingNotesController.dispose();    super.dispose();
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return (num.tryParse(v.toString()) ?? 0).toDouble();
  }

  static String _fmt(dynamic v) => _toDouble(v).toStringAsFixed(3);

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    
    try {
      final prefs = await SharedPreferences.getInstance();
      _currencySymbol = prefs.getString('pos_currency_symbol') ?? 'KD';
      final locationId = prefs.getInt('location_id');
      
      // Get current shift
      final shiftData = await _posService.getCurrentShift(locationId: locationId);
      _currentShift = shiftData['shift'];
      _hasOpenShift = _currentShift != null;
      
      // Get daily summary
      _dailySummary = await _posService.getDailySummary();
      
    } catch (e) {
      debugPrint('Error loading register data: $e');
    }
    
    setState(() => _isLoading = false);
  }

  Future<void> _openShift() async {
    final openingCash = double.tryParse(_openingCashController.text) ?? 0;
    
    setState(() => _isLoading = true);
    
    try {
      final result = await _posService.openShift(
        openingCash: openingCash,
        openingNotes: _openingNotesController.text.isNotEmpty ? _openingNotesController.text : null,
      );
      
      if (!mounted) return;
      if (result['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Shift opened successfully!'),
            backgroundColor: AppTheme.success,
          ),
        );
        _loadData();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(result['message'] ?? 'Failed to open shift'),
            backgroundColor: AppTheme.error,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.error),
      );
    }
    
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _closeShift() async {
    final countedCash = double.tryParse(_countedCashController.text);
    
    if (countedCash == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter counted cash amount'), backgroundColor: AppTheme.error),
      );
      return;
    }
    
    setState(() => _isLoading = true);
    
    try {
      final result = await _posService.closeShift(
        countedCash: countedCash,
        closingNotes: _closingNotesController.text.isNotEmpty ? _closingNotesController.text : null,
      );
      
      if (!mounted) return;
      if (result['success'] == true) {
        final data = result['data'] as Map<String, dynamic>? ?? {};
        final difference = (data['cash_difference'] != null)
            ? double.tryParse(data['cash_difference'].toString()) ?? 0.0
            : 0.0;
        
        showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            backgroundColor: AppTheme.surface,
            title: Row(
              children: [
                Icon(
                  difference == 0 ? Icons.check_circle : Icons.warning,
                  color: difference == 0 ? AppTheme.success : AppTheme.warning,
                  size: 28,
                ),
                const SizedBox(width: 8),
                const Text('Shift Closed', style: TextStyle(color: AppTheme.textPrimary)),
              ],
            ),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildResultRow('Total Sales', '$_currencySymbol ${_fmt(data['total_sales'])}'),
                _buildResultRow('Cash Sales', '$_currencySymbol ${_fmt(data['total_cash_sales'])}'),
                _buildResultRow('Expected Cash', '$_currencySymbol ${_fmt(data['expected_cash'])}'),
                _buildResultRow('Counted Cash', '$_currencySymbol ${_fmt(data['counted_cash'])}'),
                const Divider(color: AppTheme.border),
                _buildResultRow(
                  'Difference',
                  '$_currencySymbol ${_fmt(data['cash_difference'])}',
                  isPositive: difference > 0,
                  isNegative: difference < 0,
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () {
                  Navigator.pop(ctx);
                  Navigator.pop(context);
                },
                child: const Text('Done'),
              ),
            ],
          ),
        );
        
        _loadData();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result['message'] ?? 'Failed to close shift'), backgroundColor: AppTheme.error),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.error),
      );
    }
    
    if (mounted) setState(() => _isLoading = false);
  }

  Widget _buildResultRow(String label, String value, {bool isPositive = false, bool isNegative = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
          Text(
            value,
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: isPositive ? AppTheme.success : isNegative ? AppTheme.error : AppTheme.textPrimary,
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        backgroundColor: AppTheme.surface,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppTheme.textPrimary),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Daily Register',
          style: TextStyle(color: AppTheme.textPrimary, fontSize: 18, fontWeight: FontWeight.bold),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: AppTheme.textSecondary),
            onPressed: _loadData,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(AppTheme.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Shift Status Card
                  _buildShiftStatusCard(),
                  const SizedBox(height: 20),
                  
                  // Daily Summary
                  _buildDailySummaryCard(),
                  const SizedBox(height: 20),
                  
                  // Payment Breakdown
                  _buildPaymentBreakdownCard(),
                  const SizedBox(height: 20),
                  
                  // Top Products
                  if (_dailySummary['top_products'] != null && 
                      (_dailySummary['top_products'] as List).isNotEmpty)
                    _buildTopProductsCard(),
                ],
              ),
            ),
    );
  }

  Widget _buildShiftStatusCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _hasOpenShift ? AppTheme.success.withValues(alpha: 0.1) : AppTheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: _hasOpenShift ? AppTheme.success : AppTheme.border,
          width: _hasOpenShift ? 2 : 1,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                _hasOpenShift ? Icons.play_circle : Icons.pause_circle,
                color: _hasOpenShift ? AppTheme.success : AppTheme.warning,
                size: 28,
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _hasOpenShift ? 'Shift Open' : 'No Active Shift',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: _hasOpenShift ? AppTheme.success : AppTheme.warning,
                    ),
                  ),
                  if (_hasOpenShift && _currentShift != null)
                    Text(
                      'Opened at ${_currentShift!['opened_at']?.toString().substring(11, 16) ?? ''}',
                      style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                    ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 16),
          
          if (!_hasOpenShift)
            _buildOpenShiftForm()
          else
            _buildCloseShiftForm(),
        ],
      ),
    );
  }

  Widget _buildOpenShiftForm() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Open New Shift',
          style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppTheme.textPrimary),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              flex: 2,
              child: TextField(
                controller: _openingCashController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,3}'))],
                style: const TextStyle(color: AppTheme.textPrimary, fontSize: 16),
                decoration: InputDecoration(
                  labelText: 'Opening Cash',
                  prefixText: '$_currencySymbol ',
                  prefixStyle: const TextStyle(color: AppTheme.textSecondary),
                  filled: true,
                  fillColor: AppTheme.background,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              flex: 3,
              child: TextField(
                controller: _openingNotesController,
                style: const TextStyle(color: AppTheme.textPrimary),
                decoration: InputDecoration(
                  labelText: 'Notes (optional)',
                  filled: true,
                  fillColor: AppTheme.background,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: _openShift,
            icon: const Icon(Icons.play_arrow, size: 20),
            label: const Text('Open Shift'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.success,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildCloseShiftForm() {
    // Calculate expected values
    final openingCash = (num.tryParse(_currentShift?['opening_cash'].toString() ?? '') ?? 0).toDouble();
    final cashSales = (_dailySummary['payment_breakdown'] is Map)
        ? (num.tryParse((_dailySummary['payment_breakdown'] as Map)['cash']?['total'].toString() ?? '') ?? 0).toDouble()
        : 0.0;
    final expectedCash = openingCash + cashSales;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Close Current Shift',
          style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppTheme.textPrimary),
        ),
        const SizedBox(height: 12),
        
        // Expected cash display
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: AppTheme.surfaceLight,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Expected Cash:', style: TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
              Text(
                '$_currencySymbol ${expectedCash.toStringAsFixed(3)}',
                style: const TextStyle(color: AppTheme.primary, fontSize: 16, fontWeight: FontWeight.bold),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        
        Row(
          children: [
            Expanded(
              flex: 2,
              child: TextField(
                controller: _countedCashController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,3}'))],
                style: const TextStyle(color: AppTheme.textPrimary, fontSize: 16),
                decoration: InputDecoration(
                  labelText: 'Counted Cash *',
                  prefixText: '$_currencySymbol ',
                  prefixStyle: const TextStyle(color: AppTheme.textSecondary),
                  filled: true,
                  fillColor: AppTheme.background,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ),
            const SizedBox(width: 8),
            // Quick fill button
            IconButton(
              onPressed: () {
                _countedCashController.text = expectedCash.toStringAsFixed(3);
              },
              icon: const Icon(Icons.arrow_forward, color: AppTheme.primary),
              tooltip: 'Use expected amount',
            ),
            const SizedBox(width: 4),
            Expanded(
              flex: 3,
              child: TextField(
                controller: _closingNotesController,
                style: const TextStyle(color: AppTheme.textPrimary),
                decoration: InputDecoration(
                  labelText: 'Notes (optional)',
                  filled: true,
                  fillColor: AppTheme.background,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: _closeShift,
            icon: const Icon(Icons.stop, size: 20),
            label: const Text('Close Shift'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.error,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildDailySummaryCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.bar_chart, color: AppTheme.primary, size: 24),
              const SizedBox(width: 12),
              const Text(
                'Daily Summary',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
              ),
              const Spacer(),
              Text(
                _dailySummary['date'] ?? DateTime.now().toString().substring(0, 10),
                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
              ),
            ],
          ),
          const SizedBox(height: 16),
          const Divider(color: AppTheme.border),
          const SizedBox(height: 8),
          
          // Summary grid
          Row(
            children: [
              Expanded(child: _buildSummaryItem('Total Sales', '$_currencySymbol ${_fmt(_dailySummary['total_sales'])}', AppTheme.primary)),
              Expanded(child: _buildSummaryItem('Transactions', '${_dailySummary['total_transactions'] ?? 0}', AppTheme.info)),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _buildSummaryItem('Tax', '$_currencySymbol ${_fmt(_dailySummary['total_tax'])}', AppTheme.warning)),
              Expanded(child: _buildSummaryItem('Discounts', '$_currencySymbol ${_fmt(_dailySummary['total_discount'])}', AppTheme.error)),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _buildSummaryItem('Refunds', '$_currencySymbol ${_fmt(_dailySummary['total_refunds'])}', AppTheme.error)),
              Expanded(child: _buildSummaryItem('Net Sales', '$_currencySymbol ${_fmt(_dailySummary['net_sales'])}', AppTheme.success)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryItem(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 11)),
          const SizedBox(height: 4),
          Text(value, style: TextStyle(color: color, fontSize: 16, fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }

  Widget _buildPaymentBreakdownCard() {
    final breakdown = _dailySummary['payment_breakdown'];
    if (breakdown == null) return const SizedBox.shrink();
    
    // Convert to map if it's a list
    Map<String, dynamic> paymentMap = {};
    if (breakdown is Map) {
      paymentMap = Map<String, dynamic>.from(breakdown);
    } else if (breakdown is List) {
      for (final item in breakdown) {
        if (item is Map) {
          paymentMap[item['method']] = item;
        }
      }
    }
    
    if (paymentMap.isEmpty) return const SizedBox.shrink();

    const methodLabels = {
      'cash': 'Cash',
      'card': 'Debit Card',
      'visa': 'Visa',
      'mastercard': 'Mastercard',
      'cheque': 'Cheque',
      'bank_transfer': 'Bank Transfer',
      'other': 'Other',
    };

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.payment, color: AppTheme.primary, size: 24),
              const SizedBox(width: 12),
              const Text(
                'Payment Breakdown',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
              ),
            ],
          ),
          const SizedBox(height: 16),
          ...paymentMap.entries.map((entry) {
            final method = entry.key;
            final data = entry.value;
            final total = data is Map ? (num.tryParse(data['total'].toString()) ?? 0).toDouble() : (num.tryParse(data.toString()) ?? 0).toDouble();
            final count = data is Map ? (data['count'] ?? 0) : 0;
            
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Row(
                children: [
                  Icon(
                    method == 'cash' ? Icons.money : Icons.credit_card,
                    size: 18,
                    color: AppTheme.textSecondary,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      methodLabels[method] ?? method,
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 14),
                    ),
                  ),
                  Text(
                    '$count txns',
                    style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                  ),
                  const SizedBox(width: 16),
                  Text(
                    '$_currencySymbol ${total.toStringAsFixed(3)}',
                    style: const TextStyle(color: AppTheme.textPrimary, fontSize: 14, fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildTopProductsCard() {
    final products = _dailySummary['top_products'] as List;
    
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.trending_up, color: AppTheme.primary, size: 24),
              const SizedBox(width: 12),
              const Text(
                'Top Selling Products',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
              ),
            ],
          ),
          const SizedBox(height: 16),
          ...products.asMap().entries.map((entry) {
            final index = entry.key;
            final product = entry.value;
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  Container(
                    width: 24,
                    height: 24,
                    decoration: BoxDecoration(
                      color: index < 3 ? AppTheme.primary.withValues(alpha: 0.1) : AppTheme.surfaceLight,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Center(
                      child: Text(
                        '${index + 1}',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          color: index < 3 ? AppTheme.primary : AppTheme.textSecondary,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      product['name'] ?? '',
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Text(
                    '${product['total_qty'] ?? 0} sold',
                    style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                  ),
                  const SizedBox(width: 12),
                  Text(
                    '$_currencySymbol ${_fmt(product['total_revenue'])}',
                    style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }
}
