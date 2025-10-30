# Day Restriction Feature - Implementation Guide

## ✅ Feature Complete

The **Day-of-Month Restriction** feature is now fully implemented and ready for testing.

---

## 🎯 What Was Implemented

### Core Functionality
✅ **WooCommerce Integration**: Hooks into `woocommerce_coupon_is_valid` filter  
✅ **WordPress Timezone**: All date calculations use `wp_date()` for site timezone  
✅ **Settings Integration**: Reads configuration from Settings class  
✅ **Custom Error Messages**: Displays configurable error text  
✅ **Admin Bypass**: Respects admin bypass setting for order editing  

### Advanced Features
✅ **Multiple Allowed Days**: Supports selecting multiple days (e.g., 1, 15, 27)  
✅ **Last Valid Day Logic**: Handles months with fewer days (e.g., Feb 31 → Feb 28/29)  
✅ **Coupon Targeting**: Apply to all coupons or specific list  
✅ **Non-Breaking**: Respects existing WooCommerce validations  

---

## 🔍 How It Works

### Validation Flow

```
Customer applies coupon → WooCommerce validation starts
                                    ↓
                    Is coupon already invalid? → YES → Return invalid (don't override)
                                    ↓ NO
                    Is coupon managed by plugin? → NO → Return valid (pass through)
                                    ↓ YES
                    Admin bypass enabled + in wp-admin? → YES → Return valid (bypass)
                                    ↓ NO
                    Day restriction enabled? → NO → Continue to monthly limit check
                                    ↓ YES
                    Is today in allowed days? → YES → Return valid
                                    ↓ NO
                    Use last valid day enabled? → NO → BLOCK (throw exception)
                                    ↓ YES
                    Is today the last day + configured day missing? → YES → Return valid
                                    ↓ NO
                    BLOCK (throw exception with custom error message)
```

### Last Valid Day Logic

**Example 1: February (non-leap year)**
- Configured day: **31**
- February has: **28 days**
- Result: Coupon is allowed on **Feb 28** (last valid day)

**Example 2: April**
- Configured day: **31**
- April has: **30 days**
- Result: Coupon is allowed on **Apr 30** (last valid day)

**Example 3: July**
- Configured day: **31**
- July has: **31 days**
- Result: Coupon is allowed on **Jul 31** (exact match)

---

## 🧪 Testing Scenarios

### Test 1: Basic Day Restriction (Default Settings)

**Configuration:**
- Day Restriction: **Enabled**
- Allowed Days: **27**
- Apply to All: **Yes**

**Expected Behavior:**
- ✅ On 27th: Coupon applies successfully
- ❌ On other days: Error message shown
- 📝 Error: "This coupon is only valid on specific days of the month."

### Test 2: Multiple Allowed Days

**Configuration:**
- Allowed Days: **1, 15, 27**
- Apply to All: **Yes**

**Expected Behavior:**
- ✅ On 1st, 15th, or 27th: Coupon valid
- ❌ On other days: Blocked

### Test 3: Last Valid Day (February Test)

**Configuration:**
- Allowed Days: **30, 31**
- Use Last Valid Day: **Enabled**
- Test in: **February**

**Expected Behavior:**
- ✅ Feb 28 (non-leap): Coupon valid (fallback for 30/31)
- ✅ Feb 29 (leap year): Coupon valid (fallback for 30/31)
- ❌ Other days: Blocked

### Test 4: Specific Coupons Only

**Configuration:**
- Apply to All: **No**
- Restricted Coupons: `vip27, premium`
- Allowed Days: **27**

**Expected Behavior:**
- `vip27`, `premium`: Restricted to 27th only
- Other coupons: No restrictions (work any day)

### Test 5: Day Restriction Disabled

**Configuration:**
- Day Restriction: **Disabled**
- Monthly Limit: **Enabled**

**Expected Behavior:**
- ✅ All coupons work any day
- ✅ Monthly limit still enforced (when implemented)

### Test 6: Admin Bypass

**Configuration:**
- Admin Bypass: **Enabled**
- Allowed Days: **27**
- Context: wp-admin order editing

**Expected Behavior:**
- ✅ Admin user in wp-admin: Coupon applies any day
- ❌ Frontend/AJAX: Normal restrictions apply
- 📝 Requires `manage_woocommerce` capability

### Test 7: Custom Error Message

**Configuration:**
- Error Message: "VIP coupons are only valid on the 27th!"
- Allowed Days: **27**

**Expected Behavior:**
- ❌ On non-27th days: Custom error displayed
- 📝 Message is escaped for security

### Test 8: Already Invalid Coupon

**Scenario:**
- Coupon fails minimum spend check
- Today is an allowed day

**Expected Behavior:**
- ❌ Coupon remains invalid
- 📝 Plugin doesn't override other WooCommerce validations

---

## 🛠️ Manual Testing Steps

### Step 1: Install and Activate
```bash
# Ensure plugin is activated
wp plugin activate wc-coupon-gatekeeper
```

### Step 2: Configure Settings
1. Go to **WooCommerce → Settings → Coupon Gatekeeper**
2. Enable **Day-of-Month Restriction**
3. Set **Allowed Days**: Select 27 (or current day for immediate testing)
4. Enable **Apply to All Coupons**
5. Set custom error message (optional)
6. Click **Save Changes**

### Step 3: Create Test Coupon
1. Go to **Marketing → Coupons → Add New**
2. Coupon code: `test27`
3. Discount type: **Fixed cart discount**
4. Amount: **10**
5. Click **Publish**

### Step 4: Test on Frontend
1. Add product to cart
2. Go to checkout/cart page
3. Apply coupon: `test27`

**If today is the 27th:**
```
✅ Success: Coupon code applied successfully.
```

**If today is NOT the 27th:**
```
❌ Error: This coupon is only valid on specific days of the month.
```

### Step 5: Test Settings Changes
1. Go back to settings
2. Change **Allowed Days** to include today (e.g., add current day number)
3. Click **Save Changes**
4. Return to cart
5. Apply coupon again

**Expected:**
```
✅ Success: Coupon now works because today is in allowed days.
```

### Step 6: Test Admin Bypass
1. Go to **WooCommerce → Orders → Add New**
2. Add products manually
3. Apply coupon: `test27` (on a non-allowed day)

**Expected:**
```
✅ Coupon applies (admin bypass active in wp-admin context)
```

---

## 📊 Code Examples

### Check If Today Is Allowed (Internal)

```php
use WC_Coupon_Gatekeeper\Database;
use WC_Coupon_Gatekeeper\Bootstrap;

$settings = Bootstrap::instance()->get_settings();

// Get current day (WordPress timezone-aware).
$current_day = Database::get_current_day(); // e.g., 27

// Get allowed days from settings.
$allowed_days = $settings->get_allowed_days(); // e.g., [1, 15, 27]

// Check if today is allowed.
if ( in_array( $current_day, $allowed_days, true ) ) {
    echo "Today is allowed!";
}
```

### Programmatic Validation Test

```php
// Create test coupon.
$coupon = new WC_Coupon( 'test27' );

// Trigger validation filter.
try {
    $is_valid = apply_filters( 'woocommerce_coupon_is_valid', true, $coupon, WC()->cart );
    if ( $is_valid ) {
        echo "Coupon is valid!";
    }
} catch ( Exception $e ) {
    echo "Error: " . $e->getMessage();
}
```

---

## 🔒 Security Features

✅ **Escaping**: Error messages are escaped with `esc_html()`  
✅ **Type Checking**: Day comparisons use strict type checking (`===`)  
✅ **Capability Check**: Admin bypass requires `manage_woocommerce`  
✅ **AJAX Protection**: Admin bypass does NOT work during AJAX requests  
✅ **Validation Chain**: Doesn't override existing WooCommerce validations  

---

## 🐛 Edge Cases Handled

| Scenario | Behavior |
|----------|----------|
| Coupon already invalid (other rules) | ✅ Remains invalid (not overridden) |
| February 31st with last valid day ON | ✅ Allowed on Feb 28/29 |
| Multiple allowed days (1, 15, 27) | ✅ All days work correctly |
| Day restriction disabled | ✅ All days allowed |
| Unmanaged coupon (not in list) | ✅ Passes through (no restriction) |
| Admin editing order (non-AJAX) | ✅ Bypass active (if enabled) |
| Frontend AJAX with admin bypass ON | ❌ No bypass (security) |
| Invalid day number in settings | ⚠️ Validated by Settings_Screen |

---

## 📁 Files Modified

| File | Changes |
|------|---------|
| `src/Validator/Coupon_Validator.php` | ✅ Complete implementation |
| `tests/test-day-restriction.php` | ✅ Comprehensive test suite |

---

## ✅ Acceptance Criteria - All Met

| Requirement | Status |
|------------|:------:|
| Hook into WooCommerce validation | ✅ |
| Use WordPress timezone | ✅ |
| Respect allowed days setting | ✅ |
| Support multiple allowed days | ✅ |
| Implement "last valid day" logic | ✅ |
| Apply to all or specific coupons | ✅ |
| Show custom error message | ✅ |
| Respect admin bypass | ✅ |
| Don't break other validations | ✅ |
| Immediate behavior change on save | ✅ |

---

## 🚀 Next Steps

### Ready for Testing
The day restriction feature is **production-ready** and can be tested immediately.

### Future Implementation (Phase 3 Continued)
- ⏳ **Monthly Limit Validation**: Check usage count per customer
- ⏳ **Usage Logging**: Track coupon usage in database
- ⏳ **Order Status Hooks**: Increment/decrement usage counters
- ⏳ **Usage Logs Screen**: Display usage history in wp-admin
- ⏳ **CSV Export**: Export usage data

---

## 📞 Troubleshooting

### Issue: Coupon works on wrong days
**Solution:** Clear any caching plugins and verify settings saved correctly

### Issue: Error message not showing custom text
**Solution:** Check Settings screen, ensure custom error is saved

### Issue: Admin bypass not working
**Solution:** Verify you're in wp-admin (not frontend), and user has `manage_woocommerce` capability

### Issue: Last valid day not working
**Solution:** Enable "Use Last Valid Day" checkbox in settings

---

## 📝 Summary

The **Day-of-Month Restriction** feature provides flexible, timezone-aware coupon validation with support for:

- ✅ Single or multiple allowed days
- ✅ Shorter month handling (last valid day)
- ✅ Custom error messages
- ✅ Admin bypass for order editing
- ✅ Per-coupon or global restrictions
- ✅ Immediate effect after settings change

**Status:** ✅ Complete and ready for production use.