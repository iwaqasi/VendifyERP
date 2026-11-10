import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/pos_service.dart';

class AuditTrailDialog extends StatefulWidget {
  final PosService posService;

  const AuditTrailDialog({
    super.key,
    required this.posService,
  });

  static Future<void> show(BuildContext context, PosService posService) {
    return showDialog(
      context: context,
      barrierDismissible: true,
      builder: (_) => AuditTrailDialog(posService: posService),
    );
  }

  @override
  State<AuditTrailDialog> createState() => _AuditTrailDialogState();
}

class _AuditTrailDialogState extends State<AuditTrailDialog> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _items = [];
  Map<String, dynamic> _meta = {};
  int _currentPage = 1;
  String _search = '';
  String? _selectedEvent;
  final TextEditingController _searchController = TextEditingController();

  final List<String> _eventFilters = [
    'All Events',
    'created',
    'updated',
    'deleted',
    'sell_created',
  ];

  @override
  void initState() {
    super.initState();
    _fetchLogs();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _fetchLogs() async {
    setState(() => _isLoading = true);
    final eventParam = (_selectedEvent == null || _selectedEvent == 'All Events')
        ? null
        : _selectedEvent;

    final result = await widget.posService.getAuditLogs(
      page: _currentPage,
      perPage: 20,
      search: _search.isNotEmpty ? _search : null,
      event: eventParam,
    );

    if (mounted) {
      setState(() {
        _items = (result['items'] as List?)?.cast<Map<String, dynamic>>() ?? [];
        _meta = (result['meta'] as Map?)?.cast<String, dynamic>() ?? {};
        _isLoading = false;
      });
    }
  }

  Color _getEventColor(String? event) {
    final e = event?.toLowerCase() ?? '';
    if (e.contains('creat') || e.contains('add')) return Colors.greenAccent;
    if (e.contains('updat') || e.contains('edit')) return Colors.orangeAccent;
    if (e.contains('delet') || e.contains('cancel')) return Colors.redAccent;
    return AppTheme.primary;
  }

  void _showPropertiesDialog(Map<String, dynamic> log) {
    final props = log['properties'];
    final prettyJson = const JsonEncoder.withIndent('  ').convert(props);

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppTheme.surface,
        title: Text(
          'Log #${log['id']} Payload Details',
          style: const TextStyle(color: AppTheme.textPrimary, fontSize: 16),
        ),
        content: SizedBox(
          width: 500,
          child: SingleChildScrollView(
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.background,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: AppTheme.border),
              ),
              child: SelectableText(
                prettyJson,
                style: const TextStyle(
                  color: AppTheme.textSecondary,
                  fontFamily: 'monospace',
                  fontSize: 12,
                ),
              ),
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Close', style: TextStyle(color: AppTheme.primary)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final total = _meta['total'] ?? _items.length;
    final lastPage = _meta['last_page'] ?? 1;

    return Dialog(
      backgroundColor: AppTheme.background,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      insetPadding: const EdgeInsets.all(24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 960, maxHeight: 720),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Header
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: AppTheme.primary.withValues(alpha: 0.15),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.shield_outlined, color: AppTheme.primary, size: 24),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Audit Trail & Security Logs',
                          style: TextStyle(
                            color: AppTheme.textPrimary,
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        Text(
                          'Immutable event stream for compliance, order modifications, and cashier activity ($total total)',
                          style: const TextStyle(color: AppTheme.textMuted, fontSize: 13),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: _fetchLogs,
                    tooltip: 'Refresh',
                    icon: const Icon(Icons.refresh, color: AppTheme.textSecondary),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    tooltip: 'Close',
                    icon: const Icon(Icons.close, color: AppTheme.textSecondary),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Divider(color: AppTheme.border, height: 1),
              const SizedBox(height: 16),

              // Filter Controls
              Row(
                children: [
                  // Search Input
                  Expanded(
                    child: TextField(
                      controller: _searchController,
                      style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                      decoration: InputDecoration(
                        hintText: 'Search description, causer, or entity...',
                        hintStyle: const TextStyle(color: AppTheme.textMuted, fontSize: 13),
                        prefixIcon: const Icon(Icons.search, size: 18, color: AppTheme.textMuted),
                        suffixIcon: _search.isNotEmpty
                            ? IconButton(
                                icon: const Icon(Icons.clear, size: 16, color: AppTheme.textMuted),
                                onPressed: () {
                                  _searchController.clear();
                                  _search = '';
                                  _currentPage = 1;
                                  _fetchLogs();
                                },
                              )
                            : null,
                        filled: true,
                        fillColor: AppTheme.surface,
                        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(8),
                          borderSide: const BorderSide(color: AppTheme.border),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(8),
                          borderSide: const BorderSide(color: AppTheme.border),
                        ),
                      ),
                      onSubmitted: (val) {
                        _search = val.trim();
                        _currentPage = 1;
                        _fetchLogs();
                      },
                    ),
                  ),
                  const SizedBox(width: 12),

                  // Event Filter
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    decoration: BoxDecoration(
                      color: AppTheme.surface,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: AppTheme.border),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String>(
                        value: _selectedEvent ?? 'All Events',
                        dropdownColor: AppTheme.surface,
                        style: const TextStyle(color: AppTheme.textPrimary, fontSize: 13),
                        icon: const Icon(Icons.filter_list, size: 18, color: AppTheme.textMuted),
                        items: _eventFilters.map((e) {
                          return DropdownMenuItem(value: e, child: Text(e));
                        }).toList(),
                        onChanged: (val) {
                          setState(() {
                            _selectedEvent = val;
                            _currentPage = 1;
                          });
                          _fetchLogs();
                        },
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // Content Body
              Expanded(
                child: _isLoading
                    ? const Center(
                        child: CircularProgressIndicator(color: AppTheme.primary),
                      )
                    : _items.isEmpty
                        ? Center(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: const [
                                Icon(Icons.inbox_outlined, size: 48, color: AppTheme.textMuted),
                                SizedBox(height: 12),
                                Text(
                                  'No audit records found',
                                  style: TextStyle(color: AppTheme.textSecondary, fontSize: 14),
                                ),
                              ],
                            ),
                          )
                        : ListView.separated(
                            itemCount: _items.length,
                            separatorBuilder: (_, __) => const SizedBox(height: 8),
                            itemBuilder: (ctx, index) {
                              final log = _items[index];
                              final event = log['event']?.toString() ?? 'event';
                              final eventColor = _getEventColor(event);
                              final causer = log['causer_name']?.toString() ?? 'System';
                              final desc = log['description']?.toString() ?? '';
                              final subject = log['subject_type']?.toString();
                              final subjectId = log['subject_id']?.toString();
                              final rawDate = log['created_at']?.toString();
                              String dateStr = rawDate ?? '';
                              if (rawDate != null) {
                                try {
                                  final parsed = DateTime.parse(rawDate);
                                  dateStr = DateFormat('yyyy-MM-dd HH:mm:ss').format(parsed);
                                } catch (_) {}
                              }
                              final hasProps = log['properties'] != null &&
                                  (log['properties'] is Map && (log['properties'] as Map).isNotEmpty);

                              return Container(
                                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                                decoration: BoxDecoration(
                                  color: AppTheme.surface,
                                  borderRadius: BorderRadius.circular(10),
                                  border: Border.all(color: AppTheme.border),
                                ),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.center,
                                  children: [
                                    // Event Badge
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: eventColor.withValues(alpha: 0.15),
                                        borderRadius: BorderRadius.circular(6),
                                        border: Border.all(color: eventColor.withValues(alpha: 0.5)),
                                      ),
                                      child: Text(
                                        event.toUpperCase(),
                                        style: TextStyle(
                                          color: eventColor,
                                          fontWeight: FontWeight.bold,
                                          fontSize: 11,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 12),

                                    // Main description & subject
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            children: [
                                              Text(
                                                desc.isNotEmpty ? desc : 'Activity recorded',
                                                style: const TextStyle(
                                                  color: AppTheme.textPrimary,
                                                  fontWeight: FontWeight.w600,
                                                  fontSize: 13,
                                                ),
                                              ),
                                              if (subject != null) ...[
                                                const SizedBox(width: 8),
                                                Container(
                                                  padding: const EdgeInsets.symmetric(
                                                      horizontal: 6, vertical: 2),
                                                  decoration: BoxDecoration(
                                                    color: AppTheme.surfaceLight,
                                                    borderRadius: BorderRadius.circular(4),
                                                  ),
                                                  child: Text(
                                                    '$subject ${subjectId != null ? "#$subjectId" : ""}',
                                                    style: const TextStyle(
                                                      color: AppTheme.textMuted,
                                                      fontSize: 10,
                                                    ),
                                                  ),
                                                ),
                                              ],
                                            ],
                                          ),
                                          const SizedBox(height: 4),
                                          Row(
                                            children: [
                                              const Icon(Icons.person_outline,
                                                  size: 13, color: AppTheme.textMuted),
                                              const SizedBox(width: 4),
                                              Text(
                                                causer,
                                                style: const TextStyle(
                                                  color: AppTheme.textSecondary,
                                                  fontSize: 11,
                                                ),
                                              ),
                                              const SizedBox(width: 12),
                                              const Icon(Icons.access_time,
                                                  size: 13, color: AppTheme.textMuted),
                                              const SizedBox(width: 4),
                                              Text(
                                                dateStr,
                                                style: const TextStyle(
                                                  color: AppTheme.textMuted,
                                                  fontSize: 11,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),

                                    // Properties detail button
                                    if (hasProps)
                                      TextButton.icon(
                                        onPressed: () => _showPropertiesDialog(log),
                                        style: TextButton.styleFrom(
                                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                          backgroundColor: AppTheme.surfaceLight,
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                                        ),
                                        icon: const Icon(Icons.data_object, size: 14, color: AppTheme.primary),
                                        label: const Text(
                                          'Payload',
                                          style: TextStyle(color: AppTheme.primary, fontSize: 11),
                                        ),
                                      ),
                                  ],
                                ),
                              );
                            },
                          ),
              ),

              const SizedBox(height: 12),
              // Pagination Footer
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Page $_currentPage of $lastPage',
                    style: const TextStyle(color: AppTheme.textMuted, fontSize: 12),
                  ),
                  Row(
                    children: [
                      OutlinedButton(
                        onPressed: _currentPage > 1
                            ? () {
                                setState(() => _currentPage--);
                                _fetchLogs();
                              }
                            : null,
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: AppTheme.border),
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                        ),
                        child: const Text('Previous', style: TextStyle(fontSize: 12)),
                      ),
                      const SizedBox(width: 8),
                      OutlinedButton(
                        onPressed: _currentPage < lastPage
                            ? () {
                                setState(() => _currentPage++);
                                _fetchLogs();
                              }
                            : null,
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: AppTheme.border),
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                        ),
                        child: const Text('Next', style: TextStyle(fontSize: 12)),
                      ),
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
}

