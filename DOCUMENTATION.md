# RCP Content Filter Utility - Complete Documentation

**Version**: 1.0.38
**Last Updated**: December 11, 2025
**Author**: samybaxy

---

## Table of Contents

1. [Plugin Overview](#plugin-overview)
2. [Installation](#installation)
3. [Core Features](#core-features)
4. [Loqate Address Capture Integration](#loqate-address-capture-integration)
5. [Configuration](#configuration)
6. [Testing & Troubleshooting](#testing--troubleshooting)
7. [Advanced Customization](#advanced-customization)
8. [Changelog](#changelog)

---

## Plugin Overview

RCP Content Filter Utility is a comprehensive WordPress plugin that extends Restrict Content Pro, WooCommerce, AffiliateWP, and LearnPress functionality with several key features:

### Key Capabilities

✓ **Content Filtering** - Automatically filters restricted content from WordPress queries
✓ **Loqate Address Capture** - Real-time address autocomplete for 245+ countries
✓ **Email & Phone Validation** - Validate customer contact information
✓ **Partner+ Auto-Affiliate** - Automatic affiliate account creation on purchase
✓ **LearnPress Integration** - Fixes Elementor template loading in course context
✓ **Checkout Validation** - ASCII-only character enforcement for international shipping
✓ **DNA Kit Tracking** - Captures serial IDs from shipment tracking

---

## Installation

### Requirements

- WordPress 5.0+
- PHP 8.2+
- WooCommerce 7.0+ (for checkout features)
- Restrict Content Pro (for content filtering)

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

#### Address Capture (v1.0.38 Enhanced)
✓ Real-time autocomplete for billing address
✓ Real-time autocomplete for shipping address
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
3. Must show: `?ver=1.0.18` ✅

#### Step 2: Check Console for Initialization

**Open Browser Console:**
1. Press `F12` to open Developer Tools
2. Click **Console** tab
3. Reload the checkout page

**Expected Console Output (WITH API Key):**
```javascript
[Loqate] Billing address capture initialized successfully
[Loqate] Shipping address capture initialized successfully
```

**WITHOUT API Key:**
```javascript
[Loqate] API key not configured - add LOQATE_API_KEY constant or use admin settings
```

#### Step 3: Test Address Search

**Test Case 1: USA Address**
1. Click in "Billing Address Line 1" field
2. Type: `1600 Pennsylvania`
3. **Expected:** Dropdown with address suggestions
4. Click suggestion
5. **Expected:** All fields auto-fill (city, state, ZIP, country)

**Test Case 2: UK Address**
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

### Troubleshooting

#### Error: "Address search error"

This error appears when the Loqate API call fails. Version 1.0.18 includes detailed error logging.

**Step 1: Check Console for Detailed Error**

After hard refresh, console will show:
```javascript
[Loqate] Billing address error: [error object]
[Loqate] Error details: {
    message: "...",
    statusCode: 401,  // or other code
    ...
}
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

#### Dropdown Not Visible

**Problem:** Console shows searches working, but dropdown doesn't appear on screen

**Solution (Fixed in v1.0.18):**
The CSS has been updated to properly position the dropdown with:
- `position: absolute !important`
- `z-index: 99999 !important`
- Proper border, shadow, and background styling

If still not visible after updating to 1.0.18:
1. Hard refresh: `Ctrl+Shift+R` (3 times)
2. Verify version in page source shows `?ver=1.0.18`
3. Clear full browser cache (not just hard refresh)
4. Check for CSS conflicts from other plugins

#### No Dropdown Appears

**Check API Key:**
```javascript
// In browser console:
rcfLoqateConfig.apiKey
// Expected: "AA11****" (masked key)
```

**Check Initialization:**
```javascript
// In console:
window.RCFLoqateAddressCapture
// Expected: {config: {...}, init: function, ...}
```

**Check SDK Loaded:**
```javascript
// In console:
typeof pca
// Expected: "object"
```

#### Fields Don't Auto-Fill

**Check Field IDs:**
```javascript
// In console:
document.getElementById('billing_address_1')
document.getElementById('billing_city')
document.getElementById('billing_state')
document.getElementById('billing_postcode')
document.getElementById('billing_country')

// All should return: <input ...> elements
// If any return null, field ID is wrong
```

#### Country Doesn't Auto-Select

**Check Country Field:**
```javascript
// In console:
document.getElementById('billing_country').tagName
// Expected: "SELECT" (dropdown)

document.getElementById('billing_country').options.length
// Expected: > 0 (has countries)
```

### Testing Partner+ Auto-Affiliate

#### Step 1: Verify Product Exists

**Via WP-CLI:**
```bash
wp post list --post_type=product --name=partner-plus --format=table
```

**Expected output:**
```
ID    post_title              post_name     post_status
24092 The Partner+ Program    partner-plus  publish
```

#### Step 2: Enable Debug Logging

Add to `wp-config.php` before `/* That's all, stop editing! */`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );
```

#### Step 3: Place Test Order

1. Logout admin → Login as test customer
2. (Optional) Click affiliate referral link first
3. Add Partner+ product to cart
4. Complete checkout
5. Note order number

#### Step 4: Check Debug Log

```bash
grep "BL Auto Affiliate" wp-content/debug.log | tail -10
```

**Expected Success:**
```
[BL Auto Affiliate] Order #12345: Created affiliate #67 for user #123
[BL Auto Affiliate] Order #12345: Connected affiliate #67 to parent #45
[BL Auto Affiliate] Order #12345: Changed role from Partner Plus Pending to Partner Plus
```

#### Step 5: Verify in Admin

- **Check Order Notes**: WooCommerce → Orders → [Your order] → Notes
- **Check Affiliate**: AffiliateWP → Affiliates → Search customer email

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
│       ├── loqate-address-capture.js       # Frontend JavaScript
│       ├── checkout-ascii-validation.js    # Checkout validation
│       └── learnpress-next-button-control.js
├── docs/                                   # Detailed documentation
└── DOCUMENTATION.md                        # This file
```

---

## Support Resources

### Documentation
- **This File**: Complete reference guide
- **docs/** folder: Detailed technical documentation
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
✅ **Real-Time Address Capture** - 245+ countries with autocomplete
✅ **Email & Phone Validation** - Verify customer contact information
✅ **Affiliate Automation** - Auto-create affiliates on Partner+ purchase
✅ **LearnPress Integration** - Fix Elementor templates in course context
✅ **Checkout Validation** - Enforce ASCII characters for shipping compatibility
✅ **DNA Kit Tracking** - Capture serial IDs from shipment tracking

### Result

**Seamless, fully automatic functionality with comprehensive address validation and affiliate management!**

---

**End of Documentation**
**Version**: 1.0.18
**Last Updated**: November 27, 2025
