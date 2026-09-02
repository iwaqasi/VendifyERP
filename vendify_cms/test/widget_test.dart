import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:vendify_cms/config/api_config.dart';
import 'package:vendify_cms/screens/home_screen.dart';
import 'package:vendify_cms/services/api_service.dart';

/// In-memory Dio adapter: serves canned API responses so widget tests are
/// deterministic and never perform real network I/O.
class _FakeAdapter implements HttpClientAdapter {
  final Map<String, dynamic> Function(RequestOptions options) _handler;

  _FakeAdapter(this._handler);

  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    final body = jsonEncode({
      'success': true,
      'data': _handler(options),
    });
    return ResponseBody.fromString(
      body,
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }
}

/// Adapter that always fails, used to verify the error/loading fallback path.
class _ThrowingAdapter implements HttpClientAdapter {
  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    throw DioException.connectionTimeout(
      timeout: const Duration(seconds: 1),
      requestOptions: options,
    );
  }
}

void main() {
  group('ApiConfig', () {
    test('has safe development defaults (no production URL baked in)', () {
      expect(ApiConfig.baseUrl, isNotEmpty);
      expect(
        ApiConfig.baseUrl.contains('arksoftsolutions'),
        isFalse,
        reason: 'Production URL must be injected via --dart-define at build time',
      );
      expect(ApiConfig.businessId, greaterThan(0));
    });
  });

  group('HomeScreen', () {
    testWidgets('renders hero banner, featured products and posts from API data',
        (WidgetTester tester) async {
      final api = CmsApiService(
        adapter: _FakeAdapter((options) => {
              'featured_products': [
                {
                  'id': 1,
                  'name': 'Silk Scarf',
                  'slug': 'silk-scarf',
                  'sell_price_inc_tax': '12.500',
                  'image_url': null,
                },
              ],
              'categories': [
                {'id': 2, 'name': 'Dresses', 'slug': 'dresses'},
              ],
              'latest_posts': [
                {
                  'id': 3,
                  'title': 'Autumn Collection',
                  'slug': 'autumn-collection',
                  'excerpt': 'The new line has landed',
                  'image_url': null,
                  'published_at': '2026-08-01 10:00:00',
                },
              ],
            }),
      );

      await tester.pumpWidget(MaterialApp(home: HomeScreen(apiService: api)));
      // Flush the (fake) network future and let the data state render.
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 50));

      expect(find.text('Timeless Elegance'), findsOneWidget);
      expect(find.text('Silk Scarf'), findsOneWidget);
      expect(find.text('Autumn Collection'), findsOneWidget);
    });

    testWidgets('falls back to the hero banner when the API fails',
        (WidgetTester tester) async {
      final api = CmsApiService(adapter: _ThrowingAdapter());

      await tester.pumpWidget(MaterialApp(home: HomeScreen(apiService: api)));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 50));

      expect(find.text('Timeless Elegance'), findsOneWidget);
      expect(find.text('Silk Scarf'), findsNothing);
    });
  });
}
