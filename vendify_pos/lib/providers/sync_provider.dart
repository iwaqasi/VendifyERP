import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:vendify_pos/models/offline_transaction.dart';
import 'package:vendify_pos/providers/api_provider.dart';
import 'package:vendify_pos/services/sync_service.dart';

enum SyncStatus { idle, syncing, success, error }

class SyncState {
  final bool isOnline;
  final SyncStatus status;
  final int pendingCount;
  final int failedCount;
  final List<OfflineTransaction> queue;
  final DateTime? lastSyncTime;
  final String? lastError;

  SyncState({
    this.isOnline = true,
    this.status = SyncStatus.idle,
    this.pendingCount = 0,
    this.failedCount = 0,
    this.queue = const [],
    this.lastSyncTime,
    this.lastError,
  });

  SyncState copyWith({
    bool? isOnline,
    SyncStatus? status,
    int? pendingCount,
    int? failedCount,
    List<OfflineTransaction>? queue,
    DateTime? lastSyncTime,
    String? lastError,
    bool clearError = false,
  }) {
    return SyncState(
      isOnline: isOnline ?? this.isOnline,
      status: status ?? this.status,
      pendingCount: pendingCount ?? this.pendingCount,
      failedCount: failedCount ?? this.failedCount,
      queue: queue ?? this.queue,
      lastSyncTime: lastSyncTime ?? this.lastSyncTime,
      lastError: clearError ? null : (lastError ?? this.lastError),
    );
  }
}

class SyncNotifier extends StateNotifier<SyncState> {
  final SyncService _syncService;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;

  SyncNotifier(this._syncService) : super(SyncState()) {
    _init();
  }

  Future<void> _init() async {
    // Initial load
    await refreshQueue();

    // Monitor connectivity
    final connectivity = Connectivity();
    final initialStatus = await connectivity.checkConnectivity();
    final isOnline = !initialStatus.contains(ConnectivityResult.none);
    state = state.copyWith(isOnline: isOnline);

    _connectivitySub = connectivity.onConnectivityChanged.listen((results) {
      final online = !results.contains(ConnectivityResult.none);
      state = state.copyWith(isOnline: online);
      if (online && state.pendingCount > 0) {
        // Auto-sync when network returns
        syncAll();
      }
    });
  }

  Future<void> refreshQueue() async {
    try {
      final storage = await _syncService.getStorage();
      final queue = await storage.getSyncQueue();
      final pending = queue.where((t) => t.status != 'synced').toList();
      final failed = queue.where((t) => t.status == 'failed').length;
      final lastSync = await storage.getLastCatalogSyncTime();

      state = state.copyWith(
        queue: queue,
        pendingCount: pending.length,
        failedCount: failed,
        lastSyncTime: lastSync,
      );
    } catch (_) {}
  }

  Future<void> syncAll({int? locationId}) async {
    if (state.status == SyncStatus.syncing) return;

    state = state.copyWith(status: SyncStatus.syncing, clearError: true);

    try {
      // 1. Sync catalog
      await _syncService.syncCatalog(locationId: locationId);

      // 2. Sync pending transactions
      final result = await _syncService.syncPendingTransactions();

      await refreshQueue();

      if (result.isSuccess) {
        state = state.copyWith(
          status: SyncStatus.success,
          lastSyncTime: DateTime.now(),
        );
      } else {
        state = state.copyWith(
          status: SyncStatus.error,
          lastError: result.errors.isNotEmpty
              ? result.errors.first
              : 'Some transactions could not be synced',
        );
      }
    } catch (e) {
      await refreshQueue();
      state = state.copyWith(
        status: SyncStatus.error,
        lastError: e.toString(),
      );
    }
  }

  Future<bool> retrySingle(OfflineTransaction tx) async {
    final storage = await _syncService.getStorage();
    await storage.updateTransaction(tx.copyWith(status: 'pending', errorMessage: null));
    await refreshQueue();
    await syncAll();
    return true;
  }

  Future<void> deleteSingle(String localId) async {
    final storage = await _syncService.getStorage();
    await storage.removeTransaction(localId);
    await refreshQueue();
  }

  Future<void> syncCatalogOnly({int? locationId}) async {
    state = state.copyWith(status: SyncStatus.syncing, clearError: true);
    try {
      await _syncService.syncCatalog(locationId: locationId);
      final storage = await _syncService.getStorage();
      final lastSync = await storage.getLastCatalogSyncTime();
      state = state.copyWith(
        status: SyncStatus.success,
        lastSyncTime: lastSync ?? DateTime.now(),
      );
    } catch (e) {
      state = state.copyWith(status: SyncStatus.error, lastError: e.toString());
    }
  }

  @override
  void dispose() {
    _connectivitySub?.cancel();
    super.dispose();
  }
}

final syncProvider = StateNotifierProvider<SyncNotifier, SyncState>((ref) {
  final syncService = ref.read(syncServiceProvider);
  return SyncNotifier(syncService);
});
