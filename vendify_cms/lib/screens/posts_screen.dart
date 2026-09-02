import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:vendify_cms/config/theme.dart';
import 'package:vendify_cms/services/api_service.dart';

class PostsScreen extends StatefulWidget {
  const PostsScreen({super.key});

  @override
  State<PostsScreen> createState() => _PostsScreenState();
}

class _PostsScreenState extends State<PostsScreen> {
  final CmsApiService _api = CmsApiService();
  List<dynamic> _posts = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadPosts();
  }

  Future<void> _loadPosts() async {
    try {
      final data = await _api.getPosts();
      setState(() {
        _posts = data['posts'] ?? [];
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
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 40),
            color: CmsTheme.primary,
            child: const Row(
              children: [
                Text('BLOG', style: TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold, letterSpacing: 2)),
                Spacer(),
                Text('Home > Blog', style: TextStyle(color: Colors.white54, fontSize: 13)),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 40),
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: CmsTheme.highlight))
                : GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 3, childAspectRatio: 0.85, crossAxisSpacing: 20, mainAxisSpacing: 20),
                    itemCount: _posts.length,
                    itemBuilder: (context, index) {
                      final post = _posts[index];
                      return GestureDetector(
                        onTap: () => context.go('/blog/${post['slug']}'),
                        child: Container(
                          decoration: BoxDecoration(border: Border.all(color: CmsTheme.border), borderRadius: BorderRadius.circular(8)),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(child: Container(width: double.infinity, color: CmsTheme.surface, child: const Icon(Icons.article, size: 48, color: CmsTheme.textMuted))),
                              Padding(
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(post['title'] ?? '', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold), maxLines: 2, overflow: TextOverflow.ellipsis),
                                    const SizedBox(height: 8),
                                    Text(post['excerpt'] ?? '', style: const TextStyle(fontSize: 12, color: CmsTheme.textSecondary), maxLines: 2, overflow: TextOverflow.ellipsis),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
