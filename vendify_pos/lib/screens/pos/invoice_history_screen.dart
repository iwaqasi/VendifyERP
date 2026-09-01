import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/screens/pos/invoice_detail_screen.dart';

class InvoiceHistoryScreen extends ConsumerStatefulWidget {
  const InvoiceHistoryScreen({super.key});

  @override
  ConsumerState<InvoiceHistoryScreen> createState() => _InvoiceHistoryScreenState();
}

class _InvoiceHistoryScreenState extends ConsumerState<InvoiceHistoryScreen> {
  late final PosService _posService = ref.read(posServiceProvider);
  
  List<Map<String, dynamic>> _invoices = [];
  bool _isLoading = true;
  bool _hasMore = true;
  int _currentPage = 1;
  
  // Filters
  final _searchController = TextEditingController();
  final _invoiceNoController = TextEditingController();
  DateTime? _startDate;
  DateTime? _endDate;
  String _paymentStatus = '';
  Timer? _debounce;
  
  @override
  void initState() {
    super.initState();
    _loadInvoices();
  }
  
  @override
  void dispose() {
    _searchController.dispose();
    _invoiceNoController.dispose();
    _debounce?.cancel();
    super.dispose();
  }
  
  Future<void> _loadInvoices({bool refresh = false}) async {
    if (refresh) {
      _currentPage = 1;
      _hasMore = true;
    }
    if (!_hasMore && !refresh) return;
    
    setState(() => _isLoading = true);
    
    try {
      final result = await _posService.getSells(
        search: _searchController.text.isNotEmpty ? _searchController.text : null,
        invoiceNo: _invoiceNoController.text.isNotEmpty ? _invoiceNoController.text : null,
        startDate: _startDate != null ? DateFormat('yyyy-MM-dd').format(_startDate!) : null,
        endDate: _endDate != null ? DateFormat('yyyy-MM-dd').format(_endDate!) : null,
        paymentStatus: _paymentStatus.isNotEmpty ? _paymentStatus : null,
        page: _currentPage,
      );
      
      final data = result['data'];
      if (data is List) {
        setState(() {
          if (refresh) {
            _invoices = List<Map<String, dynamic>>.from(data);
          } else {
            _invoices.addAll(List<Map<String, dynamic>>.from(data));
          }
          _hasMore = data.length >= 20;
          _isLoading = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      setState(() => _isLoading = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.error),
        );
      }
    }
  }
  
  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      _loadInvoices(refresh: true);
    });
  }
  
  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return (num.tryParse(v.toString()) ?? 0).toDouble();
  }

  void _clearFilters() {
    _searchController.clear();
    _invoiceNoController.clear();
    _startDate = null;
    _endDate = null;
    _paymentStatus = '';
    _loadInvoices(refresh: true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        backgroundColor: AppTheme.surface,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppTheme.textPrimary),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text('Invoice History', style: TextStyle(color: AppTheme.textPrimary, fontSize: 18, fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_list, color: AppTheme.primary),
            onPressed: _showFilterSheet,
          ),
          IconButton(
            icon: const Icon(Icons.refresh, color: AppTheme.textSecondary),
            onPressed: () => _loadInvoices(refresh: true),
          ),
        ],
      ),
      body: Column(
        children: [
          // Search bar
          Container(
            padding: const EdgeInsets.all(12),
            color: AppTheme.surface,
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _searchController,
                    onChanged: _onSearchChanged,
                    style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                    decoration: InputDecoration(
                      hintText: 'Search by customer name or phone...',
                      prefixIcon: const Icon(Icons.search, color: AppTheme.textMuted, size: 18),
                      filled: true,
                      fillColor: AppTheme.background,
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      isDense: true,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                SizedBox(
                  width: 120,
                  child: TextField(
                    controller: _invoiceNoController,
                    onChanged: _onSearchChanged,
                    style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                    decoration: InputDecoration(
                      hintText: 'Invoice #',
                      filled: true,
                      fillColor: AppTheme.background,
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide.none),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      isDense: true,
                    ),
                  ),
                ),
              ],
            ),
          ),
          
          // Active filters chips
          if (_startDate != null || _endDate != null || _paymentStatus.isNotEmpty)
            Container(
              height: 40,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: [
                  if (_startDate != null)
                    _buildFilterChip('From: ${DateFormat('dd/MM/yy').format(_startDate!)}', () { setState(() => _startDate = null); _loadInvoices(refresh: true); }),
                  if (_endDate != null)
                    _buildFilterChip('To: ${DateFormat('dd/MM/yy').format(_endDate!)}', () { setState(() => _endDate = null); _loadInvoices(refresh: true); }),
                  if (_paymentStatus.isNotEmpty)
                    _buildFilterChip('Status: $_paymentStatus', () { setState(() => _paymentStatus = ''); _loadInvoices(refresh: true); }),
                  _buildFilterChip('Clear All', _clearFilters, isDestructive: true),
                ],
              ),
            ),
          
          // Invoice list
          Expanded(
            child: _isLoading && _invoices.isEmpty
                ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
                : _invoices.isEmpty
                    ? const Center(child: Text('No invoices found', style: TextStyle(color: AppTheme.textMuted)))
                    : RefreshIndicator(
                        onRefresh: () => _loadInvoices(refresh: true),
                        child: ListView.builder(
                          padding: const EdgeInsets.all(12),
                          itemCount: _invoices.length + (_hasMore ? 1 : 0),
                          itemBuilder: (context, index) {
                            if (index == _invoices.length) {
                              _currentPage++;
                              _loadInvoices();
                              return const Center(child: Padding(
                                padding: EdgeInsets.all(16),
                                child: CircularProgressIndicator(strokeWidth: 2),
                              ));
                            }
                            return _buildInvoiceCard(_invoices[index]);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
  
  Widget _buildFilterChip(String label, VoidCallback onRemove, {bool isDestructive = false}) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: Chip(
        label: Text(label, style: TextStyle(fontSize: 11, color: isDestructive ? AppTheme.error : AppTheme.textPrimary)),
        deleteIcon: Icon(Icons.close, size: 14, color: isDestructive ? AppTheme.error : AppTheme.textSecondary),
        onDeleted: onRemove,
        backgroundColor: isDestructive ? AppTheme.error.withValues(alpha: 0.1) : AppTheme.surfaceLight,
        padding: const EdgeInsets.symmetric(horizontal: 4),
        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
        visualDensity: VisualDensity.compact,
      ),
    );
  }
  
  Widget _buildInvoiceCard(Map<String, dynamic> invoice) {
    final invoiceNo = invoice['invoice_no'] ?? 'N/A';
    final total = _toDouble(invoice['final_total']);
    final amountPaid = _toDouble(invoice['amount_paid']);
    final due = total - amountPaid;
    final paymentStatus = invoice['payment_status'] ?? 'pending';
    final contact = invoice['contact'];
    final customerName = contact != null ? (contact['name'] ?? 'Walk-in') : 'Walk-in';
    final customerMobile = contact != null ? (contact['mobile'] ?? '') : '';
    final transactionDate = invoice['transaction_date'] ?? '';
    final items = invoice['sell_lines'] as List? ?? [];
    
    Color statusColor;
    IconData statusIcon;
    switch (paymentStatus) {
      case 'paid':
        statusColor = AppTheme.success;
        statusIcon = Icons.check_circle;
        break;
      case 'partial':
        statusColor = AppTheme.warning;
        statusIcon = Icons.pending;
        break;
      default:
        statusColor = AppTheme.error;
        statusIcon = Icons.cancel;
    }
    
    String dateStr = '';
    try {
      dateStr = DateFormat('dd/MM/yyyy HH:mm').format(DateTime.parse(transactionDate));
    } catch (_) {
      dateStr = transactionDate;
    }
    
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      child: InkWell(
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => InvoiceDetailScreen(sellId: invoice['id'])),
        ),
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: AppTheme.primary.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(invoiceNo, style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.primary)),
                  ),
                  const Spacer(),
                  Icon(statusIcon, size: 16, color: statusColor),
                  const SizedBox(width: 4),
                  Text(paymentStatus.toUpperCase(), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: statusColor)),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.person, size: 14, color: AppTheme.textSecondary),
                  const SizedBox(width: 4),
                  Text(customerName, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textPrimary)),
                  if (customerMobile.isNotEmpty) ...[
                    const SizedBox(width: 8),
                    Icon(Icons.phone, size: 12, color: AppTheme.textMuted),
                    const SizedBox(width: 2),
                    Text(customerMobile, style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                  ],
                ],
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(Icons.access_time, size: 12, color: AppTheme.textMuted),
                  const SizedBox(width: 4),
                  Text(dateStr, style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                  const SizedBox(width: 12),
                  Icon(Icons.shopping_bag, size: 12, color: AppTheme.textMuted),
                  const SizedBox(width: 2),
                  Text('${items.length} items', style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                ],
              ),
              const Divider(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Total', style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                      Text('KD ${total.toStringAsFixed(3)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                    ],
                  ),
                  if (due > 0)
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text('Due', style: TextStyle(fontSize: 11, color: AppTheme.textMuted)),
                        Text('KD ${due.toStringAsFixed(3)}', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: AppTheme.error)),
                      ],
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
  
  void _showFilterSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppTheme.surface,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (ctx) => _FilterSheet(
        startDate: _startDate,
        endDate: _endDate,
        paymentStatus: _paymentStatus,
        onApply: (startDate, endDate, paymentStatus) {
          setState(() {
            _startDate = startDate;
            _endDate = endDate;
            _paymentStatus = paymentStatus;
          });
          Navigator.pop(ctx);
          _loadInvoices(refresh: true);
        },
      ),
    );
  }
}

class _FilterSheet extends StatefulWidget {
  final DateTime? startDate;
  final DateTime? endDate;
  final String paymentStatus;
  final Function(DateTime?, DateTime?, String) onApply;
  
  const _FilterSheet({this.startDate, this.endDate, required this.paymentStatus, required this.onApply});
  
  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late DateTime? _startDate = widget.startDate;
  late DateTime? _endDate = widget.endDate;
  late String _paymentStatus = widget.paymentStatus;
  
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.filter_list, color: AppTheme.primary, size: 20),
              const SizedBox(width: 8),
              const Text('Filter Invoices', style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
              const Spacer(),
              IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
            ],
          ),
          const SizedBox(height: 16),
          // Date range
          Row(
            children: [
              Expanded(
                child: _buildDateField('Start Date', _startDate, (date) => setState(() => _startDate = date)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildDateField('End Date', _endDate, (date) => setState(() => _endDate = date)),
              ),
            ],
          ),
          const SizedBox(height: 16),
          const Text('Payment Status', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              _buildStatusChip('All', ''),
              _buildStatusChip('Paid', 'paid'),
              _buildStatusChip('Partial', 'partial'),
              _buildStatusChip('Pending', 'pending'),
              _buildStatusChip('Due', 'due'),
            ],
          ),
          const SizedBox(height: 20),
          SizedBox(
            width: double.infinity,
            height: 44,
            child: ElevatedButton(
              onPressed: () => widget.onApply(_startDate, _endDate, _paymentStatus),
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary, foregroundColor: Colors.black),
              child: const Text('Apply Filters', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
          ),
        ],
      ),
    );
  }
  
  Widget _buildDateField(String label, DateTime? date, Function(DateTime) onPicked) {
    return InkWell(
      onTap: () async {
        final picked = await showDatePicker(
          context: context,
          initialDate: date ?? DateTime.now(),
          firstDate: DateTime(2020),
          lastDate: DateTime.now(),
        );
        if (picked != null) onPicked(picked);
      },
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          labelStyle: const TextStyle(fontSize: 12),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        ),
        child: Text(
          date != null ? DateFormat('dd/MM/yyyy').format(date) : 'Select...',
          style: TextStyle(fontSize: 13, color: date != null ? AppTheme.textPrimary : AppTheme.textMuted),
        ),
      ),
    );
  }
  
  Widget _buildStatusChip(String label, String value) {
    final selected = _paymentStatus == value;
    return ChoiceChip(
      label: Text(label, style: TextStyle(fontSize: 12, color: selected ? Colors.black : AppTheme.textSecondary)),
      selected: selected,
      selectedColor: AppTheme.primary,
      onSelected: (_) => setState(() => _paymentStatus = value),
    );
  }
}
