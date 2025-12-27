# RCP Content Filter Utility - Complete Documentation

**Version**: 1.0.49
**Last Updated**: December 26, 2025
**Author**: samybaxy

---

## Table of Contents

1. [Plugin Overview](#plugin-overview)
2. [Installation](#installation)
3. [Core Features](#core-features)
4. [Loqate Address Capture Integration](#loqate-address-capture-integration)
5. [Transliteration](#transliteration)
6. [Configuration](#configuration)
7. [Testing & Troubleshooting](#testing--troubleshooting)
8. [Advanced Customization](#advanced-customization)
9. [Changelog](#changelog)

---

## Plugin Overview

RCP Content Filter Utility is a comprehensive WordPress plugin that extends Restrict Content Pro, WooCommerce, AffiliateWP, and LearnPress functionality with 11 major features:

### Key Capabilities

✓ **Content Filtering** - Automatically filters restricted content from WordPress queries
✓ **Loqate Address Capture** - Real-time address autocomplete for 245+ countries with transliteration
✓ **Email & Phone Validation** - Validate customer contact information
✓ **Partner+ Auto-Affiliate** - Automatic affiliate account creation on purchase
✓ **LearnPress Integration** - Fixes Elementor template loading in course context
✓ **Checkout Validation** - ASCII-only character enforcement for international shipping
✓ **DNA Kit Tracking** - Captures serial IDs from shipment tracking
✓ **AffiliateWP Enhancements** - Form streamlining and referral safety
✓ **Password Reset Fix** - WP Engine LinkShield bypass
✓ **Stripe Migration** - Bulk customer ID updates
✓ **Shipping Address Control** - Smart checkbox management

---

## Installation

### Requirements

- WordPress 5.0+
- PHP 8.2+
- WooCommerce 7.0+ (for checkout features)
- Restrict Content Pro (for content filtering)

### Optional Dependencies

- **AffiliateWP** 2.0+ (for auto-affiliate, form enhancement, referral safety)
- **LearnPress** 4.0+ (for course context fix)
- **Elementor** (for LearnPress template fix)
- **Advanced Shipment Tracking** (for DNA kit capture)

### Setup Steps

1. **Extract Plugin** to `/wp-content/plugins/rcp-content-filter-utility/`
2. **Activate Plugin** via WordPress Admin → Plugins
3. **Configure Settings** at Restrict Content Pro → Content Filter

---

## Core Features

### 1. Content Filtering

Automatically filters restricted content from archives and post grids based on RCP membership levels.

**Supported Post Types:**
- Posts, Pages
- Custom post types
- WooCommerce products
- LearnPress courses/lessons

**Configuration:** Restrict Content Pro → Content Filter → Content Filter Settings

---

### 2. Partner+ Auto-Affiliate Activation

**Status**: ✅ ACTIVE | **Configuration**: ✅ ZERO CONFIGURATION REQUIRED

Automatically creates affiliate accounts when customers purchase the Partner+ product.

**Features:**
- ✅ Auto-creates affiliate accounts on checkout completion
- ✅ Sets affiliate status to "active" immediately
- ✅ Assigns parent affiliate relationships automatically
- ✅ Changes user role from "Partner Plus Pending" to "Partner Plus"
- ✅ Cart automatically cleared after purchase
- ✅ Customer sees WooCommerce thank you page

**How It Works:**
1. Customer clicks affiliate referral link (optional)
2. Purchases Partner+ product
3. Order reaches "Processing" or "Completed" status
4. Plugin automatically creates affiliate account
5. Assigns parent affiliate (if referral link was used)
6. Changes user role to "Partner Plus"
7. Affiliate can immediately generate referral links

**Product Requirements:**
- Product slug must be `partner-plus` (or configure via filter)
- Product status must be "Publish"

---

### 3. AffiliateWP Registration Form Enhancement

Streamlines the affiliate registration form by auto-populating and hiding unnecessary fields.

**Hidden When Autofilled:**
- Your Name (autopopulated from user display name)
- Username (autopopulated from WordPress username)
- Account Email (autopopulated from user email)

**Always Visible:**
- Payment Email (required for affiliate payments)

**Always Hidden:**
- Website URL (optional field)
- How will you promote us? (not required)

---

### 4. LearnPress + Thim Elementor Fix

Fixes Elementor templates not loading on LearnPress course context URLs.

**Enable:** Restrict Content Pro → Content Filter → Enable LearnPress Course Context Fix

---

## Loqate Address Capture Integration

### What is Loqate?

Loqate provides real-time address capture and validation:
- Autocompletes addresses as users type
- Validates addresses against global database
- Supports 245+ countries
- Provides email and phone validation
- Uses geolocation for smart suggestions
- **Transliterates non-Latin scripts** to romanized output

### Quick Start (5 Minutes)

#### Step 1: Get API Key

1. Visit https://dashboard.loqate.com/
2. Create account (free tier: 1,000 requests/month)
3. Generate API key
4. Copy the key

#### Step 2: Configure API Key

**Option A: wp-config.php (Recommended)**
```php
// Add before "That's all, stop editing!"
define( 'LOQATE_API_KEY', 'AA11-BB22-CC33-DD44' );
```

**Option B: WordPress Admin**
1. Go to: Restrict Content Pro → Content Filter → Loqate Integration
2. Paste API key
3. Click "Save Loqate Settings"

**Option C: Code Filter**
```php
add_filter( 'rcf_loqate_api_key', function() {
    return 'your-api-key-here';
} );
```

#### Step 3: Test

1. Visit WooCommerce checkout page
2. Start typing in "Billing Address Line 1" field
3. See address suggestions appear ✓

### Features Implemented

#### Address Capture (v1.0.49 Optimized)
✓ Real-time autocomplete for billing address
✓ Real-time autocomplete for shipping address
✓ **Transliteration** - Converts kanji, cyrillic, arabic, etc. to romanized output
✓ **SubBuilding/Apt/Suite Extraction** - Automatically extracts apartment, suite, unit numbers to Address Line 2
✓ **Performance Optimizations** - Cached DOM elements, batched DB queries, lazy shipping initialization
✓ **Extended Country Support** - 60+ countries with fallback code mapping
✓ Auto-population of address lines 2, city, state, postcode
✓ Automatic country field selection
✓ Manual address entry fallback
✓ Geolocation-based suggestions

#### Validation Services
✓ Email validation (optional)
✓ Phone validation (optional)
✓ Real-time validation feedback
✓ Visual indicators (green/red/loading)

#### Configuration
✓ Multiple API key sources (constant, option, filter)
✓ Country restrictions
✓ Geolocation options (enable, radius, max items)
✓ Validation service toggles
✓ Custom field mapping via filters

---

## Transliteration

### Overview

The Loqate integration includes automatic transliteration for international addresses. This converts addresses written in non-Latin scripts (kanji, cyrillic, arabic, etc.) into romanized/Latin output that's compatible with WooCommerce and international shipping.

### How It Works

**Core Configuration:**
```javascript
languagePreference: 'eng'  // Force romanized/Latin output
options: {
    OutputScript: 'Latn'    // Force Latin/romanized output
}
```

**Critical Design:**
- The `culture` parameter is **NOT set** for transliteration countries
- Loqate auto-detects input script (kanji, cyrillic, arabic, thai, etc.)
- Returns results in **LATIN/ROMANIZED** format automatically
- Works for: Address Line 1, Line 2, City, State/Province, all components

### Supported Transliteration Countries

| Country | Script | Example Input | Example Output |
|---------|--------|---------------|----------------|
| 🇯🇵 Japan | Kanji/Kana | 東京都港区 | Tokyo, Minato-Ku |
| 🇨🇳 China | Chinese | 北京市朝阳区 | Beijing, Chaoyang |
| 🇹🇼 Taiwan | Chinese | 台北市 | Taipei |
| 🇰🇷 Korea | Hangul | 서울특별시 | Seoul |
| 🇷🇺 Russia | Cyrillic | Москва | Moskva |
| 🇬🇷 Greece | Greek | Αθήνα | Athina |
| 🇮🇱 Israel | Hebrew | תל אביב | Tel Aviv |
| 🇸🇦 Saudi Arabia | Arabic | الرياض | Riyadh |
| 🇹🇭 Thailand | Thai | กรุงเทพมหานคร | Bangkok |

### UI Localization (Non-Transliteration)

For countries already using Latin script, culture codes provide UI localization only:
- 🇫🇷 France, 🇩🇪 Germany, 🇪🇸 Spain, 🇮🇹 Italy
- 🇵🇹 Portugal, 🇳🇱 Netherlands, 🇵🇱 Poland, 🇧🇷 Brazil

### Testing Transliteration

1. Set country to **Japan**
2. Type in address field: `東京都港区` (Tokyo, Minato in kanji)
3. Select suggestion from dropdown
4. **Expected Result:**
   - Line 1: `1-7-1 Konan` (romanized)
   - City: `Minato-Ku` (romanized)
   - State: `Tokyo` (romanized, NOT 東京都)
   - Postal: `108-0075`

---

## Configuration

### Loqate Configuration Options

#### Enable Email Validation
```php
add_filter( 'rcf_loqate_validate_email', '__return_true' );
```

#### Enable Phone Validation
```php
add_filter( 'rcf_loqate_validate_phone', '__return_true' );
```

#### Restrict to Specific Countries
```php
add_filter( 'rcf_loqate_allowed_countries', function() {
    return 'USA,CAN,GBR,AUS'; // ISO 3166-1 alpha-3 codes
} );
```

#### Configure Geolocation
```php
add_filter( 'rcf_loqate_geolocation_options', function( $options ) {
    return array(
        'enabled'    => true,
        'radius'     => 100,  // kilometers
        'max_items'  => 5,    // max suggestions
    );
} );
```

#### Disable Manual Entry
```php
add_filter( 'rcf_loqate_allow_manual_entry', '__return_false' );
```

#### Custom Field Mapping
```php
add_filter( 'rcf_loqate_billing_field_mapping', function( $fields ) {
    $fields['populate'][] = 'billing_company'; // Add company field
    return $fields;
} );
```

### Available Filters

| Filter | Purpose |
|--------|---------|
| `rcf_loqate_api_key` | Override/provide Loqate API key |
| `rcf_loqate_allowed_countries` | Restrict to specific countries |
| `rcf_loqate_geolocation_options` | Configure geolocation |
| `rcf_loqate_allow_manual_entry` | Allow manual address entry |
| `rcf_loqate_validate_email` | Enable email validation |
| `rcf_loqate_validate_phone` | Enable phone validation |
| `rcf_loqate_billing_field_mapping` | Customize billing fields |
| `rcf_loqate_shipping_field_mapping` | Customize shipping fields |
| `bl_partner_plus_product_id` | Override Partner+ product ID |

---

## Testing & Troubleshooting

### Testing Loqate Integration

#### Prerequisites
1. API key configured
2. On WooCommerce checkout page
3. Browser cache cleared

#### Step 1: Clear All Caches

**CRITICAL:** Clear caches before testing!

**Hard Refresh:**
- **Windows/Linux:** `Ctrl+Shift+R` (3 times)
- **Mac:** `Cmd+Shift+R` (3 times)

**Verify Version:**
1. Right-click on checkout page → View Page Source
2. Search for: `loqate-address-capture.js`
3. Must show: `?ver=1.0.49` ✅

#### Step 2: Check Console for Initialization

**Open Browser Console:**
1. Press `F12` to open Developer Tools
2. Click **Console** tab
3. Reload the checkout page

**Expected Console Output (WITH API Key):**
```javascript
// Minimal logs - only errors/warnings appear
```

**WITHOUT API Key:**
```javascript
[Loqate] API key not configured - check wp-config.php or admin settings
```

#### Step 3: Test Address Search

**Test Case 1: USA Address**
1. Click in "Billing Address Line 1" field
2. Type: `1600 Pennsylvania`
3. **Expected:** Dropdown with address suggestions
4. Click suggestion
5. **Expected:** All fields auto-fill (city, state, ZIP, country)

**Test Case 2: Japan (Transliteration)**
1. Change country to **Japan**
2. Type: `東京都港区` (kanji)
3. **Expected:** Romanized suggestions (Tokyo, Minato-Ku)
4. Click suggestion
5. **Expected:** All fields auto-fill in **romanized/Latin** script

**Test Case 3: UK Address**
1. Change country to United Kingdom
2. Type: `10 Downing`
3. **Expected:** See "10 Downing Street, London"
4. Click suggestion
5. **Expected:** All fields auto-fill

**Common Test Addresses:**

**United States:**
```
1600 Pennsylvania Avenue NW, Washington, DC 20500
350 5th Avenue, New York, NY 10118
1 Apple Park Way, Cupertino, CA 95014
```

**United Kingdom:**
```
10 Downing Street, London SW1A 2AA
221B Baker Street, London NW1 6XE
```

**Canada:**
```
24 Sussex Drive, Ottawa, ON K1M 1M4
```

**Australia:**
```
1 Macquarie Street, Sydney NSW 2000
```

**Japan (Transliteration):**
```
東京都港区 (should return romanized: Tokyo, Minato-Ku)
```

### Troubleshooting

#### Error: "Address search error"

**Step 1: Check Console for Detailed Error**

After hard refresh, console will show:
```javascript
[Loqate] Error - Type: billing, Status: 401, Message: Invalid API key
```

**Step 2: Common Error Codes & Fixes**

##### Error Code: 401 or 403 (Unauthorized/Forbidden)

**Causes:**
- Invalid API key
- API key expired
- API key not activated
- Wrong key copied (extra spaces, missing characters)

**Fix:**
1. Visit https://dashboard.loqate.com/
2. Log in and go to API Keys section
3. Create new key or verify existing key is active
4. Copy entire key (no spaces)
5. Update in wp-config.php:
   ```php
   define( 'LOQATE_API_KEY', 'AA11-BB22-CC33-DD44' );
   ```
6. Hard refresh and test again

##### Error Code: 429 (Too Many Requests)

**Cause:**
- Free tier limit exceeded (1,000 requests/month)
- Too many searches in short time

**Fix:**
1. Visit https://dashboard.loqate.com/
2. Check usage in Dashboard
3. Options:
   - Wait until next month (quota resets)
   - Upgrade to paid plan
   - Create new free account (temporary)

##### Error Code: 0 or Network Error

**Causes:**
- Loqate API endpoint blocked
- CORS issue
- Firewall blocking api.addressy.com
- No internet connection
- Ad blocker blocking request

**Fix:**
1. Check internet connection
2. Disable ad blockers temporarily
3. Check browser DevTools → Network tab
4. Try different browser or incognito mode

#### State "Tokyo" not found in dropdown options

**Symptom:**
```
[Loqate] State "Tokyo" not found in dropdown options
```

**Diagnosis:**
WooCommerce state list may not have romanized state names for the country

**Fix:**
This is expected for some countries. The state field will remain empty and can be filled manually if needed.

### Staging/Production Debugging

If Loqate works on dev but not on staging/production:

#### Step 1: Enable Debug Mode

Add to wp-config.php:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

#### Step 2: Check Debug Log

```bash
tail -50 wp-content/debug.log | grep Loqate
```

Look for:
- `[Loqate] SDK not loaded`
- `[Loqate] API key not configured`
- `[Loqate] Failed to initialize`

#### Step 3: Compare Environments

**Check:**
- API key present in both environments?
- WooCommerce active in both?
- Same WooCommerce checkout page ID?
- Caching differences?

---

## Advanced Customization

### Example: Conditional Country Restrictions by Product

```php
add_filter( 'rcf_loqate_allowed_countries', function( $countries ) {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return $countries;
    }

    // Check for domestic-only products
    foreach ( WC()->cart->get_cart() as $item ) {
        $product = $item['data'];
        if ( has_term( 'domestic-only', 'product_cat', $product->get_id() ) ) {
            return 'USA'; // Only USA for domestic products
        }
    }

    return $countries;
} );
```

### Example: Custom Validation Message

```php
add_action( 'wp_footer', function() {
    if ( ! is_checkout() ) return;
    ?>
    <script>
    document.addEventListener( 'DOMContentLoaded', function() {
        if ( ! window.RCFLoqateAddressCapture ) return;

        var control = window.RCFLoqateAddressCapture.billingControl;
        if ( ! control ) return;

        control.listen( 'populate', function( item ) {
            console.log( 'Address confirmed:', item.text );
        } );
    } );
    </script>
    <?php
} );
```

### Example: Different Settings by Environment

```php
add_filter( 'rcf_loqate_geolocation_options', function( $options ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // Development: more results
        $options['max_items'] = 10;
    } else {
        // Production: fewer results
        $options['max_items'] = 3;
    }
    return $options;
} );
```

---

## Changelog

### Version 1.0.49 (December 26, 2025)
- ✅ **OPTIMIZED**: Removed verbose console logging (~200 lines removed)
- ✅ **IMPROVED**: Lean and efficient JavaScript (1,028 lines from ~1,200+)
- ✅ **IMPROVED**: Faster execution with reduced memory footprint
- ✅ **IMPROVED**: Production-ready with essential error logging only
- ✅ **MAINTAINED**: Full transliteration functionality intact

### Version 1.0.48 (December 26, 2025)
- ✅ **ADDED**: Comprehensive transliteration debug logging
- ✅ **ADDED**: 6-phase console tracking for transliteration verification
- ✅ **ADDED**: Detailed field-by-field transliteration confirmation
- ✅ **IMPROVED**: Enhanced debugging for international addresses

### Version 1.0.38 (December 11, 2025)
- ✅ **ADDED**: SubBuilding/Apt/Suite extraction for Address Line 2
  - Prioritizes `SubBuilding` field (contains "Apt 123", "Unit 4", "Suite 100")
  - Falls back to `BuildingName` for named buildings
  - Uses `Line2` as final fallback (pre-formatted by country)
- ✅ **IMPROVED**: Performance optimizations
  - Cached DOM element references (reduces jQuery selector overhead)
  - Batched DB option queries (single query for all Loqate settings)
  - Lazy initialization for shipping fields (only when visible)
  - `requestAnimationFrame` for batched DOM updates
- ✅ **ADDED**: Extended country code map (60+ countries)
  - North America, Europe, Asia Pacific, South America, Middle East, Africa
  - Case-insensitive matching with common aliases ("UK", "USA", etc.)
- ✅ **IMPROVED**: Better country code extraction from Loqate responses

### Version 1.0.18 (November 27, 2025)
- ✅ **FIXED**: Dropdown positioning issue - added proper CSS for `.pca.pcalist` elements
- ✅ **IMPROVED**: Dropdown now uses `position: absolute` and `z-index: 99999`
- ✅ **ADDED**: Comprehensive styling for all Loqate SDK v4 element classes
- ✅ **IMPROVED**: Better dropdown visibility with proper borders, shadows, and hover effects

### Version 1.0.17 (November 26, 2025)
- ✅ **ADDED**: Enhanced error logging with detailed error information
- ✅ **IMPROVED**: Console shows specific error codes (401, 429, etc.)
- ✅ **ADDED**: API key validation status messages
- ✅ **ADDED**: Quota exceeded detection and reporting

### Version 1.0.16 (November 26, 2025)
- ✅ **FIXED**: `pca.fieldMode.SEARCH` constructor error
- ✅ **IMPROVED**: Proper initialization of Loqate SDK controls
- ✅ **ADDED**: Field monitoring with search event detection
- ✅ **ADDED**: Testing guides and troubleshooting documentation

### Version 1.0.15 (November 26, 2025)
- ✅ Complete Loqate Address Capture integration
- ✅ Email validation support
- ✅ Phone validation support
- ✅ Geolocation-based suggestions
- ✅ Admin settings page for Loqate configuration
- ✅ 8+ configuration filters
- ✅ Comprehensive documentation

### Version 1.0.13 (November 22, 2025)
- ❌ **REMOVED**: JetForm loading overlay feature
- ❌ **REMOVED**: Password-protected access overlay UI
- ✅ **IMPROVED**: Cleaner codebase with focused features

### Version 1.0.0 (October 24, 2025)
- ✅ Content filtering implemented
- ✅ Auto-affiliate activation with zero configuration
- ✅ AffiliateWP registration form enhancement
- ✅ LearnPress course context fix
- ✅ Debug logging and order notes tracking

---

## Plugin Structure

```
rcp-content-filter-utility/
├── rcp-content-filter-utility.php          # Main plugin file
├── includes/
│   ├── class-loqate-address-capture.php    # Loqate integration
│   ├── class-loqate-admin-settings.php     # Admin settings
│   └── class-learnpress-elementor-fix.php  # LearnPress fix
├── admin/
│   ├── class-admin.php                     # Settings page
│   └── js/
│       └── affiliatewp-registration.js     # Form enhancement
├── assets/
│   └── js/
│       ├── loqate-address-capture.js       # Frontend JavaScript (1,028 lines)
│       ├── checkout-ascii-validation.js    # Checkout validation
│       └── learnpress-next-button-control.js
├── tests/                                  # Unit tests
└── DOCUMENTATION.md                        # This file
```

---

## Support Resources

### Documentation
- **This File**: Complete reference guide
- **Admin Settings**: Restrict Content Pro → Content Filter

### External Resources
- **Loqate Dashboard**: https://dashboard.loqate.com/
- **Loqate Documentation**: https://docs.loqate.com/
- **Loqate API Reference**: https://docs.loqate.com/api-reference
- **Loqate Support**: https://dashboard.loqate.com/support

---

## Requirements

- **WordPress**: 5.0+
- **PHP**: 8.2+
- **WooCommerce**: 7.0+ (for checkout features)
- **Restrict Content Pro**: Active and configured
- **AffiliateWP**: 2.0+ (for auto-affiliate feature)
- **LearnPress**: 4.0+ (for course context fix)

---

## Summary

### What This Plugin Does

✅ **Automatic Content Filtering** - Hides restricted posts based on membership
✅ **Real-Time Address Capture** - 245+ countries with autocomplete & transliteration
✅ **Email & Phone Validation** - Verify customer contact information
✅ **Affiliate Automation** - Auto-create affiliates on Partner+ purchase
✅ **LearnPress Integration** - Fix Elementor templates in course context
✅ **Checkout Validation** - Enforce ASCII characters for shipping compatibility
✅ **DNA Kit Tracking** - Capture serial IDs from shipment tracking

### Result

**Seamless, fully automatic functionality with comprehensive address validation, transliteration, and affiliate management!**

---

**End of Documentation**
**Version**: 1.0.49
**Last Updated**: December 26, 2025
