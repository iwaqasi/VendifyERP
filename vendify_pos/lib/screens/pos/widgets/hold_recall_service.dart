import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/cart_item.dart';

class HoldRecallService {
  /// Hold the current cart to SharedPreferences
  static Future<int> holdCart({
    required BuildContext context,
    required List<CartItem> cartItems,
    required String customerName,
    int? contactId,
    required double receiptDiscount,
    required String receiptDiscountType,
    required double subtotal,
  }) async {
    if (cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cart is empty')),
      );
      return 0;
    }

    final prefs = await SharedPreferences.getInstance();
    final heldCarts = prefs.getStringList('held_carts') ?? [];

    final heldCartData = {
      'id': DateTime.now().millisecondsSinceEpoch.toString(),
      'timestamp': DateTime.now().toIso8601String(),
      'customerName': customerName,
      'contactId': contactId,
      'receiptDiscount': receiptDiscount,
      'receiptDiscountType': receiptDiscountType,
      'subtotal': subtotal,
      'items': cartItems.map((item) => {
        'productId': item.productId,
        'variationId': item.variationId,
        'productName': item.productName,
        'sku': item.sku,
        'image': item.image,
        'unitPrice': item.unitPrice,
        'quantity': item.quantity,
        'discount': item.discount,
        'discountPercent': item.discountPercent,
        'taxId': item.taxId,
        'taxName': item.taxName,
        'taxRate': item.taxRate,
        'isFlexiblePrice': item.isFlexiblePrice,
        'enableStock': item.enableStock,
        'qtyAvailable': item.qtyAvailable,
      }).toList(),
    };

    heldCarts.add(jsonEncode(heldCartData));
    await prefs.setStringList('held_carts', heldCarts);

    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Cart held successfully (${heldCarts.length} held)'),
          backgroundColor: AppTheme.warning,
          duration: const Duration(seconds: 2),
        ),
      );
    }

    return heldCarts.length;
  }

  /// Show recall dialog and return the selected cart data, or null if cancelled
  static Future<Map<String, dynamic>?> showRecallDialog(BuildContext context) async {
    final prefs = await SharedPreferences.getInstance();
    final heldCarts = prefs.getStringList('held_carts') ?? [];

    if (!context.mounted) return null;
    if (heldCarts.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No held carts to recall'),
          backgroundColor: AppTheme.textMuted,
        ),
      );
      return null;
    }

    final List<Map<String, dynamic>> parsedCarts = [];
    for (final cartJson in heldCarts) {
      try {
        final cartData = jsonDecode(cartJson) as Map<String, dynamic>;
        parsedCarts.add(cartData);
      } catch (e) {
        // Skip invalid carts
      }
    }

    if (!context.mounted) return null;
    return showDialog<Map<String, dynamic>>(
      context: context,
      builder: (context) => RecallDialog(carts: parsedCarts),
    );
  }

  /// Build CartItem list from recalled cart data
  static List<CartItem> restoreCartItems(Map<String, dynamic> cartData) {
    return (cartData['items'] as List).map((itemData) {
      return CartItem(
        productId: itemData['productId'],
        variationId: itemData['variationId'],
        productName: itemData['productName'],
        sku: itemData['sku'],
        image: itemData['image'],
        unitPrice: (itemData['unitPrice'] as num).toDouble(),
        quantity: (itemData['quantity'] as num).toDouble(),
        discount: (itemData['discount'] as num?)?.toDouble() ?? 0,
        discountPercent: (itemData['discountPercent'] as num?)?.toDouble() ?? 0,
        taxId: itemData['taxId'],
        taxName: itemData['taxName'],
        taxRate: (itemData['taxRate'] as num?)?.toDouble() ?? 0,
        isFlexiblePrice: itemData['isFlexiblePrice'] ?? false,
        enableStock: itemData['enableStock'] ?? false,
        qtyAvailable: (itemData['qtyAvailable'] as num?)?.toDouble() ?? 0,
      );
    }).toList();
  }

  /// Remove a held cart by ID
  static Future<void> removeHeldCart(String? cartId) async {
    if (cartId == null) return;
    final prefs = await SharedPreferences.getInstance();
    final heldCarts = prefs.getStringList('held_carts') ?? [];

    heldCarts.removeWhere((cartJson) {
      try {
        final data = jsonDecode(cartJson) as Map<String, dynamic>;
        return data['id'] == cartId;
      } catch (e) {
        return false;
      }
    });

    await prefs.setStringList('held_carts', heldCarts);
  }
}

/// Dialog for recalling held carts
class RecallDialog extends StatelessWidget {
  final List<Map<String, dynamic>> carts;

  const RecallDialog({super.key, required this.carts});

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Container(
        width: 500,
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.7,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: const BoxDecoration(
                color: AppTheme.surfaceLight,
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(12),
                  topRight: Radius.circular(12),
                ),
                border: Border(bottom: BorderSide(color: AppTheme.border)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.history, color: AppTheme.primary, size: 20),
                  const SizedBox(width: 10),
                  const Text(
                    'Recall Held Carts',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
                  ),
                  const Spacer(),
                  Text('${carts.length} held', style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary)),
                ],
              ),
            ),
            Flexible(
              child: carts.isEmpty
                  ? const Center(child: Padding(padding: EdgeInsets.all(32), child: Text('No held carts', style: TextStyle(color: AppTheme.textMuted, fontSize: 14))))
                  : ListView.builder(
                      shrinkWrap: true,
                      padding: const EdgeInsets.all(8),
                      itemCount: carts.length,
                      itemBuilder: (context, index) {
                        final cart = carts[index];
                        final items = cart['items'] as List? ?? [];
                        final timestamp = cart['timestamp'] ?? '';
                        final customerName = cart['customerName'] ?? 'Walk-in';
                        final subtotal = (cart['subtotal'] as num?)?.toDouble() ?? 0;

                        String timeStr = '';
                        try {
                          final dt = DateTime.parse(timestamp);
                          timeStr = '${dt.day}/${dt.month}/${dt.year} ${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
                        } catch (e) {
                          timeStr = timestamp;
                        }

                        return Card(
                          margin: const EdgeInsets.symmetric(vertical: 4),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          child: ListTile(
                            onTap: () => Navigator.pop(context, cart),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                            leading: Container(
                              width: 40,
                              height: 40,
                              decoration: BoxDecoration(
                                color: AppTheme.warning.withValues(alpha: 0.1),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Center(
                                child: Text('${items.length}', style: TextStyle(color: AppTheme.warning, fontWeight: FontWeight.bold, fontSize: 16)),
                              ),
                            ),
                            title: Text(customerName, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                            subtitle: Text('$timeStr  •  KD ${subtotal.toStringAsFixed(3)}', style: const TextStyle(fontSize: 11)),
                            trailing: const Icon(Icons.chevron_right, size: 18),
                          ),
                        );
                      },
                    ),
            ),
            Container(
              padding: const EdgeInsets.all(12),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Cancel'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
