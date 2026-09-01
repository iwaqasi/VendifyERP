class Contact {
  final int id;
  final String name;
  final String? contactId;
  final String type;
  final String? mobile;
  final String? email;
  final String? taxNumber;
  final double balance;
  final double sellDue;
  final double purchaseDue;
  final int? customerGroupId;
  final double creditLimit;
  final int payTermNumber;
  final String payTermType;

  Contact({
    required this.id,
    required this.name,
    this.contactId,
    required this.type,
    this.mobile,
    this.email,
    this.taxNumber,
    this.balance = 0,
    this.sellDue = 0,
    this.purchaseDue = 0,
    this.customerGroupId,
    this.creditLimit = 0,
    this.payTermNumber = 0,
    this.payTermType = 'days',
  });

  factory Contact.fromJson(Map<String, dynamic> json) {
    return Contact(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      contactId: json['contact_id'],
      type: json['type'] ?? 'customer',
      mobile: json['mobile'],
      email: json['email'],
      taxNumber: json['tax_number'],
      balance: (json['balance'] ?? 0).toDouble(),
      sellDue: (json['sell_due'] ?? 0).toDouble(),
      purchaseDue: (json['purchase_due'] ?? 0).toDouble(),
      customerGroupId: json['customer_group_id'],
      creditLimit: (json['credit_limit'] ?? 0).toDouble(),
      payTermNumber: json['pay_term_number'] ?? 0,
      payTermType: json['pay_term_type'] ?? 'days',
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'contact_id': contactId,
    'type': type,
    'mobile': mobile,
    'email': email,
    'tax_number': taxNumber,
    'balance': balance,
    'sell_due': sellDue,
    'purchase_due': purchaseDue,
    'customer_group_id': customerGroupId,
    'credit_limit': creditLimit,
    'pay_term_number': payTermNumber,
    'pay_term_type': payTermType,
  };
}
