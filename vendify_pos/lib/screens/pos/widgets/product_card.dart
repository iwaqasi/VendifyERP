import 'package:flutter/material.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/product.dart';

class ProductCard extends StatelessWidget {
  final Product product;
  final VoidCallback onTap;

  const ProductCard({
    super.key,
    required this.product,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isOutOfStock = product.enableStock && product.qtyAvailable <= 0;
    final stockQty = product.qtyAvailable.toInt();
    final hasStockElsewhere = isOutOfStock && product.stockAtOtherLocations.isNotEmpty;

    return InkWell(
      onTap: isOutOfStock ? null : onTap,
      borderRadius: BorderRadius.circular(AppTheme.radiusMd),
      child: Container(
        decoration: BoxDecoration(
          color: AppTheme.surface,
          borderRadius: BorderRadius.circular(AppTheme.radiusMd),
          border: Border.all(
            color: isOutOfStock
                ? AppTheme.error.withValues(alpha: 0.3)
                : AppTheme.border,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Product Image
            Expanded(
              flex: 5,
              child: Container(
                decoration: BoxDecoration(
                  color: AppTheme.surfaceLight,
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(AppTheme.radiusMd),
                  ),
                ),
                child: Stack(
                  children: [
                    // Image
                    Center(
                      child: product.image != null &&
                              !product.image!.contains('default.png')
                          ? ClipRRect(
                              borderRadius: const BorderRadius.vertical(
                                top: Radius.circular(AppTheme.radiusMd),
                              ),
                              child: Image.network(
                                product.image!,
                                fit: BoxFit.cover,
                                width: double.infinity,
                                height: double.infinity,
                                errorBuilder: (_, __, ___) => _buildPlaceholder(),
                              ),
                            )
                          : _buildPlaceholder(),
                    ),

                    // Sold Out badge
                    if (isOutOfStock)
                      Positioned(
                        top: 8,
                        left: 8,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: hasStockElsewhere ? Colors.orange : AppTheme.error,
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            hasStockElsewhere ? 'Available at ${product.stockAtOtherLocations.length} other location${product.stockAtOtherLocations.length > 1 ? 's' : ''}' : 'Sold Out',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),

            // Product Info
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Name
                  Text(
                    product.name.toUpperCase(),
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.textPrimary,
                      height: 1.2,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),

                  // Stock quantity - show current location + other locations summary
                  if (product.enableStock)
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'On Hand: $stockQty',
                          style: TextStyle(
                            fontSize: 10,
                            color: stockQty <= 0 ? AppTheme.error : AppTheme.primary,
                            fontWeight: stockQty <= 0 ? FontWeight.w600 : FontWeight.normal,
                          ),
                        ),
                        if (hasStockElsewhere)
                          Text(
                            'Also at: ${product.stockAtOtherLocations.map((l) => '${l.locationName}(${l.qtyAvailable.toInt()})').join(', ')}',
                            style: TextStyle(
                              fontSize: 9,
                              color: Colors.orange.shade700,
                              fontWeight: FontWeight.w500,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                      ],
                    )
                  else
                    const Text(
                      'Non-stock',
                      style: TextStyle(fontSize: 10, color: AppTheme.textMuted),
                    ),

                  const SizedBox(height: 4),

                  // Price
                  Text(
                    'KD ${product.sellPriceIncTax.toStringAsFixed(3)}',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.priceColor,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPlaceholder() {
    return Center(
      child: Icon(
        product.isServiceProduct ? Icons.content_cut : Icons.inventory_2,
        size: 40,
        color: AppTheme.textMuted.withValues(alpha: 0.5),
      ),
    );
  }
}
