# Monthly Limit Feature - Complete Guide

## Overview

The **Monthly Limit** feature restricts how many times a customer can use a managed coupon within a calendar month (YYYY-MM). This prevents coupon abuse while maintaining flexibility through configurable limits and smart customer identification.

---

## Table of Contents

1. [How It Works](#how-it-works)
2. [Customer Identification](#customer-identification)
3. [Counting Rules](#counting-rules)
4. [Configuration](#configuration)
5. [Validation Flow](#validation-flow)
6. [Usage Logging](#usage-logging)
7. [Fallback Re-check](#fallback-re-check)
8. [Testing Scenarios](#testing-scenarios)
9. [Edge Cases](#edge-cases)
10. [Troubleshooting](#troubleshooting)

---

## How It Works

### Basic Concept

```
Customer applies coupon → Check current month usage → Compare to limit → Allow/Block
```

- **Tracked by:** Coupon code + Customer key + Month (YYYY-MM)
- **Default limit:** 1 use per month per customer
- **Per-coupon overrides:** Configure different limits for specific coupons
- **Reset timing:** Automatically resets on first day of new month

### Example Scenarios

#### Scenario 1: Basic Usage (Limit = 1)
```
Jan 15: Customer uses 27OFF → ✅ Allowed (0/1 used)
Jan 20: Customer tries 27OFF again → ❌ Blocked (1/1 used)
Feb 1:  Customer uses 27OFF → ✅ Allowed (0/1 used in Feb)
```

#### Scenario 2: Per-Coupon Override (VIP10 limit = 3)
```
Jan 5:  Customer uses VIP10 → ✅ Allowed (0/3 used)
Jan 12: Customer uses VIP10 → ✅ Allowed (1/3 used)
Jan 19: Customer uses VIP10 → ✅ Allowed (2/3 used)
Jan 26: Customer tries VIP10 → ❌ Blocked (3/3 used)
```

#### Scenario 3: Cancellation Flow
```
Jan 15: Customer uses 27OFF → ✅ Allowed (count: 1)
Jan 16: Customer completes order → Count stays at 1
Jan 17: Customer tries 27OFF → ❌ Blocked (1/1 used)
Jan 18: Order cancelled → Count decrements to 0
Jan 19: Customer tries 27OFF → ✅ Allowed (0/1 used)
```

---

## Customer Identification

The plugin uses smart customer identification to track usage across sessions:

### Identification Priority

```php
if ( is_user_logged_in() && identification_method == 'user_id_priority' ) {
    return 'user:' . get_current_user_id();  // Format: user:123
}

if ( email_available ) {
    if ( anonymization_enabled ) {
        return 'hash:' . sha256( email + salt );  // Format: hash:abc123...
    } else {
        return 'email:' . lowercase( email );      // Format: email:customer@example.com
    }
}

return '';  // Provisional validation, re-check at order creation
```

### Customer Key Formats

| Type | Format | Example | Use Case |
|------|--------|---------|----------|
| **User ID** | `user:{ID}` | `user:42` | Logged-in customers (recommended) |
| **Email (plain)** | `email:{lowercase}` | `email:john@example.com` | Guest checkout, anonymization OFF |
| **Email (hashed)** | `hash:{sha256}` | `hash:a3f2b...` | Guest checkout, anonymization ON (GDPR) |

### Configuration Options

#### **User ID Priority** (Default)
- **Setting:** `customer_identification = 'user_id_priority'`
- **Behavior:** Use user ID for logged-in users, fall back to email for guests
- **Advantages:** Most accurate, prevents duplicate accounts
- **Disadvantages:** Guest and logged-in users tracked separately

#### **Email Only**
- **Setting:** `customer_identification = 'email_only'`
- **Behavior:** Always use email for identification
- **Advantages:** Tracks same person across logged-in/guest sessions
- **Disadvantages:** User can change email to reset limit

#### **Email Anonymization**
- **Setting:** `anonymize_email = true` (Default)
- **Behavior:** Hash email addresses for GDPR compliance
- **Advantages:** Privacy-compliant, secure
- **Disadvantages:** Can't manually lookup by email in database

---

## Counting Rules

### When Usage Increments

Usage count increments when an order transitions **into** one of these statuses:

**Default statuses:** `processing`, `completed`

```php
// Example transition flows that INCREMENT:
'pending'    → 'processing'  ✅ Increment
'on-hold'    → 'completed'   ✅ Increment
'pending'    → 'completed'   ✅ Increment
'processing' → 'completed'   ❌ No change (already counted)
```

### When Usage Decrements

Usage count decrements when an order transitions **into** one of these statuses:

**Default statuses:** `cancelled`, `refunded`

```php
// Example transition flows that DECREMENT:
'processing' → 'cancelled'  ✅ Decrement
'completed'  → 'refunded'   ✅ Decrement
'pending'    → 'cancelled'  ❌ No change (not counted yet)
```

### Important Rules

1. **Double-counting prevention:** Status transitions are tracked to avoid duplicate increments in same request
2. **Multiple coupons:** Each coupon tracked separately in same order
3. **Removed before payment:** If coupon removed before order reaches count status, no increment occurs
4. **Concurrency safety:** Uses `INSERT ... ON DUPLICATE KEY UPDATE` for atomic operations
5. **Zero floor:** Decrements never reduce count below 0

---

## Configuration

### Settings Screen

Navigate to: **WooCommerce → Settings → Coupon Gatekeeper**

### Monthly Limit Settings

```php
// Feature toggle
'enable_monthly_limit' => true,

// Default limit for all managed coupons
'default_monthly_limit' => 1,

// Per-coupon overrides
'coupon_limit_overrides' => [
    'vip10'     => 3,  // VIP customers get 3 uses
    'loyalty50' => 5,  // Loyalty members get 5 uses
],

// Customer identification
'customer_identification' => 'user_id_priority',  // or 'email_only'
'anonymize_email'         => true,

// Error message
'error_limit_reached' => "You've already used this coupon this month.",

// Order statuses that count
'count_usage_statuses'     => ['processing', 'completed'],
'decrement_usage_statuses' => ['cancelled', 'refunded'],
```

### Programmatic Access

```php
$settings = new Settings();

// Check if enabled
if ( $settings->is_monthly_limit_enabled() ) {
    // Get limit for specific coupon
    $limit = $settings->get_monthly_limit_for_coupon( 'test27' );
    
    // Get customer identification method
    $method = $settings->get_customer_identification();
    
    // Check anonymization
    if ( $settings->is_email_anonymization_enabled() ) {
        // Use hashed keys
    }
}
```

---

## Validation Flow

### At Cart/Checkout (Before Payment)

```
┌─────────────────────────────────────────────────┐
│ Customer applies coupon in cart                 │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ WooCommerce triggers:                           │
│ woocommerce_coupon_is_valid filter              │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Coupon_Validator::validate_coupon()             │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Is coupon already invalid?                      │
│ ├─ YES → Return invalid (don't override)        │
│ └─ NO  → Continue                               │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Is coupon managed by plugin?                    │
│ ├─ NO  → Return valid (pass through)            │
│ └─ YES → Continue                               │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Admin bypass active?                            │
│ ├─ YES → Return valid (allow admin editing)     │
│ └─ NO  → Continue                               │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Monthly limit enabled?                          │
│ ├─ NO  → Skip monthly check                     │
│ └─ YES → Continue                               │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Get customer key                                │
│ ├─ Logged in: user:{ID}                         │
│ ├─ Guest with email: email:{hash} or {plain}    │
│ └─ No email yet: '' (provisional pass)          │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Has customer key?                               │
│ ├─ NO  → Allow provisional validation           │
│ └─ YES → Check usage count                      │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Query database for current month usage          │
│ SELECT count WHERE coupon + customer + month    │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Get applicable limit                            │
│ ├─ Per-coupon override if exists                │
│ └─ Default limit otherwise                      │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ current_count >= limit?                         │
│ ├─ YES → Throw Exception (block coupon)         │
│ └─ NO  → Return valid (allow coupon)            │
└─────────────────────────────────────────────────┘
```

### At Order Creation (Fallback Re-check)

If customer key wasn't available during validation (guest without email in cart), re-validate after order creation:

```
┌─────────────────────────────────────────────────┐
│ Order created                                   │
│ woocommerce_checkout_order_processed            │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Get customer key from order billing email       │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ For each coupon in order:                       │
│ ├─ Is managed?                                  │
│ ├─ Is monthly limit enabled?                    │
│ └─ Check if limit exceeded                      │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ If limit exceeded:                              │
│ ├─ Remove coupon from order                     │
│ ├─ Add order note                               │
│ ├─ Recalculate totals                           │
│ └─ Show customer notice                         │
└─────────────────────────────────────────────────┘
```

---

## Usage Logging

### Database Schema

```sql
CREATE TABLE wp_wc_coupon_gatekeeper_usage (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    coupon_code varchar(100) NOT NULL,
    customer_key varchar(191) NOT NULL,
    month char(7) NOT NULL,              -- YYYY-MM format
    count int(10) unsigned NOT NULL DEFAULT 0,
    last_order_id bigint(20) unsigned DEFAULT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY coupon_customer_month (coupon_code, customer_key, month)
);
```

### Increment Logic

```php
// Atomic increment using INSERT ... ON DUPLICATE KEY UPDATE
INSERT INTO wp_wc_coupon_gatekeeper_usage 
    (coupon_code, customer_key, month, count, last_order_id, updated_at)
VALUES ('test27', 'user:42', '2024-01', 1, 12345, NOW())
ON DUPLICATE KEY UPDATE
    count = count + 1,
    last_order_id = VALUES(last_order_id),
    updated_at = VALUES(updated_at);
```

### Decrement Logic

```php
// Only decrement if count > 0
UPDATE wp_wc_coupon_gatekeeper_usage
SET 
    count = GREATEST(0, count - 1),
    last_order_id = 12346,
    updated_at = NOW()
WHERE coupon_code = 'test27'
    AND customer_key = 'user:42'
    AND month = '2024-01'
    AND count > 0;
```

### Order Status Hook

```php
add_action( 'woocommerce_order_status_changed', function( $order_id, $old_status, $new_status, $order ) {
    // Increment when transitioning into count status
    if ( in_array( $new_status, $count_statuses ) && ! in_array( $old_status, $count_statuses ) ) {
        increment_usage();
    }
    
    // Decrement when transitioning into decrement status (from count status)
    if ( in_array( $new_status, $decrement_statuses ) && in_array( $old_status, $count_statuses ) ) {
        decrement_usage();
    }
}, 10, 4 );
```

---

## Fallback Re-check

### Why It's Needed

In some checkout flows, customer email may not be available when coupon validation runs:
- Guest checkout with delayed email entry
- Custom checkout flows
- AJAX validation before email field filled

### How It Works

```php
// Hook into order creation
add_action( 'woocommerce_checkout_order_processed', 'fallback_recheck', 10, 1 );

function fallback_recheck( $order_id ) {
    $order = wc_get_order( $order_id );
    $customer_key = get_customer_key_from_order( $order );  // Now has billing email
    
    foreach ( $order->get_coupon_codes() as $coupon_code ) {
        if ( has_exceeded_limit( $coupon_code, $customer_key ) ) {
            // Remove coupon
            $order->remove_coupon( $coupon_code );
            
            // Add order note
            $order->add_order_note( "Coupon '$coupon_code' removed: Monthly limit exceeded" );
            
            // Recalculate
            $order->calculate_totals();
            $order->save();
            
            // Notify customer
            wc_add_notice( "Coupon '$coupon_code' was removed: Monthly limit exceeded", 'error' );
        }
    }
}
```

### Customer Experience

1. **Best case:** Email available during validation → Blocked immediately at cart
2. **Fallback case:** Email not available → Passes validation → Removed at order creation → Customer notified

---

## Testing Scenarios

### Scenario 1: Logged-in User - Basic Limit

```bash
# Setup
wp user create testuser test@example.com --role=customer
wp wc coupon create --code=TEST27 --discount_type=fixed_cart --amount=10

# Test
1. Login as testuser
2. Add product to cart
3. Apply TEST27 → ✅ Success (0/1 used)
4. Complete order
5. Check order status: processing
6. Try TEST27 again → ❌ Blocked "Monthly limit reached"
```

### Scenario 2: Guest Customer - Email Tracking

```bash
# Setup
Settings: anonymize_email = true

# Test
1. Add product to cart (not logged in)
2. Apply TEST27
3. Proceed to checkout
4. Enter email: guest@example.com
5. Complete order
6. Clear cart, add new product
7. Apply TEST27
8. Enter SAME email: guest@example.com → ❌ Blocked
9. Enter DIFFERENT email: other@example.com → ✅ Allowed
```

### Scenario 3: Cancellation Flow

```bash
# Test
1. Customer uses TEST27, completes order #100
2. Check usage: 1/1 used
3. Try TEST27 again → ❌ Blocked
4. Cancel order #100
5. Check usage: 0/1 used (decremented)
6. Try TEST27 again → ✅ Allowed
```

### Scenario 4: Per-Coupon Overrides

```bash
# Setup
Settings: coupon_limit_overrides = { 'vip10': 3 }

# Test
1. Apply VIP10 → ✅ Allowed
2. Complete order #101
3. Apply VIP10 → ✅ Allowed (1/3)
4. Complete order #102
5. Apply VIP10 → ✅ Allowed (2/3)
6. Complete order #103
7. Apply VIP10 → ❌ Blocked (3/3)
```

### Scenario 5: Month Reset

```bash
# Test (requires date manipulation or wait)
1. January 31: Use TEST27 → ✅ Allowed
2. January 31: Try TEST27 → ❌ Blocked (1/1)
3. February 1: Try TEST27 → ✅ Allowed (new month, 0/1)
```

### Scenario 6: Multiple Coupons in One Order

```bash
# Test
1. Add TEST27 to cart → ✅ Allowed
2. Add VIP10 to cart → ✅ Allowed
3. Complete order
4. Check usage:
   - TEST27: 1/1 used
   - VIP10: 1/1 used (tracked separately)
```

### Scenario 7: Fallback Re-check

```bash
# Test (requires custom checkout flow)
1. Add product to cart
2. Apply TEST27 (before entering email)
3. Validation passes (provisional)
4. Complete checkout with email
5. Order created → Fallback re-check runs
6. If limit exceeded → Coupon removed, order note added
```

---

## Edge Cases

### Case 1: Status Change While Already at Limit

```
Situation: Customer at limit, then order status changes
Result: Status change logs correctly, validation still blocks new uses
```

### Case 2: Rapid Status Changes

```
Situation: Order goes pending → processing → completed quickly
Result: Only increments once (double-counting prevention)
```

### Case 3: Customer Changes Email

```
Situation: Guest uses coupon, then checks out with different email
Result: Treated as different customer, each email gets own limit
```

### Case 4: User Creates Account After Guest Purchase

```
Situation: Guest uses coupon, later creates account with same email
Result: Tracked separately (user:ID vs email:hash)
Solution: Use 'email_only' identification if this is concern
```

### Case 5: Partial Refunds

```
Situation: Order partially refunded but stays in 'completed'
Result: No decrement (order must transition to 'refunded' status)
```

### Case 6: Order Restored After Cancellation

```
Situation: cancelled → processing
Result: Increments again (treated as new count)
```

### Case 7: Month Boundary Race Condition

```
Situation: Order created at 2024-01-31 23:59:59
Result: Tracked in January (uses order creation date)
```

---

## Troubleshooting

### Issue: Customer bypassing limit

**Possible causes:**
1. Feature disabled in settings
2. Coupon not in "Restricted Coupons" list (and "Apply to all" is OFF)
3. Customer using different emails
4. Customer switching between logged-in and guest checkout

**Solutions:**
- Check `enable_monthly_limit` is true
- Verify coupon in restricted list OR `apply_to_all_coupons` is true
- Use `customer_identification = 'email_only'` to track across sessions
- Consider disabling guest checkout or requiring account creation

### Issue: Usage not incrementing

**Possible causes:**
1. Order not reaching count status (stuck in 'pending')
2. Coupon not managed by plugin
3. Database connection error

**Debug steps:**
```php
// Check order status
$order = wc_get_order( $order_id );
echo $order->get_status();

// Check if coupon managed
$settings = new Settings();
var_dump( $settings->is_coupon_managed( 'test27' ) );

// Check database directly
global $wpdb;
$table = $wpdb->prefix . 'wc_coupon_gatekeeper_usage';
$results = $wpdb->get_results( "SELECT * FROM $table" );
print_r( $results );
```

### Issue: Usage not decrementing on cancellation

**Possible causes:**
1. Order never reached count status (can't decrement what wasn't counted)
2. Wrong decrement status configured
3. Order note shows decrement but validation still blocks (check database)

**Debug steps:**
```php
// Check order notes
$order = wc_get_order( $order_id );
foreach ( $order->get_notes() as $note ) {
    echo $note->content . "\n";
}

// Check settings
$settings = new Settings();
print_r( $settings->get_decrement_usage_statuses() );
```

### Issue: Fallback re-check not working

**Possible causes:**
1. Customer key still empty after order creation
2. Hook not firing
3. Monthly limit disabled

**Debug steps:**
```php
// Add logging to fallback function
add_action( 'woocommerce_checkout_order_processed', function( $order_id ) {
    error_log( "Fallback recheck running for order $order_id" );
    $order = wc_get_order( $order_id );
    error_log( "Customer email: " . $order->get_billing_email() );
    error_log( "Coupons: " . implode( ', ', $order->get_coupon_codes() ) );
}, 5, 1 );
```

### Issue: Hashed keys not matching

**Possible causes:**
1. Salt changed (wp_salt() returns different value)
2. Email case differences
3. Extra whitespace in email

**Solutions:**
- All emails normalized to lowercase and trimmed
- Salt based on `wp_salt( 'auth' )` (constant per site)
- Check database for actual stored keys:

```sql
SELECT customer_key, coupon_code, count 
FROM wp_wc_coupon_gatekeeper_usage 
WHERE month = '2024-01';
```

---

## Performance Considerations

### Database Queries

**Per validation:** 1 query
```sql
SELECT count FROM wp_wc_coupon_gatekeeper_usage 
WHERE coupon_code = ? AND customer_key = ? AND month = ?
```

**Per order status change:** 1 query per managed coupon
```sql
-- Increment (upsert)
INSERT ... ON DUPLICATE KEY UPDATE ...

-- Decrement
UPDATE ... WHERE ... AND count > 0
```

### Optimization Tips

1. **Indexes:** Automatically created on:
   - `(coupon_code, customer_key, month)` - UNIQUE
   - `(coupon_code, month)`
   - `(customer_key, month)`

2. **Caching:** Settings cached in memory, no DB query per validation

3. **Cleanup:** Schedule monthly cleanup of old records:
```php
add_action( 'wp_scheduled_delete', function() {
    $settings = new Settings();
    $retention = $settings->get_log_retention_months();
    Database::cleanup_old_records( $retention );
} );
```

---

## Database Maintenance

### View Current Usage

```sql
-- All usage this month
SELECT * FROM wp_wc_coupon_gatekeeper_usage 
WHERE month = DATE_FORMAT(NOW(), '%Y-%m')
ORDER BY updated_at DESC;

-- Specific coupon usage
SELECT customer_key, count, updated_at 
FROM wp_wc_coupon_gatekeeper_usage 
WHERE coupon_code = 'test27' AND month = '2024-01';

-- Top users
SELECT customer_key, SUM(count) as total_uses
FROM wp_wc_coupon_gatekeeper_usage
WHERE month = '2024-01'
GROUP BY customer_key
ORDER BY total_uses DESC
LIMIT 10;
```

### Manual Adjustments

```sql
-- Reset specific customer's usage
UPDATE wp_wc_coupon_gatekeeper_usage
SET count = 0
WHERE coupon_code = 'test27' 
  AND customer_key = 'user:42' 
  AND month = '2024-01';

-- Delete all usage for a coupon
DELETE FROM wp_wc_coupon_gatekeeper_usage
WHERE coupon_code = 'test27';
```

### Cleanup Old Data

```php
// Programmatic cleanup
Database::cleanup_old_records( 18 );  // Keep 18 months

// Or via SQL
DELETE FROM wp_wc_coupon_gatekeeper_usage
WHERE month < DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 18 MONTH), '%Y-%m');
```

---

## GDPR Compliance

### Email Anonymization

When enabled (`anonymize_email = true`):
- Email addresses hashed with SHA-256 + site salt
- Original email not stored in database
- Irreversible (can't lookup by email)

### Data Retention

- Configure retention period: `log_retention_months` (default: 18)
- Automatic cleanup via scheduled task
- Manual cleanup available

### Data Export/Deletion

```php
// Export customer data (for GDPR request)
function export_customer_usage( $user_id ) {
    global $wpdb;
    $table = Database::get_table_name();
    $customer_key = 'user:' . $user_id;
    
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT coupon_code, month, count, updated_at 
         FROM $table 
         WHERE customer_key = %s",
        $customer_key
    ), ARRAY_A );
}

// Delete customer data (for GDPR request)
function delete_customer_usage( $user_id ) {
    global $wpdb;
    $table = Database::get_table_name();
    $customer_key = 'user:' . $user_id;
    
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM $table WHERE customer_key = %s",
        $customer_key
    ) );
}
```

---

## API Reference

### Database Methods

```php
// Get usage count
$count = Database::get_usage_count( 'test27', 'user:42', '2024-01' );

// Increment usage
$success = Database::increment_usage( 'test27', 'user:42', $order_id, '2024-01' );

// Decrement usage
$success = Database::decrement_usage( 'test27', 'user:42', $order_id, '2024-01' );

// Cleanup old records
$deleted_count = Database::cleanup_old_records( 18 );
```

### Settings Methods

```php
$settings = new Settings();

// Feature check
$enabled = $settings->is_monthly_limit_enabled();

// Get limits
$default_limit = $settings->get_default_monthly_limit();
$coupon_limit = $settings->get_monthly_limit_for_coupon( 'vip10' );

// Customer identification
$method = $settings->get_customer_identification();
$anonymize = $settings->is_email_anonymization_enabled();

// Error messages
$message = $settings->get_error_limit_reached();

// Status lists
$count_statuses = $settings->get_count_usage_statuses();
$decrement_statuses = $settings->get_decrement_usage_statuses();
```

---

## Summary

✅ **Customer can use coupon once per month by default**  
✅ **Cancelling/refunding decrements usage (customer can use again)**  
✅ **Smart customer identification (user ID → email → provisional)**  
✅ **Per-coupon limit overrides supported**  
✅ **Fallback re-check handles edge cases**  
✅ **Concurrency-safe database operations**  
✅ **GDPR-compliant email anonymization**  
✅ **Automatic monthly reset**  

---

**For more information:**
- Settings Reference: `SETTINGS_API_REFERENCE.md`
- Testing Guide: `TESTING_QUICK_REFERENCE.md`
- Day Restriction Guide: `DAY_RESTRICTION_GUIDE.md`