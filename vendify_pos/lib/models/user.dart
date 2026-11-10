class BusinessLocation {
  final int id;
  final String name;

  BusinessLocation({required this.id, required this.name});

  factory BusinessLocation.fromJson(Map<String, dynamic> json) {
    return BusinessLocation(
      id: json['id'] is int ? json['id'] as int : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      name: json['name']?.toString() ?? '',
    );
  }
}

class User {
  final int id;
  final String name;
  final String email;
  final int businessId;
  final String? businessName;
  final String? businessSlug;
  final String? businessType;
  final int? defaultLocationId;
  final String? defaultLocationName;
  final bool hasAssignedLocation;
  final List<BusinessLocation> locations;
  final List<String> roles;

  User({
    required this.id,
    required this.name,
    required this.email,
    required this.businessId,
    this.businessName,
    this.businessSlug,
    this.businessType,
    this.defaultLocationId,
    this.defaultLocationName,
    this.hasAssignedLocation = false,
    this.locations = const [],
    this.roles = const [],
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] is int ? json['id'] as int : int.tryParse(json['id']?.toString() ?? '') ?? 0,
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      businessId: json['business_id'] is int ? json['business_id'] as int : int.tryParse(json['business_id']?.toString() ?? '') ?? 0,
      businessName: json['business_name']?.toString(),
      businessSlug: json['business_slug']?.toString(),
      businessType: json['business_type']?.toString(),
      defaultLocationId: json['default_location_id'] is int
          ? json['default_location_id'] as int
          : int.tryParse(json['default_location_id']?.toString() ?? ''),
      defaultLocationName: json['default_location_name']?.toString(),
      hasAssignedLocation: json['has_assigned_location'] == true || json['has_assigned_location'] == 1,
      locations: json['locations'] is List
          ? (json['locations'] as List)
              .whereType<Map>()
              .map((l) => BusinessLocation.fromJson(Map<String, dynamic>.from(l)))
              .toList()
          : [],
      roles: json['roles'] is List
          ? (json['roles'] as List).map((r) => r.toString()).toList()
          : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'business_id': businessId,
      'business_name': businessName,
      'business_slug': businessSlug,
      'business_type': businessType,
      'default_location_id': defaultLocationId,
      'default_location_name': defaultLocationName,
      'has_assigned_location': hasAssignedLocation,
      'locations': locations.map((l) => {'id': l.id, 'name': l.name}).toList(),
      'roles': roles,
    };
  }
}

class LoginResponse {
  final String accessToken;
  final String tokenType;
  final User user;

  LoginResponse({
    required this.accessToken,
    required this.tokenType,
    required this.user,
  });

  factory LoginResponse.fromJson(Map<String, dynamic> json) {
    final rawData = json['data'];
    final Map<String, dynamic> data = (rawData is Map)
        ? Map<String, dynamic>.from(rawData)
        : json;

    final rawUser = data['user'];
    final Map<String, dynamic> userMap = (rawUser is Map)
        ? Map<String, dynamic>.from(rawUser)
        : <String, dynamic>{};

    return LoginResponse(
      accessToken: data['access_token']?.toString() ?? '',
      tokenType: data['token_type']?.toString() ?? 'Bearer',
      user: User.fromJson(userMap),
    );
  }
}
