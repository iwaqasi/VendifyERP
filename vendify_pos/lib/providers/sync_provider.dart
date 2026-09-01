import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/services/sync_service.dart';
import 'package:vendify_pos/services/offline_storage.dart';

enum SyncStatus { idle, syncing, success, error }

class SyncState {
  final bool isOnline;
  final SyncStatus status;
  final int pendingCount;
  final DateTime? lastSyncTime;
  final String? lastError;

  SyncState({
    this.isOnline = true,
    this.status = SyncStatus.idle,
    this.pendingCount = 0,
    this.lastSyncTime,
    this.lastError,
  });

  SyncState copyWith({
    bool? isOnline,
    SyncStatus? status,
    int? pendingCount,
    DateTime? lastSyncTime,
    String? lastError,
    bool clearError = false,
  }) {
    return SyncState(
      isOnline: isOnline ?? this.isOnline,
      status: status ?? this.status,
      pendingCount: pendingCount ?? this.pendingCount,
      lastSyncTime: lastSyncTime ?? this.lastSyncTime,
      lastError: clearError ? null : (lastError ?? this.lastError),
    );
  }
}

class SyncNotifier extends StateNotifier<SyncState> {
  final SyncService _syncService;
  final OfflineStorage _storage;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;

  SyncNotifier(this._syncService, this._storage) : super(SyncState()) {
    _init();
  }

  Future<void> _init() async {
    // Initial pending count & last sync time
    final count = await _storage.getPendingTransactionsCount();
    final lastSync = await _storage.getLastCatalogSyncTime();
    state = state.copyWith(pendingCount: count, lastSyncTime: lastSync);

    // Monitor connectivity
    final connectivity = Connectivity();
    final initialStatus = await connectivity.checkConnectivity();
    final isOnline = !initialStatus.contains(ConnectivityResult.none);
    state = state.copyWith(isOnline: isOnline);

    _connectivitySub = connectivity.onConnectivityChanged.listen((results) {
      final online = !results.contains(ConnectivityResult.none);
      state = state.copyWith(isOnline: online);
      if (online && state.pendingCount > 0) {
        // Auto-sync when back online
        syncAll();
      }
    });
  }

  Future<void> syncAll({int? locationId}) async {
    if (state.status == SyncStatus.syncing) return;

    state = state.copyWith(status: SyncStatus.syncing, clearError: true);

    try {
      // 1. Sync catalog
      await _syncService.syncCatalog(locationId: locationId);

      // 2. Sync pending transactions
      final result = await _syncService.syncPendingTransactions();

      final pendingCount = await _storage.getPendingTransactionsCount();
      final lastSync = await _storage.getLastCatalogSyncTime();

      if (result.isSuccess) {
        state = state.copyWith(
          status: SyncStatus.success,
          pendingCount: pendingCount,
          lastSyncTime: lastSync ?? DateTime.now(),
        );
      } else {
        state = state.copyWith(
          status: SyncStatus.error,
          pendingCount: pendingCount,
          lastError: result.errors.isNotEmpty ? result.errors.first : 'Sync failed for some transactions',
        );
      }
    } catch (e) {
      state = state.copyWith(
        status: SyncStatus.error,
        lastError: e.toString(),
      );
    }
  }

  Future<void> refreshPendingCount() async {
    final count = await _storage.getPendingTransactionsCount();
    state = state.copyWith(pendingCount: count);
  }

  @override
  void dispose() {
    _connectivitySub?.cancel();
    super.dispose();
  }
}

final syncProvider = StateNotifierProvider<SyncNotifier, SyncState>((ref) {
  final syncService = ref.read(syncServiceProvider);
  final storage = ref.read(offlineStorageProvider);
  return SyncNotifier(syncService, storage);
});
