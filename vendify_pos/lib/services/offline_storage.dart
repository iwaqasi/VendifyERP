import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/models/product.dart';
import 'package:vendify_pos/models/contact.dart';
import 'package:vendify_pos/models/offline_transaction.dart';

class OfflineStorage {
  final int businessId;

  OfflineStorage({this.businessId = 1});

  String _k(String key) => '${key}_biz_$businessId';

  static const String _keyProducts = 'cached_products';
  static const String _keyCategories = 'cached_categories';
  static const String _keyTaxRates = 'cached_tax_rates';
  static const String _keyContacts = 'cached_contacts';
  static const String _keySyncQueue = 'offline_sync_queue';
  static const String _keyLastCatalogSync = 'last_catalog_sync';

  // ============ Catalog Caching ============

  Future<void> saveProducts(List<Product> products) async {
    final prefs = await SharedPreferences.getInstance();
    final jsonList = products.map((p) => p.toJson()).toList();
    await prefs.setString(_k(_keyProducts), jsonEncode(jsonList));
    await prefs.setString(_k(_keyLastCatalogSync), DateTime.now().toIso8601String());
  }

  Future<List<Product>> getCachedProducts({String? search, int? categoryId}) async {
    final prefs = await SharedPreferences.getInstance();
    final data = prefs.getString(_k(_keyProducts));
    if (data == null) return [];

    try {
      final List decoded = jsonDecode(data);
      var products = decoded.map((json) => Product.fromJson(json)).toList();

      if (categoryId != null) {
        products = products.where((p) => p.categoryId == categoryId).toList();
      }

      if (search != null && search.isNotEmpty) {
        final query = search.toLowerCase();
        products = products.where((p) =>
          p.name.toLowerCase().contains(query) ||
          (p.sku?.toLowerCase().contains(query) ?? false)
        ).toList();
      }

      return products;
    } catch (_) {
      return [];
    }
  }

  Future<void> saveCategories(List<Category> categories) async {
    final prefs = await SharedPreferences.getInstance();
    final jsonList = categories.map((c) => c.toJson()).toList();
    await prefs.setString(_k(_keyCategories), jsonEncode(jsonList));
  }

  Future<List<Category>> getCachedCategories() async {
    final prefs = await SharedPreferences.getInstance();
    final data = prefs.getString(_k(_keyCategories));
    if (data == null) return [];

    try {
      final List decoded = jsonDecode(data);
      return decoded.map((json) => Category.fromJson(json)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> saveTaxRates(List<TaxRate> taxRates) async {
    final prefs = await SharedPreferences.getInstance();
    final jsonList = taxRates.map((t) => t.toJson()).toList();
    await prefs.setString(_k(_keyTaxRates), jsonEncode(jsonList));
  }

  Future<List<TaxRate>> getCachedTaxRates() async {
    final prefs = await SharedPreferences.getInstance();
    final data = prefs.getString(_k(_keyTaxRates));
    if (data == null) return [];

    try {
      final List decoded = jsonDecode(data);
      return decoded.map((json) => TaxRate.fromJson(json)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> saveContacts(List<Contact> contacts) async {
    final prefs = await SharedPreferences.getInstance();
    final jsonList = contacts.map((c) => c.toJson()).toList();
    await prefs.setString(_k(_keyContacts), jsonEncode(jsonList));
  }

  Future<List<Contact>> getCachedContacts({String? search}) async {
    final prefs = await SharedPreferences.getInstance();
    final data = prefs.getString(_k(_keyContacts));
    if (data == null) return [];

    try {
      final List decoded = jsonDecode(data);
      var contacts = decoded.map((json) => Contact.fromJson(json)).toList();

      if (search != null && search.isNotEmpty) {
        final query = search.toLowerCase();
        contacts = contacts.where((c) =>
          c.name.toLowerCase().contains(query) ||
          (c.mobile?.toLowerCase().contains(query) ?? false)
        ).toList();
      }

      return contacts;
    } catch (_) {
      return [];
    }
  }

  Future<DateTime?> getLastCatalogSyncTime() async {
    final prefs = await SharedPreferences.getInstance();
    final timestamp = prefs.getString(_k(_keyLastCatalogSync));
    if (timestamp != null) {
      return DateTime.tryParse(timestamp);
    }
    return null;
  }

  // ============ Sync Queue Management ============

  Future<List<OfflineTransaction>> getSyncQueue() async {
    final prefs = await SharedPreferences.getInstance();
    final data = prefs.getString(_keySyncQueue);
    if (data == null) return [];

    try {
      final List decoded = jsonDecode(data);
      return decoded.map((json) => OfflineTransaction.fromJson(json)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> enqueueTransaction(OfflineTransaction transaction) async {
    final queue = await getSyncQueue();
    queue.add(transaction);
    await _saveQueue(queue);
  }

  Future<void> updateTransaction(OfflineTransaction transaction) async {
    final queue = await getSyncQueue();
    final index = queue.indexWhere((t) => t.localId == transaction.localId);
    if (index >= 0) {
      queue[index] = transaction;
      await _saveQueue(queue);
    }
  }

  Future<void> removeTransaction(String localId) async {
    final queue = await getSyncQueue();
    queue.removeWhere((t) => t.localId == localId);
    await _saveQueue(queue);
  }

  Future<void> _saveQueue(List<OfflineTransaction> queue) async {
    final prefs = await SharedPreferences.getInstance();
    final jsonList = queue.map((t) => t.toJson()).toList();
    await prefs.setString(_keySyncQueue, jsonEncode(jsonList));
  }

  Future<int> getPendingTransactionsCount() async {
    final queue = await getSyncQueue();
    return queue.where((t) => t.status != 'synced').length;
  }

  Future<void> clearAllCache() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_k(_keyProducts));
    await prefs.remove(_k(_keyCategories));
    await prefs.remove(_k(_keyTaxRates));
    await prefs.remove(_k(_keyContacts));
    await prefs.remove(_k(_keyLastCatalogSync));
  }
}
