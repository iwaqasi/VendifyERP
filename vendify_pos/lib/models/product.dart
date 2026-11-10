double _parseDouble(dynamic value) {
  if (value == null) return 0.0;
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value) ?? 0.0;
  return 0.0;
}

int _parseInt(dynamic value) {
  if (value == null) return 0;
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value) ?? 0;
  return 0;
}

class StockAtOtherLocation {
  final int locationId;
  final String locationName;
  final double qtyAvailable;

  StockAtOtherLocation({
    required this.locationId,
    required this.locationName,
    required this.qtyAvailable,
  });

  factory StockAtOtherLocation.fromJson(Map<String, dynamic> json) {
    return StockAtOtherLocation(
      locationId: _parseInt(json['location_id']),
      locationName: json['location_name']?.toString() ?? '',
      qtyAvailable: _parseDouble(json['qty_available']),
    );
  }
}

class Product {
  final int id;
  final String name;
  final String? sku;
  final String? barcode;
  final String type;
  final int? categoryId;
  final String? categoryName;
  final String? brandName;
  final String? unitName;
  final double sellPriceIncTax;
  final double productCostPrice;
  final bool enableStock;
  final double qtyAvailable;
  final List<StockAtOtherLocation> stockAtOtherLocations;
  final bool isFlexiblePrice;
  final String? image;
  final int? taxId;
  final String? taxName;
  final double taxRate;
  final String? description;
  final bool isServiceProduct;
  final bool hasVariations;
  final int? serviceTime;
  final int? variationId;

  Product({
    required this.id,
    required this.name,
    this.sku,
    this.barcode,
    required this.type,
    this.categoryId,
    this.categoryName,
    this.brandName,
    this.unitName,
    this.sellPriceIncTax = 0,
    this.productCostPrice = 0,
    this.enableStock = false,
    this.qtyAvailable = 0,
    this.stockAtOtherLocations = const [],
    this.isFlexiblePrice = false,
    this.image,
    this.taxId,
    this.taxName,
    this.taxRate = 0,
    this.description,
    this.isServiceProduct = false,
    this.hasVariations = false,
    this.serviceTime,
    this.variationId,
  });

  /// Total stock across ALL locations
  double get totalStockAcrossLocations {
    double total = qtyAvailable;
    for (final loc in stockAtOtherLocations) {
      total += loc.qtyAvailable;
    }
    return total;
  }

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: _parseInt(json['id']),
      name: json['name']?.toString() ?? '',
      sku: json['sku']?.toString(),
      barcode: json['barcode']?.toString(),
      type: json['type']?.toString() ?? 'single',
      categoryId: json['category_id'] != null
          ? _parseInt(json['category_id'])
          : null,
      categoryName: json['category_name']?.toString(),
      brandName: json['brand_name']?.toString(),
      unitName: json['unit_name']?.toString(),
      sellPriceIncTax: _parseDouble(json['sell_price_inc_tax']),
      productCostPrice: _parseDouble(json['product_cost_price']),
      enableStock: json['enable_stock'] == true || json['enable_stock'] == 1,
      qtyAvailable: _parseDouble(json['qty_available']),
      stockAtOtherLocations: json['stock_at_other_locations'] is List
          ? (json['stock_at_other_locations'] as List)
                .whereType<Map>()
                .map(
                  (l) => StockAtOtherLocation.fromJson(
                    Map<String, dynamic>.from(l),
                  ),
                )
                .toList()
          : [],
      isFlexiblePrice:
          json['is_flexible_price'] == true || json['is_flexible_price'] == 1,
      image: json['image']?.toString(),
      taxId: json['tax_id'] != null ? _parseInt(json['tax_id']) : null,
      taxName: json['tax_name']?.toString(),
      taxRate: _parseDouble(json['tax_rate']),
      description: json['description']?.toString(),
      isServiceProduct:
          json['is_service_product'] == true ||
          json['is_service_product'] == 1 ||
          json['type'] == 'service' ||
          json['enable_stock'] == false ||
          json['enable_stock'] == 0 ||
          json['enable_stock'] == '0',
      hasVariations:
          json['has_variations'] == true || json['has_variations'] == 1,
      serviceTime: json['service_time'] != null
          ? _parseInt(json['service_time'])
          : null,
      variationId: json['variation_id'] != null
          ? _parseInt(json['variation_id'])
          : null,
    );
  }
  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'sku': sku,
    'barcode': barcode,
    'type': type,
    'category_id': categoryId,
    'category_name': categoryName,
    'brand_name': brandName,
    'unit_name': unitName,
    'sell_price_inc_tax': sellPriceIncTax,
    'product_cost_price': productCostPrice,
    'enable_stock': enableStock,
    'qty_available': qtyAvailable,
    'stock_at_other_locations': stockAtOtherLocations
        .map(
          (l) => {
            'location_id': l.locationId,
            'location_name': l.locationName,
            'qty_available': l.qtyAvailable,
          },
        )
        .toList(),
    'is_flexible_price': isFlexiblePrice,
    'image': image,
    'tax_id': taxId,
    'tax_name': taxName,
    'tax_rate': taxRate,
    'description': description,
    'is_service_product': isServiceProduct,
    'has_variations': hasVariations,
    'service_time': serviceTime,
    'variation_id': variationId,
  };
}

class ProductVariation {
  final int id;
  final String name;
  final String? sku;
  final String? barcode;
  final double sellPriceIncTax;
  final double unitPriceIncTax;
  final double qtyAvailable;
  final bool enableStock;

  ProductVariation({
    required this.id,
    required this.name,
    this.sku,
    this.barcode,
    this.sellPriceIncTax = 0,
    this.unitPriceIncTax = 0,
    this.qtyAvailable = 0,
    this.enableStock = false,
  });

  factory ProductVariation.fromJson(Map<String, dynamic> json) {
    return ProductVariation(
      id: _parseInt(json['id']),
      name: json['name']?.toString() ?? '',
      sku: json['sku']?.toString(),
      barcode: json['barcode']?.toString(),
      sellPriceIncTax: _parseDouble(json['sell_price_inc_tax']),
      unitPriceIncTax: _parseDouble(json['unit_price_inc_tax']),
      qtyAvailable: _parseDouble(json['qty_available']),
      enableStock: json['enable_stock'] == true || json['enable_stock'] == 1,
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'sku': sku,
    'barcode': barcode,
    'sell_price_inc_tax': sellPriceIncTax,
    'unit_price_inc_tax': unitPriceIncTax,
    'qty_available': qtyAvailable,
    'enable_stock': enableStock,
  };
}

class Category {
  final int id;
  final String name;
  final int? parentId;
  final int productCount;
  final String? image;
  final String? imageUrl;

  Category({
    required this.id,
    required this.name,
    this.parentId,
    this.productCount = 0,
    this.image,
    this.imageUrl,
  });

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      id: _parseInt(json['id']),
      name: json['name']?.toString() ?? '',
      parentId: json['parent_id'] != null ? _parseInt(json['parent_id']) : null,
      productCount: _parseInt(json['product_count']),
      image: json['image']?.toString(),
      imageUrl: json['image_url']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'parent_id': parentId,
    'product_count': productCount,
    'image': image,
    'image_url': imageUrl,
  };
}

class TaxRate {
  final int id;
  final String name;
  final double amount;

  TaxRate({required this.id, required this.name, this.amount = 0});

  factory TaxRate.fromJson(Map<String, dynamic> json) {
    return TaxRate(
      id: _parseInt(json['id']),
      name: json['name']?.toString() ?? '',
      amount: _parseDouble(json['amount']),
    );
  }

  Map<String, dynamic> toJson() => {'id': id, 'name': name, 'amount': amount};
}
