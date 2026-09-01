import 'package:vendify_pos/providers/api_provider.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/config/api_config.dart';
import 'package:vendify_pos/providers/theme_provider.dart';
import 'package:vendify_pos/screens/pos/widgets/print_settings_dialog.dart';

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  late TextEditingController _urlController;
  bool _isSaving = false;
  String? _savedMessage;

  @override
  void initState() {
    super.initState();
    final apiService = ref.read(apiProvider);
    _urlController = TextEditingController(
      text: apiService.baseUrl ?? ApiConfig.baseUrl,
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

          // About
          _buildSectionHeader('About', isDark),
          const SizedBox(height: 8),
          _buildInfoTile('Version', '1.0.0', isDark),
          _buildInfoTile('Build', '2026.08', isDark),
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
}
