# Settings API Quick Reference

## Getting Settings Instance

```php
use WC_Coupon_Gatekeeper\Bootstrap;

$settings = Bootstrap::instance()->get_settings();
```

---

## Feature Toggles

```php
// Check if day restriction is enabled
if ( $settings->is_day_restriction_enabled() ) {
    // Apply day-of-month restrictions
}

// Check if monthly limit is enabled
if ( $settings->is_monthly_limit_enabled() ) {
    // Apply monthly usage limits
}
```

---

## Coupon Management

```php
// Check if a coupon is managed by this plugin
if ( $settings->is_coupon_managed( 'vip27' ) ) {
    // Apply restrictions to this coupon
}

// Get all restricted coupons
$coupons = $settings->get_restricted_coupons();
// Returns: array( '27off', 'vip27', 'special' )

// Check if applies to all coupons
if ( $settings->apply_to_all_coupons() ) {
    // Every coupon is restricted
}
```

---

## Day Validation

```php
// Get allowed days
$allowed_days = $settings->get_allowed_days();
// Returns: array( 1, 15, 27 )

// Get current day in WordPress timezone
$current_day = (int) wp_date( 'j' ); // 1-31

// Check if today is allowed
if ( in_array( $current_day, $allowed_days, true ) ) {
    // Coupon can be used today
}

// Check last valid day fallback
if ( $settings->use_last_valid_day() ) {
    $last_day = (int) wp_date( 't' ); // Last day of current month
    if ( $current_day === $last_day && in_array( 31, $allowed_days, true ) ) {
        // Allow on last day if 31 is selected
    }
}
```

---

## Monthly Limits

```php
// Get default monthly limit
$default_limit = $settings->get_default_monthly_limit();
// Returns: 1 (default)

// Get limit for specific coupon (with overrides)
$limit = $settings->get_monthly_limit_for_coupon( 'vip27' );
// Returns: 5 (if override exists), or default

// Get all overrides
$overrides = $settings->get_coupon_limit_overrides();
// Returns: array( 'vip27' => 5, 'special' => 10 )
```

---

## Customer Identification

```php
// Get identification method
$method = $settings->get_customer_identification();
// Returns: 'user_id_priority' or 'email_only'

// Implementation example
function get_customer_key( $user_id, $email ) {
    global $settings;
    
    if ( 'user_id_priority' === $settings->get_customer_identification() ) {
        if ( $user_id > 0 ) {
            return 'user:' . $user_id;
        }
    }
    
    // Use email
    if ( $settings->is_email_anonymization_enabled() ) {
        return 'hash:' . hash( 'sha256', strtolower( $email ) . wp_salt( 'auth' ) );
    }
    
    return 'email:' . strtolower( $email );
}

// Check if email anonymization is enabled
if ( $settings->is_email_anonymization_enabled() ) {
    $customer_key = 'hash:' . hash( 'sha256', $email . wp_salt( 'auth' ) );
}
```

---

## Error Messages

```php
// Get day restriction error
$error = $settings->get_error_not_allowed_day();
// Returns: "This coupon can only be used on the allowed day(s) each month."

// Get monthly limit error
$error = $settings->get_error_limit_reached();
// Returns: "You've already used this coupon this month."

// Usage in WooCommerce
throw new Exception( $settings->get_error_not_allowed_day() );
```

---

## Order Status Tracking

```php
// Get statuses that should count towards usage
$count_statuses = $settings->get_count_usage_statuses();
// Returns: array( 'processing', 'completed' )

// Check if order status should count
if ( in_array( $order->get_status(), $count_statuses, true ) ) {
    // Increment usage counter
}

// Get statuses that should decrement usage
$decrement_statuses = $settings->get_decrement_usage_statuses();
// Returns: array( 'cancelled', 'refunded' )

// Check if order status should decrement
if ( in_array( $new_status, $decrement_statuses, true ) ) {
    // Decrement usage counter
}
```

---

## Admin Features

```php
// Check if admin bypass is enabled
if ( $settings->is_admin_bypass_enabled() && is_admin() ) {
    // Skip validation for admin-created orders
    return true;
}

// Get log retention period
$months = $settings->get_log_retention_months();
// Returns: 18 (default)

// Calculate cutoff date for logs
$cutoff = wp_date( 'Y-m-d H:i:s', strtotime( "-{$months} months" ) );

// Check if data should be deleted on uninstall
if ( $settings->delete_data_on_uninstall() ) {
    // Delete all data during uninstall
}
```

---

## Complete Validation Example

```php
use WC_Coupon_Gatekeeper\Bootstrap;
use WC_Coupon_Gatekeeper\Database;

function validate_coupon_usage( $coupon_code, $user_id, $email ) {
    $settings = Bootstrap::instance()->get_settings();
    
    // 1. Check if coupon is managed
    if ( ! $settings->is_coupon_managed( $coupon_code ) ) {
        return true; // Not managed, allow
    }
    
    // 2. Check day restriction
    if ( $settings->is_day_restriction_enabled() ) {
        $current_day  = Database::get_current_day();
        $allowed_days = $settings->get_allowed_days();
        
        if ( ! in_array( $current_day, $allowed_days, true ) ) {
            // Check last valid day fallback
            if ( $settings->use_last_valid_day() ) {
                $last_day = (int) wp_date( 't' );
                $max_allowed = max( $allowed_days );
                
                if ( ! ( $current_day === $last_day && $max_allowed > $last_day ) ) {
                    throw new Exception( $settings->get_error_not_allowed_day() );
                }
            } else {
                throw new Exception( $settings->get_error_not_allowed_day() );
            }
        }
    }
    
    // 3. Check monthly limit
    if ( $settings->is_monthly_limit_enabled() ) {
        $customer_key = get_customer_key( $user_id, $email );
        $limit        = $settings->get_monthly_limit_for_coupon( $coupon_code );
        $usage_count  = get_usage_count( $coupon_code, $customer_key );
        
        if ( $usage_count >= $limit ) {
            throw new Exception( $settings->get_error_limit_reached() );
        }
    }
    
    return true;
}
```

---

## Complete Logging Example

```php
function log_coupon_usage( $order_id ) {
    $settings = Bootstrap::instance()->get_settings();
    $order    = wc_get_order( $order_id );
    
    // Get coupons from order
    $coupons = $order->get_coupon_codes();
    
    // Get customer info
    $user_id = $order->get_customer_id();
    $email   = $order->get_billing_email();
    
    // Generate customer key
    $customer_key = get_customer_key( $user_id, $email );
    
    foreach ( $coupons as $coupon_code ) {
        // Only log managed coupons
        if ( ! $settings->is_coupon_managed( $coupon_code ) ) {
            continue;
        }
        
        // Insert or update usage record
        global $wpdb;
        $table_name = Database::get_table_name();
        $month      = Database::get_current_month();
        $now        = Database::get_current_datetime();
        
        // Try to update existing record
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name} 
                SET count = count + 1, last_order_id = %d, updated_at = %s 
                WHERE coupon_code = %s AND customer_key = %s AND month = %s",
                $order_id,
                $now,
                strtolower( $coupon_code ),
                $customer_key,
                $month
            )
        );
        
        // If no rows updated, insert new record
        if ( 0 === $updated ) {
            $wpdb->insert(
                $table_name,
                array(
                    'coupon_code'   => strtolower( $coupon_code ),
                    'customer_key'  => $customer_key,
                    'month'         => $month,
                    'count'         => 1,
                    'last_order_id' => $order_id,
                    'updated_at'    => $now,
                ),
                array( '%s', '%s', '%s', '%d', '%d', '%s' )
            );
        }
    }
}
```

---

## Updating Settings

```php
// Update specific settings
$settings->update( array(
    'allowed_days'         => array( 1, 15, 27 ),
    'default_monthly_limit' => 2,
    'anonymize_email'      => true,
) );

// Clear cache after programmatic update
$settings->clear_cache();

// Get all settings
$all = $settings->get_all();
```

---

## Common Patterns

### Pattern 1: Check and Validate
```php
if ( $settings->is_coupon_managed( $code ) && 
     $settings->is_day_restriction_enabled() ) {
    // Validate day
}
```

### Pattern 2: Get Limit with Override
```php
$limit = $settings->get_monthly_limit_for_coupon( $code );
// Automatically returns override if exists, otherwise default
```

### Pattern 3: Customer Key Generation
```php
function get_customer_key( $user_id, $email ) {
    $settings = Bootstrap::instance()->get_settings();
    
    if ( 'user_id_priority' === $settings->get_customer_identification() && $user_id > 0 ) {
        return 'user:' . $user_id;
    }
    
    if ( $settings->is_email_anonymization_enabled() ) {
        return 'hash:' . hash( 'sha256', strtolower( $email ) . wp_salt( 'auth' ) );
    }
    
    return 'email:' . strtolower( $email );
}
```

### Pattern 4: Order Status Check
```php
function should_count_usage( $status ) {
    $settings = Bootstrap::instance()->get_settings();
    return in_array( $status, $settings->get_count_usage_statuses(), true );
}

function should_decrement_usage( $old_status, $new_status ) {
    $settings = Bootstrap::instance()->get_settings();
    $count_statuses = $settings->get_count_usage_statuses();
    $decrement_statuses = $settings->get_decrement_usage_statuses();
    
    return in_array( $old_status, $count_statuses, true ) &&
           in_array( $new_status, $decrement_statuses, true );
}
```

---

## Type Reference

```php
// Return types for all getters

// bool
is_day_restriction_enabled(): bool
is_monthly_limit_enabled(): bool
apply_to_all_coupons(): bool
use_last_valid_day(): bool
is_email_anonymization_enabled(): bool
is_admin_bypass_enabled(): bool
delete_data_on_uninstall(): bool
is_coupon_managed( string $code ): bool

// int
get_default_monthly_limit(): int
get_monthly_limit_for_coupon( string $code ): int
get_log_retention_months(): int

// string
get_customer_identification(): string  // 'user_id_priority'|'email_only'
get_error_not_allowed_day(): string
get_error_limit_reached(): string

// array
get_restricted_coupons(): array        // string[]
get_allowed_days(): array              // int[]
get_coupon_limit_overrides(): array    // array<string, int>
get_count_usage_statuses(): array      // string[]
get_decrement_usage_statuses(): array  // string[]
get_all(): array                       // array<string, mixed>
```

---

## WordPress Timezone Helper Functions

```php
use WC_Coupon_Gatekeeper\Database;

// Get current month (YYYY-MM)
$month = Database::get_current_month();

// Get current day (1-31)
$day = Database::get_current_day();

// Get current datetime for database
$datetime = Database::get_current_datetime();

// Manual usage
$current_day = (int) wp_date( 'j' );
$last_day = (int) wp_date( 't' );
$month = wp_date( 'Y-m' );
$datetime = wp_date( 'Y-m-d H:i:s' );
```

---

## Filters & Actions

### Available Filters
```php
// Modify settings array
add_filter( 'wc_coupon_gatekeeper_settings', function( $settings ) {
    // Add custom field
    return $settings;
} );
```

### Available Actions
```php
// After settings saved
add_action( 'woocommerce_update_options_coupon_gatekeeper', function() {
    // Do something after save
} );
```

---

**Quick Links**:
- [Complete Settings Guide](SETTINGS.md)
- [Implementation Status](IMPLEMENTATION_CHECKLIST.md)
- [Phase 2 Summary](PHASE2_COMPLETE.md)