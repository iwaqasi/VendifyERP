import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:vendify_cms/config/theme.dart';

class CmsFooter extends StatelessWidget {
  const CmsFooter({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: CmsTheme.primary,
      child: Column(
        children: [
          // Main footer content
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 60),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Brand column
                Expanded(
                  flex: 2,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 40,
                            height: 40,
                            decoration: BoxDecoration(
                              color: CmsTheme.highlight,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Icon(Icons.store, color: Colors.white, size: 24),
                          ),
                          const SizedBox(width: 12),
                          const Text(
                            'SAYA ELEGANT STYLE',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                              letterSpacing: 2,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      const Text(
                        'Curated collections of scarves, jewelry, watches, and accessories from the finest Italian brands.',
                        style: TextStyle(color: Colors.white70, fontSize: 13, height: 1.6),
                      ),
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          _buildSocialIcon(Icons.facebook),
                          const SizedBox(width: 10),
                          _buildSocialIcon(Icons.camera_alt),
                          const SizedBox(width: 10),
                          _buildSocialIcon(Icons.close),
                        ],
                      ),
                    ],
                  ),
                ),

                // Quick links
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('QUICK LINKS', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 16),
                      _buildLink('Home', '/', context),
                      _buildLink('Shop', '/products', context),
                      _buildLink('Blog', '/blog', context),
                      _buildLink('About Us', '/page/about', context),
                      _buildLink('Contact', '/contact', context),
                    ],
                  ),
                ),

                // Categories
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('CATEGORIES', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 16),
                      _buildLink('Scarves', '/products?category=scarves', context),
                      _buildLink('Earrings', '/products?category=earrings', context),
                      _buildLink('Bracelets', '/products?category=bracelets', context),
                      _buildLink('Watches', '/products?category=watches', context),
                      _buildLink('Rings', '/products?category=rings', context),
                    ],
                  ),
                ),

                // Contact info
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('CONTACT US', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 16),
                      _buildContactItem(Icons.location_on, 'Kuwait City, Kuwait'),
                      const SizedBox(height: 10),
                      _buildContactItem(Icons.phone, '+965 XXXX XXXX'),
                      const SizedBox(height: 10),
                      _buildContactItem(Icons.email, 'info@sayaelegantstyle.com'),
                      const SizedBox(height: 10),
                      _buildContactItem(Icons.access_time, 'Sun-Thu: 10AM - 8PM'),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Bottom bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 16),
            decoration: const BoxDecoration(
              border: Border(top: BorderSide(color: Colors.white12)),
            ),
            child: const Row(
              children: [
                Text(
                  '© 2026 Saya Elegant Style. All rights reserved.',
                  style: TextStyle(color: Colors.white54, fontSize: 12),
                ),
                Spacer(),
                Text(
                  'Powered by VendifyERP',
                  style: TextStyle(color: Colors.white54, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static Widget _buildSocialIcon(IconData icon) {
    return Container(
      width: 36,
      height: 36,
      decoration: BoxDecoration(
        color: Colors.white12,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Icon(icon, color: Colors.white70, size: 18),
    );
  }

  static Widget _buildLink(String label, String path, BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: GestureDetector(
        onTap: () => context.go(path),
        child: Text(label, style: const TextStyle(color: Colors.white70, fontSize: 13)),
      ),
    );
  }

  static Widget _buildContactItem(IconData icon, String text) {
    return Row(
      children: [
        Icon(icon, color: CmsTheme.highlight, size: 16),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: const TextStyle(color: Colors.white70, fontSize: 13))),
      ],
    );
  }
}
