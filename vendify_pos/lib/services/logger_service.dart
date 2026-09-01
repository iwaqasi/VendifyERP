import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

enum LogLevel { debug, info, warning, error, fatal }

class LogEntry {
  final DateTime timestamp;
  final LogLevel level;
  final String tag;
  final String message;
  final String? stackTrace;

  LogEntry({
    required this.timestamp,
    required this.level,
    required this.tag,
    required this.message,
    this.stackTrace,
  });

  Map<String, dynamic> toJson() => {
    'timestamp': timestamp.toIso8601String(),
    'level': level.name,
    'tag': tag,
    'message': message,
    'stack_trace': stackTrace,
  };

  factory LogEntry.fromJson(Map<String, dynamic> json) => LogEntry(
    timestamp: DateTime.tryParse(json['timestamp'] ?? '') ?? DateTime.now(),
    level: LogLevel.values.firstWhere(
      (l) => l.name == json['level'],
      orElse: () => LogLevel.info,
    ),
    tag: json['tag'] ?? 'APP',
    message: json['message'] ?? '',
    stackTrace: json['stack_trace'],
  );
}

class LoggerService {
  static const String _keyLogs = 'app_crash_logs';
  static const int _maxStoredLogs = 200;

  static final LoggerService _instance = LoggerService._();
  LoggerService._();
  factory LoggerService() => _instance;

  Future<void> debug(String message, {String tag = 'DEBUG'}) async {
    await _log(LogLevel.debug, tag, message);
  }

  Future<void> info(String message, {String tag = 'INFO'}) async {
    await _log(LogLevel.info, tag, message);
  }

  Future<void> warning(String message, {String tag = 'WARN', Object? error, StackTrace? stackTrace}) async {
    await _log(LogLevel.warning, tag, message, stackTrace: stackTrace?.toString() ?? error?.toString());
  }

  Future<void> error(String message, {String tag = 'ERROR', Object? error, StackTrace? stackTrace}) async {
    await _log(LogLevel.error, tag, message, stackTrace: stackTrace?.toString() ?? error?.toString());
  }

  Future<void> fatal(String message, {String tag = 'FATAL', Object? error, StackTrace? stackTrace}) async {
    await _log(LogLevel.fatal, tag, message, stackTrace: stackTrace?.toString() ?? error?.toString());
  }

  Future<void> _log(LogLevel level, String tag, String message, {String? stackTrace}) async {
    final entry = LogEntry(
      timestamp: DateTime.now(),
      level: level,
      tag: tag,
      message: message,
      stackTrace: stackTrace,
    );

    if (kDebugMode) {
      debugPrint('[${entry.level.name.toUpperCase()}][${entry.tag}] ${entry.message}');
      if (stackTrace != null) {
        debugPrint(stackTrace);
      }
    }

    if (level == LogLevel.error || level == LogLevel.fatal || level == LogLevel.warning) {
      await _persistLog(entry);
    }
  }

  Future<void> _persistLog(LogEntry entry) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final existingJson = prefs.getStringList(_keyLogs) ?? [];
      final updatedList = List<String>.from(existingJson);

      updatedList.add(jsonEncode(entry.toJson()));

      // Keep within max logs limit to prevent unbounded storage
      if (updatedList.length > _maxStoredLogs) {
        updatedList.removeRange(0, updatedList.length - _maxStoredLogs);
      }

      await prefs.setStringList(_keyLogs, updatedList);
    } catch (_) {
      // Avoid recursive failure during logging
    }
  }

  Future<List<LogEntry>> getStoredLogs() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final logsJson = prefs.getStringList(_keyLogs) ?? [];
      return logsJson.map((str) {
        try {
          return LogEntry.fromJson(jsonDecode(str));
        } catch (_) {
          return null;
        }
      }).whereType<LogEntry>().toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> clearLogs() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyLogs);
  }

  void setupGlobalErrorHandlers() {
    FlutterError.onError = (FlutterErrorDetails details) {
      FlutterError.presentError(details);
      error(
        details.exceptionAsString(),
        tag: 'FLUTTER_ERROR',
        stackTrace: details.stack,
      );
    };

    PlatformDispatcher.instance.onError = (Object error, StackTrace stack) {
      fatal(
        error.toString(),
        tag: 'UNCAUGHT_EXCEPTION',
        stackTrace: stack,
      );
      return true;
    };
  }
}
