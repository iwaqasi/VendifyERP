import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:vendify_cms/config/theme.dart';
import 'package:vendify_cms/services/api_service.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  final CmsApiService _api = CmsApiService();
  final _searchController = TextEditingController();
  
  List<dynamic> _products = [];
  List<dynamic> _categories = [];
  int? _selectedCategoryId;
  String _sortBy = 'newest';
  int _currentPage = 1;
  int _totalPages = 1;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadProducts();
  }

  Future<void> _loadProducts() async {
    setState(() => _isLoading = true);
    try {
      final data = await _api.getProducts(
        categoryId: _selectedCategoryId,
        search: _searchController.text.isNotEmpty ? _searchController.text : null,
        sortBy: _sortBy,
        page: _currentPage,
      );
      setState(() {
        _products = data['products'] ?? [];
        _categories = data['categories'] ?? [];
        _totalPages = data['total_pages'] ?? 1;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Column(
        children: [
          // Page header
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 40),
            color: CmsTheme.primary,
            child: const Row(
              children: [
                Text('SHOP', style: TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold, letterSpacing: 2)),
                Spacer(),
                Text('Home > Shop', style: TextStyle(color: Colors.white54, fontSize: 13)),
              ],
            ),
          ),

          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 40),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Sidebar filters
                SizedBox(
                  width: 250,
                  child: _buildFiltersSidebar(),
                ),
                const SizedBox(width: 30),

                // Products grid
                Expanded(
                  child: _buildProductsContent(),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFiltersSidebar() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Search
        TextField(
          controller: _searchController,
          decoration: InputDecoration(
            hintText: 'Search products...',
            prefixIcon: const Icon(Icons.search, size: 18),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          ),
          onSubmitted: (_) {
            _currentPage = 1;
            _loadProducts();
          },
        ),
        const SizedBox(height: 24),

        // Categories
        const Text('CATEGORIES', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: CmsTheme.textPrimary)),
        const SizedBox(height: 12),
        _buildFilterItem('All Products', null),
        ..._categories.map((cat) => _buildFilterItem(cat.name, cat.id)),
        const SizedBox(height: 24),

        // Sort
        const Text('SORT BY', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: CmsTheme.textPrimary)),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          value: _sortBy,
          decoration: InputDecoration(
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          ),
          items: const [
            DropdownMenuItem(value: 'newest', child: Text('Newest')),
            DropdownMenuItem(value: 'price_low', child: Text('Price: Low to High')),
            DropdownMenuItem(value: 'price_high', child: Text('Price: High to Low')),
            DropdownMenuItem(value: 'name', child: Text('Name: A-Z')),
          ],
          onChanged: (v) {
            setState(() => _sortBy = v ?? 'newest');
            _currentPage = 1;
            _loadProducts();
          },
        ),
      ],
    );
  }

  Widget _buildFilterItem(String label, int? categoryId) {
    final isSelected = _selectedCategoryId == categoryId;
    return InkWell(
      onTap: () {
        setState(() => _selectedCategoryId = categoryId);
        _currentPage = 1;
        _loadProducts();
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          children: [
            Container(
              width: 16,
              height: 16,
              decoration: BoxDecoration(
                border: Border.all(color: isSelected ? CmsTheme.highlight : CmsTheme.border),
                borderRadius: BorderRadius.circular(3),
                color: isSelected ? CmsTheme.highlight : Colors.white,
              ),
              child: isSelected ? const Icon(Icons.check, size: 12, color: Colors.white) : null,
            ),
            const SizedBox(width: 10),
            Text(label, style: TextStyle(
              fontSize: 13,
              color: isSelected ? CmsTheme.highlight : CmsTheme.textSecondary,
              fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
            )),
          ],
        ),
      ),
    );
  }

  Widget _buildProductsContent() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator(color: CmsTheme.highlight));
    }

    return Column(
      children: [
        // Product count
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text('Showing ${_products.length} products', style: const TextStyle(color: CmsTheme.textSecondary, fontSize: 13)),
          ],
        ),
        const SizedBox(height: 20),

        // Grid
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 3,
            childAspectRatio: 0.8,
            crossAxisSpacing: 20,
            mainAxisSpacing: 20,
          ),
          itemCount: _products.length,
          itemBuilder: (context, index) => _buildProductCard(_products[index]),
        ),

        // Pagination
        if (_totalPages > 1) ...[
          const SizedBox(height: 30),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (_currentPage > 1)
                IconButton(
                  icon: const Icon(Icons.chevron_left),
                  onPressed: () {
                    setState(() => _currentPage--);
                    _loadProducts();
                  },
                ),
              Text('$_currentPage / $_totalPages', style: const TextStyle(color: CmsTheme.textSecondary)),
              if (_currentPage < _totalPages)
                IconButton(
                  icon: const Icon(Icons.chevron_right),
                  onPressed: () {
                    setState(() => _currentPage++);
                    _loadProducts();
                  },
                ),
            ],
          ),
        ],
      ],
    );
  }

  Widget _buildProductCard(dynamic product) {
    return GestureDetector(
      onTap: () => context.go('/products/${product.slug}'),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: CmsTheme.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Container(
                width: double.infinity,
                decoration: BoxDecoration(
                  color: CmsTheme.surface,
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(8)),
                ),
                child: product.image_url != null
                    ? ClipRRect(
                        borderRadius: const BorderRadius.vertical(top: Radius.circular(8)),
                        child: Image.network(product.image_url, fit: BoxFit.cover),
                      )
                    : const Icon(Icons.image, size: 48, color: CmsTheme.textMuted),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (product.category_name != null)
                    Text(product.category_name, style: const TextStyle(fontSize: 11, color: CmsTheme.highlight)),
                  const SizedBox(height: 4),
                  Text(
                    product.name,
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: CmsTheme.textPrimary),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'KD ${double.tryParse(product.sell_price_inc_tax?.toString() ?? '0')?.toStringAsFixed(3) ?? '0.000'}',
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: CmsTheme.highlight),
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
