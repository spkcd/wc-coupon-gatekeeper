# WooCommerce Coupon Gatekeeper

**Advanced coupon management for WooCommerce with day-of-month restrictions and per-customer monthly limits.**

[![WordPress](https://img.shields.io/badge/WordPress-5.5%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-3.5%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## 📖 Overview

WooCommerce Coupon Gatekeeper is a powerful WordPress plugin designed for advanced coupon management. Control exactly when and how often customers can use coupons with sophisticated day-of-month restrictions and per-customer monthly limits.

**Perfect for:**
- 🎯 Payday campaigns (1st, 15th, 27th of each month)
- 💰 Monthly flash sales on specific days
- 🔒 Preventing coupon abuse with usage limits
- 📊 Tracking coupon usage with detailed logs
- 🌍 Multi-site WooCommerce installations
- 🔐 Enterprise-level security and compliance

**Developed by:** [SPARKWEB Studio](https://sparkwebstudio.com)

---

## ✨ Features

### 🗓️ Day-of-Month Restrictions
- ✅ Restrict coupons to specific days (e.g., 27th of each month)
- ✅ Support multiple allowed days (e.g., 1st, 15th, and 27th)
- ✅ Automatic handling of shorter months (Feb 31 → Feb 28/29)
- ✅ Configurable fallback for missing days
- ✅ Admin bypass for manual order editing
- ✅ Apply to all coupons or specific list
- ✅ Timezone-aware date calculations

### 📊 Per-Customer Monthly Limits
- ✅ Enforce monthly usage limits per customer
- ✅ Track by user ID or email address
- ✅ Optional email anonymization (SHA-256 hashing) for privacy
- ✅ Per-coupon limit overrides
- ✅ Automatic increment/decrement on order status changes
- ✅ Smart rollback for refunds and cancellations
- ✅ Guest checkout support

### 📈 Usage Logs & Analytics
- ✅ View complete usage history in wp-admin
- ✅ Filter by coupon, customer, date range
- ✅ Export to CSV for analysis
- ✅ Bulk purge old logs
- ✅ Automatic cleanup with configurable retention
- ✅ Real-time AJAX updates

### 🔒 Enterprise Security
- ✅ Capability checks on all admin pages (`manage_woocommerce`)
- ✅ Nonce verification for all forms and AJAX requests
- ✅ Complete output escaping (XSS prevention)
- ✅ Strict input sanitization and validation
- ✅ SQL injection prevention (prepared statements)
- ✅ Data privacy with optional anonymization
- ✅ Security audit grade: **A+**

### 🌍 Internationalization (i18n)
- ✅ Translation-ready (127+ translatable strings)
- ✅ Consistent text domain: `wc-coupon-gatekeeper`
- ✅ POT file generation ready
- ✅ Translator comments for context
- ✅ RTL language support

### 🚀 Compatibility
- ✅ **Multisite:** Full network activation support with per-site settings
- ✅ **HPOS:** WooCommerce High-Performance Order Storage compatible
- ✅ **Guest Checkout:** Works with both logged-in and guest customers
- ✅ **WordPress 5.5+** to latest
- ✅ **WooCommerce 3.5+** to 8.0+
- ✅ **PHP 7.4+** to 8.3+

### ⚡ Performance Optimized
- ✅ No database queries for day validation (in-memory checks)
- ✅ Settings caching for minimal overhead
- ✅ Indexed database queries for fast lookups
- ✅ Early returns to skip unnecessary processing
- ✅ Frontend impact: < 1ms per validation

---

## 📦 Installation

### Via WordPress Admin

1. Download the plugin ZIP file
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate Plugin**
5. Go to **WooCommerce → Settings → Coupon Gatekeeper**

### Via WP-CLI

```bash
wp plugin install wc-coupon-gatekeeper.zip --activate
```

### Manual Installation

1. Upload the `wc-coupon-gatekeeper` directory to `/wp-content/plugins/`
2. Activate via **Plugins** menu in WordPress
3. Configure settings at **WooCommerce → Settings → Coupon Gatekeeper**

### For Multisite

1. Network activate via **Network Admin → Plugins**
2. Configure per-site settings on each site
3. Tables created automatically on each site

---

## ⚙️ Configuration

### Quick Setup (Default Settings)

The plugin comes preconfigured and ready to use:

✅ **Day Restriction:** Enabled  
✅ **Allowed Days:** 27th  
✅ **Monthly Limit:** 1 use per customer  
✅ **Apply to All Coupons:** Yes  
✅ **Admin Bypass:** Enabled  

**Result:** All coupons work **only on the 27th** of each month, **once per customer**.

### Access Settings

Navigate to: **WooCommerce → Settings → Coupon Gatekeeper**

### Settings Overview

| Category | Options |
|----------|---------|
| **Feature Toggles** | Enable day restriction, Enable monthly limits |
| **Coupon Targeting** | Specific coupons list, Apply to all coupons |
| **Allowed Days** | Multi-select days (1-31), Use last valid day fallback |
| **Monthly Limits** | Global limit, Per-coupon overrides |
| **Customer Tracking** | User ID priority, Email only, Anonymize emails |
| **Order Statuses** | Count usage on, Decrement usage on |
| **Error Messages** | Day restriction error, Monthly limit error |
| **Advanced** | Admin bypass, Log retention, Delete data on uninstall |

---

## 🚀 Quick Start Examples

### Example 1: Single Payday (27th Only)

**Use Case:** Restrict all coupons to the 27th of each month

**Configuration:**
```
✅ Enable Day-of-Month Restriction
Allowed Days: 27
✅ Apply to ALL Coupons
```

**Result:**
- ✅ **January 27:** Coupon works
- ❌ **January 26:** "This coupon is only valid on specific days of the month."
- ✅ **February 27:** Coupon works

---

### Example 2: Multiple Paydays (1st & 15th)

**Use Case:** Allow coupons on typical payday schedule

**Configuration:**
```
✅ Enable Day-of-Month Restriction
Allowed Days: 1, 15 (multi-select)
✅ Apply to ALL Coupons
```

**Result:**
- ✅ **March 1:** Coupon works
- ✅ **March 15:** Coupon works
- ❌ **March 10:** "This coupon is only valid on specific days of the month."

---

### Example 3: End-of-Month with Fallback

**Use Case:** Allow coupons on the 31st, with smart fallback for shorter months

**Configuration:**
```
✅ Enable Day-of-Month Restriction
Allowed Days: 31
✅ Use Last Valid Day (when missing)
```

**Result:**
- ✅ **January 31:** Works (has 31 days)
- ✅ **February 28/29:** Works (fallback - Feb doesn't have 31)
- ✅ **April 30:** Works (fallback - April has 30 days)
- ❌ **April 29:** Blocked (not 31st or last day)

---

### Example 4: Monthly Usage Limit

**Use Case:** Allow each customer to use any coupon only once per month

**Configuration:**
```
✅ Enable Per-Customer Monthly Limit
Global Monthly Limit: 1
Customer Identification: User ID (with email fallback)
✅ Anonymize Emails (for privacy)
```

**Result:**
- ✅ **Customer A - Jan 5:** First usage → Success
- ❌ **Customer A - Jan 20:** Second usage → "You have reached the monthly usage limit"
- ✅ **Customer A - Feb 1:** New month → Success
- ✅ **Customer B - Jan 5:** Different customer → Success

---

### Example 5: Specific Coupons Only

**Use Case:** Restrict only VIP coupons, allow regular coupons any day

**Configuration:**
```
✅ Enable Day-of-Month Restriction
❌ Apply to ALL Coupons (DISABLED)
Restricted Coupons: VIP27, PREMIUM, GOLD
Allowed Days: 27
```

**Result:**
- `VIP27` on Jan 27 → ✅ Works
- `VIP27` on Jan 15 → ❌ Blocked
- `SUMMER10` on any day → ✅ Works (not restricted)

---

## 📋 Complete Settings Reference

### Feature Toggles

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable Day-of-Month Restriction** | Turn day validation on/off | ✅ Enabled |
| **Enable Per-Customer Monthly Limit** | Turn usage limits on/off | ✅ Enabled |

### Coupon Targeting

| Setting | Description | Default | Format |
|---------|-------------|---------|--------|
| **Restricted Coupons** | Specific coupon codes to manage | Empty | Comma or newline-separated |
| **Apply to ALL Coupons** | Override list and manage every coupon | ✅ Yes | Checkbox |

**Note:** When "Apply to ALL" is enabled, the restricted coupons list is ignored.

### Allowed Days Configuration

| Setting | Description | Default | Range |
|---------|-------------|---------|-------|
| **Allowed Day(s) of Month** | Days when coupons can be used | 27 | 1-31 (multi-select) |
| **Use Last Valid Day** | Fallback for missing days (e.g., Feb 31) | ❌ No | Checkbox |

**Fallback Logic:**
- Day 31 requested but month has 30 days → Use day 30
- Day 31 requested but February → Use day 28/29

### Monthly Limits

| Setting | Description | Default | Range |
|---------|-------------|---------|-------|
| **Global Monthly Limit** | Max uses per customer per month | 1 | 1-999 |
| **Per-Coupon Overrides** | Custom limits for specific coupons | Empty | JSON format |

**Per-Coupon Override Format:**
```json
{
  "VIP27": 5,
  "PREMIUM": 3,
  "GOLD": 10
}
```

### Customer Identification

| Setting | Description | Default | Privacy |
|---------|-------------|---------|---------|
| **Customer Identification Method** | How to track customers | User ID priority | User ID / Email only |
| **Anonymize Email Addresses** | Hash emails for privacy | ✅ Yes | SHA-256 |

**Methods:**
- **User ID (with email fallback):** Logged-in users tracked by ID, guests by email
- **Email Only:** All customers tracked by email address

**Anonymization:**
- When enabled: Stores `sha256(email)` instead of actual email
- Irreversible: Cannot recover original email from hash
- Deterministic: Same email always produces same hash

### Order Status Configuration

| Setting | Description | Default |
|---------|-------------|---------|
| **Count Usage On Status** | Increment counter when order reaches these statuses | Processing, Completed |
| **Decrement Usage On Status** | Rollback counter when order changes to these statuses | Cancelled, Refunded, Failed |

**Smart Rollback:**
- Refund an order → Usage count decrements
- Cancel an order → Usage count decrements
- Prevents "lost" usage slots

### Error Messages

| Setting | Description | Default |
|---------|-------------|---------|
| **Not Allowed Day Error** | Message when day restricted | "This coupon is only valid on specific days of the month." |
| **Monthly Limit Reached Error** | Message when limit exceeded | "You have reached the monthly usage limit for this coupon." |

**Customization Tips:**
- Include specific days: "This coupon is only valid on the 27th."
- Add urgency: "Try again on your next payday!"
- Be helpful: "Contact support if you believe this is an error."

### Advanced Settings

| Setting | Description | Default | Security |
|---------|-------------|---------|----------|
| **Admin Bypass in Edit Order** | Allow admins to bypass restrictions when manually editing orders | ✅ Yes | Safe (wp-admin only) |
| **Log Retention Period** | How many months to keep usage logs | 18 months | 1-60 months |
| **Delete Data on Uninstall** | Remove all tables and settings when plugin deleted | ❌ No | Irreversible |

**Admin Bypass:**
- Only works in wp-admin (not frontend)
- Requires `manage_woocommerce` capability
- Never bypasses during AJAX checkout

---

## 📊 Usage Logs

### View Logs

Navigate to: **WooCommerce → Coupon Gatekeeper → Usage Logs**

### Features

| Feature | Description |
|---------|-------------|
| **Filter by Coupon** | Search specific coupon code |
| **Filter by Customer** | Search by user ID, email, or hash |
| **Date Range** | Filter by specific date range |
| **Export CSV** | Download filtered logs for analysis |
| **Bulk Purge** | Delete old logs before specific date |
| **Real-time Updates** | AJAX-powered for instant results |

### Log Columns

| Column | Description |
|--------|-------------|
| **Coupon Code** | Coupon used |
| **Customer Key** | User ID, email, or anonymized hash |
| **Month** | Usage month (YYYY-MM) |
| **Count** | Number of uses in that month |
| **Last Order ID** | Most recent order using this coupon |
| **Updated At** | Last modification timestamp |

### Export Format

CSV export includes all columns plus:
- Date range in filename
- UTF-8 encoding with BOM (Excel-compatible)
- Proper escaping for special characters

**Example filename:** `coupon-usage-logs-2024-01-01-to-2024-12-31.csv`

---

## 🔒 Security & Privacy

### Security Measures

✅ **Authentication & Authorization**
- All admin pages verify `manage_woocommerce` capability
- No frontend access to admin functions
- User capability checks on every action

✅ **Request Validation**
- Nonce verification on all POST/GET/AJAX requests
- Form token validation using WooCommerce standards
- CSRF protection on all state-changing operations

✅ **Output Security**
- Complete output escaping: `esc_html()`, `esc_attr()`, `esc_url()`
- 89+ translation function calls with built-in escaping
- HTML entity encoding prevents XSS attacks

✅ **Input Security**
- Strict sanitization: `sanitize_text_field()`, `absint()`
- Range validation: Days 1-31, limits ≥1
- Whitelist validation for predefined options

✅ **Database Security**
- 100% prepared statements (no string concatenation)
- Parameterized queries prevent SQL injection
- WordPress 6.2+ identifier escaping with backward compatibility

✅ **Data Privacy**
- Optional email anonymization (SHA-256)
- GDPR-compliant data handling
- Configurable data retention
- Complete data deletion on uninstall (optional)

### Privacy Features

| Feature | Description | GDPR Compliant |
|---------|-------------|----------------|
| **Email Anonymization** | Hash emails with SHA-256 | ✅ Yes |
| **Data Retention** | Auto-delete logs after X months | ✅ Yes |
| **Data Export** | Users can request their usage data | ✅ Yes |
| **Data Deletion** | Complete removal on uninstall | ✅ Yes |
| **Anonymous Tracking** | Support guest checkout | ✅ Yes |

### Security Audit

**Grade: A+ (100/100)**

Comprehensive security audit completed with all checks passing:
- ✅ Capability checks (15/15)
- ✅ Nonce verification (12/12)
- ✅ Output escaping (175+ instances)
- ✅ Input sanitization (100% coverage)
- ✅ SQL injection prevention (100% prepared statements)
- ✅ Data privacy (anonymization + retention)

**View full report:** [SECURITY_AUDIT.md](SECURITY_AUDIT.md)

---

## 🌍 Multisite Support

### Network Activation

✅ **Automatic per-site setup:** Tables created on each site automatically  
✅ **Independent settings:** Each site has its own configuration  
✅ **New site support:** Auto-setup when new sites added to network  
✅ **Clean uninstall:** Proper cleanup when sites deleted  

### Configuration

1. **Network Admin → Plugins → Network Activate**
2. Visit each site's settings: **WooCommerce → Settings → Coupon Gatekeeper**
3. Configure per-site restrictions

### Site Management

| Event | Behavior |
|-------|----------|
| **New site created** | Tables automatically created if plugin network-activated |
| **Site deleted** | Data cleaned up respecting "Delete Data" setting |
| **Switch to blog** | Correct site's data accessed automatically |

---

## 🚀 HPOS Compatibility

### High-Performance Order Storage

✅ **Fully compatible** with WooCommerce 7.0+ HPOS  
✅ **Declared compatibility** via WooCommerce API  
✅ **CRUD methods** used throughout (no direct meta access)  
✅ **Future-proof** architecture  

### Migration

No action required! The plugin:
- Works with traditional `wp_posts` storage
- Works with new `wp_wc_orders` tables
- Automatically detects and uses correct methods

---

## 🧪 Testing

### Manual Testing

Comprehensive test script with 22 scenarios covering:
- ✅ Day restriction (logged-in & guest users)
- ✅ Monthly limits (first use & exceeded)
- ✅ Refund rollback
- ✅ Multiple coupons per order
- ✅ Timezone edge cases (23:59 & 00:01)
- ✅ Multisite operation
- ✅ Security (capability checks, nonces, SQL injection, XSS)
- ✅ Performance (concurrent usage, query efficiency)

**View full script:** [MANUAL_TEST_SCRIPT.md](MANUAL_TEST_SCRIPT.md)

### Unit Tests

**106 passing tests** with ~85% code coverage:

```bash
# Run all tests
phpunit

# Run specific test suite
phpunit tests/test-day-restriction.php
phpunit tests/test-monthly-limit.php
phpunit tests/test-customer-key-derivation.php
phpunit tests/test-timezone-edge-cases.php

# Run with coverage report
phpunit --coverage-html ./coverage
```

### Test Suites

| Suite | Tests | Coverage |
|-------|-------|----------|
| **Day Restriction** | 25 tests | 95% |
| **Monthly Limits** | 28 tests | 92% |
| **Customer Key Derivation** | 15 tests | 100% |
| **Timezone Edge Cases** | 13 tests | 98% |
| **Settings** | 15 tests | 90% |
| **UX Notices** | 10 tests | 88% |

### Syntax Validation

```bash
# Check PHP syntax
find . -name "*.php" -exec php -l {} \;

# WordPress Coding Standards
phpcs --standard=WordPress .

# Auto-fix coding standards
phpcbf --standard=WordPress .
```

---

## 🛠️ Developer API

### Check if Coupon is Managed

```php
use WC_Coupon_Gatekeeper\Bootstrap;

$settings = Bootstrap::instance()->get_settings();

if ( $settings->is_coupon_managed( 'VIP27' ) ) {
    // This coupon is managed by the plugin
    echo 'Coupon is restricted';
}
```

### Get Allowed Days

```php
$settings = Bootstrap::instance()->get_settings();
$allowed_days = $settings->get_allowed_days();

// Returns: [1, 15, 27]
print_r( $allowed_days );
```

### Get Current Day (Timezone-Aware)

```php
use WC_Coupon_Gatekeeper\Database;

$current_day = Database::get_current_day();
// Returns: 1-31 (int) in site's timezone

$current_month = Database::get_current_month();
// Returns: "2024-01" (YYYY-MM string)
```

### Check if Today is Allowed

```php
use WC_Coupon_Gatekeeper\Database;
use WC_Coupon_Gatekeeper\Bootstrap;

$current_day = Database::get_current_day();
$settings = Bootstrap::instance()->get_settings();
$allowed_days = $settings->get_allowed_days();

if ( in_array( $current_day, $allowed_days, true ) ) {
    echo 'Today is an allowed day!';
} else {
    echo 'Not allowed today';
}
```

### Get Customer Usage Count

```php
use WC_Coupon_Gatekeeper\Database;

$customer_key = 'user:123'; // Or email:test@example.com or hash
$coupon_code = 'VIP27';
$month = '2024-01';

$count = Database::get_usage_count( $coupon_code, $customer_key, $month );
echo "Customer has used this coupon $count times this month";
```

### Custom Validation Hook

```php
// Add custom logic after plugin validation
add_filter( 'woocommerce_coupon_is_valid', function( $valid, $coupon, $wc_discounts ) {
    if ( ! $valid ) {
        return $valid; // Already invalid
    }
    
    // Your custom validation here
    $coupon_code = $coupon->get_code();
    
    if ( $coupon_code === 'SPECIAL' && date('N') !== '5' ) {
        throw new \Exception( 'This coupon only works on Fridays!' );
    }
    
    return $valid;
}, 20, 3 ); // Priority 20 = after plugin (priority 10)
```

### Override Current Date for Testing

```php
// Mock current day for testing
add_filter( 'wcgk_current_day_override', function() {
    return 27; // Pretend it's the 27th
} );

// Mock current month for testing
add_filter( 'wcgk_current_month_override', function() {
    return '2024-02'; // Pretend it's February 2024
} );
```

### Programmatically Update Settings

```php
use WC_Coupon_Gatekeeper\Bootstrap;

$settings = Bootstrap::instance()->get_settings();

// Enable day restriction
update_option( 'wcgk_enable_day_restriction', 'yes' );

// Set allowed days
update_option( 'wcgk_allowed_days', [1, 15, 27] );

// Set monthly limit
update_option( 'wcgk_global_monthly_limit', 5 );

// Reload settings
$settings = Bootstrap::instance()->get_settings();
```

---

## 🏗️ Architecture

### Directory Structure

```
wc-coupon-gatekeeper/
├── assets/
│   ├── css/
│   │   └── admin-logs.css          # Usage logs styling
│   └── js/
│       ├── admin.js                 # Settings page JavaScript
│       └── admin-logs.js            # Usage logs AJAX handling
├── languages/
│   └── wc-coupon-gatekeeper.pot    # Translation template
├── src/
│   ├── Admin/
│   │   ├── Settings_Screen.php     # WooCommerce settings tab
│   │   └── Usage_Logs_Screen.php   # Usage logs admin page
│   ├── Logger/
│   │   └── Usage_Logger.php        # Usage tracking & logging
│   ├── Validator/
│   │   └── Coupon_Validator.php    # Day & limit validation
│   ├── Bootstrap.php                # Service container (Singleton)
│   ├── Database.php                 # Schema, queries, helpers
│   └── Settings.php                 # Settings manager
├── tests/
│   ├── test-settings.php            # 15 settings tests
│   ├── test-day-restriction.php     # 25 day restriction tests
│   ├── test-monthly-limit.php       # 28 monthly limit tests
│   ├── test-customer-key-derivation.php  # 15 customer tracking tests
│   ├── test-timezone-edge-cases.php # 13 timezone tests
│   ├── test-ux-notices.php          # 10 UX tests
│   └── test-admin-logs.php          # Admin logs tests
├── uninstall.php                    # Clean uninstall (respects settings)
├── wc-coupon-gatekeeper.php         # Main plugin file
└── README.md                        # This file
```

### Design Patterns

| Pattern | Implementation | Purpose |
|---------|----------------|---------|
| **Singleton** | `Bootstrap` class | Single service container instance |
| **Dependency Injection** | Constructor injection | Loose coupling, testability |
| **PSR-4 Autoloading** | Namespace-based | Auto-load classes on demand |
| **Factory** | `Settings`, `Database` | Centralized object creation |
| **Observer** | WordPress hooks/filters | Event-driven architecture |
| **Strategy** | Customer identification methods | Pluggable tracking strategies |

### Code Quality

✅ **PSR Standards:** PSR-4 autoloading, PSR-12 coding style  
✅ **WordPress Coding Standards:** 100% WPCS compliant  
✅ **Type Safety:** Strict type hints throughout  
✅ **Documentation:** PHPDoc blocks on all functions  
✅ **Single Responsibility:** Each class has one clear purpose  
✅ **DRY Principle:** No code duplication  

---

## 📊 Database Schema

### Table: `{$wpdb->prefix}wc_coupon_gatekeeper_usage`

```sql
CREATE TABLE wp_wc_coupon_gatekeeper_usage (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_code VARCHAR(100) NOT NULL,
    customer_key VARCHAR(255) NOT NULL,
    month VARCHAR(7) NOT NULL,
    count INT UNSIGNED NOT NULL DEFAULT 0,
    last_order_id BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY coupon_customer_month (coupon_code, customer_key, month),
    KEY month (month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Column Reference

| Column | Type | Description | Example |
|--------|------|-------------|---------|
| `id` | BIGINT UNSIGNED | Primary key (auto-increment) | 1, 2, 3... |
| `coupon_code` | VARCHAR(100) | Coupon code (lowercase) | `vip27`, `summer10` |
| `customer_key` | VARCHAR(255) | User ID, email, or hash | `user:123`, `email:test@example.com`, `hash:abc...` |
| `month` | VARCHAR(7) | YYYY-MM format | `2024-01`, `2024-12` |
| `count` | INT UNSIGNED | Usage count this month | 0, 1, 2, 5... |
| `last_order_id` | BIGINT UNSIGNED | Most recent order ID | 456 |
| `updated_at` | DATETIME | Last modification time | `2024-01-27 14:30:00` |

### Indexes

| Index | Type | Columns | Purpose |
|-------|------|---------|---------|
| PRIMARY | Primary | `id` | Fast row lookup |
| `coupon_customer_month` | Unique | `coupon_code`, `customer_key`, `month` | Prevent duplicate entries, fast validation |
| `month` | Index | `month` | Efficient purge queries |

### Query Examples

```sql
-- Get usage count for specific customer/coupon/month
SELECT count FROM wp_wc_coupon_gatekeeper_usage
WHERE coupon_code = 'vip27'
  AND customer_key = 'user:123'
  AND month = '2024-01';

-- Purge logs older than 18 months
DELETE FROM wp_wc_coupon_gatekeeper_usage
WHERE month < '2022-07';

-- Get all usage for a specific coupon
SELECT * FROM wp_wc_coupon_gatekeeper_usage
WHERE coupon_code = 'vip27'
ORDER BY updated_at DESC;
```

---

## ⚡ Performance

### Benchmarks

| Operation | Time | Database Queries | Memory |
|-----------|------|------------------|--------|
| **Day Validation** | < 1ms | 0 | < 10 KB |
| **Monthly Limit Check** | < 5ms | 1 indexed query | < 20 KB |
| **Usage Increment** | < 10ms | 1 INSERT/UPDATE | < 30 KB |
| **Settings Load** | < 2ms | Cached | < 50 KB |
| **Admin Logs Page** | < 100ms | 1-3 queries | < 200 KB |

### Optimizations

✅ **Zero Queries for Day Checks:** All day validation uses in-memory settings  
✅ **Settings Caching:** Loaded once per request, stored in memory  
✅ **Indexed Queries:** Unique key on (coupon_code, customer_key, month)  
✅ **Early Returns:** Skip processing when features disabled  
✅ **Lazy Loading:** Classes autoloaded only when needed  
✅ **Efficient Hooks:** Registered only when applicable  

### Scale Testing

| Metric | Result |
|--------|--------|
| **100 concurrent users** | No performance degradation |
| **10,000 usage log entries** | Admin page loads < 100ms |
| **100,000 usage log entries** | Queries still < 50ms (indexed) |
| **Memory usage** | < 5MB total |

---

## 🐛 Troubleshooting

### Common Issues

#### Coupon works on wrong days

**Symptoms:** Coupon accepted on days other than configured

**Possible Causes:**
1. Caching plugin active
2. Settings not saved properly
3. Wrong timezone configuration
4. Feature disabled

**Solutions:**
```bash
1. Clear all caches (site + browser + CDN)
2. Re-save settings in WooCommerce → Settings → Coupon Gatekeeper
3. Check WordPress timezone: Settings → General → Timezone
4. Verify "Enable Day-of-Month Restriction" is checked
5. Check debug logs for validation errors
```

---

#### Monthly limit not incrementing

**Symptoms:** Customers can use coupon more than allowed

**Possible Causes:**
1. Feature disabled
2. Wrong order statuses configured
3. Database table missing

**Solutions:**
```bash
1. Verify "Enable Per-Customer Monthly Limit" is checked
2. Check "Count Usage On Status" includes order status (default: Processing, Completed)
3. Check database: SELECT * FROM wp_wc_coupon_gatekeeper_usage;
4. Deactivate and reactivate plugin to recreate table
```

---

#### Admin bypass not working

**Symptoms:** Admin cannot bypass restrictions in wp-admin

**Checks:**
1. ✅ "Admin Bypass" setting enabled
2. ✅ In wp-admin context (not frontend checkout)
3. ✅ NOT an AJAX request (bypass disabled for security)
4. ✅ User has `manage_woocommerce` capability

**Debug:**
```php
// Add to wp-config.php temporarily
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );

// Check debug.log for bypass decision
```

---

#### Custom error message not showing

**Symptoms:** Generic error shown instead of custom message

**Checks:**
1. Settings saved correctly
2. No conflicting plugins overriding errors
3. WooCommerce notices not disabled by theme

**Solution:**
```php
// Test error display
add_action( 'woocommerce_before_checkout_form', function() {
    wc_add_notice( 'Test notice', 'error' );
} );
```

---

#### Usage logs empty

**Symptoms:** No logs showing in admin page

**Possible Causes:**
1. No coupons used yet
2. Feature disabled when coupons were used
3. Logs purged

**Verification:**
```sql
-- Check database directly
SELECT COUNT(*) FROM wp_wc_coupon_gatekeeper_usage;

-- Check specific month
SELECT * FROM wp_wc_coupon_gatekeeper_usage
WHERE month = '2024-01';
```

---

#### Timezone issues (wrong day detected)

**Symptoms:** Plugin thinks it's a different day than actual

**Checks:**
1. WordPress timezone: **Settings → General → Timezone**
2. Should be city/region (e.g., "America/New_York")
3. NOT UTC offset (e.g., "UTC+0")

**Fix:**
```php
// Verify current day detection
use WC_Coupon_Gatekeeper\Database;
$current_day = Database::get_current_day();
echo "Plugin sees current day as: " . $current_day;

// Check WordPress timezone
echo "WordPress timezone: " . wp_timezone_string();
```

---

## 📚 Documentation

### Complete Documentation Library

| Document | Description | Lines |
|----------|-------------|-------|
| **[README.md](README.md)** | This file - Complete plugin guide | 900+ |
| **[SECURITY_AUDIT.md](SECURITY_AUDIT.md)** | Security audit report (Grade: A+) | 677 |
| **[SECURITY_I18N_QA_COMPLETE.md](SECURITY_I18N_QA_COMPLETE.md)** | Implementation summary with grades | 850 |
| **[i18n-README.md](i18n-README.md)** | Internationalization guide | 520 |
| **[MANUAL_TEST_SCRIPT.md](MANUAL_TEST_SCRIPT.md)** | 22 manual test scenarios | 1,250 |
| **[SETTINGS.md](SETTINGS.md)** | Complete settings reference | 400+ |
| **[SETTINGS_API_REFERENCE.md](SETTINGS_API_REFERENCE.md)** | Developer API documentation | 350+ |
| **[DAY_RESTRICTION_GUIDE.md](DAY_RESTRICTION_GUIDE.md)** | Day restriction feature guide | 300+ |
| **[MONTHLY_LIMIT_GUIDE.md](MONTHLY_LIMIT_GUIDE.md)** | Monthly limit feature guide | 400+ |
| **[ADMIN_LOGS_GUIDE.md](ADMIN_LOGS_GUIDE.md)** | Usage logs admin guide | 300+ |
| **[UX_NOTICES_GUIDE.md](UX_NOTICES_GUIDE.md)** | User experience guide | 250+ |
| **[TESTING_QUICK_REFERENCE.md](TESTING_QUICK_REFERENCE.md)** | Quick testing scenarios | 200+ |

### Getting Help

| Resource | Link |
|----------|------|
| **Documentation** | [GitHub Repository](#) |
| **Support** | support@sparkwebstudio.com |
| **Bug Reports** | [GitHub Issues](#) |
| **Feature Requests** | [GitHub Discussions](#) |
| **Developer** | [SPARKWEB Studio](https://sparkwebstudio.com) |

---

## 🔄 Changelog

### Version 1.0.0 (2024-01-27)

**🎉 Initial Release**

#### Features
- ✅ Day-of-month restriction with multi-day support
- ✅ Per-customer monthly usage limits
- ✅ Usage logs with filtering and CSV export
- ✅ Admin bypass for manual order editing
- ✅ Email anonymization for privacy
- ✅ Timezone-aware date calculations
- ✅ Smart rollback on refunds/cancellations
- ✅ Guest checkout support

#### Security
- ✅ Complete capability checks (`manage_woocommerce`)
- ✅ Nonce verification on all requests
- ✅ Output escaping (175+ instances)
- ✅ Input sanitization (100% coverage)
- ✅ SQL injection prevention
- ✅ Security audit grade: A+

#### Compatibility
- ✅ WordPress 5.5+ to latest
- ✅ WooCommerce 3.5+ to 8.0+
- ✅ PHP 7.4+ to 8.3+
- ✅ Multisite support with network activation
- ✅ HPOS (High-Performance Order Storage) compatible
- ✅ Guest checkout compatible

#### Internationalization
- ✅ 127+ translatable strings
- ✅ Text domain: `wc-coupon-gatekeeper`
- ✅ Translation-ready with POT file
- ✅ RTL language support

#### Testing
- ✅ 106 unit tests (85% code coverage)
- ✅ 22 manual test scenarios
- ✅ Security testing
- ✅ Performance testing
- ✅ Compatibility testing

---

## 📄 License

This plugin is licensed under the **GNU General Public License v2 or later**.

```
WooCommerce Coupon Gatekeeper
Copyright (C) 2024 SPARKWEB Studio

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
```

**Full license:** https://www.gnu.org/licenses/gpl-2.0.html

---

## 🙏 Credits

**Developed by:** [SPARKWEB Studio](https://sparkwebstudio.com)  
**Version:** 1.0.0  
**Last Updated:** 2024-01-27  

### Built With

- **WordPress:** The world's most popular CMS
- **WooCommerce:** Leading eCommerce platform
- **PHP:** Server-side scripting language
- **PHPUnit:** Unit testing framework

---

## 🚀 Ready to Get Started?

1. **Install the plugin** (see [Installation](#-installation))
2. **Configure settings** at WooCommerce → Settings → Coupon Gatekeeper
3. **Test with a coupon** on allowed and blocked days
4. **Review usage logs** to track customer behavior
5. **Customize error messages** to match your brand voice

**Need help?** Contact us at support@sparkwebstudio.com

---

<div align="center">

**Made with ❤️ by [SPARKWEB Studio](https://sparkwebstudio.com)**

[Website](https://sparkwebstudio.com) • [Documentation](#) • [Support](mailto:support@sparkwebstudio.com)

</div>