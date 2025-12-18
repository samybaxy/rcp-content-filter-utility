# RCP Content Filter Utility - Comprehensive Testing Guide

**Version**: 1.0.39
**Environment**: Development
**Last Updated**: December 18, 2025

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Setup](#environment-setup)
3. [Feature-by-Feature Testing](#feature-by-feature-testing)
   - [1. Content Filtering](#1-content-filtering)
   - [2. Loqate Address Capture](#2-loqate-address-capture)
   - [3. Partner+ Auto-Affiliate](#3-partner-auto-affiliate)
   - [4. AffiliateWP Form Enhancement](#4-affiliatewp-form-enhancement)
   - [5. LearnPress + Elementor Fix](#5-learnpress--elementor-fix)
   - [6. Checkout ASCII Validation](#6-checkout-ascii-validation)
   - [7. DNA Kit Tracking](#7-dna-kit-tracking)
   - [8. AffiliateWP Referral Safety](#8-affiliatewp-referral-safety)
   - [9. Reset Password Link Fix](#9-reset-password-link-fix)
   - [10. Stripe Migration](#10-stripe-migration)
   - [11. Shipping Address Control](#11-shipping-address-control)
4. [Automated Testing](#automated-testing)
5. [Pre-Production Checklist](#pre-production-checklist)
6. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Plugins
- ✅ **Restrict Content Pro** (active)
- ✅ **WooCommerce** 7.0+ (for e-commerce features)

### Optional Plugins (for specific features)
- AffiliateWP 2.0+ (for Partner+, form enhancement, referral safety)
- LearnPress 4.0+ (for course context fix)
- Elementor & Elementor Pro (for LearnPress template fix)
- Advanced Shipment Tracking (for DNA kit capture)

### Development Tools
- Browser DevTools (Console, Network, Elements tabs)
- WP_DEBUG enabled
- Access to `wp-content/debug.log`
- Test user accounts with different membership levels
- Test credit cards (WooCommerce test mode)

---

## Environment Setup

### Step 1: Enable Debug Logging

Edit `wp-config.php`:

```php
// Before "/* That's all, stop editing! */"
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );
```

### Step 2: Verify Plugin Activation

```bash
wp plugin list | grep "rcp-content-filter-utility"
```

Expected output:
```
rcp-content-filter-utility  active    1.0.39
```

### Step 3: Check Dependencies

```bash
# Check RCP
wp plugin is-active restrict-content-pro && echo "RCP: Active" || echo "RCP: Not active"

# Check WooCommerce
wp plugin is-active woocommerce && echo "WooCommerce: Active" || echo "WooCommerce: Not active"

# Check AffiliateWP
wp plugin is-active affiliate-wp && echo "AffiliateWP: Active" || echo "AffiliateWP: Not active"

# Check LearnPress
wp plugin is-active learnpress && echo "LearnPress: Active" || echo "LearnPress: Not active"
```

### Step 4: Clear All Caches

```bash
# Clear WordPress caches
wp cache flush

# Clear WooCommerce caches
wp transient delete --all

# Clear browser cache (hard refresh)
# Windows/Linux: Ctrl+Shift+R (3 times)
# Mac: Cmd+Shift+R (3 times)
```

### Step 5: Tail Debug Log

In a separate terminal:

```bash
tail -f /home/samuel/local-sites/build-dev/app/public/wp-content/debug.log
```

---

## Feature-by-Feature Testing

### 1. Content Filtering

**Purpose**: Hide restricted posts from archive pages based on membership levels

#### Test Case 1.1: Setup Restricted Content
1. Go to: **Posts → Add New**
2. Create 3 test posts:
   - "Public Post" (no restrictions)
   - "Member Post" (restricted to any membership level)
   - "Premium Post" (restricted to specific premium level)
3. Save all posts

#### Test Case 1.2: Configure Content Filter
1. Go to: **Restrict Content Pro → Content Filter**
2. Select post types to filter: ✅ Posts
3. Filter Priority: **10** (default)
4. Click **Save Settings**

#### Test Case 1.3: Test as Guest
1. Open incognito window
2. Visit blog archive page
3. **Expected**: Only "Public Post" visible
4. **Verify**: Member and Premium posts are hidden (not just restricted)

#### Test Case 1.4: Test as Member
1. Login as user with basic membership
2. Visit blog archive page
3. **Expected**: "Public Post" + "Member Post" visible
4. **Expected**: "Premium Post" hidden

#### Test Case 1.5: Test as Premium Member
1. Login as user with premium membership
2. Visit blog archive page
3. **Expected**: All 3 posts visible

#### Test Case 1.6: Test Custom Post Types
1. Add custom post type (e.g., "resource") to filter settings
2. Create restricted resources
3. **Expected**: Resources filtered same as posts

#### Test Case 1.7: Verify Pagination
1. Create 20 restricted posts
2. Set posts per page to 10
3. Visit archive as guest
4. **Expected**: Pagination shows correct count (only accessible posts)

**Debug Log Check**:
- No errors related to content filtering
- Query adjustments logged (if debug mode enabled)

---

### 2. Loqate Address Capture

**Purpose**: Real-time address autocomplete on WooCommerce checkout

#### Test Case 2.1: API Key Configuration

**Option A: wp-config.php (Recommended)**
```php
define( 'LOQATE_API_KEY', 'AA11-BB22-CC33-DD44' );
```

**Option B: Admin Panel**
1. Go to: **Restrict Content Pro → Content Filter → Loqate Integration**
2. Paste API key
3. Click **Save Loqate Settings**

#### Test Case 2.2: Verify Integration Status
1. Go to: **Restrict Content Pro → Content Filter → Loqate Integration**
2. Check **Integration Status** section:
   - ● API Key: **Configured** (green)
   - ● Integration: **Enabled** (green)
   - ● WooCommerce: **Active** (green)

#### Test Case 2.3: Test Billing Address Autocomplete
1. Add product to cart
2. Go to checkout
3. Click in "Billing Address Line 1" field
4. Type: `1600 Pennsylvania`
5. **Expected**: Dropdown appears with address suggestions
6. Click: "1600 Pennsylvania Avenue NW, Washington, DC 20500"
7. **Expected**: All fields auto-fill:
   - Address Line 2: (empty)
   - City: **Washington**
   - State: **DC** (dropdown selected)
   - Postcode: **20500**
   - Country: **United States (US)** (dropdown selected)

#### Test Case 2.4: Test Shipping Address Autocomplete
1. Check "Ship to a different address?"
2. Click in "Shipping Address Line 1"
3. Type: `10 Downing`
4. **Expected**: Dropdown with "10 Downing Street, London"
5. Click suggestion
6. **Expected**: Shipping fields auto-fill correctly

#### Test Case 2.5: Test SubBuilding Extraction
1. Search for address with apartment: `350 5th Avenue, Unit 5`
2. Select from dropdown
3. **Expected**:
   - Address Line 1: **350 5th Avenue**
   - Address Line 2: **Unit 5** (or Apt, Suite, Flat, etc.)
   - Other fields filled correctly

#### Test Case 2.6: Test Country Switching
1. Select country: **United Kingdom**
2. Type in Address Line 1: `221B Baker`
3. **Expected**: Only UK addresses in dropdown
4. Switch country to **Australia**
5. Type: `1 Macquarie`
6. **Expected**: Only Australian addresses in dropdown

#### Test Case 2.7: Test Error Handling
1. Enter invalid API key
2. Try to search address
3. **Expected**: Error message appears
4. **Console**: Shows detailed error with status code

#### Test Case 2.8: Test Manual Entry
1. Click "Enter Manually" (if enabled)
2. **Expected**: Fields become editable
3. Enter address manually
4. **Expected**: Form submits successfully

#### Test Case 2.9: Browser Console Checks
Open DevTools Console, look for:
```javascript
[Loqate] Environment validated - SDK loaded, API key: AA11****
[Loqate] Initialized - billing address capture ready
[Loqate] Initialized - shipping address capture ready
```

**No Errors Expected** (unless API key invalid)

#### Test Case 2.10: Network Tab Verification
1. Open DevTools → Network tab
2. Filter: `XHR`
3. Type in address field
4. **Expected**: Requests to `api.addressy.com`
5. **Status**: 200 OK
6. **Response**: JSON with address suggestions

**Debug Log Check**:
```
[Loqate] Environment validated - SDK loaded, API key: AA11****
[Loqate] Initialized - billing address capture ready
```

---

### 3. Partner+ Auto-Affiliate

**Purpose**: Automatically create affiliate account when Partner+ is purchased

#### Test Case 3.1: Verify Partner+ Product Exists
```bash
wp post list --post_type=product --name=partner-plus --format=table
```

Expected:
```
ID    post_title              post_name     post_status
24092 The Partner+ Program    partner-plus  publish
```

**If product doesn't exist**, create it:
```bash
wp wc product create --name="The Partner+ Program" --type=simple --sku=partner-plus --regular_price=97 --user=admin
```

#### Test Case 3.2: Test Order Without Referral
1. **Logout** (clear all cookies)
2. Create new test account: `testuser@example.com`
3. Add Partner+ product to cart
4. Complete checkout (use WooCommerce test payment)
5. Wait for order status to change to "Processing" or "Completed"

**Expected Results**:
- ✅ Order reaches completed status
- ✅ Thank you page displays (NOT redirected to partnership console)
- ✅ Cart is cleared
- ✅ Affiliate account created automatically

**Verify in Admin**:
1. Go to: **AffiliateWP → Affiliates**
2. Search for: `testuser@example.com`
3. **Expected**:
   - Affiliate exists
   - Status: **Active**
   - Parent: **(none)**

#### Test Case 3.3: Test Order With Referral Link
1. **Create parent affiliate**: Login as admin, create affiliate with ID #1
2. Get referral URL: `https://yourdomain.com/?ref=1`
3. **Logout** (clear all cookies/incognito)
4. Click referral link
5. Create new account: `childuser@example.com`
6. Purchase Partner+ product
7. Complete checkout

**Expected Results**:
- ✅ Affiliate account created
- ✅ Parent affiliate assigned: **#1**
- ✅ Status: **Active**

**Verify Parent Relationship**:
```bash
wp affwp affiliate get-meta {child_affiliate_id} parent_affiliate_id
```

Expected: Parent affiliate ID returned

#### Test Case 3.4: Test Role Change
1. Before purchase: Assign user role "Partner Plus Pending"
2. Purchase Partner+ product
3. **Expected**: Role changed to "Partner Plus"

```bash
# Check user role
wp user get testuser@example.com --field=roles
```

Expected: `partner-plus`

#### Test Case 3.5: Test Thank You Page (No Redirect)
1. Purchase Partner+ product
2. **Expected**: Stay on WooCommerce thank you page
3. **NOT Expected**: Redirect to `/console/partnership`

**Verify Redirect Interception**:
- Check browser console for: `[BL Partner+] Blocking all redirects on thank you page`
- No redirect occurs for at least 10 seconds

#### Test Case 3.6: Test Multiple Purchases (Idempotency)
1. User already has affiliate account
2. Purchase Partner+ again
3. **Expected**: No duplicate affiliate created
4. **Expected**: Order note says affiliate already exists

#### Test Case 3.7: Test Failed Order
1. Place Partner+ order
2. Admin: Change order status to "Failed"
3. **Expected**: No affiliate created
4. **Expected**: No role change

#### Test Case 3.8: Verify Order Notes
1. Go to: **WooCommerce → Orders → [Partner+ order]**
2. Scroll to **Order Notes**

**Expected notes**:
```
Affiliate account #67 automatically created for customer.
Affiliate #67 connected to parent affiliate #45.
User role changed from Partner Plus Pending to Partner Plus.
```

**Debug Log Check**:
```bash
grep "BL Auto Affiliate" wp-content/debug.log | tail -10
```

Expected:
```
[BL Auto Affiliate] Order #12345: Created affiliate #67 for user #123
[BL Auto Affiliate] Order #12345: Connected affiliate #67 to parent #45
[BL Auto Affiliate] Order #12345: Changed role from Partner Plus Pending to Partner Plus
```

---

### 4. AffiliateWP Form Enhancement

**Purpose**: Streamline affiliate registration form

#### Test Case 4.1: Test Logged-In User
1. Login as regular user
2. Go to affiliate registration page (usually `/affiliate-area/register/`)
3. **Expected**:
   - **Hidden**: "Your Name" (autofilled)
   - **Hidden**: "Username" (autofilled)
   - **Hidden**: "Account Email" (autofilled)
   - **Visible**: "Payment Email" (editable)
   - **Hidden**: "Website URL"
   - **Hidden**: "How will you promote us?"

#### Test Case 4.2: Test Guest User
1. Logout
2. Visit affiliate registration page
3. **Expected**: All fields visible (no autofill)

#### Test Case 4.3: Browser Console Verification
1. Visit registration page
2. Open DevTools Console
3. **Expected**: `RCP: AffiliateWP form enhancements applied successfully`
4. Type `?debug=affwp` in URL
5. **Expected**: Detailed field state logged

---

### 5. LearnPress + Elementor Fix

**Purpose**: Fix Elementor templates in course context URLs

#### Test Case 5.1: Enable Fix
1. Go to: **Restrict Content Pro → Content Filter**
2. Check: ✅ **Enable LearnPress Course Context Fix**
3. Click **Save Settings**

#### Test Case 5.2: Verify Status
1. Same settings page
2. Check **Fix Status** box:
   - ● LearnPress: **Active** (green)
   - ● Elementor: **Active** (green)
   - ● Elementor Pro: **Active** (green) or **Not Active** (yellow, optional)
   - ● Fix Hooks: **Registered** (green)

#### Test Case 5.3: Test Direct Lesson URL
1. Visit: `/lessons/lesson-name/`
2. **Expected**: Elementor template loads correctly
3. **Baseline**: This should work by default

#### Test Case 5.4: Test Course Context URL
1. Visit: `/courses/course-name/lessons/lesson-name/`
2. **Expected**: Elementor template loads correctly ✅
3. **Without Fix**: Would show broken layout or no template

#### Test Case 5.5: Test Next Button Control
1. Visit lesson page
2. **Expected**: "Next" button hidden until lesson completed
3. Click "Complete Lesson"
4. **Expected**: "Next" button appears

#### Test Case 5.6: Test Retake Count Removal
1. Complete course
2. Retake course
3. **Expected**: Button shows "Retake Course" (NOT "Retake Course (942)")
4. **Console**: `Removed retake count from 5 buttons`

#### Test Case 5.7: Verify URL Pattern Detection
```javascript
// In browser console
window.location.pathname
```

If path is `/courses/abc/lessons/xyz/`, fix should activate.

**Debug Log Check**:
- No errors related to LearnPress or Elementor
- Template loading successful

---

### 6. Checkout ASCII Validation

**Purpose**: Ensure checkout fields only contain ASCII characters

#### Test Case 6.1: Test Valid Input
1. Go to checkout
2. Enter address: `123 Main Street, Apt 4B`
3. **Expected**: No error, green border (valid)

#### Test Case 6.2: Test Kanji Input
1. Type in "Billing First Name": `山田太郎` (Yamada Taro in kanji)
2. **Expected**:
   - Red border appears
   - Error message: "Use Roman/English characters only (A–Z, 0–9)"
   - Cannot submit checkout

#### Test Case 6.3: Test Hiragana/Katakana
1. Type in address: `あいうえお` (hiragana) or `アイウエオ` (katakana)
2. **Expected**: Same error as kanji

#### Test Case 6.4: Test Emoji
1. Type in address: `123 Main St 🏠`
2. **Expected**: Error, emoji rejected

#### Test Case 6.5: Test Email Field (@ Allowed)
1. Type in "Billing Email": `test@example.com`
2. **Expected**: Valid (@ symbol allowed in email fields)

#### Test Case 6.6: Test Paste Prevention
1. Copy non-ASCII text: `山田太郎`
2. Paste into "Billing First Name"
3. **Expected**: Paste blocked, field remains empty, error shown briefly

#### Test Case 6.7: Test Phone Field Requirement
1. Leave phone field empty
2. Try to submit
3. **Expected**: "Phone field is required" error

#### Test Case 6.8: Test Real-Time Validation
1. Start typing valid address: `123 Main`
2. **Expected**: No error
3. Add invalid character: `123 Main 山`
4. **Expected**: Instant red border and error

**Browser Console Check**:
- No JavaScript errors
- Validation events firing correctly

---

### 7. DNA Kit Tracking

**Purpose**: Capture DNA kit serial IDs from shipment tracking API

#### Test Case 7.1: Simulate API Request

Create test file `test-dna-capture.php`:

```php
<?php
// Simulate AST API request
$order_id = 25225; // Replace with real order ID

$payload = array(
    'order_id' => $order_id,
    'tracking_number' => 'TEST123',
    'products' => array(
        '6141' => 'T12349',
        '6137' => 'T12345,T12346,T12347'
    )
);

// Make POST request to AST endpoint
$url = home_url( '/wp-json/wc-shipment-tracking/v3/orders/' . $order_id . '/shipment-trackings' );

$response = wp_remote_post( $url, array(
    'body' => json_encode( $payload ),
    'headers' => array(
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer YOUR_API_KEY' // Replace
    )
));

// Check result
var_dump( $response );
```

Run:
```bash
wp eval-file test-dna-capture.php
```

#### Test Case 7.2: Verify Storage
```bash
wp post meta get {order_id} dna_kit_ids
```

Expected:
```php
array(
    array( 'dna_kit_id' => 'T12349' ),
    array( 'dna_kit_id' => 'T12345' ),
    array( 'dna_kit_id' => 'T12346' ),
    array( 'dna_kit_id' => 'T12347' )
)
```

#### Test Case 7.3: Test Duplicate Prevention
1. Send same payload again
2. **Expected**: No duplicate kit IDs stored

#### Test Case 7.4: Test Multiple API Calls
1. Send payload with kits: T12345, T12346
2. Send payload with kits: T12347, T12348
3. **Expected**: All 4 kits merged in one meta field

**Debug Log Check**:
```
[BL DNA Kit Capture] Order #25225: Captured 4 unique kit IDs. Total stored: 4
```

---

### 8. AffiliateWP Referral Safety

**Purpose**: Auto-reject referrals for failed/cancelled/refunded orders

#### Test Case 8.1: Test Failed Order
1. Create order with referral
2. Change order status to "Failed"
3. **Expected**: Referral status changes to "Rejected"
4. **Expected**: Order note added

#### Test Case 8.2: Test Cancelled Order
1. Create order with referral
2. Change status to "Cancelled"
3. **Expected**: Referral rejected

#### Test Case 8.3: Test Refunded Order
1. Create completed order with referral
2. Refund order
3. **Expected**: Referral rejected (if not already paid)

#### Test Case 8.4: Test Already Paid Referral
1. Pay referral to affiliate
2. Refund order
3. **Expected**: Referral NOT rejected (already paid)

**Verify in Admin**:
1. **AffiliateWP → Referrals**
2. Find referral for test order
3. **Status**: Should be "Rejected"

---

### 9. Reset Password Link Fix

**Purpose**: Bypass WP Engine LinkShield for password reset emails

#### Test Case 9.1: Test Password Reset Email
1. Go to login page
2. Click "Lost your password?"
3. Enter email address
4. Submit

#### Test Case 9.2: Check Email Source
1. Open received email
2. Right-click → "View Source" or "Show Original"
3. Find reset URL
4. **Expected**: `https://biolimitless.com/wp-login.php?action=rp&key={key}&login={login}`
5. **NOT Expected**: `https://url5758.biolimitless.com/...`

#### Test Case 9.3: Test Link Functionality
1. Click reset link in email
2. **Expected**: Redirects to WordPress password reset form
3. Enter new password
4. **Expected**: Password reset successful

**Debug Log Check**:
```
[RCF Reset URL] Generated clean reset URL for user: testuser
```

---

### 10. Stripe Migration

**Purpose**: Bulk update Stripe customer/source IDs

#### Test Case 10.1: Create Test CSV

Create `stripe-test.csv`:
```csv
customer_id_old,source_id_old,customer_id_new,source_id_new
cus_OLD123,src_OLD456,cus_NEW123,src_NEW456
cus_OLD789,src_OLD012,cus_NEW789,src_NEW012
```

#### Test Case 10.2: Create Test Order Meta
```bash
# Create test order
wp wc order create --status=completed --user=testuser@example.com

# Add old Stripe IDs
wp post meta update {order_id} _stripe_customer_id cus_OLD123
wp post meta update {order_id} _stripe_source_id src_OLD456
```

#### Test Case 10.3: Run Dry Run Migration
1. Go to: **Restrict Content Pro → Content Filter → Stripe Migration**
2. Upload `stripe-test.csv`
3. Check: ✅ **Dry Run**
4. Click **Upload and Process CSV**

**Expected Results**:
```
🔍 Dry Run Results (No Changes Made)
Summary:
- Total CSV rows processed: 2
- Customer IDs: Will be updated: 1
- Source IDs: Will be updated: 1
```

#### Test Case 10.4: Run Actual Migration
1. Uncheck "Dry Run"
2. Upload CSV again
3. Click **Upload and Process CSV**

**Expected**:
```
✅ Migration Complete
- Customer IDs: Updated: 1
- Source IDs: Updated: 1
```

#### Test Case 10.5: Verify Updates
```bash
wp post meta get {order_id} _stripe_customer_id
# Expected: cus_NEW123

wp post meta get {order_id} _stripe_source_id
# Expected: src_NEW456
```

---

### 11. Shipping Address Control

**Purpose**: Uncheck "Ship to different address?" by default

#### Test Case 11.1: Enable Feature
1. Go to: **Restrict Content Pro → Content Filter**
2. Check: ✅ **Uncheck "Ship to different address?" by default**
3. Save

#### Test Case 11.2: Test Fresh Checkout (No Saved Addresses)
1. Logout, clear cookies
2. Add product to cart
3. Go to checkout
4. **Expected**: "Ship to a different address?" checkbox is **unchecked**
5. **Expected**: Shipping fields are **hidden**

#### Test Case 11.3: Test With Saved Shipping Address
1. Login as user with saved shipping address
2. Go to checkout
3. **Expected**: Checkbox auto-checks (JavaScript detects saved address)
4. **Expected**: Shipping fields visible

#### Test Case 11.4: Test Manual Check/Uncheck
1. Check checkbox manually
2. **Expected**: Shipping fields appear
3. Uncheck checkbox
4. **Expected**: Shipping fields hide

**Browser Console Check**:
```javascript
// Check if checkbox is unchecked on load
$('#ship-to-different-address-checkbox').is(':checked')
// Expected: false (on fresh checkout)
```

---

## Automated Testing

### PHP Unit Tests

The plugin includes PHPUnit tests for all major components. To run tests:

```bash
cd /home/samuel/local-sites/build-dev/app/public/wp-content/plugins/rcp-content-filter-utility

# Run all tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit tests/test-content-filtering.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### JavaScript Unit Tests

Jest tests for JavaScript components:

```bash
# Install dependencies
npm install

# Run all JS tests
npm test

# Run specific test
npm test -- loqate-address-capture.test.js

# Run with coverage
npm test -- --coverage
```

---

## Pre-Production Checklist

### Configuration Review
- [ ] **API Keys**: Loqate API key configured (constant or option)
- [ ] **Post Types**: Correct post types selected for filtering
- [ ] **Product Slug**: Partner+ product exists with slug `partner-plus`
- [ ] **User Roles**: "Partner Plus Pending" and "Partner Plus" roles exist
- [ ] **Payment Gateway**: WooCommerce payment gateway configured for live mode
- [ ] **Email Testing**: Password reset emails work correctly
- [ ] **Caching**: All caches cleared before go-live

### Security Review
- [ ] **Input Sanitization**: All user inputs sanitized
- [ ] **Output Escaping**: All outputs escaped
- [ ] **Nonce Verification**: Forms protected with nonces
- [ ] **Capability Checks**: Admin actions check `manage_options`
- [ ] **SQL Injection**: No raw SQL queries without preparation

### Performance Review
- [ ] **Caching**: DOM elements cached where possible
- [ ] **Lazy Loading**: Shipping Loqate only loads when needed
- [ ] **Debouncing**: Search inputs debounced to reduce API calls
- [ ] **DB Queries**: Batched queries used for options
- [ ] **Asset Loading**: Scripts only enqueued on relevant pages

### Browser Compatibility
- [ ] **Chrome**: All features work
- [ ] **Firefox**: All features work
- [ ] **Safari**: All features work
- [ ] **Edge**: All features work
- [ ] **Mobile Safari**: Checkout works on iOS
- [ ] **Mobile Chrome**: Checkout works on Android

### Debug Cleanup
- [ ] **WP_DEBUG**: Set to `false` in production
- [ ] **Error Logging**: Disable display, keep logging
- [ ] **Console Logs**: Remove or conditionally disable debug logs
- [ ] **Test Data**: Remove test orders, users, affiliates

### Backup
- [ ] **Database**: Full database backup taken
- [ ] **Files**: Full file backup taken
- [ ] **Rollback Plan**: Documented steps to rollback if needed

---

## Troubleshooting

### Loqate Dropdown Not Appearing

**Symptoms**: Type in address field, no dropdown shows

**Checks**:
1. **Console**: `[Loqate] API key not configured` → Add API key
2. **Console**: `pca is undefined` → SDK not loading
3. **Network**: 401 error → Invalid API key
4. **Network**: 429 error → Quota exceeded

**Fixes**:
- Hard refresh: `Ctrl+Shift+R` (3 times)
- Verify API key in settings
- Check Loqate dashboard for quota
- Test with incognito mode (disable ad blockers)

### Content Filtering Not Working

**Symptoms**: Restricted posts still visible

**Checks**:
1. **Settings**: Post types selected in admin?
2. **RCP**: Restrictions properly configured on posts?
3. **User**: Is user logged in with correct membership?
4. **Admin**: Are you testing as admin? (Admins see all content)

**Fixes**:
- Test as guest or regular user (not admin)
- Verify RCP restrictions on post edit screen
- Check filter priority if conflicts with other plugins

### Partner+ Affiliate Not Created

**Symptoms**: Order completes but no affiliate account

**Checks**:
1. **Product Slug**: Is it exactly `partner-plus`?
2. **Order Status**: Did order reach "Processing" or "Completed"?
3. **User Account**: Does customer have a user account?
4. **AffiliateWP**: Is plugin active?
5. **Debug Log**: Check for errors

**Fixes**:
```bash
# Check product slug
wp post list --post_type=product --name=partner-plus

# Check order status
wp wc order get {order_id} --field=status

# Check debug log
grep "BL Auto Affiliate" wp-content/debug.log | tail -20
```

### Checkout Validation Too Strict

**Symptoms**: Valid addresses rejected

**Checks**:
- What characters are being rejected?
- Is it an email field? (@ should be allowed)

**Fixes**:
- Email fields allow @ symbol by default
- Allowed characters: A-Z, 0-9, space, `-.,\'/#()&+_%`
- If you need additional characters, modify `rcf_is_ascii_only()` function

### LearnPress Template Not Loading

**Symptoms**: Blank page or broken layout in course context

**Checks**:
1. **Fix Enabled**: Is LearnPress fix enabled in settings?
2. **Plugins**: Are LearnPress and Elementor both active?
3. **Template**: Does lesson have an Elementor template assigned?
4. **URL**: Is URL pattern correct? `/courses/{slug}/lessons/{slug}/`

**Fixes**:
- Enable fix in settings
- Assign Elementor template to lesson post type
- Check Fix Status box in settings for diagnostic info

---

## Summary

This testing guide covers:
- ✅ 11 major feature sets
- ✅ 50+ individual test cases
- ✅ Prerequisites and environment setup
- ✅ Automated testing instructions
- ✅ Pre-production checklist
- ✅ Troubleshooting guide

**Before Production Deployment**:
1. Complete all test cases in dev environment
2. Run automated tests (PHP + JS)
3. Complete pre-production checklist
4. Take full backup
5. Monitor debug log for first 24 hours after deployment

**Estimated Testing Time**: 4-6 hours for complete testing

**Questions/Issues**: Check `debug.log` first, then review troubleshooting section
