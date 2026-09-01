import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:vendify_cms/config/theme.dart';
import 'package:vendify_cms/services/api_service.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final CmsApiService _api = CmsApiService();
  List<dynamic> _featuredProducts = [];
  List<dynamic> _categories = [];
  List<dynamic> _latestPosts = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadHome();
  }

  Future<void> _loadHome() async {
    try {
      final data = await _api.getHome();
      setState(() {
        _featuredProducts = data['featured_products'] ?? [];
        _categories = data['categories'] ?? [];
        _latestPosts = data['latest_posts'] ?? [];
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator(color: CmsTheme.highlight));
    }

    return SingleChildScrollView(
      child: Column(
        children: [
          _buildHeroBanner(),
          const SizedBox(height: 60),
          _buildCategoriesSection(),
          const SizedBox(height: 60),
          _buildFeaturedProducts(),
          const SizedBox(height: 60),
          _buildBlogSection(),
          const SizedBox(height: 60),
        ],
      ),
    );
  }

  Widget _buildHeroBanner() {
    return Container(
      height: 500,
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [CmsTheme.primary, CmsTheme.secondary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              'SAYA ELEGANT STYLE',
              style: TextStyle(
                color: Colors.white.withOpacity(0.8),
                fontSize: 16,
                letterSpacing: 6,
                fontWeight: FontWeight.w300,
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Timeless Elegance',
              style: TextStyle(
                color: Colors.white,
                fontSize: 48,
                fontWeight: FontWeight.bold,
                letterSpacing: 2,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Curated Italian Fashion & Accessories',
              style: TextStyle(
                color: Colors.white70,
                fontSize: 18,
              ),
            ),
            const SizedBox(height: 40),
            ElevatedButton(
              onPressed: () => context.go('/products'),
              style: ElevatedButton.styleFrom(
                backgroundColor: CmsTheme.highlight,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
              ),
              child: const Text(
                'SHOP NOW',
                style: TextStyle(letterSpacing: 2, fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoriesSection() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 60),
      child: Column(
        children: [
          const Text(
            'SHOP BY CATEGORY',
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.bold,
              color: CmsTheme.primary,
              letterSpacing: 2,
            ),
          ),
          const SizedBox(height: 8),
          Container(width: 60, height: 2, color: CmsTheme.highlight),
          const SizedBox(height: 40),
          Wrap(
            spacing: 20,
            runSpacing: 20,
            children: _categories.map((cat) => _buildCategoryCard(cat)).toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryCard(dynamic category) {
    return GestureDetector(
      onTap: () => context.go('/products?category=${category.id}'),
      child: Container(
        width: 200,
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          color: CmsTheme.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: CmsTheme.border),
        ),
        child: Column(
          children: [
            const Icon(Icons.category, size: 40, color: CmsTheme.highlight),
            const SizedBox(height: 12),
            Text(
              category.name,
              style: const TextStyle(fontWeight: FontWeight.bold, color: CmsTheme.textPrimary),
            ),
            const SizedBox(height: 4),
            Text(
              '${category.product_count} products',
              style: const TextStyle(fontSize: 12, color: CmsTheme.textMuted),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFeaturedProducts() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 60),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'FEATURED PRODUCTS',
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                  color: CmsTheme.primary,
                  letterSpacing: 2,
                ),
              ),
              TextButton(
                onPressed: () => context.go('/products'),
                child: const Text('View All →', style: TextStyle(color: CmsTheme.highlight)),
              ),
            ],
          ),
          const SizedBox(height: 40),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 4,
              childAspectRatio: 0.75,
              crossAxisSpacing: 20,
              mainAxisSpacing: 20,
            ),
            itemCount: _featuredProducts.length,
            itemBuilder: (context, index) {
              return _buildProductCard(_featuredProducts[index]);
            },
          ),
        ],
      ),
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
            // Image
            Expanded(
              child: Container(
                width: double.infinity,
                decoration: BoxDecoration(
                  color: CmsTheme.surface,
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(8)),
                ),
                child: product.image_url != null
                    ? Image.network(product.image_url, fit: BoxFit.cover)
                    : const Icon(Icons.image, size: 48, color: CmsTheme.textMuted),
              ),
            ),
            // Info
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.name,
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: CmsTheme.textPrimary),
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

  Widget _buildBlogSection() {
    if (_latestPosts.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 60),
      child: Column(
        children: [
          const Text(
            'FROM OUR BLOG',
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.bold,
              color: CmsTheme.primary,
              letterSpacing: 2,
            ),
          ),
          const SizedBox(height: 40),
          Row(
            children: _latestPosts.take(3).map((post) => Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10),
                child: _buildPostCard(post),
              ),
            )).toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildPostCard(dynamic post) {
    return GestureDetector(
      onTap: () => context.go('/blog/${post.slug}'),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: CmsTheme.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 180,
              width: double.infinity,
              decoration: const BoxDecoration(
                color: CmsTheme.surface,
                borderRadius: BorderRadius.vertical(top: Radius.circular(8)),
              ),
              child: post.image_url != null
                  ? Image.network(post.image_url, fit: BoxFit.cover)
                  : const Icon(Icons.article, size: 48, color: CmsTheme.textMuted),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    post.title,
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: CmsTheme.textPrimary),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    post.excerpt ?? '',
                    style: const TextStyle(fontSize: 13, color: CmsTheme.textSecondary),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    post.published_at?.toString().substring(0, 10) ?? '',
                    style: const TextStyle(fontSize: 12, color: CmsTheme.textMuted),
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
