class OfflineTransaction {
  final String localId;
  final int businessId;
  final int locationId;
  final int? contactId;
  final int userId;
  final List<Map<String, dynamic>> products;
  final List<Map<String, dynamic>> payments;
  final double subtotal;
  final double tax;
  final double discount;
  final double grandTotal;
  final String? additionalNotes;
  final String? saleNote;
  final DateTime createdAt;
  final String status; // 'pending', 'syncing', 'synced', 'failed'
  final String? errorMessage;
  final int retryCount;

  OfflineTransaction({
    required this.localId,
    required this.businessId,
    required this.locationId,
    this.contactId,
    required this.userId,
    required this.products,
    required this.payments,
    required this.subtotal,
    required this.tax,
    required this.discount,
    required this.grandTotal,
    this.additionalNotes,
    this.saleNote,
    required this.createdAt,
    this.status = 'pending',
    this.errorMessage,
    this.retryCount = 0,
  });

  Map<String, dynamic> toJson() => {
    'local_id': localId,
    'business_id': businessId,
    'location_id': locationId,
    'contact_id': contactId,
    'user_id': userId,
    'products': products,
    'payments': payments,
    'subtotal': subtotal,
    'tax': tax,
    'discount': discount,
    'grand_total': grandTotal,
    'additional_notes': additionalNotes,
    'sale_note': saleNote,
    'created_at': createdAt.toIso8601String(),
    'status': status,
    'error_message': errorMessage,
    'retry_count': retryCount,
  };

  factory OfflineTransaction.fromJson(Map<String, dynamic> json) => OfflineTransaction(
    localId: json['local_id'] ?? '',
    businessId: json['business_id'] ?? 1,
    locationId: json['location_id'] ?? 1,
    contactId: json['contact_id'],
    userId: json['user_id'] ?? 1,
    products: List<Map<String, dynamic>>.from(json['products'] ?? []),
    payments: List<Map<String, dynamic>>.from(json['payments'] ?? []),
    subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0.0,
    tax: (json['tax'] as num?)?.toDouble() ?? 0.0,
    discount: (json['discount'] as num?)?.toDouble() ?? 0.0,
    grandTotal: (json['grand_total'] as num?)?.toDouble() ?? 0.0,
    additionalNotes: json['additional_notes'],
    saleNote: json['sale_note'],
    createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
    status: json['status'] ?? 'pending',
    errorMessage: json['error_message'],
    retryCount: json['retry_count'] ?? 0,
  );

  OfflineTransaction copyWith({
    String? status,
    String? errorMessage,
    int? retryCount,
  }) {
    return OfflineTransaction(
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
      createdAt: createdAt,
      status: status ?? this.status,
      errorMessage: errorMessage ?? this.errorMessage,
      retryCount: retryCount ?? this.retryCount,
    );
  }
}
