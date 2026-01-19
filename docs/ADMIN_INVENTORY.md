# Admin Panel Inventory - Complete Module List

**Status**: Last Updated: 2024-01-19  
**Purpose**: Complete inventory of all required admin pages, resources, and features

---

## 📊 MODULE 1: Dashboard & Overview

### Navigation Group
- **Name**: Dashboard
- **Icon**: ChartBarIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Dashboard | Widget | `App\Filament\Widgets\DashboardStats` | `/admin` | `dashboard.view` | ✅ DONE |
| Sales Trend Chart | Widget | `App\Filament\Widgets\SalesTrendChart` | `/admin` | `dashboard.view` | ⏳ TODO |
| Top Products Chart | Widget | `App\Filament\Widgets\TopProductsChart` | `/admin` | `dashboard.view` | ⏳ TODO |
| Top Categories Chart | Widget | `App\Filament\Widgets\TopCategoriesChart` | `/admin` | `dashboard.view` | ⏳ TODO |
| System Health Widget | Widget | `App\Filament\Widgets\SystemHealthWidget` | `/admin` | `dashboard.view` | ⏳ TODO |

### KPI Widgets Required
- Total Sales (with trend)
- Total Orders (with trend)
- Conversion Rate
- Refund Rate
- Low Stock Alerts Count
- Failed Webhooks Count

### Quick Actions
- Create Product
- Create Banner
- Create Coupon

### Audit Events
- None (read-only dashboard)

---

## 👥 MODULE 2: Users & Customers

### Navigation Group
- **Name**: Users
- **Icon**: UserGroupIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Customers | Resource | `App\Filament\Resources\UserResource` | `/admin/users` | `users.view` | ⏳ TODO |
| Customer Segments | Page | `App\Filament\Pages\CustomerSegments` | `/admin/users/segments` | `users.segments` | ⏳ TODO |
| Customer Detail | Page | `App\Filament\Pages\CustomerDetail` | `/admin/users/{id}` | `users.view` | ⏳ TODO |
| Wallet Adjustments | Action | `UserResource::adjustWallet()` | `/admin/users` | `users.wallet.adjust` | ⏳ TODO |

### Form Fields
- Name, Email, Phone
- Status (active/blocked)
- Type (admin/customer/vendor)
- Registration Date
- Last Login
- Total Orders
- Wallet Balance

### Filters
- Status
- Type
- Registration Date Range
- Total Orders Range

### Bulk Actions
- Activate/Deactivate
- Export CSV

### Audit Events
- `customer.created`
- `customer.updated`
- `customer.deleted`
- `customer.status_changed`
- `wallet.adjusted`

---

## 🔐 MODULE 3: Roles & Permissions (RBAC)

### Navigation Group
- **Name**: Security
- **Icon**: ShieldCheckIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Roles | Resource | `App\Filament\Resources\RoleResource` | `/admin/roles` | `roles.view` | ⏳ TODO |
| Permissions | Resource | `App\Filament\Resources\PermissionResource` | `/admin/permissions` | `permissions.view` | ⏳ TODO |
| User Role Assignment | Page | `App\Filament\Pages\UserRoleAssignment` | `/admin/roles/assign` | `roles.assign` | ⏳ TODO |

### Default Roles
- Super Admin (all permissions)
- Manager (all except security)
- Support (orders, customers, refunds)
- Content Editor (CMS, content, home builder)
- Inventory Staff (products, inventory, warehouse)

### Permissions Structure
- Module-based: `{module}.{action}` (e.g., `products.create`, `orders.view`)
- Page-based: `{page}.{action}` (e.g., `settings.edit`, `branding.update`)

### Audit Events
- `role.created`
- `role.updated`
- `role.deleted`
- `permission.assigned`
- `permission.revoked`

---

## ⚙️ MODULE 4: System Settings (Non-secret)

### Navigation Group
- **Name**: Settings
- **Icon**: CogIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Settings | Page | `App\Filament\Pages\SettingsPage` | `/admin/settings` | `settings.view` | ⏳ TODO |
| Feature Flags | Page | `App\Filament\Pages\FeatureFlagsPage` | `/admin/settings/feature-flags` | `settings.feature_flags` | ⏳ TODO |

### Settings Tabs
- General (app name, currency, timezone)
- Store (store details, contact info)
- Localization (languages, defaults)
- Email (SMTP config - references secrets)
- Tax (tax settings)
- Shipping (default shipping)
- Notifications (notification preferences)

### Feature Flags
- Module enable/disable per platform
- Module rules (time-based, version-based)
- Platform toggles (web/app/both)

### Audit Events
- `setting.updated`
- `module.toggled`
- `feature_flag.changed`

---

## 🔑 MODULE 5: Secrets Vault (Encrypted Credentials)

### Navigation Group
- **Name**: Providers
- **Icon**: KeyIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Providers | Resource | `App\Filament\Resources\ProviderResource` | `/admin/providers` | `providers.view` | ⏳ TODO |
| Provider Credentials | Page | `App\Filament\Pages\ProviderCredentialsPage` | `/admin/providers/{id}/credentials` | `providers.credentials.edit` | ⏳ TODO |
| Test Connection | Action | `ProviderResource::testConnection()` | `/admin/providers` | `providers.test` | ⏳ TODO |

### Provider Types
- Payment (Razorpay, PayU, Stripe, Cashfree, PhonePe)
- Shipping (ShipRocket, etc.)
- Notification (Firebase, Twilio, SendGrid, etc.)
- Auth (Firebase, Custom OTP)
- Storage (S3, Local)

### Credential Fields (Encrypted)
- API Key
- Secret Key
- Merchant ID
- Webhook Secret
- Environment (sandbox/production)

### Actions
- Test Connection
- Toggle Active
- Switch Environment
- View Credential History (audit only)

### Audit Events
- `credential.created`
- `credential.updated`
- `credential.deleted`
- `credential.tested`

---

## 🎨 MODULE 6: Branding & App Management

### Navigation Group
- **Name**: Branding
- **Icon**: PaintBrushIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Branding Settings | Page | `App\Filament\Pages\BrandingSettings` | `/admin/branding` | `branding.edit` | ⏳ TODO |
| Theme Tokens | Page | `App\Filament\Pages\ThemeTokens` | `/admin/branding/theme` | `branding.theme.edit` | ⏳ TODO |
| App Version Control | Page | `App\Filament\Pages\AppVersionControl` | `/admin/branding/app-versions` | `branding.app_versions` | ⏳ TODO |
| Maintenance Mode | Page | `App\Filament\Pages\MaintenanceMode` | `/admin/branding/maintenance` | `branding.maintenance` | ⏳ TODO |
| Kill Switch | Page | `App\Filament\Pages\KillSwitch` | `/admin/branding/kill-switch` | `branding.kill_switch` | ⏳ TODO |

### Branding Fields
- App Name (full + short)
- Company Name
- Logo (light/dark)
- App Icon
- Favicon
- Splash Screen

### Theme Fields
- Primary/Secondary/Accent Colors
- Background/Surface Colors
- Text Colors
- Border Radius
- UI Density
- Font Family/URL

### App Version Fields
- Platform (Android/iOS/Web)
- Version/Build Number
- Update Type (none/optional/force)
- Update Message
- Store URL
- Minimum Supported Version
- Maintenance Mode Toggle
- Maintenance Message

### Actions
- Preview Changes
- Publish Changes
- Reset to Default

### Audit Events
- `branding.updated`
- `theme.changed`
- `app_version.updated`
- `maintenance_mode.toggled`
- `kill_switch.activated`

---

## 📄 MODULE 7: Content & CMS

### Navigation Group
- **Name**: Content
- **Icon**: DocumentTextIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| CMS Pages | Resource | `App\Filament\Resources\CmsPageResource` | `/admin/cms/pages` | `cms.view` | ⏳ TODO |
| Content Strings | Resource | `App\Filament\Resources\ContentStringResource` | `/admin/cms/strings` | `cms.strings.view` | ⏳ TODO |
| Localization Manager | Page | `App\Filament\Pages\LocalizationManager` | `/admin/cms/localization` | `cms.localization` | ⏳ TODO |

### CMS Page Types
- About
- Privacy Policy
- Terms & Conditions
- Refund Policy
- Shipping Policy
- Custom Pages

### Content Strings
- App strings (welcome message, etc.)
- Checkout strings
- Product strings
- Error messages

### Audit Events
- `cms_page.created`
- `cms_page.updated`
- `cms_page.deleted`
- `content_string.updated`

---

## 🏠 MODULE 8: Home Layout Builder

### Navigation Group
- **Name**: Home Builder
- **Icon**: HomeIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Home Sections | Resource | `App\Filament\Resources\HomeSectionResource` | `/admin/home-sections` | `home_builder.view` | ⏳ TODO |
| Section Items | Resource | `App\Filament\Resources\HomeSectionItemResource` | `/admin/home-sections/{id}/items` | `home_builder.items` | ⏳ TODO |
| Placement Manager | Page | `App\Filament\Pages\PlacementManager` | `/admin/home-sections/placement` | `home_builder.placement` | ⏳ TODO |
| Media Library | Resource | `App\Filament\Resources\MediaLibraryResource` | `/admin/media` | `media.view` | ⏳ TODO |

### Section Types
- Slider/Banner
- Category Grid
- Product Carousel
- Offer Cards
- Custom HTML

### Features
- Drag-drop ordering
- Scheduling (start/end date)
- Platform scope (web/app/both)
- Deep link builder

### Audit Events
- `home_section.created`
- `home_section.updated`
- `home_section.reordered`
- `home_section.deleted`

---

## 📦 MODULE 9: Catalog

### Navigation Group
- **Name**: Catalog
- **Icon**: CubeIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Products | Resource | `App\Filament\Resources\ProductResource` | `/admin/products` | `products.view` | ✅ DONE |
| Categories | Resource | `App\Filament\Resources\CategoryResource` | `/admin/categories` | `categories.view` | ⏳ TODO |
| Brands | Resource | `App\Filament\Resources\BrandResource` | `/admin/brands` | `brands.view` | ⏳ TODO |
| Attributes | Resource | `App\Filament\Resources\AttributeResource` | `/admin/attributes` | `attributes.view` | ⏳ TODO |
| Product Import | Page | `App\Filament\Pages\ProductImport` | `/admin/products/import` | `products.import` | ⏳ TODO |
| Product SEO | Action | `ProductResource::editSeo()` | `/admin/products/{id}/seo` | `products.seo.edit` | ⏳ TODO |

### Product Variants
- Size, Color, Material, etc.
- Variant pricing
- Variant inventory

### Bulk Actions
- Publish/Unpublish
- Bulk Price Update
- Bulk Inventory Update
- Export CSV

### Audit Events
- `product.created`
- `product.updated`
- `product.price_changed`
- `product.deleted`
- `category.created`
- `category.updated`

---

## 📊 MODULE 10: Inventory & Warehousing

### Navigation Group
- **Name**: Inventory
- **Icon**: ArchiveBoxIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Warehouses | Resource | `App\Filament\Resources\WarehouseResource` | `/admin/warehouses` | `inventory.warehouses` | ⏳ TODO |
| Stock Ledger | Resource | `App\Filament\Resources\StockLedgerResource` | `/admin/inventory/ledger` | `inventory.ledger.view` | ⏳ TODO |
| Stock Adjustments | Page | `App\Filament\Pages\StockAdjustments` | `/admin/inventory/adjustments` | `inventory.adjust` | ⏳ TODO |
| Low Stock Rules | Page | `App\Filament\Pages\LowStockRules` | `/admin/inventory/low-stock` | `inventory.low_stock` | ⏳ TODO |

### Audit Events
- `stock.adjusted`
- `warehouse.created`
- `low_stock_alert.triggered`

---

## 💰 MODULE 11: Pricing & Discounts

### Navigation Group
- **Name**: Pricing
- **Icon**: CurrencyDollarIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Pricing Rules | Resource | `App\Filament\Resources\PricingRuleResource` | `/admin/pricing/rules` | `pricing.view` | ⏳ TODO |
| Scheduled Price Changes | Page | `App\Filament\Pages\ScheduledPriceChanges` | `/admin/pricing/scheduled` | `pricing.scheduled` | ⏳ TODO |
| Price Audit Log | Resource | `App\Filament\Resources\PriceAuditLogResource` | `/admin/pricing/audit` | `pricing.audit.view` | ⏳ TODO |

### Audit Events
- `price.changed`
- `pricing_rule.created`
- `pricing_rule.updated`

---

## ⚡ MODULE 12: Deals / Flash Sales

### Navigation Group
- **Name**: Sales
- **Icon**: BoltIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Sales Campaigns | Resource | `App\Filament\Resources\SalesCampaignResource` | `/admin/sales/campaigns` | `sales.view` | ⏳ TODO |
| Flash Sales | Resource | `App\Filament\Resources\FlashSaleResource` | `/admin/sales/flash` | `sales.flash` | ⏳ TODO |
| Conflict Resolver | Page | `App\Filament\Pages\ConflictResolver` | `/admin/sales/conflicts` | `sales.conflicts` | ⏳ TODO |
| Campaign Calendar | Page | `App\Filament\Pages\CampaignCalendar` | `/admin/sales/calendar` | `sales.calendar` | ⏳ TODO |

### Audit Events
- `sales_campaign.created`
- `sales_campaign.updated`
- `flash_sale.activated`

---

## 🎟️ MODULE 13: Coupons / Promo Codes

### Navigation Group
- **Name**: Promotions
- **Icon**: TicketIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Coupons | Resource | `App\Filament\Resources\CouponResource` | `/admin/coupons` | `coupons.view` | ⏳ TODO |
| Coupon Rule Builder | Page | `App\Filament\Pages\CouponRuleBuilder` | `/admin/coupons/rules` | `coupons.rules` | ⏳ TODO |
| Coupon Test Simulator | Page | `App\Filament\Pages\CouponTestSimulator` | `/admin/coupons/test` | `coupons.test` | ⏳ TODO |

### Coupon Types
- Percentage
- Fixed Amount
- Free Shipping

### Audit Events
- `coupon.created`
- `coupon.updated`
- `coupon.usage_tracked`

---

## 🛒 MODULE 14: Cart & Checkout Rules

### Navigation Group
- **Name**: Checkout
- **Icon**: ShoppingCartIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Checkout Rules | Page | `App\Filament\Pages\CheckoutRules` | `/admin/checkout/rules` | `checkout.rules` | ⏳ TODO |
| Pincode Serviceability | Page | `App\Filament\Pages\PincodeServiceability` | `/admin/checkout/pincodes` | `checkout.pincodes` | ⏳ TODO |

### Audit Events
- `checkout_rule.updated`
- `pincode_serviceability.updated`

---

## 📋 MODULE 15: Orders & Fulfillment

### Navigation Group
- **Name**: Orders
- **Icon**: ShoppingBagIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Orders | Resource | `App\Filament\Resources\OrderResource` | `/admin/orders` | `orders.view` | ✅ DONE |
| Order Workflow Builder | Page | `App\Filament\Pages\OrderWorkflowBuilder` | `/admin/orders/workflow` | `orders.workflow` | ⏳ TODO |
| Order Batch Actions | Page | `App\Filament\Pages\OrderBatchActions` | `/admin/orders/batch` | `orders.batch` | ⏳ TODO |
| Invoice Templates | Resource | `App\Filament\Resources\InvoiceTemplateResource` | `/admin/orders/invoices` | `orders.invoices` | ⏳ TODO |

### Custom Order Status Workflow
- Admin-defined statuses
- Status transitions
- Status-based actions

### Audit Events
- `order.created`
- `order.status_changed`
- `order.cancelled`
- `order.refunded`

---

## 🚚 MODULE 16: Shipping Providers

### Navigation Group
- **Name**: Shipping
- **Icon**: TruckIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Shipping Providers | Page | `App\Filament\Pages\ShippingProviders` | `/admin/shipping/providers` | `shipping.view` | ⏳ TODO |
| Shipments | Resource | `App\Filament\Resources\ShipmentResource` | `/admin/shipping/shipments` | `shipping.shipments.view` | ⏳ TODO |
| Shipping Logs | Resource | `App\Filament\Resources\ShippingLogResource` | `/admin/shipping/logs` | `shipping.logs.view` | ⏳ TODO |
| Tracking Events | Resource | `App\Filament\Resources\TrackingEventResource` | `/admin/shipping/tracking` | `shipping.tracking.view` | ⏳ TODO |

### Audit Events
- `shipment.created`
- `shipment.tracked`
- `shipping_provider.toggled`

---

## 💳 MODULE 17: Payments

### Navigation Group
- **Name**: Payments
- **Icon**: CreditCardIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Payment Methods | Page | `App\Filament\Pages\PaymentMethods` | `/admin/payments/methods` | `payments.view` | ⏳ TODO |
| Payment Logs | Resource | `App\Filament\Resources\PaymentLogResource` | `/admin/payments/logs` | `payments.logs.view` | ⏳ TODO |
| Webhook Logs | Resource | `App\Filament\Resources\WebhookLogResource` | `/admin/payments/webhooks` | `payments.webhooks.view` | ⏳ TODO |
| Refunds | Resource | `App\Filament\Resources\RefundResource` | `/admin/payments/refunds` | `payments.refunds.view` | ⏳ TODO |

### Audit Events
- `payment.processed`
- `payment.refunded`
- `webhook.received`
- `webhook.failed`

---

## 🔄 MODULE 18: Returns & Refunds (RMA)

### Navigation Group
- **Name**: Returns
- **Icon**: ArrowPathIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Return Reasons | Resource | `App\Filament\Resources\ReturnReasonResource` | `/admin/returns/reasons` | `returns.reasons` | ⏳ TODO |
| Return Rules | Page | `App\Filament\Pages\ReturnRules` | `/admin/returns/rules` | `returns.rules` | ⏳ TODO |
| RMAs | Resource | `App\Filament\Resources\RmaResource` | `/admin/returns` | `returns.view` | ⏳ TODO |
| Refund Approvals | Page | `App\Filament\Pages\RefundApprovals` | `/admin/returns/approvals` | `returns.approve` | ⏳ TODO |

### Audit Events
- `return.created`
- `return.approved`
- `return.rejected`
- `refund.approved`

---

## 🎁 MODULE 19: Loyalty / Wallet / Referrals

### Navigation Group
- **Name**: Loyalty
- **Icon**: GiftIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Wallet Transactions | Resource | `App\Filament\Resources\WalletTransactionResource` | `/admin/wallet/transactions` | `wallet.view` | ⏳ TODO |
| Loyalty Rules | Page | `App\Filament\Pages\LoyaltyRules` | `/admin/loyalty/rules` | `loyalty.rules` | ⏳ TODO |
| Referral Program | Page | `App\Filament\Pages\ReferralProgram` | `/admin/loyalty/referrals` | `loyalty.referrals` | ⏳ TODO |
| Fraud Rules | Page | `App\Filament\Pages\FraudRules` | `/admin/loyalty/fraud` | `loyalty.fraud` | ⏳ TODO |

### Audit Events
- `wallet.transaction.created`
- `loyalty_points.awarded`
- `referral.tracked`

---

## ⭐ MODULE 20: Reviews & Ratings

### Navigation Group
- **Name**: Reviews
- **Icon**: StarIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Reviews | Resource | `App\Filament\Resources\ReviewResource` | `/admin/reviews` | `reviews.view` | ⏳ TODO |
| Review Moderation | Page | `App\Filament\Pages\ReviewModeration` | `/admin/reviews/moderation` | `reviews.moderate` | ⏳ TODO |

### Audit Events
- `review.approved`
- `review.rejected`
- `review.deleted`

---

## 📢 MODULE 21: Notifications Hub

### Navigation Group
- **Name**: Notifications
- **Icon**: BellIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Notification Providers | Page | `App\Filament\Pages\NotificationProviders` | `/admin/notifications/providers` | `notifications.view` | ⏳ TODO |
| Notification Templates | Resource | `App\Filament\Resources\NotificationTemplateResource` | `/admin/notifications/templates` | `notifications.templates` | ⏳ TODO |
| Notification Events Toggle | Page | `App\Filament\Pages\NotificationEventsToggle` | `/admin/notifications/events` | `notifications.events` | ⏳ TODO |
| Notification Logs | Resource | `App\Filament\Resources\NotificationLogResource` | `/admin/notifications/logs` | `notifications.logs.view` | ⏳ TODO |

### Notification Channels
- Push (Firebase)
- SMS (Twilio, etc.)
- Email (SMTP, SendGrid, Mailgun)
- WhatsApp (pluggable)

### Audit Events
- `notification.sent`
- `notification.failed`
- `notification_template.updated`

---

## 📧 MODULE 22: Email Credentials & Templates

### Navigation Group
- **Name**: Email
- **Icon**: EnvelopeIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Email Providers | Page | `App\Filament\Pages\EmailProviders` | `/admin/email/providers` | `email.view` | ⏳ TODO |
| Email Credentials | Page | `App\Filament\Pages\EmailCredentials` | `/admin/email/credentials` | `email.credentials` | ⏳ TODO |
| Email Templates | Resource | `App\Filament\Resources\EmailTemplateResource` | `/admin/email/templates` | `email.templates` | ⏳ TODO |
| Email Preview Test | Page | `App\Filament\Pages\EmailPreviewTest` | `/admin/email/preview` | `email.preview` | ⏳ TODO |

### Audit Events
- `email_template.updated`
- `email.test_sent`

---

## 🚨 MODULE 23: Alerts & System Health

### Navigation Group
- **Name**: System
- **Icon**: ExclamationTriangleIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| System Health | Page | `App\Filament\Pages\SystemHealth` | `/admin/system/health` | `system.health` | ⏳ TODO |
| Alerts Center | Page | `App\Filament\Pages\AlertsCenter` | `/admin/system/alerts` | `system.alerts` | ⏳ TODO |
| Alert Rules | Page | `App\Filament\Pages\AlertRules` | `/admin/system/alert-rules` | `system.alert_rules` | ⏳ TODO |

### Health Metrics
- Queue Status
- Cron Status
- Webhook Failures
- API Health
- Database Status

### Alert Types
- Low Stock
- Payment Failures
- Shipping Failures
- High Error Rate

---

## 📈 MODULE 24: Reports & Exports

### Navigation Group
- **Name**: Reports
- **Icon**: ChartBarIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Reports Dashboard | Page | `App\Filament\Pages\ReportsDashboard` | `/admin/reports` | `reports.view` | ⏳ TODO |
| Exports | Page | `App\Filament\Pages\Exports` | `/admin/reports/exports` | `reports.export` | ⏳ TODO |

### Report Types
- Sales Report
- Customer Report
- Product Report
- Inventory Report
- Coupon Usage Report
- Returns Report

### Export Formats
- CSV
- PDF
- Excel

---

## 📝 MODULE 25: Audit Logs

### Navigation Group
- **Name**: Audit
- **Icon**: DocumentSearchIcon

### Pages & Resources
| Page Name | Type | Class | Route | Permission | Status |
|-----------|------|-------|-------|------------|--------|
| Audit Logs | Resource | `App\Filament\Resources\AuditLogResource` | `/admin/audit-logs` | `audit.view` | ⏳ TODO |
| Audit Diff Viewer | Page | `App\Filament\Pages\AuditDiffViewer` | `/admin/audit-logs/{id}/diff` | `audit.diff.view` | ⏳ TODO |

### Audit Event Types
- `credential_change`
- `price_change`
- `module_toggle`
- `setting_change`
- `order_change`

---

## Summary Statistics

**Total Modules**: 25  
**Total Resources**: ~45  
**Total Custom Pages**: ~35  
**Total Widgets**: ~8  

**Status**:
- ✅ DONE: 3 resources (Products, Orders, Settings)
- ⏳ TODO: ~77 resources/pages/widgets

