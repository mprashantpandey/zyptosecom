# Localization and Multi-Currency Guide

This document explains how to use the multi-language and multi-currency features in ZyptoseComm.

## Table of Contents

1. [Multi-Language Setup](#multi-language-setup)
2. [Translation Management](#translation-management)
3. [Multi-Currency Setup](#multi-currency-setup)
4. [Currency Conversion](#currency-conversion)
5. [API Usage](#api-usage)
6. [Frontend Integration](#frontend-integration)

---

## Multi-Language Setup

### Adding Languages

1. Navigate to **Settings → Languages** in the admin panel
2. Click **"Add Language"**
3. Fill in:
   - **Code**: ISO language code (e.g., `en`, `hi`, `ta`)
   - **Name**: Display name (e.g., "English", "Hindi")
   - **Native Name**: Native script name (e.g., "हिन्दी")
   - **Right-to-Left**: Enable for Arabic, Hebrew, etc.
   - **Active**: Enable to make it available
   - **Sort Order**: Display order

4. Click **"Save"**

### Setting Default Language

1. In **Settings → Languages**, select the default language from the dropdown
2. Click **"Save Default Language"**
3. Only one language can be default at a time
4. Default language is used as fallback for missing translations

### Language Management

- **Enable/Disable**: Toggle language availability
- **Set as Default**: Make a language the default
- **Edit**: Modify language details
- **Bulk Actions**: Activate/deactivate multiple languages

---

## Translation Management

### Adding Translations

1. Navigate to **Settings → Translations**
2. Select a language from the dropdown
3. Click **"Add Translation"**
4. Fill in:
   - **Language**: Target locale
   - **Group**: Translation group (app, auth, checkout, etc.)
   - **Key**: Translation key (e.g., `checkout.place_order`)
   - **Value**: Translated text

5. Click **"Save"**

### Editing Translations

1. In the translations table, click **"Edit"** on a row
2. Modify the translation value
3. Click **"Save"**
4. Locked translations (system keys) cannot be edited

### Import/Export

#### Export Translations

1. Click **"Export CSV"** in the header actions
2. Select the language
3. CSV file will be generated with all translations
4. File saved to `storage/app/temp/`

#### Import Translations

1. Click **"Import CSV"** in the header actions
2. Select the target language
3. Upload CSV file with columns: `Group`, `Key`, `Value`
4. Translations will be imported/updated

### Auto-fill Missing Keys

1. Click **"Auto-fill Missing Keys"**
2. Select target language
3. System will copy missing keys from default language
4. Useful for adding a new language quickly

### Translation Groups

- **app**: General application strings
- **auth**: Authentication messages
- **checkout**: Checkout process strings
- **products**: Product-related strings
- **orders**: Order management strings

---

## Multi-Currency Setup

### Adding Currencies

1. Navigate to **Settings → Currencies**
2. Click **"Add Currency"**
3. Fill in:
   - **Code**: ISO currency code (e.g., `INR`, `USD`)
   - **Name**: Currency name (e.g., "Indian Rupee")
   - **Symbol**: Currency symbol (e.g., `₹`, `$`)
   - **Symbol Position**: Before or after amount
   - **Decimals**: Number of decimal places
   - **Thousand Separator**: Character for thousands (default: `,`)
   - **Decimal Separator**: Character for decimals (default: `.`)
   - **Active**: Enable to make it available

4. Click **"Save"**

### Setting Default Currency

1. In **Settings → Currencies**, select the default currency
2. Enable **"Allow customers to choose currency"** if needed
3. Enable **"Auto convert prices using exchange rates"** for conversion
4. Click **"Save Settings"**

### Currency Management

- **Enable/Disable**: Toggle currency availability
- **Set as Default**: Make a currency the default
- **Edit**: Modify currency formatting
- **Cannot disable default**: Must set another default first

---

## Currency Conversion

### Exchange Rates

When **"Auto convert prices"** is enabled:

1. Exchange rates are stored in the `exchange_rates` table
2. Rates are from `base_currency` (default) to `quote_currency`
3. Rates can be updated manually or via API (future feature)

### Pricing Behavior

- **Product prices** are stored in the default currency
- **Display prices** are converted using exchange rates
- **Orders** store:
  - `base_currency`: Default currency
  - `display_currency`: Selected currency
  - `exchange_rate_used`: Rate at time of order
  - Totals in both currencies

### Using CurrencyService

```php
use App\Core\Services\CurrencyService;

$currencyService = app(CurrencyService::class);

// Format amount
$formatted = $currencyService->format(1000.50, 'INR');
// Returns: "₹1,000.50"

// Convert amount
$converted = $currencyService->convert(1000, 'INR', 'USD');
// Returns: Converted amount based on exchange rate

// Get rate
$rate = $currencyService->getRate('INR', 'USD');
// Returns: Exchange rate
```

---

## API Usage

### Get Translations

**Endpoint**: `GET /api/v1/translations`

**Query Parameters**:
- `locale` (required): Language code (e.g., `hi`)
- `group` (optional): Translation group (default: `app`)

**Response**:
```json
{
  "locale": "hi",
  "fallback": "en",
  "group": "app",
  "strings": {
    "checkout.place_order": "ऑर्डर करें",
    "products.add_to_cart": "कार्ट में जोड़ें"
  }
}
```

**Example**:
```bash
curl "https://yourdomain.com/api/v1/translations?locale=hi&group=checkout"
```

### Get App Config (includes localization & currency)

**Endpoint**: `GET /api/v1/config`

**Response** includes:
```json
{
  "localization": {
    "supported_locales": [
      {
        "code": "en",
        "name": "English",
        "native_name": "English",
        "is_rtl": false
      },
      {
        "code": "hi",
        "name": "Hindi",
        "native_name": "हिन्दी",
        "is_rtl": false
      }
    ],
    "default_locale": "en",
    "locale_fallback": "en"
  },
  "currency": {
    "default_currency": {
      "code": "INR",
      "symbol": "₹",
      "decimals": 2,
      "symbol_position": "before"
    },
    "supported_currencies": [
      {
        "code": "INR",
        "name": "Indian Rupee",
        "symbol": "₹",
        "decimals": 2
      },
      {
        "code": "USD",
        "name": "US Dollar",
        "symbol": "$",
        "decimals": 2
      }
    ],
    "currency_selection_enabled": true,
    "currency_auto_convert": true
  }
}
```

---

## Frontend Integration

### Web (Next.js/React)

#### 1. Load Supported Locales

```typescript
const response = await fetch('/api/v1/config');
const config = await response.json();
const locales = config.localization.supported_locales;
```

#### 2. Fetch Translations

```typescript
const locale = 'hi'; // User selected or default
const response = await fetch(`/api/v1/translations?locale=${locale}`);
const data = await response.json();
const translations = data.strings;

// Use translations
const placeOrderText = translations['checkout.place_order'] || 'Place Order';
```

#### 3. Handle Currency

```typescript
const currency = config.currency.default_currency;
const selectedCurrency = userSelectedCurrency || currency.code;

// Format price
const formattedPrice = formatPrice(price, selectedCurrency, config.currency.supported_currencies);
```

### Flutter App

#### 1. Load Configuration

```dart
final response = await http.get(Uri.parse('$baseUrl/api/v1/config'));
final config = jsonDecode(response.body);

final locales = config['localization']['supported_locales'];
final defaultLocale = config['localization']['default_locale'];
```

#### 2. Fetch Translations

```dart
final locale = 'hi'; // User selected or default
final response = await http.get(
  Uri.parse('$baseUrl/api/v1/translations?locale=$locale')
);
final data = jsonDecode(response.body);
final translations = data['strings'] as Map<String, dynamic>;

// Use translations
final placeOrderText = translations['checkout.place_order'] ?? 'Place Order';
```

#### 3. Handle Currency

```dart
final currency = config['currency']['default_currency'];
final selectedCurrency = userSelectedCurrency ?? currency['code'];

// Format price
final formattedPrice = formatPrice(price, selectedCurrency);
```

### Caching

- Translations are cached per locale/group for 1 hour
- App config is cached for 1 hour
- Cache is cleared automatically when translations/currencies are updated

---

## Best Practices

1. **Always provide fallback**: Use default language values if translation is missing
2. **Lock system keys**: Mark critical translations as locked to prevent accidental changes
3. **Group translations**: Use logical groups (app, auth, checkout) for organization
4. **Test currency conversion**: Verify exchange rates are accurate before enabling auto-convert
5. **Store order currency**: Always store both base and display currency in orders
6. **Cache translations**: Use client-side caching to reduce API calls
7. **Validate locale**: Check if locale is active before using it

---

## Troubleshooting

### Translations not showing

1. Check if language is active in **Settings → Languages**
2. Verify translations exist for the locale
3. Clear cache: `php artisan cache:clear`
4. Check API response for errors

### Currency conversion not working

1. Verify **"Auto convert prices"** is enabled
2. Check exchange rates exist in database
3. Ensure default currency is set
4. Verify `CurrencyService` is being used correctly

### Cache issues

1. Clear app config cache: `php artisan cache:clear`
2. Clear translation cache: `php artisan cache:forget translations:v1:*`
3. Restart application if needed

---

## Permissions

Required permissions for admin access:

- `settings.languages.view` - View languages
- `settings.languages.edit` - Edit languages
- `settings.translations.view` - View translations
- `settings.translations.edit` - Edit translations
- `settings.translations.import` - Import translations
- `settings.translations.export` - Export translations
- `settings.currencies.view` - View currencies
- `settings.currencies.edit` - Edit currencies
- `settings.exchange_rates.edit` - Edit exchange rates

---

## Support

For issues or questions, refer to:
- Admin Panel: Settings → Languages / Translations / Currencies
- API Documentation: `/api/v1/config` and `/api/v1/translations`
- Code: `app/Core/Services/CurrencyService.php`, `app/Models/Language.php`, `app/Models/Translation.php`

