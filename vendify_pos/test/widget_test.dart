import 'package:flutter_test/flutter_test.dart';
import 'package:vendify_pos/config/api_config.dart';

void main() {
  group('ApiConfig', () {
    test('has safe development defaults (no production URL baked in)', () {
      expect(ApiConfig.baseUrl, isNotEmpty);
      expect(
        ApiConfig.baseUrl.contains('arksoftsolutions'),
        isFalse,
        reason: 'Production URL must be injected via --dart-define at build time',
      );
      expect(ApiConfig.connectTimeout, const Duration(seconds: 15));
      expect(ApiConfig.receiveTimeout, const Duration(seconds: 15));
      expect(ApiConfig.defaultHeaders['Accept'], 'application/json');
    });
  });
}
