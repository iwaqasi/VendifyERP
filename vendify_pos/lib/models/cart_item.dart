class CartItem {
  final int productId;
  final int variationId;
  final String productName;
  final String? sku;
  final String? image;
  final double unitPrice;
  final double quantity;
  final double discount;
  final double discountPercent;
  final int? taxId;
  final String? taxName;
  final double taxRate;
  final bool isFlexiblePrice;
  final int? serviceStaffId;
  final String? serviceStaffName;
  final bool enableStock;
  final double qtyAvailable;

  CartItem({
    required this.productId,
    required this.variationId,
    required this.productName,
    this.sku,
    this.image,
    required this.unitPrice,
    this.quantity = 1,
    this.discount = 0,
    this.discountPercent = 0,
    this.taxId,
    this.taxName,
    this.taxRate = 0,
    this.isFlexiblePrice = false,
    this.serviceStaffId,
    this.serviceStaffName,
    this.enableStock = false,
    this.qtyAvailable = 0,
  });

  double get discountAmount => discountPercent > 0 ? (unitPrice * quantity * discountPercent / 100) : discount;
  double get lineTotal => (unitPrice * quantity) - discountAmount;
  double get lineTax => lineTotal * (taxRate / 100);
  double get lineTotalIncTax => lineTotal + lineTax;

  CartItem copyWith({
    double? quantity,
    double? unitPrice,
    double? discount,
    double? discountPercent,
    int? serviceStaffId,
    String? serviceStaffName,
  }) {
    return CartItem(
      productId: productId,
      variationId: variationId,
      productName: productName,
      sku: sku,
      image: image,
      unitPrice: unitPrice ?? this.unitPrice,
      quantity: quantity ?? this.quantity,
      discount: discount ?? this.discount,
      discountPercent: discountPercent ?? this.discountPercent,
      taxId: taxId,
      taxName: taxName,
      taxRate: taxRate,
      isFlexiblePrice: isFlexiblePrice,
      serviceStaffId: serviceStaffId ?? this.serviceStaffId,
      serviceStaffName: serviceStaffName ?? this.serviceStaffName,
      enableStock: enableStock,
      qtyAvailable: qtyAvailable,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'product_id': productId,
      'variation_id': variationId,
      'quantity': quantity,
      'unit_price': unitPrice,
      'discount': discount,
      'tax_id': taxId,
      'service_staff_id': serviceStaffId,
    };
  }
}

class Cart {
  final List<CartItem> items;
  final int? contactId;
  final String? contactName;
  final int locationId;
  final String? locationName;
  final double orderDiscount;
  final String orderDiscountType;
  final double shippingCharges;
  final String? additionalNotes;
  final String? saleNote;

  Cart({
    this.items = const [],
    this.contactId,
    this.contactName,
    required this.locationId,
    this.locationName,
    this.orderDiscount = 0,
    this.orderDiscountType = 'fixed',
    this.shippingCharges = 0,
    this.additionalNotes,
    this.saleNote,
  });

  double get subtotal => items.fold(0, (sum, item) => sum + item.lineTotal);
  double get totalTax => items.fold(0, (sum, item) => sum + item.lineTax);
  double get totalDiscount => orderDiscountType == 'percentage'
      ? subtotal * (orderDiscount / 100)
      : orderDiscount;
  double get grandTotal => subtotal + totalTax - totalDiscount + shippingCharges;
  int get itemCount => items.length;

  Cart copyWith({
    List<CartItem>? items,
    int? contactId,
    String? contactName,
    double? orderDiscount,
    String? orderDiscountType,
    double? shippingCharges,
    String? additionalNotes,
    String? saleNote,
  }) {
    return Cart(
      items: items ?? this.items,
      contactId: contactId ?? this.contactId,
      contactName: contactName ?? this.contactName,
      locationId: locationId,
      locationName: locationName,
      orderDiscount: orderDiscount ?? this.orderDiscount,
      orderDiscountType: orderDiscountType ?? this.orderDiscountType,
      shippingCharges: shippingCharges ?? this.shippingCharges,
      additionalNotes: additionalNotes ?? this.additionalNotes,
      saleNote: saleNote ?? this.saleNote,
    );
  }
}
