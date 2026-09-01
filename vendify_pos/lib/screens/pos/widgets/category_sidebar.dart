import 'package:flutter/material.dart';
import 'package:vendify_pos/config/theme.dart';
import 'package:vendify_pos/models/product.dart';

class CategorySidebar extends StatelessWidget {
  final List<Category> categories;
  final int? selectedId;
  final Function(int?) onCategorySelected;
  final int totalProductCount;

  const CategorySidebar({
    super.key,
    required this.categories,
    required this.selectedId,
    required this.onCategorySelected,
    this.totalProductCount = 0,
  });

  // Fallback icons for categories without images
  static const List<IconData> _categoryIcons = [
    Icons.home,
    Icons.diamond,
    Icons.auto_awesome,
    Icons.account_balance_wallet,
    Icons.shopping_bag,
    Icons.backpack,
    Icons.work,
    Icons.checkroom,
    Icons.brunch_dining,
    Icons.watch,
    Icons.ring_volume,
    Icons.card_giftcard,
    Icons.stars,
    Icons.local_fire_department,
    Icons.style,
    Icons.celebration,
  ];

  // Colors for category icons
  static const List<Color> _categoryColors = [
    Color(0xFF26A69A),
    Color(0xFF5C6BC0),
    Color(0xFFAB47BC),
    Color(0xFFEF5350),
    Color(0xFF42A5F5),
    Color(0xFFFFA726),
    Color(0xFF66BB6A),
    Color(0xFFEC407A),
    Color(0xFF78909C),
    Color(0xFF5D4037),
    Color(0xFF00BCD4),
    Color(0xFFFF7043),
    Color(0xFF9CCC65),
    Color(0xFF8D6E63),
    Color(0xFF29B6F6),
    Color(0xFF7E57C2),
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 200,
      decoration: const BoxDecoration(
        color: AppTheme.surface,
        border: Border(right: BorderSide(color: AppTheme.border)),
      ),
      child: Column(
        children: [
          // Header
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: AppTheme.border)),
            ),
            child: const Row(
              children: [
                Icon(Icons.category, color: AppTheme.primary, size: 18),
                SizedBox(width: 10),
                Text(
                  'CATEGORIES',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                    color: AppTheme.textSecondary,
                    letterSpacing: 1,
                  ),
                ),
              ],
            ),
          ),

          // Category list
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                // "All Products" category
                _buildCategoryItem(
                  index: 0,
                  icon: Icons.grid_view,
                  name: 'All Products',
                  count: totalProductCount,
                  imageUrl: null,
                  isSelected: selectedId == null,
                  onTap: () => onCategorySelected(null),
                ),

                const Divider(height: 1, indent: 16, endIndent: 16),

                // Dynamic categories
                ...categories.asMap().entries.map((entry) {
                  final index = entry.key;
                  final category = entry.value;
                  return _buildCategoryItem(
                    index: index,
                    icon: _categoryIcons[index % _categoryIcons.length],
                    name: category.name,
                    count: category.productCount,
                    imageUrl: category.imageUrl,
                    isSelected: selectedId == category.id,
                    onTap: () => onCategorySelected(category.id),
                  );
                }),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryItem({
    required int index,
    required IconData icon,
    required String name,
    required int count,
    String? imageUrl,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    final color = _categoryColors[index % _categoryColors.length];

    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: isSelected ? AppTheme.primary.withValues(alpha: 0.1) : null,
          border: Border(
            left: BorderSide(
              color: isSelected ? AppTheme.primary : Colors.transparent,
              width: 3,
            ),
          ),
        ),
        child: Row(
          children: [
            // Category image (circular) or fallback icon
            _buildCategoryAvatar(
              imageUrl: imageUrl,
              icon: icon,
              color: color,
              isSelected: isSelected,
            ),
            const SizedBox(width: 12),

            // Category name + count
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name.toUpperCase(),
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: isSelected ? FontWeight.w600 : FontWeight.w500,
                      color: isSelected ? AppTheme.textPrimary : AppTheme.textSecondary,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '$count items',
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppTheme.textMuted,
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

  Widget _buildCategoryAvatar({
    required String? imageUrl,
    required IconData icon,
    required Color color,
    required bool isSelected,
  }) {
    const double size = 40;

    if (imageUrl != null && imageUrl.isNotEmpty) {
      // Show category image
      return Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(
            color: isSelected ? AppTheme.primary : AppTheme.border,
            width: isSelected ? 2 : 1,
          ),
        ),
        child: ClipOval(
          child: Image.network(
            imageUrl,
            fit: BoxFit.cover,
            errorBuilder: (context, error, stackTrace) {
              // Fallback to icon if image fails to load
              return _buildIconFallback(icon, color, isSelected);
            },
            loadingBuilder: (context, child, loadingProgress) {
              if (loadingProgress == null) return child;
              return _buildIconFallback(icon, color, isSelected);
            },
          ),
        ),
      );
    }

    // Fallback to icon
    return _buildIconFallback(icon, color, isSelected);
  }

  Widget _buildIconFallback(IconData icon, Color color, bool isSelected) {
    const double size = 40;

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: isSelected
            ? AppTheme.primary.withValues(alpha: 0.2)
            : color.withValues(alpha: 0.15),
        border: Border.all(
          color: isSelected ? AppTheme.primary : AppTheme.border,
          width: isSelected ? 2 : 1,
        ),
      ),
      child: Icon(
        icon,
        size: 20,
        color: isSelected ? AppTheme.primary : color,
      ),
    );
  }
}
