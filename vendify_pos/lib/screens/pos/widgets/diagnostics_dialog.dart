import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/api_config.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/logger_service.dart';

class DiagnosticsDialog extends StatefulWidget {
  const DiagnosticsDialog({super.key});

  static Future<void> show(BuildContext context) {
    return showDialog(
      context: context,
      barrierDismissible: true,
      builder: (_) => const DiagnosticsDialog(),
    );
  }

  @override
  State<DiagnosticsDialog> createState() => _DiagnosticsDialogState();
}

class _DiagnosticsDialogState extends State<DiagnosticsDialog> {
  bool _isLoading = true;
  Map<String, dynamic> _systemInfo = {};
  List<LogEntry> _logs = [];

  @override
  void initState() {
    super.initState();
    _loadDiagnostics();
  }

  Future<void> _loadDiagnostics() async {
    setState(() => _isLoading = true);
    final prefs = await SharedPreferences.getInstance();
    final logs = await LoggerService().getStoredLogs();

    final info = <String, dynamic>{
      'app_name': 'Vendify POS',
      'app_version': '1.0.0',
      'platform': kIsWeb ? 'Web' : defaultTargetPlatform.name,
      'base_url': prefs.getString('base_url') ?? ApiConfig.baseUrl,
      'business_id': prefs.getInt('business_id') ?? 1,
      'business_name': prefs.getString('business_name') ?? 'Default Store',
      'business_type': prefs.getString('business_type') ?? 'retail',
      'location_id': prefs.getInt('location_id') ?? 1,
      'user_name': prefs.getString('user_name') ?? 'Admin',
      'device_timestamp': DateTime.now().toIso8601String(),
      'stored_logs_count': logs.length,
    };

    if (mounted) {
      setState(() {
        _systemInfo = info;
        _logs = logs.reversed.toList();
        _isLoading = false;
      });
    }
  }

  void _copyToClipboard() {
    final buffer = StringBuffer();
    buffer.writeln('=== VENDIFY POS DIAGNOSTIC DUMP ===');
    buffer.writeln('Generated: ${DateTime.now().toIso8601String()}');
    buffer.writeln('\n--- SYSTEM INFO ---');
    _systemInfo.forEach((k, v) => buffer.writeln('$k: $v'));
    buffer.writeln('\n--- STORED ERROR LOGS (${_logs.length}) ---');
    for (final l in _logs) {
      buffer.writeln('[${l.timestamp.toIso8601String()}][${l.level.name.toUpperCase()}][${l.tag}] ${l.message}');
      if (l.stackTrace != null) {
        buffer.writeln(l.stackTrace);
      }
    }
    buffer.writeln('=== END OF DUMP ===');

    Clipboard.setData(ClipboardData(text: buffer.toString()));

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Diagnostic bundle copied to clipboard'),
        backgroundColor: AppTheme.success,
        duration: Duration(seconds: 2),
      ),
    );
  }

  Future<void> _clearLogs() async {
    await LoggerService().clearLogs();
    await _loadDiagnostics();
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        width: 700,
        height: 560,
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.bug_report_outlined, color: AppTheme.primary, size: 24),
                ),
                const SizedBox(width: 12),
                const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'POS Diagnostics & Support Bundle',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                    ),
                    Text(
                      'Export logs and system parameters for debugging and support',
                      style: TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                    ),
                  ],
                ),
                const Spacer(),
                IconButton(
                  icon: const Icon(Icons.close, color: AppTheme.textMuted),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            const SizedBox(height: 16),

            // System Parameters Grid
            if (_isLoading)
              const Expanded(child: Center(child: CircularProgressIndicator()))
            else
              Expanded(
                child: Column(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppTheme.surfaceLight,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppTheme.border),
                      ),
                      child: Column(
                        children: [
                          Row(
                            children: [
                              _buildParam('App Version', '${_systemInfo['app_version']} (${_systemInfo['platform']})'),
                              _buildParam('Business', '${_systemInfo['business_name']} (#${_systemInfo['business_id']})'),
                              _buildParam('Location ID', '#${_systemInfo['location_id']}'),
                            ],
                          ),
                          const Divider(height: 16, color: AppTheme.border),
                          Row(
                            children: [
                              _buildParam('Active User', '${_systemInfo['user_name']}'),
                              _buildParam('Layout Mode', '${_systemInfo['business_type']}'),
                              _buildParam('Base API URL', '${_systemInfo['base_url']}'),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),

                    // Logs Section
                    Row(
                      children: [
                        Text(
                          'Recorded Error Logs (${_logs.length})',
                          style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.textPrimary, fontSize: 13),
                        ),
                        const Spacer(),
                        if (_logs.isNotEmpty)
                          TextButton.icon(
                            onPressed: _clearLogs,
                            icon: const Icon(Icons.delete_sweep, size: 16, color: AppTheme.textMuted),
                            label: const Text('Clear Logs', style: TextStyle(color: AppTheme.textMuted, fontSize: 12)),
                          ),
                      ],
                    ),
                    const SizedBox(height: 6),

                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.3),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: AppTheme.border),
                        ),
                        child: _logs.isEmpty
                            ? const Center(
                                child: Text('No recorded errors. Everything running cleanly!',
                                    style: TextStyle(color: AppTheme.success, fontSize: 13)),
                              )
                            : ListView.separated(
                                itemCount: _logs.length,
                                separatorBuilder: (_, __) => const Divider(height: 12, color: Colors.white12),
                                itemBuilder: (ctx, i) {
                                  final log = _logs[i];
                                  final isError = log.level == LogLevel.error || log.level == LogLevel.fatal;
                                  return Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: (isError ? AppTheme.error : Colors.amber).withValues(alpha: 0.2),
                                              borderRadius: BorderRadius.circular(4),
                                            ),
                                            child: Text(
                                              log.level.name.toUpperCase(),
                                              style: TextStyle(
                                                color: isError ? AppTheme.error : Colors.amber,
                                                fontSize: 10,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                          ),
                                          const SizedBox(width: 8),
                                          Text(
                                            '[${log.tag}] ${DateFormat('HH:mm:ss dd/MM').format(log.timestamp)}',
                                            style: const TextStyle(color: AppTheme.textMuted, fontSize: 11),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        log.message,
                                        style: const TextStyle(color: AppTheme.textPrimary, fontSize: 12, fontFamily: 'monospace'),
                                      ),
                                      if (log.stackTrace != null) ...[
                                        const SizedBox(height: 2),
                                        Text(
                                          log.stackTrace!,
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 10, fontFamily: 'monospace'),
                                        ),
                                      ],
                                    ],
                                  );
                                },
                              ),
                      ),
                    ),
                  ],
                ),
              ),

            const SizedBox(height: 16),
            // Actions
            Row(
              children: [
                ElevatedButton.icon(
                  onPressed: _copyToClipboard,
                  icon: const Icon(Icons.copy, size: 16),
                  label: const Text('Copy Diagnostics Bundle'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    foregroundColor: Colors.black,
                  ),
                ),
                const Spacer(),
                OutlinedButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Close'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildParam(String label, String value) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(fontSize: 11, color: AppTheme.textMuted)),
          const SizedBox(height: 2),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
          ),
        ],
      ),
    );
  }
}

