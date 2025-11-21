# Quick Testing Reference - Day Restriction

## 🚀 Fast Setup (2 minutes)

### 1. Activate Plugin
```bash
wp plugin activate wc-coupon-gatekeeper
```
**Or:** WP Admin → Plugins → Activate "WC Coupon Gatekeeper"

### 2. Verify Default Settings
Navigate to: **WooCommerce → Settings → Coupon Gatekeeper**

**Defaults:**
- ✅ Day-of-Month Restriction: **Enabled**
- ✅ Allowed Days: **27**
- ✅ Apply to All Coupons: **Yes**
- ✅ Error Message: "This coupon is only valid on specific days of the month."

### 3. Create Test Coupon
**WooCommerce → Marketing → Coupons → Add New**
- Code: `TEST27`
- Type: **Fixed cart discount**
- Amount: **10**
- Click **Publish**

---

## ✅ Test Scenarios

### Scenario 1: Basic Functionality (Default Settings)

| Day | Action | Expected Result |
|-----|--------|-----------------|
| 27th | Apply `TEST27` | ✅ Success |
| Other | Apply `TEST27` | ❌ "This coupon is only valid on specific days of the month." |

**Steps:**
1. Add any product to cart
2. Go to cart or checkout
3. Enter coupon code: `TEST27`
4. Click Apply

---

### Scenario 2: Multiple Allowed Days

**Settings:**
- Allowed Days: **1, 15, 27** (hold Ctrl/Cmd to multi-select)
- Save Changes

**Test:**
| Day | Result |
|-----|--------|
| 1st, 15th, or 27th | ✅ Works |
| Any other day | ❌ Blocked |

---

### Scenario 3: Last Valid Day (February Test)

**Settings:**
- Allowed Days: **30, 31**
- Use Last Valid Day: **✅ Enabled**
- Save Changes

**Test (in February):**
| Day | Month Days | Expected | Reason |
|-----|-----------|----------|--------|
| Feb 28 | 28 (non-leap) | ✅ Works | Last valid day for 30/31 |
| Feb 29 | 29 (leap year) | ✅ Works | Last valid day for 30/31 |
| Feb 15 | - | ❌ Blocked | Not last day |

---

### Scenario 4: Specific Coupons Only

**Settings:**
- Apply to All Coupons: **❌ Disabled**
- Restricted Coupons: `vip27` (one per line or comma-separated)
- Allowed Days: **27**
- Save Changes

**Test:**
| Coupon | Day | Result | Reason |
|--------|-----|--------|--------|
| `VIP27` | 27th | ✅ Works | In restricted list + allowed day |
| `VIP27` | 15th | ❌ Blocked | In restricted list + wrong day |
| `SUMMER10` | 15th | ✅ Works | NOT in restricted list |

---

### Scenario 5: Day Restriction Disabled

**Settings:**
- Day-of-Month Restriction: **❌ Disabled**
- Save Changes

**Test:**
| Day | Result |
|-----|--------|
| Any day | ✅ All coupons work |

---

### Scenario 6: Admin Bypass

**Settings:**
- Admin Bypass in Edit Order: **✅ Enabled**
- Allowed Days: **27**
- Save Changes

**Test:**
| Context | Day | Result | Reason |
|---------|-----|--------|--------|
| WP Admin Order Edit | 15th | ✅ Works | Admin bypass active |
| Frontend Cart/Checkout | 15th | ❌ Blocked | No bypass on frontend |
| AJAX Request (frontend) | 15th | ❌ Blocked | No bypass during AJAX |

**Steps for Admin Test:**
1. Go to **WooCommerce → Orders → Add New**
2. Add products manually
3. Enter coupon: `TEST27` (on a non-27th day)
4. Coupon should apply successfully

---

### Scenario 7: Custom Error Message

**Settings:**
- Error Message (Not Allowed Day): `Sorry, VIP coupons only work on the 27th!`
- Save Changes

**Test:**
- Apply coupon on wrong day
- Expected error: **"Sorry, VIP coupons only work on the 27th!"**

---

## 🔍 Verification Checklist

### ✅ Basic Functionality
- [ ] Coupon works on configured day
- [ ] Coupon blocked on other days
- [ ] Error message displays correctly
- [ ] Settings save and persist

### ✅ Advanced Features
- [ ] Multiple allowed days work
- [ ] Last valid day logic works (test in February)
- [ ] "Apply to all" vs "specific list" works correctly
- [ ] Custom error messages display
- [ ] Admin bypass works in wp-admin
- [ ] Admin bypass does NOT work on frontend

### ✅ Edge Cases
- [ ] Changing settings takes immediate effect
- [ ] Disabling feature allows all days
- [ ] Other WooCommerce validations still work
- [ ] Unmanaged coupons always work

---

## 🐛 Troubleshooting

### Issue: Coupon works on wrong days

**Solutions:**
1. Clear any caching plugins
2. Verify settings saved (green notice at top)
3. Check "Allowed Days" multi-select has correct values
4. Ensure "Day-of-Month Restriction" is **Enabled**

### Issue: Admin bypass not working

**Check:**
1. "Admin Bypass" setting is **Enabled**
2. You're in wp-admin (not frontend)
3. Not an AJAX request
4. User has `manage_woocommerce` capability

### Issue: Custom error message not showing

**Check:**
1. Settings saved correctly
2. Clear browser cache
3. Try different coupon to test
4. Check for JavaScript errors in console

### Issue: Last valid day not working

**Check:**
1. "Use Last Valid Day" is **✅ Enabled**
2. Test in a month with fewer days (e.g., February)
3. Current day is actually the last day of month
4. Configured day is greater than last day of month

---

## 📊 Database Verification

### Check Plugin Tables Exist
```bash
wp db query "SHOW TABLES LIKE '%wc_coupon_gatekeeper%';"
```

**Expected:**
```
wp_wc_coupon_gatekeeper_usage
```

### Check Settings Saved
```bash
wp option get wc_coupon_gatekeeper_settings
```

**Expected Structure:**
```php
array(
    'day_restriction_enabled' => 'yes',
    'allowed_days' => array( 27 ),
    'apply_to_all_coupons' => 'yes',
    // ... more settings
)
```

---

## 🎯 Quick Commands (WP-CLI)

### Activate Plugin
```bash
wp plugin activate wc-coupon-gatekeeper
```

### Check Plugin Status
```bash
wp plugin status wc-coupon-gatekeeper
```

### View Settings
```bash
wp option get wc_coupon_gatekeeper_settings --format=json
```

### Create Test Coupon
```bash
wp wc shop_coupon create \
  --code=TEST27 \
  --discount_type=fixed_cart \
  --amount=10 \
  --user=admin
```

### Delete Test Coupon
```bash
wp wc shop_coupon delete $(wp wc shop_coupon list --code=TEST27 --field=id) --force
```

---

## 📝 Test Results Template

```
## Day Restriction Testing - [Date]

### Environment
- WordPress Version: [version]
- WooCommerce Version: [version]
- PHP Version: [version]
- Plugin Version: [version]

### Test Results

| Scenario | Expected | Actual | Status |
|----------|----------|--------|--------|
| Basic (27th allowed) | Works on 27th | [result] | ✅/❌ |
| Basic (other days) | Blocked | [result] | ✅/❌ |
| Multiple days (1,15,27) | Works on all | [result] | ✅/❌ |
| Last valid day (Feb) | Works on Feb 28/29 | [result] | ✅/❌ |
| Specific coupons only | Targets correct coupons | [result] | ✅/❌ |
| Feature disabled | All days work | [result] | ✅/❌ |
| Admin bypass | Works in wp-admin | [result] | ✅/❌ |
| Custom error | Displays correctly | [result] | ✅/❌ |

### Notes
[Any additional observations or issues]
```

---

## 🎨 Visual Testing (Browser)

### Frontend (Customer View)

**Success State:**
```
Coupon code: [TEST27]
[Apply coupon]

✅ Coupon code applied successfully.
Cart subtotal: $100.00
Discount (TEST27): -$10.00
Total: $90.00
```

**Blocked State:**
```
Coupon code: [TEST27]
[Apply coupon]

❌ This coupon is only valid on specific days of the month.
Cart subtotal: $100.00
Total: $100.00
```

### Backend (Admin View)

**Settings Page:**
- Navigate to: **WooCommerce → Settings**
- Tab visible: **Coupon Gatekeeper**
- Sections: 6 distinct sections with clear headings
- All fields render without errors
- Save button at bottom

**Order Edit:**
- Navigate to: **WooCommerce → Orders → Add New**
- Coupon field visible in order items section
- Apply restricted coupon (with bypass)
- Should apply without error

---

## ⚡ Performance Check

### Expected Behavior
- ✅ No noticeable delay when applying coupons
- ✅ No additional database queries
- ✅ Settings load once per request
- ✅ Validation completes in < 1ms

### How to Verify
```php
// Add to wp-config.php temporarily
define( 'SAVEQUERIES', true );

// Then check in footer or debug.log
// Should see NO new queries from validation
```

---

## 📞 Support

### Documentation Files
- `SETTINGS.md` - Complete settings reference
- `DAY_RESTRICTION_GUIDE.md` - Feature documentation
- `PHASE3A_COMPLETE.md` - Implementation details
- `IMPLEMENTATION_CHECKLIST.md` - Development status

### Debug Mode
```php
// Add to wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Check debug.log for errors
```

---

**Ready to test! 🚀**

Complete all scenarios above to verify the Day Restriction feature is working correctly.