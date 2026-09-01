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
      locationId: json['location_id'] ?? 0,
      locationName: json['location_name'] ?? '',
      qtyAvailable: (json['qty_available'] ?? 0).toDouble(),
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
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      sku: json['sku'],
      barcode: json['barcode']?.toString(),
      type: json['type'] ?? 'single',
      categoryId: json['category_id'],
      categoryName: json['category_name'],
      brandName: json['brand_name'],
      unitName: json['unit_name'],
      sellPriceIncTax: (json['sell_price_inc_tax'] ?? 0).toDouble(),
      productCostPrice: (json['product_cost_price'] ?? 0).toDouble(),
      enableStock: json['enable_stock'] == true || json['enable_stock'] == 1,
      qtyAvailable: (json['qty_available'] ?? 0).toDouble(),
      stockAtOtherLocations: json['stock_at_other_locations'] != null
          ? (json['stock_at_other_locations'] as List)
              .map((l) => StockAtOtherLocation.fromJson(l))
              .toList()
          : [],
      isFlexiblePrice: json['is_flexible_price'] == true || json['is_flexible_price'] == 1,
      image: json['image'],
      taxId: json['tax_id'],
      taxName: json['tax_name'],
      taxRate: (json['tax_rate'] ?? 0).toDouble(),
      description: json['description'],
      isServiceProduct: json['is_service_product'] == true,
      hasVariations: json['has_variations'] == true,
      serviceTime: json['service_time'],
      variationId: json['variation_id'],
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
    'stock_at_other_locations': stockAtOtherLocations.map((l) => {
      'location_id': l.locationId,
      'location_name': l.locationName,
      'qty_available': l.qtyAvailable,
    }).toList(),
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
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      sku: json['sku'],
      barcode: json['barcode']?.toString(),
      sellPriceIncTax: (json['sell_price_inc_tax'] ?? 0).toDouble(),
      unitPriceIncTax: (json['unit_price_inc_tax'] ?? 0).toDouble(),
      qtyAvailable: (json['qty_available'] ?? 0).toDouble(),
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
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      parentId: json['parent_id'],
      productCount: json['product_count'] ?? 0,
      image: json['image'],
      imageUrl: json['image_url'],
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

  TaxRate({
    required this.id,
    required this.name,
    this.amount = 0,
  });

  factory TaxRate.fromJson(Map<String, dynamic> json) {
    return TaxRate(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      amount: (json['amount'] ?? 0).toDouble(),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'amount': amount,
  };
}
