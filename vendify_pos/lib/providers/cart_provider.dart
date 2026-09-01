import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:vendify_pos/models/cart_item.dart';

// Cart State
class CartState {
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

  CartState({
    this.items = const [],
    this.contactId,
    this.contactName,
    this.locationId = 0,
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
  bool get isEmpty => items.isEmpty;

  CartState copyWith({
    List<CartItem>? items,
    int? contactId,
    String? contactName,
    int? locationId,
    String? locationName,
    double? orderDiscount,
    String? orderDiscountType,
    double? shippingCharges,
    String? additionalNotes,
    String? saleNote,
    bool clearContact = false,
  }) {
    return CartState(
      items: items ?? this.items,
      contactId: clearContact ? null : (contactId ?? this.contactId),
      contactName: clearContact ? null : (contactName ?? this.contactName),
      locationId: locationId ?? this.locationId,
      locationName: locationName ?? this.locationName,
      orderDiscount: orderDiscount ?? this.orderDiscount,
      orderDiscountType: orderDiscountType ?? this.orderDiscountType,
      shippingCharges: shippingCharges ?? this.shippingCharges,
      additionalNotes: additionalNotes ?? this.additionalNotes,
      saleNote: saleNote ?? this.saleNote,
    );
  }
}

// Cart Notifier
class CartNotifier extends StateNotifier<CartState> {
  CartNotifier() : super(CartState());

  void addItem(CartItem item) {
    // Check if same product already in cart
    final existingIndex = state.items.indexWhere(
      (i) => i.variationId == item.variationId,
    );

    if (existingIndex >= 0) {
      // Update quantity
      final existing = state.items[existingIndex];
      final newQty = existing.quantity + 1;

      // Check stock
      if (existing.enableStock && newQty > existing.qtyAvailable) {
        return; // Don't add if exceeds stock
      }

      final updated = existing.copyWith(quantity: newQty);
      final newItems = List<CartItem>.from(state.items);
      newItems[existingIndex] = updated;
      state = state.copyWith(items: newItems);
    } else {
      state = state.copyWith(items: [...state.items, item]);
    }
  }

  void removeItem(int variationId) {
    state = state.copyWith(
      items: state.items.where((i) => i.variationId != variationId).toList(),
    );
  }

  void updateQuantity(int variationId, double quantity) {
    if (quantity <= 0) {
      removeItem(variationId);
      return;
    }

    final newItems = state.items.map((item) {
      if (item.variationId == variationId) {
        return item.copyWith(quantity: quantity);
      }
      return item;
    }).toList();

    state = state.copyWith(items: newItems);
  }

  void updateItemPrice(int variationId, double price) {
    final newItems = state.items.map((item) {
      if (item.variationId == variationId && item.isFlexiblePrice) {
        return item.copyWith(unitPrice: price);
      }
      return item;
    }).toList();

    state = state.copyWith(items: newItems);
  }

  void setContact(int? id, String? name) {
    state = state.copyWith(contactId: id, contactName: name);
  }

  void setLocation(int id, String? name) {
    state = state.copyWith(locationId: id, locationName: name);
  }

  void setDiscount(double amount, String type) {
    state = state.copyWith(orderDiscount: amount, orderDiscountType: type);
  }

  void setShipping(double amount) {
    state = state.copyWith(shippingCharges: amount);
  }

  void clear() {
    state = CartState(locationId: state.locationId, locationName: state.locationName);
  }
}

final cartProvider = StateNotifierProvider<CartNotifier, CartState>((ref) {
  return CartNotifier();
});
