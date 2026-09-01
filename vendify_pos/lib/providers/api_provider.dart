import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:vendify_pos/services/api_service.dart';
import 'package:vendify_pos/services/auth_service.dart';
import 'package:vendify_pos/services/pos_service.dart';
import 'package:vendify_pos/services/offline_storage.dart';
import 'package:vendify_pos/services/sync_service.dart';

final apiProvider = Provider<ApiService>((ref) {
  return ApiService();
});

final offlineStorageProvider = Provider<OfflineStorage>((ref) {
  // Business-specific cache: read businessId from SharedPreferences
  // This is a workaround since SharedPreferences is async
  return OfflineStorage(businessId: 1); // Will be overridden by PosService
});

final authServiceProvider = Provider<AuthService>((ref) {
  return AuthService(ref.read(apiProvider));
});

final posServiceProvider = Provider<PosService>((ref) {
  return PosService(
    ref.read(apiProvider),
    ref.read(offlineStorageProvider),
  );
});

final syncServiceProvider = Provider<SyncService>((ref) {
  return SyncService(
    ref.read(apiProvider),
    ref.read(offlineStorageProvider),
  );
});
