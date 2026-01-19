# Flutter App Integration Guide

## Overview

The Flutter mobile app must be **completely remote-config driven**. No hardcoded strings, colors, or configuration. All branding, features, and behavior are fetched at runtime from the backend.

## Architecture Principles

1. **Remote Config First** - All UI elements pulled from `/api/v1/config`
2. **No Hardcoding** - No strings, colors, or URLs hardcoded
3. **Dynamic Theming** - Theme applied from remote config
4. **Feature Flags** - Module and rule-based feature toggles
5. **Update Management** - Handle force/optional updates from config

## Remote Config Structure

### API Endpoint

```
GET /api/v1/config?platform=app&version=1.2.0
```

### Response Structure

```json
{
  "success": true,
  "data": {
    "branding": {
      "app_name": "ZyptoseComm",
      "app_name_short": "ZC",
      "company_name": "Company Name",
      "logo": "https://...",
      "logo_dark": "https://...",
      "icon": "https://...",
      "favicon": "https://..."
    },
    "theme": {
      "primary_color": "#007bff",
      "secondary_color": "#6c757d",
      "accent_color": "#ffc107",
      "background_color": "#ffffff",
      "surface_color": "#f8f9fa",
      "text_color": "#212529",
      "text_secondary_color": "#6c757d",
      "border_radius": "8px",
      "ui_density": "normal",
      "font_family": "Roboto",
      "font_url": "https://fonts.googleapis.com/css2?family=Roboto",
      "additional_colors": {
        "success": "#28a745",
        "error": "#dc3545",
        "warning": "#ffc107",
        "info": "#17a2b8"
      }
    },
    "modules": {
      "payments": { "enabled": true },
      "shipping": { "enabled": true },
      "wallet": { "enabled": false }
    },
    "app_management": {
      "version": "1.2.0",
      "build_number": "123",
      "update_type": "optional",
      "update_message": "New features available!",
      "store_url": "https://play.google.com/...",
      "is_minimum_supported": false,
      "maintenance_mode": false,
      "maintenance_message": null
    },
    "feature_flags": {
      "payments.cod_enabled": true,
      "payments.cod_max_amount": 5000,
      "wallet.enabled": false
    },
    "home_layout": [
      {
        "id": 1,
        "type": "banner",
        "title": "Featured Offers",
        "data": {
          "images": ["https://..."],
          "auto_slide": true,
          "duration": 3000
        },
        "style": { "height": 200, "margin": 16 }
      },
      {
        "id": 2,
        "type": "products",
        "title": "Featured Products",
        "data": {
          "category": "featured",
          "limit": 10
        },
        "style": { "layout": "grid", "columns": 2 }
      }
    ],
    "content_strings": {
      "app.welcome_message": "Welcome to {app_name}",
      "checkout.title": "Checkout",
      "product.add_to_cart": "Add to Cart"
    },
    "timestamp": "2024-01-01T00:00:00Z"
  }
}
```

## Implementation Guide

### 1. Remote Config Service

Create a `RemoteConfigService` in Flutter:

```dart
// lib/services/remote_config_service.dart
class RemoteConfigService {
  static const String baseUrl = 'https://api.example.com';
  RemoteConfigData? _config;
  DateTime? _lastFetch;
  
  Future<RemoteConfigData> fetchConfig({
    String? version,
    bool forceRefresh = false,
  }) async {
    // Cache for 1 hour
    if (!forceRefresh && _config != null && _lastFetch != null) {
      if (DateTime.now().difference(_lastFetch!).inHours < 1) {
        return _config!;
      }
    }
    
    final response = await http.get(
      Uri.parse('$baseUrl/api/v1/config').replace(
        queryParameters: {
          'platform': 'app',
          if (version != null) 'version': version,
        },
      ),
    );
    
    if (response.statusCode == 200) {
      final data = json.decode(response.body)['data'];
      _config = RemoteConfigData.fromJson(data);
      _lastFetch = DateTime.now();
      return _config!;
    }
    
    // Fallback to cached config if available
    if (_config != null) {
      return _config!;
    }
    
    throw Exception('Failed to fetch remote config');
  }
  
  RemoteConfigData? get config => _config;
}
```

### 2. Config Models

Create data models for config:

```dart
// lib/models/remote_config.dart
class RemoteConfigData {
  final BrandingConfig branding;
  final ThemeConfig theme;
  final Map<String, dynamic> modules;
  final AppManagementConfig appManagement;
  final Map<String, dynamic> featureFlags;
  final List<HomeSection> homeLayout;
  final Map<String, String> contentStrings;
  final String timestamp;
  
  RemoteConfigData({
    required this.branding,
    required this.theme,
    required this.modules,
    required this.appManagement,
    required this.featureFlags,
    required this.homeLayout,
    required this.contentStrings,
    required this.timestamp,
  });
  
  factory RemoteConfigData.fromJson(Map<String, dynamic> json) {
    return RemoteConfigData(
      branding: BrandingConfig.fromJson(json['branding']),
      theme: ThemeConfig.fromJson(json['theme']),
      modules: Map<String, dynamic>.from(json['modules']),
      appManagement: AppManagementConfig.fromJson(json['app_management']),
      featureFlags: Map<String, dynamic>.from(json['feature_flags']),
      homeLayout: (json['home_layout'] as List)
          .map((e) => HomeSection.fromJson(e))
          .toList(),
      contentStrings: Map<String, String>.from(json['content_strings']),
      timestamp: json['timestamp'],
    );
  }
}

class BrandingConfig {
  final String appName;
  final String appNameShort;
  final String? companyName;
  final String? logo;
  final String? logoDark;
  final String? icon;
  
  BrandingConfig({
    required this.appName,
    required this.appNameShort,
    this.companyName,
    this.logo,
    this.logoDark,
    this.icon,
  });
  
  factory BrandingConfig.fromJson(Map<String, dynamic> json) {
    return BrandingConfig(
      appName: json['app_name'],
      appNameShort: json['app_name_short'],
      companyName: json['company_name'],
      logo: json['logo'],
      logoDark: json['logo_dark'],
      icon: json['icon'],
    );
  }
}

class ThemeConfig {
  final Color primaryColor;
  final Color secondaryColor;
  final Color accentColor;
  final Color backgroundColor;
  final Color surfaceColor;
  final Color textColor;
  final Color textSecondaryColor;
  final double borderRadius;
  final String? fontFamily;
  
  ThemeConfig({
    required this.primaryColor,
    required this.secondaryColor,
    required this.accentColor,
    required this.backgroundColor,
    required this.surfaceColor,
    required this.textColor,
    required this.textSecondaryColor,
    required this.borderRadius,
    this.fontFamily,
  });
  
  factory ThemeConfig.fromJson(Map<String, dynamic> json) {
    return ThemeConfig(
      primaryColor: _parseColor(json['primary_color']),
      secondaryColor: _parseColor(json['secondary_color']),
      accentColor: _parseColor(json['accent_color']),
      backgroundColor: _parseColor(json['background_color']),
      surfaceColor: _parseColor(json['surface_color']),
      textColor: _parseColor(json['text_color']),
      textSecondaryColor: _parseColor(json['text_secondary_color']),
      borderRadius: _parseDouble(json['border_radius']),
      fontFamily: json['font_family'],
    );
  }
  
  static Color _parseColor(String? color) {
    if (color == null) return Colors.black;
    return Color(int.parse(color.replaceFirst('#', '0xFF')));
  }
  
  static double _parseDouble(String? value) {
    if (value == null) return 8.0;
    return double.tryParse(value.replaceAll('px', '')) ?? 8.0;
  }
}
```

### 3. App Initialization

Fetch config on app startup:

```dart
// lib/main.dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Fetch remote config
  final remoteConfigService = RemoteConfigService();
  final config = await remoteConfigService.fetchConfig(
    version: '1.2.0', // Get from package_info_plus
  );
  
  // Check app management
  if (config.appManagement.maintenanceMode) {
    runApp(MaintenanceApp(config));
  } else if (config.appManagement.updateType == 'force') {
    runApp(UpdateRequiredApp(config));
  } else {
    runApp(MyApp(config));
  }
}
```

### 4. Dynamic Theming

Apply theme from remote config:

```dart
// lib/theme/app_theme.dart
class AppTheme {
  static ThemeData getTheme(ThemeConfig config) {
    return ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme(
        primary: config.primaryColor,
        secondary: config.secondaryColor,
        surface: config.surfaceColor,
        background: config.backgroundColor,
        error: Colors.red,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: config.textColor,
        onBackground: config.textColor,
        onError: Colors.white,
        brightness: Brightness.light,
      ),
      textTheme: TextTheme(
        bodyLarge: TextStyle(
          color: config.textColor,
          fontFamily: config.fontFamily,
        ),
        bodyMedium: TextStyle(
          color: config.textSecondaryColor,
          fontFamily: config.fontFamily,
        ),
      ),
      cardTheme: CardTheme(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(config.borderRadius),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(config.borderRadius),
          ),
        ),
      ),
    );
  }
}
```

### 5. Dynamic Home Layout

Render home sections from JSON:

```dart
// lib/widgets/home_section_renderer.dart
class HomeSectionRenderer extends StatelessWidget {
  final HomeSection section;
  
  Widget build(BuildContext context) {
    switch (section.type) {
      case 'banner':
        return _buildBanner(section);
      case 'products':
        return _buildProducts(section);
      case 'categories':
        return _buildCategories(section);
      case 'grid':
        return _buildGrid(section);
      case 'carousel':
        return _buildCarousel(section);
      default:
        return SizedBox.shrink();
    }
  }
  
  Widget _buildBanner(HomeSection section) {
    final images = (section.data['images'] as List).cast<String>();
    return CarouselSlider(
      items: images.map((url) => Image.network(url)).toList(),
      options: CarouselOptions(
        autoPlay: section.data['auto_slide'] ?? true,
        autoPlayInterval: Duration(
          milliseconds: section.data['duration'] ?? 3000,
        ),
      ),
    );
  }
  
  // ... other builders
}
```

### 6. Feature Flags

Check feature flags before showing features:

```dart
// lib/utils/feature_flags.dart
class FeatureFlags {
  static bool isEnabled(
    Map<String, dynamic> flags,
    String key,
    bool defaultValue = false,
  ) {
    return flags[key] ?? defaultValue;
  }
  
  static T getValue<T>(
    Map<String, dynamic> flags,
    String key,
    T defaultValue,
  ) {
    return flags[key] ?? defaultValue;
  }
}

// Usage
final config = RemoteConfigService().config;
if (FeatureFlags.isEnabled(config.featureFlags, 'payments.cod_enabled')) {
  // Show COD option
}

final codMaxAmount = FeatureFlags.getValue(
  config.featureFlags,
  'payments.cod_max_amount',
  5000,
);
```

### 7. Update Management

Handle app updates:

```dart
// lib/widgets/update_dialog.dart
class UpdateDialog extends StatelessWidget {
  final AppManagementConfig config;
  
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text('Update Available'),
      content: Text(config.updateMessage ?? 'Please update the app'),
      actions: [
        if (config.updateType == 'optional')
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text('Later'),
          ),
        ElevatedButton(
          onPressed: () => _openStore(context),
          child: Text('Update'),
        ),
      ],
    );
  }
  
  void _openStore(BuildContext context) {
    final url = config.storeUrl;
    if (url != null) {
      launchUrl(Uri.parse(url));
    }
  }
}
```

## Best Practices

1. **Always check remote config on app start**
2. **Cache config for offline fallback**
3. **Handle network errors gracefully**
4. **Check maintenance mode before showing UI**
5. **Verify update requirements before proceeding**
6. **Use feature flags for conditional features**
7. **Load images from URLs (no assets for branding)**
8. **Apply theme dynamically from config**
9. **Render home layout from JSON structure**
10. **Never hardcode strings - use content_strings**

## Package Dependencies

```yaml
dependencies:
  flutter:
    sdk: flutter
  http: ^1.1.0
  shared_preferences: ^2.2.0
  package_info_plus: ^5.0.0
  url_launcher: ^6.2.0
  cached_network_image: ^3.3.0
  carousel_slider: ^4.2.0
```

## Testing

Test with different config scenarios:
- Maintenance mode
- Force update required
- Optional update available
- Feature flags enabled/disabled
- Different themes
- Various home layouts

