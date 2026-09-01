import 'package:flutter/material.dart';
import 'package:vendify_cms/config/theme.dart';
import 'package:vendify_cms/services/api_service.dart';

class ProductDetailScreen extends StatefulWidget {
  final String slug;
  const ProductDetailScreen({super.key, required this.slug});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  final CmsApiService _api = CmsApiService();
  Map<String, dynamic>? _product;
  List<dynamic> _relatedProducts = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadProduct();
  }

  Future<void> _loadProduct() async {
    try {
      final data = await _api.getProduct(widget.slug);
      setState(() {
        _product = data['product'];
        _relatedProducts = data['related_products'] ?? [];
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) return const Center(child: CircularProgressIndicator(color: CmsTheme.highlight));
    if (_product == null) return const Center(child: Text('Product not found'));

    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 40),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Breadcrumb
            Text('Home > Shop > ${_product!['category_name'] ?? 'Products'} > ${_product!['name']}', style: const TextStyle(fontSize: 13, color: CmsTheme.textMuted)),
            const SizedBox(height: 30),

            // Product detail
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Image
                Expanded(
                  flex: 2,
                  child: Container(
                    height: 500,
                    decoration: BoxDecoration(
                      color: CmsTheme.surface,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: _product!['image_url'] != null
                        ? Image.network(_product!['image_url'], fit: BoxFit.cover)
                        : const Icon(Icons.image, size: 80, color: CmsTheme.textMuted),
                  ),
                ),
                const SizedBox(width: 40),

                // Info
                Expanded(
                  flex: 2,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (_product!['category_name'] != null)
                        Text(_product!['category_name'], style: const TextStyle(color: CmsTheme.highlight, fontSize: 13)),
                      const SizedBox(height: 8),
                      Text(_product!['name'], style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: CmsTheme.textPrimary)),
                      const SizedBox(height: 16),
                      Text(
                        'KD ${double.tryParse(_product!['sell_price_inc_tax']?.toString() ?? '0')?.toStringAsFixed(3) ?? '0.000'}',
                        style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: CmsTheme.highlight),
                      ),
                      const SizedBox(height: 20),
                      Text(_product!['description'] ?? 'No description available', style: const TextStyle(fontSize: 14, color: CmsTheme.textSecondary, height: 1.6)),
                      const SizedBox(height: 20),
                      Text('SKU: ${_product!['sku'] ?? 'N/A'}', style: const TextStyle(fontSize: 13, color: CmsTheme.textMuted)),
                    ],
                  ),
                ),
              ],
            ),

            // Related products
            if (_relatedProducts.isNotEmpty) ...[
              const SizedBox(height: 60),
              const Text('RELATED PRODUCTS', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: CmsTheme.primary)),
              const SizedBox(height: 30),
              GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 4, childAspectRatio: 0.8, crossAxisSpacing: 20, mainAxisSpacing: 20),
                itemCount: _relatedProducts.length,
                itemBuilder: (context, index) => Container(
                  decoration: BoxDecoration(border: Border.all(color: CmsTheme.border), borderRadius: BorderRadius.circular(8)),
                  child: Column(children: [
                    Expanded(child: Container(color: CmsTheme.surface)),
                    Padding(padding: const EdgeInsets.all(8), child: Text(_relatedProducts[index].name, style: const TextStyle(fontSize: 12))),
                  ]),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
