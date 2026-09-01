import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/models/offline_transaction.dart';
import 'package:vendify_pos/models/product.dart';
import 'package:vendify_pos/models/contact.dart';
import 'package:vendify_pos/services/offline_storage.dart';
import 'package:vendify_pos/services/logger_service.dart';
import 'package:vendify_pos/services/sync_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  group('OfflineTransaction Model Tests', () {
    test('Should properly serialize and deserialize OfflineTransaction', () {
      final now = DateTime.now();
      final tx = OfflineTransaction(
        localId: 'TX_1001',
        businessId: 3,
        locationId: 3,
        contactId: 10,
        userId: 1,
        products: [
          {'product_id': 1, 'quantity': 2, 'unit_price': 15.0}
        ],
        payments: [
          {'method': 'cash', 'amount': 30.0}
        ],
        subtotal: 30.0,
        tax: 0.0,
        discount: 0.0,
        grandTotal: 30.0,
        createdAt: now,
      );

      final json = tx.toJson();
      expect(json['local_id'], equals('TX_1001'));
      expect(json['status'], equals('pending'));
      expect(json['grand_total'], equals(30.0));

      final fromJson = OfflineTransaction.fromJson(json);
      expect(fromJson.localId, equals('TX_1001'));
      expect(fromJson.products.length, equals(1));
      expect(fromJson.status, equals('pending'));
    });

    test('copyWith should update status and retry count', () {
      final tx = OfflineTransaction(
        localId: 'TX_1002',
        businessId: 3,
        locationId: 3,
        userId: 1,
        products: [],
        payments: [],
        subtotal: 10.0,
        tax: 0.0,
        discount: 0.0,
        grandTotal: 10.0,
        createdAt: DateTime.now(),
      );

      final updated = tx.copyWith(status: 'syncing', retryCount: 1);
      expect(updated.status, equals('syncing'));
      expect(updated.retryCount, equals(1));
      expect(updated.localId, equals('TX_1002'));
    });
  });

  group('OfflineStorage Tests', () {
    late OfflineStorage storage;

    setUp(() {
      storage = OfflineStorage();
    });

    test('Should cache and retrieve products accurately', () async {
      final products = [
        Product(
          id: 1,
          name: 'Espresso Coffee',
          sku: 'COF-01',
          type: 'single',
          sellPriceIncTax: 4.50,
          categoryId: 2,
        ),
        Product(
          id: 2,
          name: 'Croissant',
          sku: 'BAK-01',
          type: 'single',
          sellPriceIncTax: 3.00,
          categoryId: 3,
        ),
      ];

      await storage.saveProducts(products);

      final cached = await storage.getCachedProducts();
      expect(cached.length, equals(2));
      expect(cached[0].name, equals('Espresso Coffee'));

      // Filter by category
      final categoryFiltered = await storage.getCachedProducts(categoryId: 2);
      expect(categoryFiltered.length, equals(1));
      expect(categoryFiltered.first.id, equals(1));

      // Filter by search query
      final searchFiltered = await storage.getCachedProducts(search: 'croissant');
      expect(searchFiltered.length, equals(1));
      expect(searchFiltered.first.name, equals('Croissant'));
    });

    test('Should cache and retrieve contacts', () async {
      final contacts = [
        Contact(id: 1, name: 'Alice Smith', type: 'customer', mobile: '1234567890'),
        Contact(id: 2, name: 'Bob Jones', type: 'customer', mobile: '9876543210'),
      ];

      await storage.saveContacts(contacts);

      final cached = await storage.getCachedContacts();
      expect(cached.length, equals(2));

      final searched = await storage.getCachedContacts(search: 'Alice');
      expect(searched.length, equals(1));
      expect(searched.first.name, equals('Alice Smith'));
    });

    test('Should enqueue, update, and remove offline transactions from queue', () async {
      final tx = OfflineTransaction(
        localId: 'TX_OFF_1',
        businessId: 3,
        locationId: 3,
        userId: 1,
        products: [],
        payments: [],
        subtotal: 50.0,
        tax: 5.0,
        discount: 0.0,
        grandTotal: 55.0,
        createdAt: DateTime.now(),
      );

      await storage.enqueueTransaction(tx);
      var pendingCount = await storage.getPendingTransactionsCount();
      expect(pendingCount, equals(1));

      // Update status
      final updating = tx.copyWith(status: 'synced');
      await storage.updateTransaction(updating);

      pendingCount = await storage.getPendingTransactionsCount();
      expect(pendingCount, equals(0));

      // Remove
      await storage.removeTransaction('TX_OFF_1');
      final queue = await storage.getSyncQueue();
      expect(queue.isEmpty, isTrue);
    });
  });

  group('LoggerService Tests', () {
    test('Should record error and warning logs to storage', () async {
      final logger = LoggerService();
      await logger.info('Info message');
      await logger.error('Error occurred in POS test', tag: 'UNIT_TEST');

      final stored = await logger.getStoredLogs();
      expect(stored.any((l) => l.message == 'Error occurred in POS test'), isTrue);
    });
  });

  group('SyncResult Tests', () {
    test('SyncResult calculates isSuccess correctly', () {
      final successResult = SyncResult(syncedCount: 5, failedCount: 0, errors: []);
      expect(successResult.isSuccess, isTrue);

      final failureResult = SyncResult(syncedCount: 3, failedCount: 2, errors: ['Network timeout']);
      expect(failureResult.isSuccess, isFalse);
    });
  });
}
