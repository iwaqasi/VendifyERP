import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/config/api_config.dart';
import 'package:vendify_pos/providers/theme_provider.dart';
import 'package:vendify_pos/screens/login/pin_screen.dart';

import 'package:flutter_riverpod/flutter_riverpod.dart' hide ChangeNotifierProvider;
import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/services/logger_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Setup crash logging and error handlers
  LoggerService().setupGlobalErrorHandlers();
  LoggerService().info('Application starting up', tag: 'INIT');

  final container = ProviderContainer();
  final apiService = container.read(apiProvider);
  
  // Force update the base URL from config (clears old cached URL)
  final savedUrl = await apiService.loadBaseUrl();
  
  // If saved URL doesn't match config, force update
  if (savedUrl != ApiConfig.baseUrl) {
    await apiService.saveBaseUrl(ApiConfig.baseUrl);
  }
  
  apiService.init(baseUrl: ApiConfig.baseUrl);
  await apiService.loadToken();

  runApp(
    UncontrolledProviderScope(
      container: container,
      child: ChangeNotifierProvider(
        create: (_) => ThemeProvider(),
        child: const VendifyPosApp(),
      ),
    ),
  );
}

class VendifyPosApp extends StatelessWidget {
  const VendifyPosApp({super.key});

  @override
  Widget build(BuildContext context) {
    final themeProvider = context.watch<ThemeProvider>();

    return MaterialApp(
      title: 'Vendify',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: themeProvider.flutterThemeMode,
      home: const PinScreen(),
    );
  }
}
