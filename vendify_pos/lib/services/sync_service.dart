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

  Future<void> syncCatalog({int? locationId}) async {
    try {
      final params = <String, dynamic>{'per_page': 100};
      if (locationId != null) params['location_id'] = locationId;

      final results = await Future.wait([
        _api.get('/v1/products', queryParameters: params),
        _api.get('/v1/categories'),
        _api.get('/v1/tax-rates'),
        _api.get('/v1/contacts', queryParameters: {'type': 'customer', 'per_page': 100}),
      ]);

      // Cache products
      final prodRes = results[0];
      if (prodRes.data['success'] == true) {
        final products = (prodRes.data['data'] as List)
            .map((p) => Product.fromJson(p))
            .toList();
        await _storage.saveProducts(products);
      }

      // Cache categories
      final catRes = results[1];
      if (catRes.data['data'] is List) {
        final categories = (catRes.data['data'] as List)
            .map((c) => Category.fromJson(c))
            .toList();
        await _storage.saveCategories(categories);
      }

      // Cache tax rates
      final taxRes = results[2];
      if (taxRes.data['data'] is List) {
        final taxRates = (taxRes.data['data'] as List)
            .map((t) => TaxRate.fromJson(t))
            .toList();
        await _storage.saveTaxRates(taxRates);
      }

      // Cache contacts
      final contactRes = results[3];
      if (contactRes.data['success'] == true) {
        final contacts = (contactRes.data['data'] as List)
            .map((c) => Contact.fromJson(c))
            .toList();
        await _storage.saveContacts(contacts);
      }
    } catch (_) {
      // Offline or error during sync; existing cache remains safe
    }
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
