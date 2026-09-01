class BusinessLocation {
  final int id;
  final String name;

  BusinessLocation({required this.id, required this.name});

  factory BusinessLocation.fromJson(Map<String, dynamic> json) {
    return BusinessLocation(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
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
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      businessId: json['business_id'] ?? 0,
      businessName: json['business_name'],
      businessSlug: json['business_slug'],
      businessType: json['business_type'],
      defaultLocationId: json['default_location_id'],
      defaultLocationName: json['default_location_name'],
      hasAssignedLocation: json['has_assigned_location'] ?? false,
      locations: json['locations'] != null
          ? (json['locations'] as List).map((l) => BusinessLocation.fromJson(l)).toList()
          : [],
      roles: json['roles'] != null
          ? List<String>.from(json['roles'])
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
    final data = json['data'];
    return LoginResponse(
      accessToken: data['access_token'],
      tokenType: data['token_type'] ?? 'Bearer',
      user: User.fromJson(data['user']),
    );
  }
}
