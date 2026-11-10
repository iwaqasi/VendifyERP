import 'dart:convert';
import 'package:vendify_pos/models/user.dart';

void main() {
  final jsonString = '''
{"success":true,"message":"Login successful","data":{"access_token":"dummy_token","token_type":"Bearer","expires_at":"2026-12-09T22:33:35+03:00","user":{"id":1,"name":"Mr. Syed Waqas Ahmad","email":"info@arksoftsolutions.com","business_id":1,"business_name":"ARK Software Solutions","business_slug":"ark-software-solutions","business_type":"retail","default_location_id":1,"default_location_name":"ARK Software Solutions","has_assigned_location":false,"locations":[{"id":1,"name":"ARK Software Solutions"}],"roles":["Admin#1"]}}}
''';

  final data = jsonDecode(jsonString);
  print('Decoded data: $data');

  try {
    final loginResponse = LoginResponse.fromJson(data);
    print('LoginResponse parsed successfully: ${loginResponse.accessToken}');
    print('User name: ${loginResponse.user.name}');
    print('User businessId: ${loginResponse.user.businessId}');
    print('User defaultLocationId: ${loginResponse.user.defaultLocationId}');
    print('User hasAssignedLocation: ${loginResponse.user.hasAssignedLocation}');
    print('User roles: ${loginResponse.user.roles}');
  } catch (e, st) {
    print('Error: $e\n$st');
  }
}
