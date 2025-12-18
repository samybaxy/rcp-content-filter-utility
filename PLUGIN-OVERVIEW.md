# RCP Content Filter Utility - Complete Plugin Overview

**Version**: 1.0.39
**Author**: samybaxy
**Last Updated**: December 18, 2025
**PHP Required**: 8.2+
**WordPress Required**: 5.0+

---

## Executive Summary

RCP Content Filter Utility is a comprehensive WordPress plugin that extends functionality for Restrict Content Pro, WooCommerce, AffiliateWP, and LearnPress. It provides 11 major feature sets ranging from content filtering to address validation, affiliate automation, and e-commerce enhancements.

---

## Core Features Overview

### 1. Content Filtering (Primary Feature)
**File**: `rcp-content-filter-utility.php` (lines 44-350)

**Purpose**: Automatically filters restricted content from WordPress queries based on Restrict Content Pro membership levels.

**How It Works**:
- Hooks into `pre_get_posts` to fetch extra posts (3x the requested amount)
- Hooks into `the_posts` filter to remove restricted content
- Adjusts `found_posts` count for accurate pagination
- Respects RCP's `rcp_user_can_access()` and `rcp_is_restricted_content()` functions

**Key Methods**:
- `adjust_query_for_restrictions()` - Modifies WP_Query to fetch more posts
- `filter_posts()` - Removes posts user cannot access
- `should_filter_post_type()` - Checks if post type should be filtered

**Configuration**:
- Admin settings at: Restrict Content Pro → Content Filter
- Select post types to filter (posts, pages, custom post types)
- Set filter priority (default: 10)

---

### 2. Loqate Address Capture Integration
**Files**:
- PHP: `includes/class-loqate-address-capture.php`
- JS: `assets/js/loqate-address-capture.js`

**Purpose**: Real-time address autocomplete and validation for WooCommerce checkout using Loqate SDK.

**Key Features**:
- ✅ Autocomplete for 245+ countries
- ✅ SubBuilding/Apartment/Suite extraction to Address Line 2
- ✅ Performance optimizations (cached DOM, batched DB queries, lazy loading)
- ✅ Country context handling for accurate results
- ✅ Email and phone validation (optional)
- ✅ Debounced search (150ms delay to reduce API calls)

**Technical Details**:
- **API Key Sources**: `LOQATE_API_KEY` constant, `rcf_loqate_api_key` option, or filter
- **Field Mapping**: Maps WooCommerce fields to Loqate SDK field modes
- **Address Line 2 Priority**:
  1. `SubBuilding` (Apt, Suite, Unit, Flat)
  2. `BuildingName` (named buildings)
  3. `Line2` fallback (pre-formatted by country)
- **Country Code Map**: 60+ countries with aliases (USA, UK, etc.)

**Configuration**:
- Admin settings at: Restrict Content Pro → Content Filter → Loqate Integration
- Enable geolocation, email/phone validation, country restrictions
- API key can be set in wp-config.php or admin panel

---

### 3. Partner+ Auto-Affiliate Activation
**File**: `rcp-content-filter-utility.php` (lines 514-1208)

**Purpose**: Automatically creates active affiliate accounts when customers purchase the Partner+ product.

**Workflow**:
1. Customer purchases Partner+ product (slug: `partner-plus`)
2. Order reaches "Processing" or "Completed" status
3. Plugin creates affiliate account with status "active"
4. Assigns parent affiliate (if referral link was used)
5. Changes user role from "Partner Plus Pending" to "Partner Plus"
6. Clears cart
7. Forces thank you page display (prevents automation redirects)

**Key Functions**:
- `bl_auto_create_affiliate_on_partner_purchase()` - Main affiliate creation
- `bl_get_default_partner_product_id()` - Looks up product by slug
- `bl_force_partner_thankyou_page()` - Forces WooCommerce thank you page
- `bl_intercept_wp_redirect()` - Intercepts redirects to partnership console
- `bl_hijack_partnership_console_for_partner_orders()` - Aggressive redirect interception

**Anti-Redirect System**:
- Stores thank you URL in transient (5-minute expiry)
- Multiple redirect interception points:
  - `woocommerce_get_return_url` filter
  - `wp_redirect` filter
  - `template_redirect` action (priority 1)
  - `init` action (priority 1)
- JavaScript-based redirect blocking on thank you page

**Parent Affiliate Assignment**:
- Uses order meta `_affwp_affiliate_id` (NOT current visitor's referrer)
- Connects via Multi-Tier Commissions if available
- Falls back to direct meta update

**Error Handling**:
- Extensive WP_DEBUG logging
- Order notes for all affiliate operations
- Try-catch blocks for all critical operations
- Validates parent affiliate exists and is active

**Configuration**: ZERO configuration required - works automatically with product slug `partner-plus`

---

### 4. AffiliateWP Registration Form Enhancement
**Files**:
- PHP: `rcp-content-filter-utility.php` (lines 356-390)
- JS: `admin/js/affiliatewp-registration.js`

**Purpose**: Streamlines affiliate registration by auto-populating and hiding unnecessary fields.

**Field Rules**:
- **Hidden When Autofilled**: Name, Username, Account Email
- **Always Visible**: Payment Email (required for payouts)
- **Always Hidden**: Website URL, "How will you promote us?"

**Implementation**:
- JavaScript checks for autofilled values
- Applies CSS class `rcf-hidden-field` to hide fields
- Smooth transitions with CSS animations
- Re-initializes after 1 second for AJAX-loaded forms

---

### 5. LearnPress + Thim Elementor Kit Fix
**Files**:
- PHP: `includes/class-learnpress-elementor-fix.php`
- JS: `assets/js/learnpress-next-button-control.js`

**Purpose**: Fixes Elementor templates not loading on LearnPress course context URLs.

**Problem Solved**:
- Direct URL works: `/lessons/lesson-name/`
- Course context URL doesn't work: `/courses/course-name/lessons/lesson-name/`

**Solution**:
1. **PHP Side**: Overrides WordPress query to treat course context as singular lesson page
2. **JS Side**:
   - Controls Next button visibility (hide until lesson completed)
   - Removes retake count from buttons (e.g., removes "(942)" from "Retake Course")
   - Removes success message after completion

**Key Methods**:
- `force_thim_template_in_course_context()` - Hijacks WP_Query
- `remove_retake_count_from_button()` - Cleans button text
- `filter_output_buffer()` - Aggressive output buffering as last resort

**Configuration**: Enable at Restrict Content Pro → Content Filter → LearnPress Course Context Fix

---

### 6. WooCommerce Checkout ASCII Validation
**Files**:
- PHP: `rcp-content-filter-utility.php` (lines 1413-1605)
- JS: `assets/js/checkout-ascii-validation.js`

**Purpose**: Restricts checkout fields to ASCII characters only for international shipping compatibility.

**Blocks**:
- Kanji (漢字)
- Hiragana (ひらがな)
- Katakana (カタカナ)
- Emoji and other Unicode characters

**Allows**:
- Roman letters: A-Z, a-z
- Arabic numerals: 0-9
- Standard punctuation: space, hyphen, period, comma, apostrophe, #, /, (, ), &, +, _, %
- Email-only: @ symbol (in email fields only)

**Implementation**:
- **PHP**: Backend validation on checkout submission
- **JS**: Real-time validation as user types
- Dual validation ensures no non-ASCII slips through
- Visual feedback with red borders and error messages

**Phone Field Requirement**:
- Makes billing and shipping phone fields required
- Updates labels to remove "(optional)"

**Address Line 2 Placeholder**:
- Updated to: "Building, Apartment, suite, unit, etc. (optional)"
- Aligns with Loqate's SubBuilding field

---

### 7. DNA Kit Serial ID Capture
**File**: `rcp-content-filter-utility.php` (lines 1799-1940)

**Purpose**: Captures DNA kit serial IDs from Advanced Shipment Tracking API payloads.

**How It Works**:
1. Intercepts POST requests to AST endpoint: `/wc-shipment-tracking/v3/orders/{order_id}/shipment-trackings`
2. Extracts kit IDs from `products` payload
3. Handles single kits (`"6141": "T12349"`) and multiple kits (`"6137": "T12345,T12346,T12347"`)
4. Stores as JetEngine repeater format in `dna_kit_ids` post meta

**Payload Structure**:
```json
{
  "order_id": "25225",
  "tracking_number": "ZZ12347",
  "products": {
    "6141": "T12349",
    "6137": "T12345,T12346,T12347"
  }
}
```

**Storage Format** (JetEngine Repeater):
```php
[
  ['dna_kit_id' => 'T12349'],
  ['dna_kit_id' => 'T12345'],
  ['dna_kit_id' => 'T12346'],
  ['dna_kit_id' => 'T12347']
]
```

**Features**:
- Merges kits from multiple API calls
- Prevents duplicate kit IDs
- Removes empty rows from previous failed attempts
- WP_DEBUG logging for debugging

---

### 8. AffiliateWP Referral Safety Hook
**File**: `rcp-content-filter-utility.php` (lines 473-510)

**Purpose**: Automatically rejects AffiliateWP referrals for failed/cancelled/refunded orders.

**Triggers**:
- `woocommerce_order_status_failed`
- `woocommerce_order_status_cancelled`
- `woocommerce_order_status_refunded`

**Logic**:
- Gets referral associated with order
- Rejects referral if status is not "paid"
- Adds order note for tracking

**Prevents**: Affiliates from receiving commissions for incomplete orders

---

### 9. Reset Password Link Fix (WP Engine LinkShield Bypass)
**File**: `rcp-content-filter-utility.php` (lines 1211-1410)

**Purpose**: Bypasses WP Engine's LinkShield URL rewriting for password reset links.

**Problem**: LinkShield rewrites reset URLs to `url5758.biolimitless.com` which breaks password resets

**Solution**:
- Hijacks `retrieve_password_message` filter
- Hijacks `site_url` and `network_site_url` filters
- Intercepts `wp_mail` before sending
- Hardcodes domain to `biolimitless.com`
- Extracts key and login from any URL pattern
- Generates clean URL: `https://biolimitless.com/wp-login.php?action=rp&key={key}&login={login}`

**Compatibility**: Works with JetFormBuilder's password reset macro `%_reset_pass_link%`

---

### 10. Stripe Customer/Source ID Migration
**File**: `admin/class-admin.php` (lines 416-738)

**Purpose**: Bulk update Stripe customer and source IDs from CSV file.

**CSV Format**:
```
customer_id_old,source_id_old,customer_id_new,source_id_new
cus_OLD123,src_OLD456,cus_NEW123,src_NEW456
```

**Features**:
- Dry run mode (preview changes without updating)
- Updates `_stripe_customer_id` and `_stripe_source_id` post meta
- Detailed results with update counts
- Error logging for failed updates
- Skips rows where old = new (no change needed)

**Configuration**: Restrict Content Pro → Content Filter → Stripe Migration

---

### 11. Ship to Different Address Control
**Files**:
- PHP: `rcp-content-filter-utility.php` (lines 1629-1704)
- JS: `assets/js/shipping-address-control.js`
- CSS: Inline in PHP (lines 1680-1704)

**Purpose**: Unchecks "Ship to different address?" checkbox by default on checkout.

**Behavior**:
- Checkbox unchecked by default
- JavaScript auto-checks if shipping data exists (saved addresses)
- Hides shipping fields initially with CSS
- Shows fields when checkbox is checked

**User Experience**:
- Most customers use same billing/shipping address
- Reduces form clutter
- Smart auto-check preserves user's saved shipping addresses

**Configuration**: Enable at Restrict Content Pro → Content Filter → Uncheck "Ship to different address?"

---

## File Structure

```
rcp-content-filter-utility/
├── rcp-content-filter-utility.php     # Main plugin file (1,940 lines)
├── readme.txt                         # WordPress plugin readme
├── DOCUMENTATION.md                   # Comprehensive documentation (713 lines)
├── PLUGIN-OVERVIEW.md                 # This file
├── admin/
│   ├── class-admin.php                # Admin settings page (1,047 lines)
│   ├── css/
│   │   └── admin.css                  # Admin styles
│   └── js/
│       └── affiliatewp-registration.js # AffiliateWP form enhancement
├── assets/
│   └── js/
│       ├── checkout-ascii-validation.js      # Checkout validation
│       ├── learnpress-next-button-control.js # LearnPress UI control
│       ├── loqate-address-capture.js         # Loqate SDK integration (927 lines)
│       └── shipping-address-control.js       # Shipping checkbox control
└── includes/
    ├── class-learnpress-elementor-fix.php     # LearnPress fix (574 lines)
    ├── class-loqate-address-capture.php       # Loqate PHP class (733 lines)
    └── class-loqate-admin-settings.php        # Loqate admin UI (308 lines)
```

---

## Dependencies

### Required
- **WordPress**: 5.0+
- **PHP**: 8.2+
- **Restrict Content Pro**: Active (for content filtering)

### Optional
- **WooCommerce**: 7.0+ (for checkout features, Loqate, DNA tracking)
- **AffiliateWP**: 2.0+ (for auto-affiliate, form enhancement, referral safety)
- **LearnPress**: 4.0+ (for course context fix)
- **Elementor**: (for LearnPress template fix)
- **Elementor Pro**: (optional, for Theme Builder templates)
- **Advanced Shipment Tracking**: (for DNA kit capture)

---

## Configuration Overview

### Admin Menu Location
**Restrict Content Pro → Content Filter**

### Tabs
1. **Content Filter Settings** - Post type filtering, priority, LearnPress fix, shipping address control
2. **Loqate Integration** - API key, geolocation, validation services, country restrictions
3. **Stripe Migration** - CSV upload for Stripe ID migration

---

## Performance Optimizations

### Loqate Integration
1. **Cached DOM References** - Reduces jQuery selector overhead
2. **Batched DB Queries** - Single query for all Loqate options
3. **Lazy Initialization** - Shipping fields only initialized when visible
4. **Debounced Search** - 150ms delay reduces API calls while typing
5. **requestAnimationFrame** - Batched DOM updates for smooth UI

### Content Filtering
1. **Query Adjustment** - Fetches 3x posts upfront to compensate for filtering
2. **Early Returns** - Skips filtering on admin, checkout, cart pages
3. **Cached Settings** - Plugin settings cached in memory

---

## Security Considerations

### Input Sanitization
- All user inputs sanitized with `sanitize_text_field()`, `sanitize_key()`, etc.
- Nonce verification on all form submissions
- Capability checks (`manage_options`) for admin actions

### Output Escaping
- `esc_attr()`, `esc_html()`, `esc_url()` used throughout
- Prepared SQL statements for database queries

### API Key Protection
- Loqate API key masked in admin display
- Constant-based configuration option (keeps key out of database)

---

## Hooks and Filters

### Actions
- `init` - Initialize plugin, LearnPress fix, Partner+ redirect interception
- `plugins_loaded` - Initialize main plugin class, LearnPress fix, Loqate integration
- `wp_enqueue_scripts` - Enqueue frontend scripts (Loqate, AffiliateWP, LearnPress, validation)
- `admin_menu` - Add admin settings page
- `admin_init` - Register settings
- `admin_notices` - Display admin notices
- `pre_get_posts` - Adjust query for content filtering
- `template_redirect` - LearnPress fix, Partner+ redirect prevention
- `woocommerce_after_checkout_validation` - ASCII validation
- `woocommerce_order_status_completed` - Partner+ auto-affiliate
- `woocommerce_order_status_processing` - Partner+ auto-affiliate
- `woocommerce_order_status_failed` - Reject AffiliateWP referral
- `woocommerce_order_status_cancelled` - Reject AffiliateWP referral
- `woocommerce_order_status_refunded` - Reject AffiliateWP referral

### Filters
- `the_posts` - Filter restricted content
- `found_posts` - Adjust pagination count
- `woocommerce_get_return_url` - Force Partner+ thank you page
- `wp_redirect` - Intercept Partner+ redirects
- `wp_safe_redirect` - Intercept Partner+ redirects
- `retrieve_password_message` - Fix reset password links
- `site_url` - Fix reset password links
- `network_site_url` - Fix reset password links
- `wp_mail` - Fix reset password links
- `woocommerce_billing_fields` - Make phone required
- `woocommerce_shipping_fields` - Make phone required, shipping checkbox control
- `woocommerce_default_address_fields` - Update Address Line 2 placeholder
- `woocommerce_ship_to_different_address_checked` - Uncheck shipping by default
- `rest_request_after_callbacks` - Capture DNA kit IDs
- `bl_partner_plus_product_id` - Override Partner+ product ID
- `bl_auto_affiliate_data` - Customize affiliate data
- `rcf_loqate_api_key` - Provide Loqate API key
- `rcf_loqate_allowed_countries` - Restrict Loqate countries
- `rcf_loqate_geolocation_options` - Configure geolocation
- `rcf_loqate_allow_manual_entry` - Allow manual address entry
- `rcf_loqate_validate_email` - Enable email validation
- `rcf_loqate_validate_phone` - Enable phone validation
- `rcf_loqate_billing_field_mapping` - Customize billing field mapping
- `rcf_loqate_shipping_field_mapping` - Customize shipping field mapping
- `learn-press/course-button-text` - Remove retake count
- `learn-press/button-retake-html` - Remove retake count
- `elementor/widget/render_content` - Remove retake count from Elementor widgets

---

## Debug Logging

### WP_DEBUG Integration
All major operations log to `wp-content/debug.log` when `WP_DEBUG` is enabled:

**Partner+ Auto-Affiliate**:
```
[BL Auto Affiliate] Order #12345: Created affiliate #67 for user #123
[BL Auto Affiliate] Order #12345: Connected affiliate #67 to parent #45
[BL Auto Affiliate] Order #12345: Changed role from Partner Plus Pending to Partner Plus
```

**DNA Kit Capture**:
```
[BL DNA Kit Capture] Order #25225: Captured 3 unique kit IDs. Total stored: 4
```

**Loqate**:
```
[Loqate] Environment validated - SDK loaded, API key: AA11****
[Loqate] Initialized - billing address capture ready
[Loqate] Error - Type: billing, Status: 401, Message: Invalid API key
```

---

## Version History

### 1.0.39 (Current)
- Updated Address Line 2 placeholder to include "Building"

### 1.0.38 (December 11, 2025)
- SubBuilding/Apt/Suite extraction for Address Line 2
- Performance optimizations (cached config, batched queries, lazy init)
- Extended country code map (60+ countries)

### 1.0.25
- Loqate integration enhancements
- Debounced search
- Country context handling

### 1.0.18 (November 27, 2025)
- Fixed Loqate dropdown positioning
- Improved Loqate SDK styling

### 1.0.17 (November 26, 2025)
- Enhanced Loqate error logging
- API key validation status messages

### 1.0.16 (November 26, 2025)
- Fixed Loqate SDK constructor error
- Improved field monitoring

### 1.0.15 (November 26, 2025)
- Complete Loqate Address Capture integration
- Email and phone validation support
- Geolocation-based suggestions
- Admin settings page

### 1.0.13 (November 22, 2025)
- Removed JetForm loading overlay feature
- Removed password-protected access overlay UI

### 1.0.0 (October 24, 2025)
- Initial release
- Content filtering
- Auto-affiliate activation
- AffiliateWP form enhancement
- LearnPress course context fix

---

## Known Issues

### None Currently Reported

---

## Support Resources

### Documentation
- **DOCUMENTATION.md** - Comprehensive guide (713 lines)
- **readme.txt** - WordPress plugin readme
- **PLUGIN-OVERVIEW.md** - This file
- **Admin Settings** - Restrict Content Pro → Content Filter

### External Resources
- **Loqate Dashboard**: https://dashboard.loqate.com/
- **Loqate Documentation**: https://docs.loqate.com/
- **Loqate API Reference**: https://docs.loqate.com/api-reference

---

## Summary

RCP Content Filter Utility is a comprehensive, production-ready WordPress plugin providing 11 major feature sets:

1. ✅ **Content Filtering** - Hide restricted posts based on membership
2. ✅ **Loqate Address Capture** - 245+ countries with autocomplete
3. ✅ **Partner+ Auto-Affiliate** - Zero-config affiliate activation
4. ✅ **AffiliateWP Form Enhancement** - Streamlined registration
5. ✅ **LearnPress + Elementor Fix** - Course context template loading
6. ✅ **ASCII Checkout Validation** - International shipping compatibility
7. ✅ **DNA Kit Tracking** - Serial ID capture from shipments
8. ✅ **AffiliateWP Referral Safety** - Auto-reject failed order referrals
9. ✅ **Reset Password Link Fix** - WP Engine LinkShield bypass
10. ✅ **Stripe Migration** - Bulk ID updates via CSV
11. ✅ **Shipping Address Control** - Smart checkbox management

**Total Code**: ~5,900 lines (PHP + JS)
**Performance**: Optimized with caching, lazy loading, batched operations
**Security**: Sanitized inputs, escaped outputs, capability checks
**Compatibility**: WordPress 5.0+, PHP 8.2+, WooCommerce 7.0+
**Status**: Production-ready, actively maintained
