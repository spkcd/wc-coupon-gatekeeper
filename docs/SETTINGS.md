# WC Coupon Gatekeeper - Settings Guide

Complete guide to configuring the WC Coupon Gatekeeper plugin settings.

## Settings Location

**WooCommerce → Settings → Coupon Gatekeeper**

---

## Feature Toggles

### Enable Day-of-Month Restriction
- **Type**: Checkbox
- **Default**: ON
- **Description**: When enabled, coupons can only be used on specific days of each month that you configure below.

### Enable Per-Customer Monthly Limit
- **Type**: Checkbox
- **Default**: ON
- **Description**: When enabled, enforces monthly usage limits per customer for each coupon.

---

## Coupon Targeting

### Restricted Coupons
- **Type**: Textarea (comma or line-separated)
- **Default**: Empty
- **Format**: 
  ```
  27off, vip27, SUMMERSALE
  ```
  or
  ```
  27off
  vip27
  SUMMERSALE
  ```
- **Notes**:
  - Coupon codes are automatically normalized to lowercase
  - Whitespace is trimmed
  - Both comma and line separation are supported
  - Case-insensitive matching
  
**Example**:
```
27off
vip27
summersale
```

### Apply to ALL Coupons
- **Type**: Checkbox
- **Default**: OFF
- **Description**: When enabled, restrictions apply to every coupon in your store, ignoring the "Restricted Coupons" list above.
- **Warning**: This will affect all coupons globally!

---

## Allowed Day(s)

### Allowed Day(s) of Month
- **Type**: Multi-select (1-31)
- **Default**: 27
- **Description**: Select which days of the month coupons can be used.
- **Example**: Selecting 1, 15, and 27 means coupons work only on the 1st, 15th, and 27th of each month.

### If Day Missing → Use Last Valid Day of Month
- **Type**: Checkbox
- **Default**: OFF
- **Description**: Handle months that don't have the selected day(s).
  
**Example Scenarios**:
- Selected day: **31**
- Month: **February** (only 28/29 days)
- **If OFF**: Coupon cannot be used in February
- **If ON**: Coupon can be used on Feb 28 (or 29 in leap years)

---

## Monthly Limit

### Default Monthly Limit per Customer
- **Type**: Number
- **Default**: 1
- **Min**: 1
- **Description**: Maximum number of times a customer can use each coupon per calendar month.

### Per-Coupon Overrides
- **Type**: Textarea
- **Format**: `coupon_code:limit` (one per line)
- **Example**:
  ```
  vip27:5
  special:10
  premium:3
  ```
- **Notes**:
  - Case-insensitive
  - Invalid lines are silently ignored
  - Overrides take precedence over default limit
  
**Invalid examples** (will be ignored):
```
invalid-no-colon
:5
coupon:
coupon:abc
```

### Identify Customer By
- **Type**: Radio buttons
- **Default**: Logged-in User ID (preferred)
- **Options**:
  1. **Logged-in User ID (preferred), fallback to billing email for guests**
     - If user is logged in → track by User ID
     - If guest → track by billing email
     - Most accurate method
  
  2. **Always use billing email (even for logged-in users)**
     - Always track by billing email
     - Use when you need email-based tracking regardless of login status

### Anonymize Email in Logs
- **Type**: Checkbox
- **Default**: ON
- **Description**: Store email addresses as SHA-256 hashes with WordPress salt for privacy.
- **Impact**:
  - **ON**: Emails stored as irreversible hashes (GDPR-friendly)
  - **OFF**: Emails stored in plain text
- **Note**: Only applies to email-based tracking; User IDs are never anonymized.

---

## Error Messages

### Error: Not Allowed Day
- **Type**: Text field
- **Default**: "This coupon can only be used on the allowed day(s) each month."
- **Description**: Message shown to customers when they try to use a coupon on a non-allowed day.
- **Example custom messages**:
  - "This coupon is only valid on the 27th of each month!"
  - "Oops! This discount is available on specific days only."

### Error: Monthly Limit Reached
- **Type**: Text field
- **Default**: "You've already used this coupon this month."
- **Description**: Message shown when customer has exceeded their monthly usage limit.
- **Example custom messages**:
  - "You've reached your monthly limit for this coupon."
  - "This coupon can only be used once per month."

---

## Advanced Settings

### Count Usage On Status
- **Type**: Multi-select
- **Default**: Processing, Completed
- **Description**: Order statuses that increment the usage counter.
- **Common choices**:
  - **Processing**: Count as soon as payment is received
  - **Completed**: Count only when order is fulfilled
  - **On-hold**: Include orders awaiting payment

**Recommendation**: Keep default (Processing + Completed) for most use cases.

### Decrement On Status
- **Type**: Multi-select
- **Default**: Cancelled, Refunded
- **Description**: Order statuses that decrement the usage counter (give the usage back to customer).
- **Common choices**:
  - **Cancelled**: Order was cancelled
  - **Refunded**: Order was refunded
  - **Failed**: Payment failed

**Use case**: If an order is cancelled or refunded, the customer should be able to use the coupon again.

### Admin Bypass in Edit Order
- **Type**: Checkbox
- **Default**: ON
- **Description**: Skip all validation checks when an admin manually adds coupons in the WordPress admin order edit screen.
- **When to disable**: If you want strict enforcement even for admin-created orders.

### Clear Logs Older Than N Months
- **Type**: Number
- **Default**: 18
- **Min**: 1
- **Description**: Log retention period. Logs older than this can be purged.
- **Example**: Setting to 12 means logs older than 12 months can be deleted.

### Purge Old Logs Now
- **Type**: Button
- **Description**: Immediately delete all logs older than the retention period set above.
- **Warning**: This action is permanent and cannot be undone!
- **Use case**: Clean up database periodically or before exporting data.

### Delete Data on Uninstall
- **Type**: Checkbox
- **Default**: OFF
- **Description**: Remove all plugin data when uninstalling.
- **What gets deleted**:
  - Settings (all configuration)
  - Usage logs database table
  - All cached data
- **Warning**: Enable only if you're sure you won't need the data later!

---

## Common Configuration Scenarios

### Scenario 1: Single Day Per Month (27th)
```
✓ Enable Day-of-Month Restriction
✓ Enable Per-Customer Monthly Limit
Restricted Coupons: 27off
Allowed Days: 27
Default Monthly Limit: 1
```

### Scenario 2: First and Last Day of Month
```
✓ Enable Day-of-Month Restriction
✓ Enable Per-Customer Monthly Limit
Allowed Days: 1, 31
✓ Use Last Valid Day (for months with < 31 days)
```

### Scenario 3: VIP Customers with Higher Limits
```
Restricted Coupons: vip27, regular27
Default Monthly Limit: 1
Per-Coupon Overrides:
  vip27:5
  regular27:1
```

### Scenario 4: Apply to ALL Coupons
```
✓ Apply to ALL Coupons
Allowed Days: 15, 27
Default Monthly Limit: 2
```

### Scenario 5: Email-Based Tracking with Privacy
```
Customer Identification: Always use billing email
✓ Anonymize Email in Logs
```

---

## Validation Rules

The plugin validates all settings before saving:

1. **Allowed Days**: Must select at least one day (1-31)
2. **Monthly Limit**: Must be at least 1
3. **Log Retention**: Must be at least 1 month
4. **Count Usage Statuses**: Must select at least one status
5. **Coupon Codes**: Automatically normalized to lowercase
6. **Per-Coupon Overrides**: Invalid formats are silently ignored

---

## Data Storage

All settings are stored in a single WordPress option:
- **Option name**: `wc_coupon_gatekeeper_settings`
- **Format**: Serialized PHP array
- **Caching**: Settings are cached in memory during request

---

## Troubleshooting

### Settings not saving?
1. Check browser console for JavaScript errors
2. Verify you have `manage_woocommerce` capability
3. Check PHP error logs for validation errors

### Purge button not working?
1. Ensure JavaScript is enabled
2. Check browser console for errors
3. Verify AJAX requests are not blocked

### Coupons not being restricted?
1. Verify coupon codes are in "Restricted Coupons" list OR "Apply to ALL" is enabled
2. Check feature toggles are enabled
3. Verify correct timezone settings in WordPress

---

## Related Documentation

- [Main README](README.md)
- [Database Schema](README.md#database-schema)
- [Timezone Handling](README.md#timezone-handling)

---

**Need Help?** Check the plugin support forum or documentation for more assistance.