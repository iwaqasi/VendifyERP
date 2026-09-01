import 'dart:convert';
import 'package:vendify_pos/models/product.dart';
import 'package:vendify_pos/models/contact.dart';
import 'package:vendify_pos/models/cart_item.dart';
import 'package:vendify_pos/services/api_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:vendify_pos/models/offline_transaction.dart';
import 'package:vendify_pos/services/offline_storage.dart';

class PosService {
  final ApiService _api;
  final OfflineStorage? _storage;
  OfflineStorage? _businessStorage;
  
  PosService(this._api, [this._storage]);

  /// Get business-specific offline storage
  Future<OfflineStorage?> _getStorage() async {
    if (_businessStorage != null) return _businessStorage;
    try {
      final prefs = await SharedPreferences.getInstance();
      final businessId = prefs.getInt('business_id') ?? 1;
      _businessStorage = OfflineStorage(businessId: businessId);
      return _businessStorage;
    } catch (_) {
      return _storage;
    }
  }

  // ============ Products ============

  Future<List<Product>> getProducts({
    String? search,
    int? categoryId,
    int? locationId,
    int perPage = 50,
  }) async {
    try {
      final params = <String, dynamic>{'per_page': perPage};
      if (search != null && search.isNotEmpty) params['search'] = search;
      if (categoryId != null) params['category_id'] = categoryId;
      if (locationId != null) params['location_id'] = locationId;

      final response = await _api.get('/v1/products', queryParameters: params);

      if (response.data['success'] == true) {
        final products = (response.data['data'] as List)
            .map((p) => Product.fromJson(p))
            .toList();

        // Update local cache with business-specific storage
        if (search == null && categoryId == null) {
          final storage = await _getStorage();
          storage?.saveProducts(products);
        }

        return products;
      }
    } catch (_) {
      // Fallback to business-specific local cache if network/API fails
      final storage = await _getStorage();
      if (storage != null) {
        return storage.getCachedProducts(search: search, categoryId: categoryId);
      }
    }

    return [];
  }

  Future<Product> getProductDetail(int productId, {int? locationId}) async {
    final params = <String, dynamic>{};
    if (locationId != null) params['location_id'] = locationId;

    final response = await _api.get(
      '/v1/products/$productId',
      queryParameters: params,
    );

    return Product.fromJson(response.data['data']);
  }

  Future<List<ProductVariation>> getProductVariations(
    int productId, {
    int? locationId,
  }) async {
    final params = <String, dynamic>{};
    if (locationId != null) params['location_id'] = locationId;

    final response = await _api.get(
      '/v1/products/$productId/variations',
      queryParameters: params,
    );

    return (response.data['data'] as List)
        .map((v) => ProductVariation.fromJson(v))
        .toList();
  }

  Future<List<Category>> getCategories() async {
    try {
      final response = await _api.get('/v1/categories');
      if (response.data['data'] is List) {
        final categories = (response.data['data'] as List)
            .map((c) => Category.fromJson(c))
            .toList();
        final storage = await _getStorage();
        storage?.saveCategories(categories);
        return categories;
      }
    } catch (_) {
      final storage = await _getStorage();
      if (storage != null) {
        return storage.getCachedCategories();
      }
    }
    return [];
  }

  Future<List<TaxRate>> getTaxRates() async {
    try {
      final response = await _api.get('/v1/tax-rates');
      if (response.data['data'] is List) {
        final taxRates = (response.data['data'] as List)
            .map((t) => TaxRate.fromJson(t))
            .toList();
        final storage = await _getStorage();
        storage?.saveTaxRates(taxRates);
        return taxRates;
      }
    } catch (_) {
      final storage = await _getStorage();
      if (storage != null) {
        return storage.getCachedTaxRates();
      }
    }
    return [];
  }

  // ============ Contacts ============

  Future<List<Contact>> getContacts({
    String? search,
    String type = 'customer',
    int perPage = 50,
  }) async {
    try {
      final params = <String, dynamic>{
        'type': type,
        'per_page': perPage,
      };
      if (search != null && search.isNotEmpty) params['search'] = search;

      final response = await _api.get('/v1/contacts', queryParameters: params);

      if (response.data['success'] == true) {
        final contacts = (response.data['data'] as List)
            .map((c) => Contact.fromJson(c))
            .toList();
        if (search == null) {
          final storage = await _getStorage();
          storage?.saveContacts(contacts);
        }
        return contacts;
      }
    } catch (_) {
      final storage = await _getStorage();
      if (storage != null) {
        return storage.getCachedContacts(search: search);
      }
    }

    return [];
  }

  Future<Map<String, dynamic>> getCustomerDetails(int contactId) async {
    final response = await _api.get('/v1/contacts/$contactId');
    return response.data['data'] as Map<String, dynamic>;
  }

  Future<Contact> createContact({
    required String name,
    String type = 'customer',
    String? mobile,
    String? email,
    String? taxNumber,
    String? shippingAddress,
    String? billingAddress,
    double? creditLimit,
    int? payTermNumber,
    String? payTermType,
  }) async {
    final response = await _api.post('/v1/contacts', data: {
      'name': name,
      'type': type,
      'mobile': mobile,
      'email': email,
      'tax_number': taxNumber,
      'shipping_address': shippingAddress,
      'billing_address': billingAddress,
      'credit_limit': creditLimit ?? 0,
      'pay_term_number': payTermNumber ?? 0,
      'pay_term_type': payTermType ?? 'days',
    });

    return Contact.fromJson(response.data['data']);
  }

  // ============ Sales ============

  Future<Map<String, dynamic>> createSell({
    required int businessId,
    required int locationId,
    int? contactId,
    required int userId,
    required List<CartItem> cartItems,
    required double subtotal,
    required double tax,
    required double discount,
    required double grandTotal,
    required List<Map<String, dynamic>> payments,
    String? additionalNotes,
    String? saleNote,
    double rpRedeemAmount = 0.0,
    int rpRedeemed = 0,
  }) async {
    // Build products array for API
    final products = cartItems.map((item) => {
      'product_id': item.productId,
      'variation_id': item.variationId,
      'quantity': item.quantity,
      'unit_price': item.unitPrice,
      'discount': item.discountAmount,
      'tax_id': item.taxId,
    }).toList();

    // Generate invoice number with prefix from settings
    final prefs = await SharedPreferences.getInstance();
    final receiptPrefix = prefs.getString('pos_receipt_prefix') ?? 'INV-POS-';
    final timestamp = DateTime.now().millisecondsSinceEpoch;
    final invoiceNo = '$receiptPrefix$timestamp';

    try {
      final response = await _api.post('/v1/sells', data: {
        'location_id': locationId,
        'contact_id': contactId,
        'products': products,
        'payments': payments,
        'additional_notes': additionalNotes ?? 'Walk-in Customer',
        'sale_note': saleNote,
        'discount_type': 'fixed',
        'discount_amount': discount,
        'invoice_no': invoiceNo,
        'rp_redeemed': rpRedeemed,
        'rp_redeemed_amount': rpRedeemAmount,
      });

      return response.data;
    } catch (e) {
      // Offline fallback: store transaction in business-specific sync queue
      final storage = await _getStorage();
      if (storage != null) {
        final localId = 'OFFLINE_${DateTime.now().millisecondsSinceEpoch}';
        final offlineTx = OfflineTransaction(
          localId: localId,
          businessId: businessId,
          locationId: locationId,
          contactId: contactId,
          userId: userId,
          products: products,
          payments: payments,
          subtotal: subtotal,
          tax: tax,
          discount: discount,
          grandTotal: grandTotal,
          additionalNotes: additionalNotes,
          saleNote: saleNote,
          createdAt: DateTime.now(),
        );

        await storage.enqueueTransaction(offlineTx);

        return {
          'success': true,
          'offline': true,
          'local_id': localId,
          'msg': 'Saved offline. Order will automatically sync once connected.',
          'data': {
            'invoice_no': localId,
            'final_total': grandTotal,
          },
        };
      }
      rethrow;
    }
  }

  // Legacy method for backwards compatibility
  Future<Map<String, dynamic>> createSale({
    required int locationId,
    int? contactId,
    required List<Map<String, dynamic>> products,
    required List<Map<String, dynamic>> payments,
    String? additionalNotes,
    String? saleNote,
    String? discountType,
    double? discountAmount,
    double? shippingCharges,
  }) async {
    final response = await _api.post('/v1/sells', data: {
      'location_id': locationId,
      'contact_id': contactId,
      'products': products,
      'payments': payments,
      'additional_notes': additionalNotes,
      'sale_note': saleNote,
      'discount_type': discountType,
      'discount_amount': discountAmount,
      'shipping_charges': shippingCharges,
    });

    return response.data;
  }

  Future<List<dynamic>> getSales({
    String? status,
    int? locationId,
    int perPage = 20,
  }) async {
    final params = <String, dynamic>{'per_page': perPage};
    if (status != null) params['status'] = status;
    if (locationId != null) params['location_id'] = locationId;

    final response = await _api.get('/v1/sells', queryParameters: params);
    return response.data['data'] ?? [];
  }

  // ============ Invoice History ============

  Future<Map<String, dynamic>> getSells({
    int? locationId,
    int? contactId,
    String? search,
    String? invoiceNo,
    String? startDate,
    String? endDate,
    String? paymentStatus,
    int page = 1,
    int perPage = 20,
  }) async {
    final params = <String, dynamic>{
      'page': page,
      'per_page': perPage,
    };
    if (locationId != null) params['location_id'] = locationId;
    if (contactId != null) params['contact_id'] = contactId;
    if (search != null && search.isNotEmpty) params['search'] = search;
    if (invoiceNo != null && invoiceNo.isNotEmpty) params['invoice_no'] = invoiceNo;
    if (startDate != null) params['start_date'] = startDate;
    if (endDate != null) params['end_date'] = endDate;
    if (paymentStatus != null) params['payment_status'] = paymentStatus;

    final response = await _api.get('/v1/sells', queryParameters: params);
    return response.data;
  }

  Future<Map<String, dynamic>> getSellDetail(int id) async {
    final response = await _api.get('/v1/sells/$id');
    return response.data['data'];
  }

  Future<Map<String, dynamic>> returnSellItems({
    required int sellId,
    required List<Map<String, dynamic>> returnItems,
    String refundMethod = 'cash',
    int? exchangeProductId,
    int? exchangeVariationId,
    double? exchangeQuantity,
    double? exchangeUnitPrice,
  }) async {
    final response = await _api.post('/v1/sells/$sellId/return', data: {
      'return_items': returnItems,
      'refund_method': refundMethod,
      'exchange_product_id': exchangeProductId,
      'exchange_variation_id': exchangeVariationId,
      'exchange_quantity': exchangeQuantity,
      'exchange_unit_price': exchangeUnitPrice,
    });
    return response.data;
  }

  Future<Map<String, dynamic>> payCustomerCredit({
    required int contactId,
    required double amount,
    required String method,
    String? reference,
  }) async {
    final response = await _api.post('/v1/contacts/$contactId/pay-credit', data: {
      'amount': amount,
      'method': method,
      'reference': reference,
    });
    return response.data;
  }

  // ============ Stock ============

  Future<List<dynamic>> getStockLevels({
    int? locationId,
    String? search,
  }) async {
    final params = <String, dynamic>{};
    if (locationId != null) params['location_id'] = locationId;
    if (search != null) params['search'] = search;

    final response = await _api.get('/v1/stock', queryParameters: params);
    return response.data['data'] ?? [];
  }

  // ============ Settings ============

  Future<Map<String, dynamic>> getBusinessSettings() async {
    final response = await _api.get('/v1/settings');
    return response.data['data'];
  }

  Future<List<dynamic>> getPaymentMethods() async {
    final response = await _api.get('/v1/payment-methods');
    return response.data['data'] ?? [];
  }

  /// Fetch VendifyPOS settings from Laravel backend
  Future<Map<String, dynamic>> getPosSettings() async {
    try {
      final response = await _api.get('/v1/pos-settings');
      if (response.data['success'] == true) {
        return response.data['data'];
      }
    } catch (e) {
      // Return defaults if API fails
    }
    // Default POS settings
    return {
      'pos_settings': {
        'pos_receipt_prefix': 'INV-POS-',
        'pos_default_payment_method': 'cash',
        'pos_tax_behavior': 'exclusive',
        'pos_currency_symbol': 'KD',
        'pos_receipt_footer': 'Thank you for your purchase!',
        'pos_enable_hold_recall': true,
        'pos_enable_split_payment': true,
        'pos_enable_auth_code': true,
        'pos_enable_customer_display': false,
      },
      'currency': {
        'symbol': 'KD',
        'code': 'KWD',
        'precision': 3,
        'symbol_placement': 'before',
      },
      'enabled_payment_methods': [
        {'method': 'cash', 'account': null},
        {'method': 'card', 'account': null},
      ],
      'tax_settings': {
        'enable_inline_tax': false,
        'default_sales_tax': null,
      },
    };
  }

  /// Save POS settings locally for offline use
  Future<void> savePosSettings(Map<String, dynamic> settings) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('pos_settings', jsonEncode(settings));
  }

  /// Load POS settings from local storage
  Future<Map<String, dynamic>?> loadPosSettings() async {
    final prefs = await SharedPreferences.getInstance();
    final data = prefs.getString('pos_settings');
    if (data != null) {
      return jsonDecode(data);
    }
    return null;
  }

  // ============ Business Type ============

  /// Get available business types
  Future<List<Map<String, dynamic>>> getBusinessTypes() async {
    try {
      final response = await _api.get('/v1/business-types');
      if (response.data['success'] == true) {
        return List<Map<String, dynamic>>.from(response.data['data']['types']);
      }
    } catch (e) {
      // Return default types on error
    }
    return [];
  }

  /// Get current business type and configuration
  Future<Map<String, dynamic>> getBusinessType() async {
    try {
      final response = await _api.get('/v1/business-type');
      if (response.data['success'] == true) {
        return Map<String, dynamic>.from(response.data['data']);
      }
    } catch (e) {
      // Return defaults on error
    }
    return {
      'business_type': 'retail',
      'pos_layout': 'retail',
      'features': {},
      'enabled_modules': {},
    };
  }

  /// Set business type
  Future<bool> setBusinessType(String type) async {
    try {
      final response = await _api.post('/v1/business-type', data: {
        'business_type': type,
      });
      return response.data['success'] == true;
    } catch (e) {
      return false;
    }
  }

  /// Save business type locally for offline use
  Future<void> saveBusinessType(Map<String, dynamic> typeConfig) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('business_type', jsonEncode(typeConfig));
  }

  /// Load business type from local storage
  Future<Map<String, dynamic>?> loadBusinessType() async {
    final prefs = await SharedPreferences.getInstance();
    final data = prefs.getString('business_type');
    if (data != null) {
      return jsonDecode(data);
    }
    return null;
  }

  // ============ Reward Points ============

  Future<Map<String, dynamic>> getCustomerRewardPoints(int contactId) async {
    try {
      final response = await _api.get('/v1/contacts/$contactId/reward-points');
      if (response.data['success'] == true) {
        return Map<String, dynamic>.from(response.data['data']);
      }
    } catch (e) {
      // Intentional fallback
    }
    return {'enabled': false, 'available_points': 0, 'equivalent_amount': 0.0};
  }

  // ============ Saloon / Spa ============

  Future<List<Map<String, dynamic>>> getSaloonAppointments({String? date}) async {
    try {
      final params = <String, dynamic>{};
      if (date != null) params['date'] = date;
      final response = await _api.get('/v1/saloon/appointments', queryParameters: params);
      if (response.data['success'] == true) {
        return List<Map<String, dynamic>>.from(response.data['data']['appointments']);
      }
    } catch (e) {
      // Intentional fallback
    }
    return [];
  }

  Future<Map<String, dynamic>> createSaloonAppointment(Map<String, dynamic> data) async {
    final response = await _api.post('/v1/saloon/appointments', data: data);
    return response.data;
  }

  Future<void> updateAppointmentStatus(int id, String status) async {
    await _api.put('/v1/saloon/appointments/$id/status', data: {
      'status': status,
    });
  }

  Future<List<Map<String, dynamic>>> getSaloonStaff() async {
    try {
      final response = await _api.get('/v1/saloon/staff');
      if (response.data['success'] == true) {
        return List<Map<String, dynamic>>.from(response.data['data']['staff']);
      }
    } catch (e) {
      // Intentional fallback
    }
    return [];
  }

  Future<Map<String, dynamic>> startService(int appointmentId, int staffId) async {
    final response = await _api.post('/v1/saloon/service/start', data: {
      'appointment_id': appointmentId,
      'staff_id': staffId,
    });
    return response.data;
  }

  Future<Map<String, dynamic>> completeService(int sessionId) async {
    final response = await _api.post('/v1/saloon/service/complete', data: {
      'session_id': sessionId,
    });
    return response.data;
  }

  // ============ Repair ============

  Future<List<Map<String, dynamic>>> getRepairTickets({String? status, String? search}) async {
    try {
      final params = <String, dynamic>{};
      if (status != null) params['status'] = status;
      if (search != null) params['search'] = search;
      final response = await _api.get('/v1/repairs', queryParameters: params);
      if (response.data['success'] == true) {
        return List<Map<String, dynamic>>.from(response.data['data']['repairs']);
      }
    } catch (e) {
      // Intentional fallback
    }
    return [];
  }

  Future<Map<String, dynamic>> createRepairTicket(Map<String, dynamic> data) async {
    final response = await _api.post('/v1/repairs', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> updateRepairStatus(int id, String status, {String? notes}) async {
    final response = await _api.put('/v1/repairs/$id/status', data: {
      'status': status,
      'notes': notes,
    });
    return response.data;
  }

  // ============ Restaurant ============

  Future<List<Map<String, dynamic>>> getRestaurantTables() async {
    try {
      final response = await _api.get('/v1/restaurant/tables');
      if (response.data['success'] == true) {
        return List<Map<String, dynamic>>.from(response.data['data']['tables']);
      }
    } catch (e) {
      // Intentional fallback
    }
    return [];
  }

  Future<Map<String, dynamic>> updateTableStatus(int id, String status) async {
    final response = await _api.put('/v1/restaurant/tables/$id/status', data: {
      'status': status,
    });
    return response.data;
  }

  Future<List<Map<String, dynamic>>> getRestaurantOrders({String? status}) async {
    try {
      final params = <String, dynamic>{};
      if (status != null) params['status'] = status;
      final response = await _api.get('/v1/restaurant/orders', queryParameters: params);
      if (response.data['success'] == true) {
        return List<Map<String, dynamic>>.from(response.data['data']['orders']);
      }
    } catch (e) {
      // Intentional fallback
    }
    return [];
  }

  Future<Map<String, dynamic>> createRestaurantOrder(Map<String, dynamic> data) async {
    final response = await _api.post('/v1/restaurant/orders', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> sendToKot(int orderId, List<int> itemIds) async {
    final response = await _api.post('/v1/restaurant/kot/send', data: {
      'order_id': orderId,
      'item_ids': itemIds,
    });
    return response.data;
  }

  // ============ Shifts / Daily Register ============

  Future<Map<String, dynamic>> getCurrentShift({int? locationId}) async {
    try {
      final params = <String, dynamic>{};
      if (locationId != null) params['location_id'] = locationId;
      final response = await _api.get('/v1/shifts/current', queryParameters: params);
      if (response.data['success'] == true) {
        return response.data['data'];
      }
    } catch (e) {
      // Intentional fallback
    }
    return {'shift': null};
  }

  Future<Map<String, dynamic>> openShift({
    double openingCash = 0,
    String? openingNotes,
    int? locationId,
  }) async {
    final response = await _api.post('/v1/shifts/open', data: {
      'opening_cash': openingCash,
      'opening_notes': openingNotes,
      'location_id': locationId,
    });
    return response.data;
  }

  Future<Map<String, dynamic>> closeShift({
    double? countedCash,
    String? closingNotes,
  }) async {
    final response = await _api.post('/v1/shifts/close', data: {
      'counted_cash': countedCash,
      'closing_notes': closingNotes,
    });
    return response.data;
  }

  Future<Map<String, dynamic>> getDailySummary({String? date}) async {
    try {
      final params = <String, dynamic>{};
      if (date != null) params['date'] = date;
      final response = await _api.get('/v1/shifts/daily-summary', queryParameters: params);
      if (response.data['success'] == true) {
        return response.data['data'];
      }
    } catch (e) {
      // Intentional fallback
    }
    return {};
  }

  // ============ Multi-Location ============

  Future<List<Map<String, dynamic>>> getLocations() async {
    try {
      final response = await _api.get('/v1/locations');
      if (response.data['success'] == true) {
        return List<Map<String, dynamic>>.from(response.data['data']['locations']);
      }
    } catch (e) {
      // Intentional fallback
    }
    return [];
  }

  Future<List<Map<String, dynamic>>> getStockSummary() async {
    try {
      final response = await _api.get('/v1/locations/stock-summary');
      if (response.data['success'] == true) {
        return List<Map<String, dynamic>>.from(response.data['data']['locations']);
      }
    } catch (e) {
      // Intentional fallback
    }
    return [];
  }

  Future<Map<String, dynamic>> getProductStockByLocation(int productId) async {
    try {
      final response = await _api.get('/v1/locations/stock/$productId');
      if (response.data['success'] == true) {
        return response.data['data'];
      }
    } catch (e) {
      // Intentional fallback
    }
    return {'stock': []};
  }

  Future<Map<String, dynamic>> createTransfer({
    required int fromLocationId,
    required int toLocationId,
    required List<Map<String, dynamic>> products,
  }) async {
    final response = await _api.post('/v1/locations/transfer', data: {
      'from_location_id': fromLocationId,
      'to_location_id': toLocationId,
      'products': products,
    });
    return response.data;
  }

  Future<List<Map<String, dynamic>>> getTransfers() async {
    try {
      final response = await _api.get('/v1/locations/transfers');
      if (response.data['success'] == true) {
        return List<Map<String, dynamic>>.from(response.data['data']['transfers']);
      }
    } catch (e) {
      // Intentional fallback
    }
    return [];
  }

  Future<Map<String, dynamic>> getSalesReportByLocation({String? dateFrom, String? dateTo}) async {
    try {
      final params = <String, dynamic>{};
      if (dateFrom != null) params['date_from'] = dateFrom;
      if (dateTo != null) params['date_to'] = dateTo;
      final response = await _api.get('/v1/locations/sales-report', queryParameters: params);
      if (response.data['success'] == true) {
        return response.data['data'];
      }
    } catch (e) {
      // Intentional fallback
    }
    return {};
  }
}
