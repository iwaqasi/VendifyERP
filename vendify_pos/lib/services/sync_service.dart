import 'package:vendify_pos/services/api_service.dart';
import 'package:vendify_pos/services/offline_storage.dart';
import 'package:vendify_pos/models/product.dart';
import 'package:vendify_pos/models/contact.dart';

class SyncResult {
  final int syncedCount;
  final int failedCount;
  final List<String> errors;

  SyncResult({
    required this.syncedCount,
    required this.failedCount,
    required this.errors,
  });

  bool get isSuccess => failedCount == 0;
}

class SyncService {
  final ApiService _api;
  final OfflineStorage _storage;

  SyncService(this._api, this._storage);

  // ============ Sync Catalog Data ============

  /// Fetch every page of a paginated API resource.
  ///
  /// POS terminals must have the COMPLETE catalog offline — previously only
  /// page 1 (100 rows) was cached, silently hiding products from the grid.
  /// Walks `meta.last_page` with a hard safety cap.
  Future<List<dynamic>> _fetchPaginated(
    String path, {
    Map<String, dynamic>? queryParameters,
    int maxPages = 200,
  }) async {
    final items = <dynamic>[];
    var page = 1;

    while (page <= maxPages) {
      final response = await _api.get(path, queryParameters: {
        ...?queryParameters,
        'page': page,
      });

      if (response.data['success'] != true) break;

      final data = response.data['data'];
      if (data is! List || data.isEmpty) break;

      items.addAll(data);

      final meta = response.data['meta'];
      final lastPage = (meta is Map ? meta['last_page'] : null) ?? page;
      if (page >= lastPage) break;
      page++;
    }

    return items;
  }

  Future<void> syncCatalog({int? locationId}) async {
    // Products & contacts: fetch ALL pages (per_page 100 matches the
    // server-side pagination cap).
    final productParams = <String, dynamic>{'per_page': 100};
    if (locationId != null) productParams['location_id'] = locationId;
    final contactParams = <String, dynamic>{'type': 'customer', 'per_page': 100};

    // Each resource syncs independently: a failure in one must not
    // prevent the others (and their existing caches) from refreshing.
    try {
      final raw = await _fetchPaginated('/v1/products', queryParameters: productParams);
      await _storage.saveProducts(
          raw.map((p) => Product.fromJson(p as Map<String, dynamic>)).toList());
    } catch (_) {/* offline — existing cache remains safe */}

    try {
      final raw = await _fetchPaginated('/v1/contacts', queryParameters: contactParams);
      await _storage.saveContacts(
          raw.map((c) => Contact.fromJson(c as Map<String, dynamic>)).toList());
    } catch (_) {/* offline — existing cache remains safe */}

    try {
      final response = await _api.get('/v1/categories');
      final data = response.data['data'];
      if (data is List) {
        await _storage.saveCategories(
            data.map((c) => Category.fromJson(c as Map<String, dynamic>)).toList());
      }
    } catch (_) {/* offline — existing cache remains safe */}

    try {
      final response = await _api.get('/v1/tax-rates');
      final data = response.data['data'];
      if (data is List) {
        await _storage.saveTaxRates(
            data.map((t) => TaxRate.fromJson(t as Map<String, dynamic>)).toList());
      }
    } catch (_) {/* offline — existing cache remains safe */}
  }

  // ============ Sync Pending Transactions ============

  Future<SyncResult> syncPendingTransactions() async {
    final queue = await _storage.getSyncQueue();
    final pending = queue.where((t) => t.status != 'synced').toList();

    int synced = 0;
    int failed = 0;
    final List<String> errors = [];

    for (final tx in pending) {
      // Mark syncing
      await _storage.updateTransaction(tx.copyWith(status: 'syncing'));

      try {
        final response = await _api.post('/v1/sells', data: {
          'location_id': tx.locationId,
          'contact_id': tx.contactId,
          'products': tx.products,
          'payments': tx.payments,
          'additional_notes': tx.additionalNotes ?? 'Offline POS Sale',
          'sale_note': tx.saleNote,
          'discount_type': 'fixed',
          'discount_amount': tx.discount,
          'local_transaction_id': tx.localId,
          'offline_created_at': tx.createdAt.toIso8601String(),
        });

        if (response.data['success'] == true) {
          await _storage.removeTransaction(tx.localId);
          synced++;
        } else {
          final err = response.data['msg'] ?? 'Server error during sync';
          await _storage.updateTransaction(tx.copyWith(
            status: 'failed',
            errorMessage: err.toString(),
            retryCount: tx.retryCount + 1,
          ));
          failed++;
          errors.add('Tx ${tx.localId}: $err');
        }
      } catch (e) {
        await _storage.updateTransaction(tx.copyWith(
          status: 'failed',
          errorMessage: e.toString(),
          retryCount: tx.retryCount + 1,
        ));
        failed++;
        errors.add('Tx ${tx.localId}: Network error');
      }
    }

    return SyncResult(
      syncedCount: synced,
      failedCount: failed,
      errors: errors,
    );
  }
}
