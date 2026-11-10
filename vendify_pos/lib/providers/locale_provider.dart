import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LocaleProvider extends ChangeNotifier {
  Locale _locale = const Locale('en', 'US');

  Locale get locale => _locale;
  bool get isRtl => _locale.languageCode == 'ar';

  LocaleProvider() {
    _loadLocale();
  }

  Future<void> _loadLocale() async {
    final prefs = await SharedPreferences.getInstance();
    final savedCode = prefs.getString('app_locale') ?? 'en';
    if (savedCode == 'ar') {
      _locale = const Locale('ar', 'SA');
    } else {
      _locale = const Locale('en', 'US');
    }
    notifyListeners();
  }

  Future<void> setLocale(Locale locale) async {
    if (_locale == locale) return;
    _locale = locale;
    notifyListeners();

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('app_locale', locale.languageCode);
  }

  Future<void> toggleLocale() async {
    if (_locale.languageCode == 'en') {
      await setLocale(const Locale('ar', 'SA'));
    } else {
      await setLocale(const Locale('en', 'US'));
    }
  }
}

