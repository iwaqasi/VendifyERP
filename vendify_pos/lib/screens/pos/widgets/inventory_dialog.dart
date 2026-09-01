import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/product.dart';
import 'package:vendify_pos/providers/api_provider.dart';

/// Shows a searchable list of all inventory items with on-hand quantity.
/// Displays stock at current location + stock at other locations when available.
class InventoryDialog extends ConsumerStatefulWidget {
  const InventoryDialog({super.key});

  static void show(BuildContext context) {
    showDialog(
      context: context,
      builder: (_) => const InventoryDialog(),
    );
  }

  @override
  ConsumerState<InventoryDialog> createState() => _InventoryDialogState();
}

class _InventoryDialogState extends ConsumerState<InventoryDialog> {
  List<Product> _allProducts = [];
  List<Product> _filteredProducts = [];
  bool _isLoading = true;
  String _searchQuery = '';
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _loadInventory();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _loadInventory() async {
    setState(() => _isLoading = true);
    try {
      final prefs = await SharedPreferences.getInstance();
      final locationId = prefs.getInt('location_id');

      final posService = ref.read(posServiceProvider);
      final products = await posService.getProducts(
        locationId: locationId,
        perPage: 500,
      );

      setState(() {
        _allProducts = products;
        _filteredProducts = products;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  void _filterProducts(String query) {
    setState(() {
      _searchQuery = query.toLowerCase();
      _filteredProducts = _allProducts.where((p) {
        return p.name.toLowerCase().contains(_searchQuery) ||
            (p.sku?.toLowerCase().contains(_searchQuery) ?? false) ||
            (p.barcode?.toLowerCase().contains(_searchQuery) ?? false);
      }).toList();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppTheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        width: 600,
        height: MediaQuery.of(context).size.height * 0.8,
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                const Icon(Icons.inventory_2, color: AppTheme.primary, size: 24),
                const SizedBox(width: 10),
                const Expanded(
                  child: Text(
                    'Inventory Check',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    '${_allProducts.length} items',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: AppTheme.primary,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton(
                  icon: const Icon(Icons.close, size: 20),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Search bar
            TextField(
              controller: _searchController,
              onChanged: _filterProducts,
              style: const TextStyle(color: AppTheme.textPrimary, fontSize: 14),
              decoration: InputDecoration(
                hintText: 'Search by name, SKU, or barcode...',
                hintStyle: TextStyle(color: AppTheme.textMuted),
                prefixIcon: const Icon(Icons.search, color: AppTheme.textMuted, size: 20),
                suffixIcon: _searchQuery.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          _filterProducts('');
                        },
                      )
                    : null,
                filled: true,
                fillColor: AppTheme.background,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: AppTheme.border),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: AppTheme.border),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: AppTheme.primary, width: 2),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              ),
            ),
            const SizedBox(height: 12),

            // Column headers
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: AppTheme.background,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Row(
                children: [
                  Expanded(flex: 3, child: Text('PRODUCT', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.textSecondary))),
                  Expanded(flex: 1, child: Text('ON HAND', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.textSecondary), textAlign: TextAlign.center)),
                  Expanded(flex: 2, child: Text('OTHER LOCATIONS', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.textSecondary), textAlign: TextAlign.center)),
                  Expanded(flex: 1, child: Text('PRICE', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.textSecondary), textAlign: TextAlign.right)),
                ],
              ),
            ),
            const SizedBox(height: 6),

            // Product list
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
                  : _filteredProducts.isEmpty
                      ? Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.search_off, size: 48, color: AppTheme.textMuted.withValues(alpha: 0.4)),
                              const SizedBox(height: 12),
                              Text(
                                _searchQuery.isEmpty ? 'No products found' : 'No matches for "$_searchQuery"',
                                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 14),
                              ),
                            ],
                          ),
                        )
                      : ListView.builder(
                          controller: _scrollController,
                          itemCount: _filteredProducts.length,
                          itemBuilder: (context, index) {
                            final product = _filteredProducts[index];
                            return _buildProductRow(product);
                          },
                        ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProductRow(Product product) {
    final stockQty = product.qtyAvailable.toInt();
    final isOutOfStock = product.enableStock && stockQty <= 0;
    final hasStockElsewhere = product.stockAtOtherLocations.isNotEmpty;

    return Container(
      margin: const EdgeInsets.only(bottom: 4),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: isOutOfStock
            ? AppTheme.error.withValues(alpha: 0.04)
            : AppTheme.surface,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: isOutOfStock
              ? AppTheme.error.withValues(alpha: 0.2)
              : AppTheme.border.withValues(alpha: 0.5),
        ),
      ),
      child: Row(
        children: [
          // Product name + SKU
          Expanded(
            flex: 3,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  product.name,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppTheme.textPrimary,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (product.sku != null && product.sku!.isNotEmpty)
                  Text(
                    'SKU: ${product.sku}',
                    style: const TextStyle(fontSize: 10, color: AppTheme.textMuted),
                  ),
              ],
            ),
          ),
          // On Hand quantity
          Expanded(
            flex: 1,
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: isOutOfStock
                      ? AppTheme.error.withValues(alpha: 0.1)
                      : stockQty <= 5
                          ? AppTheme.warning.withValues(alpha: 0.1)
                          : AppTheme.success.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  '$stockQty',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: isOutOfStock
                        ? AppTheme.error
                        : stockQty <= 5
                            ? AppTheme.warning
                            : AppTheme.success,
                  ),
                ),
              ),
            ),
          ),
          // Other locations
          Expanded(
            flex: 2,
            child: hasStockElsewhere
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: product.stockAtOtherLocations.map((loc) {
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 2),
                        child: Text(
                          '${loc.locationName}: ${loc.qtyAvailable.toInt()}',
                          style: TextStyle(
                            fontSize: 10,
                            color: Colors.orange.shade700,
                            fontWeight: FontWeight.w500,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                        ),
                      );
                    }).toList(),
                  )
                : Text(
                    isOutOfStock ? 'None' : '-',
                    style: TextStyle(
                      fontSize: 11,
                      color: isOutOfStock ? AppTheme.error : AppTheme.textMuted,
                    ),
                    textAlign: TextAlign.center,
                  ),
          ),
          // Price
          Expanded(
            flex: 1,
            child: Text(
              product.enableStock ? '' : 'KD ${product.sellPriceIncTax.toStringAsFixed(3)}',
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: AppTheme.priceColor,
              ),
              textAlign: TextAlign.right,
            ),
          ),
        ],
      ),
    );
  }
}
