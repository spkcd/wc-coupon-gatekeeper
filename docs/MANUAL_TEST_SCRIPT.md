# Manual Test Script
## WC Coupon Gatekeeper Plugin

**Version:** 1.0.0  
**Test Date:** ___________  
**Tester:** ___________  
**Environment:** ___________

---

## Test Environment Setup

### Prerequisites

- [ ] WordPress 5.5+ installed
- [ ] WooCommerce 3.5+ installed and activated
- [ ] WC Coupon Gatekeeper plugin installed and activated
- [ ] Test coupons created (see setup below)
- [ ] Test products available in store
- [ ] Payment gateway configured (e.g., Cash on Delivery)

### Test Data Setup

**Create Test Coupons:**

1. **Coupon:** `DAY27` (Day Restricted)
   - Discount: Fixed $10
   - Usage restriction: None (managed by plugin)
   
2. **Coupon:** `MONTHLY1` (Monthly Limit)
   - Discount: Fixed $15
   - Usage restriction: None (managed by plugin)

3. **Coupon:** `COMBINED` (Both Restrictions)
   - Discount: Percentage 20%
   - Usage restriction: None (managed by plugin)

**Plugin Settings:**
```
WooCommerce → Settings → Coupon Gatekeeper

Feature Toggles:
☑ Enable Day-of-Month Restriction
☑ Enable Per-Customer Monthly Limit

Coupon Targeting:
Restricted Coupons: DAY27, MONTHLY1, COMBINED
☐ Apply to ALL Coupons

Allowed Days: 27
☐ Use Last Valid Day

Monthly Limit: 1
Per-Coupon Overrides: (leave empty)

Customer Identification: User ID Priority
☑ Anonymize Email

Messages:
Error: Not Allowed Day: "This coupon can only be used on the 27th each month."
Error: Monthly Limit: "You've already used this coupon this month."
☐ Show Success Message

Advanced:
Count Usage Statuses: Processing, Completed
Decrement Usage Statuses: Cancelled, Refunded
☑ Admin Bypass Edit Order
Log Retention: 18 months
```

---

## Test Scenarios

### Scenario 1: Day Restriction - Logged User

**Objective:** Test day-of-month restriction for logged-in user.

**Pre-conditions:**
- Current day is NOT 27
- User is logged in
- Cart has product worth $20

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Navigate to cart page | Cart displays with product | |
| 2 | Apply coupon code `DAY27` | ❌ Error: "This coupon can only be used on the 27th each month." | |
| 3 | Verify discount NOT applied | Cart total unchanged ($20) | |
| 4 | Check coupon field styling | Red error box displayed | |

**Post-conditions:** No order created, cart total unchanged.

---

### Scenario 2: Day Restriction - Guest User

**Objective:** Test day-of-month restriction for guest user.

**Pre-conditions:**
- Current day is NOT 27
- User is logged out
- Cart has product worth $30

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Log out if logged in | Logged out successfully | |
| 2 | Add product to cart | Cart has $30 product | |
| 3 | Apply coupon code `DAY27` | ❌ Error: "This coupon can only be used on the 27th each month." | |
| 4 | Proceed to checkout | Checkout loads, no discount applied | |
| 5 | Fill billing details | Form filled | |
| 6 | Try applying coupon again | ❌ Same error message | |

**Post-conditions:** No order created.

---

### Scenario 3: Day Restriction - Allowed Day

**Objective:** Test coupon success on allowed day.

**Pre-conditions:**
- **IMPORTANT:** Change system date to 27th or wait until 27th
- User is logged in
- Cart has product worth $25

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Verify current date is 27th | Date confirmed | |
| 2 | Add product to cart ($25) | Cart displays correctly | |
| 3 | Apply coupon code `DAY27` | ✅ Success: "Coupon code applied successfully." | |
| 4 | Verify discount applied | Cart total reduced to $15 ($25 - $10) | |
| 5 | Complete checkout | Order created successfully | |
| 6 | Check order details | Discount reflected in order | |

**Post-conditions:** Order created with coupon applied.

---

### Scenario 4: Monthly Limit - First Usage

**Objective:** Test monthly limit on first usage (should allow).

**Pre-conditions:**
- User is logged in
- No previous usage of `MONTHLY1` this month
- Cart has product worth $50

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Apply coupon code `MONTHLY1` | ✅ Coupon applied successfully | |
| 2 | Verify discount applied | Cart total: $35 ($50 - $15) | |
| 3 | Complete checkout | Order created successfully | |
| 4 | Check order status | Status: Processing | |
| 5 | Go to WooCommerce → Gatekeeper Logs | Log entry created for current month | |
| 6 | Verify usage count | Count = 1 | |

**Post-conditions:** Order created, usage logged.

---

### Scenario 5: Monthly Limit - Exceeded

**Objective:** Test monthly limit enforcement (should block).

**Pre-conditions:**
- **Same user** from Scenario 4 (already used `MONTHLY1` once)
- Still in same month
- Cart has product worth $40

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Clear cart | Cart empty | |
| 2 | Add new product ($40) | Product in cart | |
| 3 | Apply coupon code `MONTHLY1` | ❌ Error: "You've already used this coupon this month." | |
| 4 | Verify discount NOT applied | Cart total unchanged ($40) | |
| 5 | Try proceeding to checkout | Checkout loads without discount | |
| 6 | Attempt to apply coupon again | ❌ Same error message | |

**Post-conditions:** No new order created, count stays at 1.

---

### Scenario 6: Refund/Rollback

**Objective:** Test usage count decrement on order refund.

**Pre-conditions:**
- Order from Scenario 4 exists (Status: Processing)
- User has used `MONTHLY1` once
- Admin is logged in

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Navigate to WooCommerce → Orders | Orders list displayed | |
| 2 | Open order from Scenario 4 | Order details displayed | |
| 3 | Check order notes | Note: "Coupon Gatekeeper: Usage incremented..." | |
| 4 | Change status to "Refunded" | Status changed successfully | |
| 5 | Check order notes again | Note: "Coupon Gatekeeper: Usage decremented..." | |
| 6 | Go to Gatekeeper Logs | Usage count decremented to 0 | |
| 7 | Log out admin, log in as customer | Customer logged in | |
| 8 | Add product ($50), apply `MONTHLY1` | ✅ Coupon applied successfully | |

**Post-conditions:** Usage count reset, coupon can be used again.

---

### Scenario 7: Multiple Coupons in Same Order

**Objective:** Test handling multiple managed coupons.

**Pre-conditions:**
- Current day is 27th
- User is logged in
- User has NOT used either coupon this month
- Cart has products worth $100

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Add product to cart ($100) | Product in cart | |
| 2 | Apply coupon code `DAY27` | ✅ Applied: -$10 | |
| 3 | Apply coupon code `MONTHLY1` | ✅ Applied: -$15 | |
| 4 | Verify total | Cart total: $75 ($100 - $10 - $15) | |
| 5 | Complete checkout | Order created | |
| 6 | Go to Gatekeeper Logs | Two log entries created | |
| 7 | Verify counts | DAY27: 1, MONTHLY1: 1 | |

**Post-conditions:** Both coupons tracked separately.

---

### Scenario 8: Multiple Orders in Same Month

**Objective:** Test monthly limit across multiple orders.

**Pre-conditions:**
- User is logged in
- Monthly limit set to 3 for testing (temporarily change setting)
- User has NOT used coupon this month

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Change monthly limit to 3 | Setting saved | |
| 2 | Create order #1 with `MONTHLY1` | Order created, count: 1 | |
| 3 | Create order #2 with `MONTHLY1` | Order created, count: 2 | |
| 4 | Create order #3 with `MONTHLY1` | Order created, count: 3 | |
| 5 | Try to create order #4 | ❌ Error: "You've already used this coupon this month." | |
| 6 | Check Gatekeeper Logs | Count shows 3 | |
| 7 | Restore monthly limit to 1 | Setting saved | |

**Post-conditions:** Limit enforced across multiple orders.

---

### Scenario 9: Timezone Edge Case - 23:59 to 00:00

**Objective:** Test day transition at midnight.

**Pre-conditions:**
- Current time is 23:59 on day 26
- Allowed day is 27
- User is logged in

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | At 23:59:50 on 26th, apply `DAY27` | ❌ Error: Not allowed day | |
| 2 | Wait until 00:00:10 on 27th | Time changed | |
| 3 | Refresh page | Page refreshed | |
| 4 | Apply `DAY27` again | ✅ Coupon applied successfully | |

**Note:** This test requires precise timing or server time manipulation.

---

### Scenario 10: Guest Checkout with Account Creation

**Objective:** Test guest → user conversion during checkout.

**Pre-conditions:**
- User is logged out
- Cart has product worth $30
- Current day is 27th

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Log out if logged in | Logged out | |
| 2 | Add product to cart | Product added | |
| 3 | Apply coupon code `DAY27` | ✅ Coupon applied | |
| 4 | Proceed to checkout | Checkout page loaded | |
| 5 | Fill billing details with NEW email | Form filled: newuser@test.com | |
| 6 | ☑ "Create an account?" checkbox | Checkbox checked | |
| 7 | Complete order | Order created, account created | |
| 8 | Go to Gatekeeper Logs (as admin) | Log entry with customer key | |
| 9 | Log in as newuser@test.com | Login successful | |
| 10 | Try using `DAY27` again | ❌ Error: Limit reached | |

**Post-conditions:** User account linked to usage history.

---

### Scenario 11: Admin Bypass - Manual Order

**Objective:** Test admin bypass when creating manual orders.

**Pre-conditions:**
- Admin is logged in
- Current day is NOT 27
- Customer has exceeded monthly limit
- Admin bypass enabled in settings

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Go to WooCommerce → Orders → Add Order | New order screen | |
| 2 | Select customer | Customer selected | |
| 3 | Add product | Product added | |
| 4 | Click "Apply coupon" | Coupon field displayed | |
| 5 | Enter `DAY27` (day restriction) | ✅ Coupon applied (bypassed) | |
| 6 | Enter `MONTHLY1` (limit exceeded) | ✅ Coupon applied (bypassed) | |
| 7 | Verify both applied | Both discounts visible | |
| 8 | Change status to Processing | Status changed | |
| 9 | Check order notes | Notes show coupons applied | |
| 10 | Check Gatekeeper Logs | Usage NOT incremented (admin order) | |

**Post-conditions:** Admin can override restrictions.

---

### Scenario 12: Fallback Day - February 31st

**Objective:** Test "Use Last Valid Day" fallback logic.

**Pre-conditions:**
- Current month is February (28 or 29 days)
- Current day is last day of February
- Allowed days set to 31
- "Use Last Valid Day" enabled

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Change settings: Allowed days = 31 | Saved | |
| 2 | Enable "Use Last Valid Day" | ☑ Enabled | |
| 3 | **Wait until February 28/29** | On last day of Feb | |
| 4 | Apply coupon code `DAY27` | ✅ Coupon applied | |
| 5 | Check notice | ℹ️ "Coupon valid today because the configured day doesn't occur this month." | |
| 6 | Complete checkout | Order created | |

**Note:** This test requires waiting for February or server date manipulation.

---

### Scenario 13: Cancelled Order Rollback

**Objective:** Test usage count decrement on order cancellation.

**Pre-conditions:**
- Order exists with `MONTHLY1` (Status: Processing)
- Usage count = 1

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Go to WooCommerce → Orders | Orders list | |
| 2 | Open order with `MONTHLY1` | Order details | |
| 3 | Change status to "Cancelled" | Status changed | |
| 4 | Check order notes | Note: "Usage decremented..." | |
| 5 | Go to Gatekeeper Logs | Count decremented to 0 | |
| 6 | Customer tries to use coupon again | ✅ Allowed (count reset) | |

**Post-conditions:** Cancellation rolls back usage.

---

### Scenario 14: UX Notices - Success Message

**Objective:** Test optional success message display.

**Pre-conditions:**
- Current day is 27
- User is logged in
- Success message DISABLED by default

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Apply `DAY27` on cart page | ✅ Standard success message | |
| 2 | Check for custom success notice | ❌ No custom notice (feature disabled) | |
| 3 | Go to Settings → Enable Success Message | ☑ Enabled | |
| 4 | Set custom message: "Great timing! 🎉" | Message saved | |
| 5 | Clear cart, re-add product | Cart refreshed | |
| 6 | Apply `DAY27` again | ✅ Shows: "Great timing! 🎉" | |
| 7 | Verify notice color | Green success notice | |

**Post-conditions:** Custom success message displayed.

---

### Scenario 15: Multisite Compatibility

**Objective:** Test plugin on multisite network (if available).

**Pre-conditions:**
- WordPress Multisite enabled
- Plugin network-activated
- At least 2 sites in network

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Activate plugin network-wide | Activated successfully | |
| 2 | Switch to Site 1 | Site 1 active | |
| 3 | Configure settings for Site 1 | Settings saved | |
| 4 | Create test order on Site 1 | Order created, usage logged | |
| 5 | Switch to Site 2 | Site 2 active | |
| 6 | Check if Site 1 settings exist | ❌ Site 2 has independent settings | |
| 7 | Configure settings for Site 2 (different) | Settings saved | |
| 8 | Create test order on Site 2 | Order created independently | |
| 9 | Check database tables | Each site has own table with prefix | |
| 10 | Switch back to Site 1 | Site 1 usage data intact | |

**Post-conditions:** Sites operate independently.

---

### Scenario 16: HPOS Compatibility

**Objective:** Test with WooCommerce High-Performance Order Storage.

**Pre-conditions:**
- WooCommerce 7.0+ installed
- HPOS feature available

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Go to WooCommerce → Settings → Advanced → Features | Features page | |
| 2 | Enable "High-performance order storage" | ☑ Enabled | |
| 3 | Verify plugin compatibility declaration | ✅ Compatible label shown | |
| 4 | Create order with managed coupon | Order created | |
| 5 | Check usage logs | Usage tracked correctly | |
| 6 | Change order status | Status changes logged | |
| 7 | Refund order | Usage decremented | |
| 8 | Verify no errors in debug log | No errors | |

**Post-conditions:** Plugin works with HPOS enabled.

---

## Security Testing

### Scenario 17: Capability Checks

**Objective:** Verify non-admin users cannot access admin functions.

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Create "Customer" role user | User created | |
| 2 | Log in as customer | Logged in | |
| 3 | Try accessing /wp-admin/admin.php?page=wc-settings&tab=coupon_gatekeeper | ❌ "You do not have permission..." | |
| 4 | Try accessing /wp-admin/admin.php?page=wc-coupon-gatekeeper-logs | ❌ "You do not have permission..." | |
| 5 | Try AJAX: wp_ajax_wcgk_purge_old_logs | ❌ Permission denied error | |
| 6 | Log out, log in as admin | Success | |
| 7 | Access same pages | ✅ Pages accessible | |

**Post-conditions:** Security enforced.

---

### Scenario 18: Nonce Verification

**Objective:** Verify CSRF protection on all forms.

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Inspect settings form HTML | Nonce field present | |
| 2 | Copy form POST data | Data copied | |
| 3 | Wait 25 hours (nonce expires after 24h) | Nonce expired | |
| 4 | Try submitting form with old nonce | ❌ "Security check failed" | |
| 5 | Refresh page, submit with new nonce | ✅ Saved successfully | |

**Post-conditions:** Nonces validated.

---

### Scenario 19: SQL Injection Test

**Objective:** Verify input sanitization prevents SQL injection.

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Create coupon with code: `'; DROP TABLE wp_users; --` | Coupon created | |
| 2 | Add to restricted coupons list | Saved | |
| 3 | Try using the malicious coupon code | Safely handled, no SQL injection | |
| 4 | Check database tables | All tables intact | |
| 5 | Check error log | No SQL errors | |

**Post-conditions:** SQL injection prevented.

---

### Scenario 20: XSS Prevention

**Objective:** Verify output escaping prevents XSS attacks.

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Set error message to: `<script>alert('XSS')</script>` | Message saved | |
| 2 | Try to trigger the error message | Error displayed | |
| 3 | Check if script executes | ❌ Script NOT executed (escaped) | |
| 4 | Inspect HTML source | Shows: `&lt;script&gt;alert...` | |
| 5 | Restore safe error message | Restored | |

**Post-conditions:** XSS prevented.

---

## Performance Testing

### Scenario 21: Database Query Efficiency

**Objective:** Verify minimal database queries added.

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Install Query Monitor plugin | Installed | |
| 2 | Apply coupon on cart page | Coupon applied | |
| 3 | Check Query Monitor | Max 2 additional queries (settings + usage check) | |
| 4 | Complete checkout | Order created | |
| 5 | Check Query Monitor | Max 3 additional queries (increment usage) | |

**Post-conditions:** Minimal performance impact.

---

### Scenario 22: Concurrent Usage

**Objective:** Test race conditions with simultaneous requests.

**Test Steps:**

| Step | Action | Expected Result | ✓/✗ | Notes |
|------|--------|----------------|-----|-------|
| 1 | Set monthly limit to 1 | Limit saved | |
| 2 | Open 2 browser tabs, log in as same user | Both tabs logged in | |
| 3 | In both tabs, add product and apply `MONTHLY1` | Both show success initially | |
| 4 | In Tab 1, complete checkout quickly | Order created | |
| 5 | In Tab 2, complete checkout | ❌ Should fail at checkout if limit check re-runs | |
| 6 | Check usage logs | Count = 1 (correct, no double-increment) | |

**Post-conditions:** Race conditions handled.

---

## Test Summary

**Total Scenarios:** 22  
**Passed:** _____  
**Failed:** _____  
**Blocked:** _____  
**Skipped:** _____

---

## Defects Found

| ID | Scenario | Severity | Description | Status |
|----|----------|----------|-------------|--------|
| 1  | | | | |
| 2  | | | | |
| 3  | | | | |

---

## Notes

**Environment Details:**
- WordPress Version: _____
- WooCommerce Version: _____
- PHP Version: _____
- Database: _____
- Theme: _____
- Other Plugins: _____

**Test Execution Notes:**
- 
- 
- 

---

## Sign-off

**Tested By:** ___________________  
**Date:** ___________  
**Signature:** ___________________

**Approved By:** ___________________  
**Date:** ___________  
**Signature:** ___________________

---

## Quick Reference: Test Data

### Test Users
- **Admin:** admin / password
- **Customer 1:** customer1@test.com / password
- **Customer 2:** customer2@test.com / password
- **Guest:** (no account)

### Test Coupons
- **DAY27:** Day restriction (27th only), $10 fixed
- **MONTHLY1:** Monthly limit (1 per month), $15 fixed
- **COMBINED:** Both restrictions, 20% percentage

### Test Products
- **Product A:** $20
- **Product B:** $30
- **Product C:** $50
- **Product D:** $100

---

**END OF TEST SCRIPT**