import 'package:flutter/material.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/services/print_service.dart';

/// Dialog for configuring print settings (paper size, copies, auto-print)
class PrintSettingsDialog extends StatefulWidget {
  const PrintSettingsDialog({super.key});

  /// Show the dialog and return true if settings were changed
  static Future<bool> show(BuildContext context) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (_) => const PrintSettingsDialog(),
    );
    return changed ?? false;
  }

  @override
  State<PrintSettingsDialog> createState() => _PrintSettingsDialogState();
}

class _PrintSettingsDialogState extends State<PrintSettingsDialog> {
  final PrintService _printService = PrintService();
  PaperSize _paperSize = PaperSize.thermal80;
  bool _autoPrint = false;
  int _copies = 1;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadSettings();
  }

  Future<void> _loadSettings() async {
    final paperSize = await _printService.getPaperSize();
    final autoPrint = await _printService.getAutoPrint();
    final copies = await _printService.getCopies();
    setState(() {
      _paperSize = paperSize;
      _autoPrint = autoPrint;
      _copies = copies;
      _isLoading = false;
    });
  }

  Future<void> _saveSettings() async {
    await _printService.setPaperSize(_paperSize);
    await _printService.setAutoPrint(_autoPrint);
    await _printService.setCopies(_copies);
    if (mounted) {
      Navigator.pop(context, true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Print settings saved'),
          backgroundColor: AppTheme.success,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Dialog(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: CircularProgressIndicator(),
        ),
      );
    }

    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Container(
        width: 400,
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                const Icon(Icons.print, color: AppTheme.primary, size: 24),
                const SizedBox(width: 12),
                const Text(
                  'Print Settings',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const Spacer(),
                IconButton(
                  icon: const Icon(Icons.close, color: AppTheme.textSecondary),
                  onPressed: () => Navigator.pop(context, false),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // Paper Size
            const Text(
              'Paper Size',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: AppTheme.textSecondary,
              ),
            ),
            const SizedBox(height: 8),
            ...PaperSize.values.map((size) => RadioListTile<PaperSize>(
              title: Text(
                _getPaperSizeLabel(size),
                style: const TextStyle(color: AppTheme.textPrimary, fontSize: 14),
              ),
              subtitle: Text(
                _getPaperSizeDescription(size),
                style: const TextStyle(color: AppTheme.textMuted, fontSize: 12),
              ),
              value: size,
              groupValue: _paperSize,
              onChanged: (v) => setState(() => _paperSize = v!),
              activeColor: AppTheme.primary,
              contentPadding: EdgeInsets.zero,
              dense: true,
            )),

            const SizedBox(height: 16),

            // Copies
            const Text(
              'Number of Copies',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: AppTheme.textSecondary,
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                _buildCopiesButton(Icons.remove, () {
                  if (_copies > 1) setState(() => _copies--);
                }),
                Container(
                  width: 60,
                  alignment: Alignment.center,
                  child: Text(
                    '$_copies',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                ),
                _buildCopiesButton(Icons.add, () {
                  if (_copies < 5) setState(() => _copies++);
                }),
              ],
            ),

            const SizedBox(height: 16),

            // Auto-print
            SwitchListTile(
              title: const Text(
                'Auto-print after sale',
                style: TextStyle(color: AppTheme.textPrimary, fontSize: 14),
              ),
              subtitle: const Text(
                'Automatically open print dialog after each sale',
                style: TextStyle(color: AppTheme.textMuted, fontSize: 12),
              ),
              value: _autoPrint,
              onChanged: (v) => setState(() => _autoPrint = v),
              activeColor: AppTheme.primary,
              contentPadding: EdgeInsets.zero,
            ),

            const SizedBox(height: 20),

            // Save button
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _saveSettings,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                child: const Text(
                  'Save Settings',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCopiesButton(IconData icon, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: AppTheme.surfaceLight,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: AppTheme.border),
        ),
        child: Icon(icon, color: AppTheme.textPrimary, size: 20),
      ),
    );
  }

  String _getPaperSizeLabel(PaperSize size) {
    switch (size) {
      case PaperSize.regular:
        return 'Regular (A4 / Letter)';
      case PaperSize.thermal80:
        return 'Thermal 80mm';
      case PaperSize.thermal58:
        return 'Thermal 58mm';
    }
  }

  String _getPaperSizeDescription(PaperSize size) {
    switch (size) {
      case PaperSize.regular:
        return 'Standard office printer';
      case PaperSize.thermal80:
        return 'Common POS thermal printer (e.g. Epson TM-T20)';
      case PaperSize.thermal58:
        return 'Compact POS thermal printer';
    }
  }
}
