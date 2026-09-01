import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:vendify_cms/config/theme.dart';

class CmsHeader extends StatelessWidget {
  const CmsHeader({super.key});

  @override
  Widget build(BuildContext context) {
    final currentPath = GoRouterState.of(context).uri.path;

    return Container(
      color: Colors.white,
      child: Column(
        children: [
          // Top bar
          Container(
            color: CmsTheme.primary,
            padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 8),
            child: const Row(
              children: [
                Icon(Icons.phone, color: Colors.white70, size: 14),
                SizedBox(width: 6),
                Text('+965 XXXX XXXX', style: TextStyle(color: Colors.white70, fontSize: 12)),
                SizedBox(width: 20),
                Icon(Icons.email, color: Colors.white70, size: 14),
                SizedBox(width: 6),
                Text('info@sayaelegantstyle.com', style: TextStyle(color: Colors.white70, fontSize: 12)),
                Spacer(),
                Icon(Icons.facebook, color: Colors.white70, size: 14),
                SizedBox(width: 12),
                Icon(Icons.camera_alt, color: Colors.white70, size: 14),
                SizedBox(width: 12),
                Icon(Icons.close, color: Colors.white70, size: 14),
              ],
            ),
          ),

          // Main header
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 40, vertical: 20),
            child: Row(
              children: [
                // Logo
                GestureDetector(
                  onTap: () => context.go('/'),
                  child: Row(
                    children: [
                      Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          color: CmsTheme.highlight,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Icon(Icons.store, color: Colors.white, size: 28),
                      ),
                      const SizedBox(width: 12),
                      const Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'SAYA',
                            style: TextStyle(
                              fontSize: 24,
                              fontWeight: FontWeight.bold,
                              color: CmsTheme.primary,
                              letterSpacing: 3,
                            ),
                          ),
                          Text(
                            'ELEGANT STYLE',
                            style: TextStyle(
                              fontSize: 11,
                              color: CmsTheme.textSecondary,
                              letterSpacing: 2,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),

                const Spacer(),

                // Navigation
                _buildNavItem('Home', '/', currentPath),
                _buildNavItem('Shop', '/products', currentPath),
                _buildNavItem('Blog', '/blog', currentPath),
                _buildNavItem('About', '/page/about', currentPath),
                _buildNavItem('Contact', '/contact', currentPath),

                const SizedBox(width: 20),

                // Cart icon
                IconButton(
                  icon: const Icon(Icons.shopping_bag_outlined, color: CmsTheme.textPrimary),
                  onPressed: () {},
                ),
              ],
            ),
          ),

          // Divider
          Container(
            height: 1,
            color: CmsTheme.border,
          ),
        ],
      ),
    );
  }

  Widget _buildNavItem(String label, String path, String currentPath) {
    final isActive = currentPath == path;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Builder(
        builder: (context) => GestureDetector(
          onTap: () => context.go(path),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: isActive ? FontWeight.bold : FontWeight.w500,
                  color: isActive ? CmsTheme.highlight : CmsTheme.textPrimary,
                ),
              ),
              const SizedBox(height: 4),
              if (isActive)
                Container(
                  width: 24,
                  height: 2,
                  color: CmsTheme.highlight,
                ),
            ],
          ),
        ),
      ),
    );
  }
}
