import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/config/api_config.dart';
import 'package:vendify_pos/providers/theme_provider.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/screens/pos/widgets/print_settings_dialog.dart';
import 'package:vendify_pos/screens/pos/widgets/sync_center_dialog.dart';
import 'package:vendify_pos/screens/pos/widgets/diagnostics_dialog.dart';
import 'package:vendify_pos/screens/pos/widgets/audit_trail_dialog.dart';

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  late TextEditingController _urlController;
  bool _isSaving = false;
  String? _savedMessage;
  bool _isAdmin = false;
  String _userName = '';
  String _userRole = '';
  late PosService _posService;

  @override
  void initState() {
    super.initState();
    final apiService = ref.read(apiProvider);
    _posService = PosService(apiService);
    _urlController = TextEditingController(
      text: apiService.baseUrl ?? ApiConfig.baseUrl,
    );
    _loadUserData();
  }

  Future<void> _loadUserData() async {
    final prefs = await SharedPreferences.getInstance();
    if (mounted) {
      setState(() {
        _isAdmin = prefs.getBool('is_admin') ?? false;
        _userName = prefs.getString('user_name') ?? '';
        final roles = prefs.getStringList('user_roles') ?? [];
        _userRole = roles.isNotEmpty ? roles.join(', ') : (_isAdmin ? 'Admin / Manager' : 'Cashier');
      });
    }
  }

  void _openManagerTool(VoidCallback action) {
    if (_isAdmin) {
      action();
      return;
    }

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppTheme.surface,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        title: const Row(
          children: [
            Icon(Icons.lock_outline, color: AppTheme.warning, size: 22),
            SizedBox(width: 8),
            Text('Manager Access Required', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
          ],
        ),
        content: const Text(
          'This tool is restricted to Store Managers and Administrators. Please ask your supervisor for assistance.',
          style: TextStyle(fontSize: 13, color: AppTheme.textSecondary),
        ),
        actions: [
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primary),
            onPressed: () => Navigator.pop(ctx),
            child: const Text('OK', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _urlController.dispose();
    super.dispose();
  }

  Future<void> _saveUrl() async {
    setState(() {
      _isSaving = true;
      _savedMessage = null;
    });

    try {
      final apiService = ref.read(apiProvider);
      String url = _urlController.text.trim();

      // Remove trailing slash
      if (url.endsWith('/')) {
        url = url.substring(0, url.length - 1);
      }

      // Ensure /api suffix
      if (!url.endsWith('/api')) {
        url = '$url/api';
      }

      await apiService.saveBaseUrl(url);
      apiService.init(baseUrl: url);

      setState(() {
        _isSaving = false;
        _savedMessage = 'Server URL updated successfully!';
      });

      Future.delayed(const Duration(seconds: 3), () {
        if (mounted) {
          setState(() => _savedMessage = null);
        }
      });
    } catch (e) {
      setState(() {
        _isSaving = false;
        _savedMessage = 'Error: $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final themeProvider = context.watch<ThemeProvider>();
    final brightness = Theme.of(context).brightness;
    final isDark = brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.background : AppTheme.lightBackground,
      appBar: AppBar(
        backgroundColor: isDark ? AppTheme.surface : AppTheme.lightSurface,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: isDark ? AppTheme.textPrimary : AppTheme.lightTextPrimary),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Settings',
          style: TextStyle(
            color: isDark ? AppTheme.textPrimary : AppTheme.lightTextPrimary,
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(AppTheme.md),
        children: [
          // Server URL Section
          _buildSectionHeader('Server Connection', isDark),
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.surface : AppTheme.lightSurface,
              borderRadius: BorderRadius.circular(AppTheme.radiusMd),
              border: Border.all(color: isDark ? AppTheme.border : AppTheme.lightBorder),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Server URL',
                  style: TextStyle(
                    color: isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary,
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _urlController,
                        style: TextStyle(
                          color: isDark ? AppTheme.textPrimary : AppTheme.lightTextPrimary,
                          fontSize: 14,
                        ),
                        decoration: InputDecoration(
                          hintText: 'http://example.com/api',
                          hintStyle: TextStyle(
                            color: isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary,
                          ),
                          prefixIcon: Icon(
                            Icons.dns,
                            color: AppTheme.primary,
                            size: 20,
                          ),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppTheme.radiusSm),
                            borderSide: BorderSide(
                              color: isDark ? AppTheme.border : AppTheme.lightBorder,
                            ),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppTheme.radiusSm),
                            borderSide: BorderSide(
                              color: isDark ? AppTheme.border : AppTheme.lightBorder,
                            ),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(AppTheme.radiusSm),
                            borderSide: const BorderSide(
                              color: AppTheme.primary,
                              width: 2,
                            ),
                          ),
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 12,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    ElevatedButton(
                      onPressed: _isSaving ? null : _saveUrl,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 14,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(AppTheme.radiusSm),
                        ),
                      ),
                      child: _isSaving
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text(
                              'Save',
                              style: TextStyle(fontWeight: FontWeight.w600),
                            ),
                    ),
                  ],
                ),
                if (_savedMessage != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    _savedMessage!,
                    style: TextStyle(
                      color: _savedMessage!.startsWith('Error')
                          ? AppTheme.error
                          : AppTheme.success,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Text(
                  'Restart the app after changing the URL',
                  style: TextStyle(
                    color: isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary,
                    fontSize: 11,
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),
          const Divider(),
          const SizedBox(height: 16),

          // Theme Section
          _buildSectionHeader('Appearance', isDark),
          const SizedBox(height: 8),
          _buildThemeOption(
            context,
            themeProvider: themeProvider,
            mode: ThemeModeOption.light,
            icon: Icons.light_mode,
            label: 'Light',
            isDark: isDark,
          ),
          _buildThemeOption(
            context,
            themeProvider: themeProvider,
            mode: ThemeModeOption.dark,
            icon: Icons.dark_mode,
            label: 'Dark',
            isDark: isDark,
          ),
          _buildThemeOption(
            context,
            themeProvider: themeProvider,
            mode: ThemeModeOption.system,
            icon: Icons.brightness_auto,
            label: 'System',
            isDark: isDark,
          ),

          const SizedBox(height: 24),
          const Divider(),
          const SizedBox(height: 16),

          // Business Info
          _buildSectionHeader('Business', isDark),
          const SizedBox(height: 8),
          _buildInfoTile('Business Name', 'VendifyERP', isDark),
          _buildInfoTile('Location', 'Main Store', isDark),

          const SizedBox(height: 24),
          const Divider(),
          const SizedBox(height: 16),

          // Print Settings
          _buildSectionHeader('Printing', isDark),
          const SizedBox(height: 8),
          Container(
            margin: const EdgeInsets.only(bottom: 8),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.surface : AppTheme.lightSurface,
              borderRadius: BorderRadius.circular(AppTheme.radiusMd),
              border: Border.all(color: isDark ? AppTheme.border : AppTheme.lightBorder),
            ),
            child: ListTile(
              leading: Icon(
                Icons.print,
                color: AppTheme.primary,
              ),
              title: Text(
                'Printer Settings',
                style: TextStyle(
                  color: isDark ? AppTheme.textPrimary : AppTheme.lightTextPrimary,
                  fontWeight: FontWeight.w500,
                ),
              ),
              subtitle: Text(
                'Paper size, copies, auto-print',
                style: TextStyle(
                  color: isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary,
                  fontSize: 12,
                ),
              ),
              trailing: Icon(
                Icons.chevron_right,
                color: isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary,
              ),
              onTap: () => PrintSettingsDialog.show(context),
            ),
          ),

          const SizedBox(height: 24),
          const Divider(),
          const SizedBox(height: 16),

          // Manager & Administrative Tools
          _buildSectionHeader('Manager & Administrative Tools', isDark),
          const SizedBox(height: 8),
          _buildManagerToolTile(
            title: 'Sync Center',
            subtitle: 'Manual sync queue, offline cache status & conflicts',
            icon: Icons.sync_alt,
            isDark: isDark,
            onTap: () => _openManagerTool(() => SyncCenterDialog.show(context)),
          ),
          _buildManagerToolTile(
            title: 'System Diagnostics & Logs',
            subtitle: 'Real-time HTTP logs, local database inspection, connectivity',
            icon: Icons.bug_report_outlined,
            isDark: isDark,
            onTap: () => _openManagerTool(() => DiagnosticsDialog.show(context)),
          ),
          _buildManagerToolTile(
            title: 'Audit Trail & Activity Logs',
            subtitle: 'Detailed chronological register of cash, voids, discounts & sales',
            icon: Icons.shield_outlined,
            isDark: isDark,
            onTap: () => _openManagerTool(() => AuditTrailDialog.show(context, _posService)),
          ),

          const SizedBox(height: 24),
          const Divider(),
          const SizedBox(height: 16),

          // About
          _buildSectionHeader('About', isDark),
          const SizedBox(height: 8),
          _buildInfoTile('Version', 'v${ApiConfig.appVersion}', isDark),
          _buildInfoTile('Build Number', ApiConfig.appBuild, isDark),
          _buildInfoTile('User Account', '$_userName ($_userRole)', isDark),
          const SizedBox(height: 8),
          Container(
            width: double.infinity,
            margin: const EdgeInsets.only(bottom: 8),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.surface : AppTheme.lightSurface,
              borderRadius: BorderRadius.circular(AppTheme.radiusMd),
              border: Border.all(color: isDark ? AppTheme.border : AppTheme.lightBorder),
            ),
            child: ListTile(
              leading: const Icon(Icons.android, color: Colors.green),
              title: Text(
                'Download Android App (APK)',
                style: TextStyle(
                  color: isDark ? AppTheme.textPrimary : AppTheme.lightTextPrimary,
                  fontWeight: FontWeight.w600,
                ),
              ),
              subtitle: Text(
                'Direct install for mobile & handheld terminals (v${ApiConfig.appVersion})',
                style: TextStyle(
                  color: isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary,
                  fontSize: 12,
                ),
              ),
              trailing: const Icon(Icons.download, color: Colors.green),
              onTap: () async {
                final uri = Uri.parse(ApiConfig.downloadApkUrl);
                if (await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
            ),
          ),
          Container(
            width: double.infinity,
            margin: const EdgeInsets.only(bottom: 8),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.surface : AppTheme.lightSurface,
              borderRadius: BorderRadius.circular(AppTheme.radiusMd),
              border: Border.all(color: isDark ? AppTheme.border : AppTheme.lightBorder),
            ),
            child: ListTile(
              leading: const Icon(Icons.desktop_windows, color: Color(0xFF0078D7)),
              title: Text(
                'Download Windows POS (.MSIX)',
                style: TextStyle(
                  color: isDark ? AppTheme.textPrimary : AppTheme.lightTextPrimary,
                  fontWeight: FontWeight.w600,
                ),
              ),
              subtitle: Text(
                'Desktop installer with offline sync & hardware peripherals (v${ApiConfig.appVersion})',
                style: TextStyle(
                  color: isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary,
                  fontSize: 12,
                ),
              ),
              trailing: const Icon(Icons.download, color: Color(0xFF0078D7)),
              onTap: () async {
                final uri = Uri.parse(ApiConfig.downloadWindowsUrl);
                if (await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String title, bool isDark) {
    return Text(
      title,
      style: TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w600,
        color: AppTheme.primary,
        letterSpacing: 0.5,
      ),
    );
  }

  Widget _buildThemeOption(
    BuildContext context, {
    required ThemeProvider themeProvider,
    required ThemeModeOption mode,
    required IconData icon,
    required String label,
    required bool isDark,
  }) {
    final isSelected = themeProvider.themeMode == mode;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.surface : AppTheme.lightSurface,
        borderRadius: BorderRadius.circular(AppTheme.radiusMd),
        border: Border.all(
          color: isSelected ? AppTheme.primary : (isDark ? AppTheme.border : AppTheme.lightBorder),
          width: isSelected ? 2 : 1,
        ),
      ),
      child: ListTile(
        leading: Icon(
          icon,
          color: isSelected ? AppTheme.primary : (isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary),
        ),
        title: Text(
          label,
          style: TextStyle(
            color: isDark ? AppTheme.textPrimary : AppTheme.lightTextPrimary,
            fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
          ),
        ),
        trailing: isSelected
            ? const Icon(Icons.check_circle, color: AppTheme.primary)
            : null,
        onTap: () => themeProvider.setThemeMode(mode),
      ),
    );
  }

  Widget _buildInfoTile(String label, String value, bool isDark) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.surface : AppTheme.lightSurface,
        borderRadius: BorderRadius.circular(AppTheme.radiusMd),
        border: Border.all(color: isDark ? AppTheme.border : AppTheme.lightBorder),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              color: isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary,
              fontSize: 14,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              color: isDark ? AppTheme.textPrimary : AppTheme.lightTextPrimary,
              fontSize: 14,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildManagerToolTile({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool isDark,
    required VoidCallback onTap,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.surface : AppTheme.lightSurface,
        borderRadius: BorderRadius.circular(AppTheme.radiusMd),
        border: Border.all(color: isDark ? AppTheme.border : AppTheme.lightBorder),
      ),
      child: ListTile(
        leading: Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: AppTheme.primary.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, color: AppTheme.primary, size: 20),
        ),
        title: Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: TextStyle(
                  color: isDark ? AppTheme.textPrimary : AppTheme.lightTextPrimary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            if (!_isAdmin) ...[
              const SizedBox(width: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: AppTheme.warning.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.lock, size: 10, color: AppTheme.warning),
                    SizedBox(width: 3),
                    Text('Manager', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.warning)),
                  ],
                ),
              ),
            ],
          ],
        ),
        subtitle: Text(
          subtitle,
          style: TextStyle(
            color: isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary,
            fontSize: 12,
          ),
        ),
        trailing: Icon(
          Icons.chevron_right,
          color: isDark ? AppTheme.textSecondary : AppTheme.lightTextSecondary,
        ),
        onTap: onTap,
      ),
    );
  }
}
