import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:vendify_cms/config/theme.dart';
import 'package:vendify_cms/services/api_service.dart';

class PostDetailScreen extends StatefulWidget {
  final String slug;
  const PostDetailScreen({super.key, required this.slug});

  @override
  State<PostDetailScreen> createState() => _PostDetailScreenState();
}

class _PostDetailScreenState extends State<PostDetailScreen> {
  final CmsApiService _api = CmsApiService();
  Map<String, dynamic>? _post;
  List<dynamic> _relatedPosts = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadPost();
  }

  Future<void> _loadPost() async {
    try {
      final data = await _api.getPost(widget.slug);
      setState(() {
        _post = data['post'];
        _relatedPosts = data['related_posts'] ?? [];
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) return const Center(child: CircularProgressIndicator(color: CmsTheme.highlight));
    if (_post == null) return const Center(child: Text('Post not found'));
    final published = _post!['published_at']?.toString() ?? '';

    return SingleChildScrollView(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 40),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Home > Blog > ${_post!['title']}', style: const TextStyle(fontSize: 13, color: CmsTheme.textMuted)),
            const SizedBox(height: 30),
            Text(_post!['title'], style: const TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: CmsTheme.textPrimary)),
            const SizedBox(height: 12),
            Row(children: [
              Text(_post!['author_name'] ?? 'Admin', style: const TextStyle(fontSize: 13, color: CmsTheme.textMuted)),
              const SizedBox(width: 16),
              Text(published.length >= 10 ? published.substring(0, 10) : published, style: const TextStyle(fontSize: 13, color: CmsTheme.textMuted)),
              const SizedBox(width: 16),
              Text('${_post!['views_count'] ?? 0} views', style: const TextStyle(fontSize: 13, color: CmsTheme.textMuted)),
            ]),
            const SizedBox(height: 30),
            if (_post!['image_url'] != null)
              ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.network(_post!['image_url'], width: double.infinity, height: 400, fit: BoxFit.cover)),
            const SizedBox(height: 30),
            Text(_post!['content'] ?? '', style: const TextStyle(fontSize: 16, color: CmsTheme.textSecondary, height: 1.8)),
          ],
        ),
      ),
    );
  }
}
