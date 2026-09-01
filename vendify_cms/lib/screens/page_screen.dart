import 'package:flutter/material.dart';
import 'package:vendify_cms/config/theme.dart';
import 'package:vendify_cms/services/api_service.dart';

class PageScreen extends StatefulWidget {
  final String slug;
  const PageScreen({super.key, required this.slug});

  @override
  State<PageScreen> createState() => _PageScreenState();
}

class _PageScreenState extends State<PageScreen> {
  final CmsApiService _api = CmsApiService();
  Map<String, dynamic>? _page;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadPage();
  }

  Future<void> _loadPage() async {
    try {
      final data = await _api.getPage(widget.slug);
      setState(() {
        _page = data['page'];
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) return const Center(child: CircularProgressIndicator(color: CmsTheme.highlight));
    if (_page == null) return const Center(child: Text('Page not found'));

    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 40),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(_page!['title'], style: const TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: CmsTheme.textPrimary)),
            const SizedBox(height: 30),
            Text(_page!['content'] ?? '', style: const TextStyle(fontSize: 16, color: CmsTheme.textSecondary, height: 1.8)),
          ],
        ),
      ),
    );
  }
}
