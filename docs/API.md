# API Documentation

## Base URL

```
Production: https://api.example.com
Development: http://localhost:8000
```

## Authentication

Most APIs require authentication via Laravel Sanctum tokens.

### Headers

```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

## API Versioning

All APIs are versioned:
- `/api/v1/*` - Current stable version
- `/api/v2/*` - Future version (reserved)

## Response Format

### Success Response

```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}
```

### Error Response

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable message",
    "details": { ... }
  }
}
```

## Endpoints

### Remote Configuration

#### Get Complete Config

```
GET /api/v1/config
```

**Query Parameters:**
- `platform` (string, optional): `app` or `web` (default: `app`)
- `version` (string, optional): App version (e.g., `1.2.0`)

**Response:**
```json
{
  "success": true,
  "data": {
    "branding": { ... },
    "theme": { ... },
    "modules": { ... },
    "app_management": { ... },
    "feature_flags": { ... },
    "home_layout": [ ... ],
    "content_strings": { ... },
    "timestamp": "2024-01-01T00:00:00Z"
  }
}
```

#### Get Branding Config

```
GET /api/v1/config/branding
```

**Response:**
```json
{
  "success": true,
  "data": {
    "app_name": "ZyptoseComm",
    "app_name_short": "ZC",
    "company_name": "Company Name",
    "logo": "https://...",
    "logo_dark": "https://...",
    "icon": "https://...",
    "favicon": "https://..."
  }
}
```

#### Get Theme Config

```
GET /api/v1/config/theme
```

**Response:**
```json
{
  "success": true,
  "data": {
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
    "font_url": "https://fonts.googleapis.com/...",
    "additional_colors": { ... }
  }
}
```

#### Get Modules Config

```
GET /api/v1/config/modules?platform=app&version=1.2.0
```

**Response:**
```json
{
  "success": true,
  "data": {
    "payments": { "enabled": true },
    "shipping": { "enabled": true },
    "wallet": { "enabled": false }
  }
}
```

#### Get App Management Config

```
GET /api/v1/config/app-management?platform=app
```

**Response:**
```json
{
  "success": true,
  "data": {
    "version": "1.2.0",
    "build_number": "123",
    "update_type": "optional",
    "update_message": "New features available!",
    "store_url": "https://play.google.com/...",
    "is_minimum_supported": false,
    "maintenance_mode": false,
    "maintenance_message": null
  }
}
```

#### Get Home Layout

```
GET /api/v1/config/home-layout?platform=app
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "banner",
      "title": "Featured Offers",
      "data": { ... },
      "style": { ... }
    },
    {
      "id": 2,
      "type": "products",
      "title": "Featured Products",
      "data": { ... },
      "style": { ... }
    }
  ]
}
```

---

## Admin APIs (Requires Authentication)

### Branding Management

#### Get All Branding Settings

```
GET /admin/branding
```

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "branding": { ... },
    "app": { ... },
    "themes": [ ... ],
    "app_versions": [ ... ]
  }
}
```

#### Update Branding

```
PUT /admin/branding/branding
```

**Body:**
```json
{
  "app_name": "ZyptoseComm",
  "app_name_short": "ZC",
  "company_name": "Company Name",
  "logo": "https://...",
  "logo_dark": "https://...",
  "icon": "https://...",
  "favicon": "https://..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Branding updated successfully"
}
```

#### Update Theme

```
PUT /admin/branding/theme
```

**Body:**
```json
{
  "theme_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Theme updated successfully"
}
```

#### Update App Version

```
POST /admin/branding/app-version
```

**Body:**
```json
{
  "platform": "android",
  "version": "1.2.0",
  "build_number": "123",
  "update_type": "optional",
  "update_message": "New features available!",
  "store_url": "https://play.google.com/...",
  "is_minimum_supported": false,
  "maintenance_mode": false,
  "maintenance_message": null
}
```

**Response:**
```json
{
  "success": true,
  "data": { ... },
  "message": "App version updated successfully"
}
```

---

## Error Codes

### Authentication Errors

- `AUTH_REQUIRED` - Authentication required
- `AUTH_INVALID` - Invalid token
- `AUTH_EXPIRED` - Token expired
- `PERMISSION_DENIED` - Insufficient permissions

### Validation Errors

- `VALIDATION_FAILED` - Request validation failed
- `INVALID_INPUT` - Invalid input data

### Resource Errors

- `RESOURCE_NOT_FOUND` - Resource not found
- `RESOURCE_EXISTS` - Resource already exists
- `RESOURCE_IN_USE` - Resource cannot be deleted

### Business Logic Errors

- `PAYMENT_FAILED` - Payment processing failed
- `INVENTORY_INSUFFICIENT` - Insufficient inventory
- `ORDER_INVALID` - Invalid order state

### System Errors

- `INTERNAL_ERROR` - Internal server error
- `SERVICE_UNAVAILABLE` - Service temporarily unavailable
- `MAINTENANCE_MODE` - System in maintenance mode

## Rate Limiting

APIs are rate-limited:
- Public APIs: 60 requests per minute per IP
- Authenticated APIs: 120 requests per minute per user
- Admin APIs: 300 requests per minute per user

Rate limit headers:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1640995200
```

## Pagination

List endpoints support pagination:

**Query Parameters:**
- `page` (integer): Page number (default: 1)
- `per_page` (integer): Items per page (default: 15, max: 100)

**Response:**
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 150,
    "last_page": 10,
    "from": 1,
    "to": 15
  },
  "links": {
    "first": "/api/v1/products?page=1",
    "last": "/api/v1/products?page=10",
    "prev": null,
    "next": "/api/v1/products?page=2"
  }
}
```

## Filtering & Sorting

List endpoints support filtering and sorting:

**Query Parameters:**
- `filter[field]` (string): Filter by field value
- `sort` (string): Sort field (e.g., `name` or `-created_at` for descending)

**Example:**
```
GET /api/v1/products?filter[category_id]=1&filter[is_active]=1&sort=-created_at
```

## Webhooks

Webhook endpoints for external providers:

```
POST /api/webhooks/razorpay
POST /api/webhooks/shiprocket
POST /api/webhooks/stripe
```

Webhooks are verified via signature validation.

---

## API Changelog

### v1.0.0 (2024-01-01)
- Initial API release
- Remote configuration endpoints
- Admin branding management
- Basic authentication

### Future Versions
- v2.0.0 - Enhanced features (TBD)

