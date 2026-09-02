import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:vendify_cms/config/api_config.dart';
import 'package:vendify_cms/config/theme.dart';
import 'package:vendify_cms/screens/home_screen.dart';
import 'package:vendify_cms/screens/products_screen.dart';
import 'package:vendify_cms/screens/product_detail_screen.dart';
import 'package:vendify_cms/screens/posts_screen.dart';
import 'package:vendify_cms/screens/post_detail_screen.dart';
import 'package:vendify_cms/screens/page_screen.dart';
import 'package:vendify_cms/screens/contact_screen.dart';
import 'package:vendify_cms/services/api_service.dart';
import 'package:vendify_cms/widgets/header.dart';
import 'package:vendify_cms/widgets/footer.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Resolve the tenant at runtime (slug -> GET /v1/cms/config). Falls back
  // to build-time defaults when the API is unreachable (offline dev/preview).
  try {
    await CmsApiService().fetchConfig().timeout(const Duration(seconds: 5));
  } catch (_) {
    // Keep build-time fallbacks; the site still renders.
  }

  runApp(VendifyCmsApp());
}

final GoRouter _router = GoRouter(
  routes: [
    GoRoute(
      path: '/',
      builder: (context, state) => const ScaffoldWithNav(body: HomeScreen()),
    ),
    GoRoute(
      path: '/products',
      builder: (context, state) => const ScaffoldWithNav(body: ProductsScreen()),
    ),
    GoRoute(
      path: '/products/:slug',
      builder: (context, state) => ScaffoldWithNav(
        body: ProductDetailScreen(slug: state.pathParameters['slug']!),
      ),
    ),
    GoRoute(
      path: '/blog',
      builder: (context, state) => const ScaffoldWithNav(body: PostsScreen()),
    ),
    GoRoute(
      path: '/blog/:slug',
      builder: (context, state) => ScaffoldWithNav(
        body: PostDetailScreen(slug: state.pathParameters['slug']!),
      ),
    ),
    GoRoute(
      path: '/page/:slug',
      builder: (context, state) => ScaffoldWithNav(
        body: PageScreen(slug: state.pathParameters['slug']!),
      ),
    ),
    GoRoute(
      path: '/contact',
      builder: (context, state) => const ScaffoldWithNav(body: ContactScreen()),
    ),
  ],
);

class VendifyCmsApp extends StatelessWidget {
  const VendifyCmsApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: ApiConfig.businessName ?? 'Vendify CMS',
      debugShowCheckedModeBanner: false,
      theme: CmsTheme.lightTheme,
      routerConfig: _router,
    );
  }
}

class ScaffoldWithNav extends StatelessWidget {
  final Widget body;
  const ScaffoldWithNav({super.key, required this.body});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          const CmsHeader(),
          Expanded(child: body),
          const CmsFooter(),
        ],
      ),
    );
  }
}