# Quick Start Guide - WC Coupon Gatekeeper

## 🚀 Get Started in 5 Minutes

This guide will help you test the **Monthly Limit** feature that was just implemented.

---

## Prerequisites

✅ WordPress installed  
✅ WooCommerce installed and activated  
✅ Plugin files uploaded to `/wp-content/plugins/wc-coupon-gatekeeper/`  

---

## Step 1: Activate Plugin

```bash
# Via WP-CLI
wp plugin activate wc-coupon-gatekeeper

# Or via WordPress Admin
# Plugins → Installed Plugins → Activate "WC Coupon Gatekeeper"
```

**What happens:**
- Database table `wp_wc_coupon_gatekeeper_usage` created
- Default settings initialized
- Day restriction: Enabled (27th only)
- Monthly limit: Enabled (1 use per month)

---

## Step 2: Configure Settings

1. Go to: **WooCommerce → Settings → Coupon Gatekeeper**

2. **Basic Configuration:**
   ```
   Feature Toggles:
   ☑ Enable Day-of-Month Restriction
   ☑ Enable Monthly Usage Limit
   
   Coupon Targeting:
   ☐ Apply to ALL coupons
   Restricted Coupons: TEST27, VIP10
   
   Allowed Days:
   Select: 1, 15, 27
   ☐ Use Last Valid Day of Month
   
   Monthly Limit:
   Default Monthly Limit: 1
   Per-Coupon Overrides: vip10:3
   
   Customer Identification:
   ◉ User ID (with email fallback)
   ☑ Anonymize guest emails (GDPR)
   ```

3. **Save Changes**

---

## Step 3: Create Test Coupons

### Coupon 1: TEST27 (Basic - 1 use/month)

```bash
wp wc coupon create \
  --code="TEST27" \
  --discount_type="fixed_cart" \
  --amount="10"
```

**Or via Admin:**
- Marketing → Coupons → Add Coupon
- Code: `TEST27`
- Discount type: Fixed cart discount
- Amount: 10

### Coupon 2: VIP10 (Override - 3 uses/month)

```bash
wp wc coupon create \
  --code="VIP10" \
  --discount_type="percent" \
  --amount="10"
```

**Settings override:** Already configured `vip10:3` in settings

---

## Step 4: Test Scenarios

### 🧪 Test 1: Day Restriction (5 min)

**Goal:** Verify coupon only works on allowed days

```
Today's date: Check if it's 1st, 15th, or 27th

If YES (allowed day):
1. Add product to cart
2. Apply TEST27 → ✅ Should work
3. View cart total → Discount applied

If NO (not allowed day):
1. Add product to cart
2. Apply TEST27 → ❌ Should fail
3. Error: "This coupon can only be used on the allowed day(s) each month."
```

**To test on any day:**
- Temporarily change settings to include today's date
- Or change "Allowed Days" to "ALL" (select 1-31)

---

### 🧪 Test 2: Monthly Limit - Logged-in User (10 min)

**Goal:** Verify 1 use per month limit

```
1. Create test user:
   wp user create testuser test@example.com --role=customer --user_pass=password

2. Login as testuser (frontend)

3. Test first use:
   - Add product to cart
   - Apply TEST27
   - Complete checkout
   - Order status → Processing ✅
   
4. Check database:
   wp db query "SELECT * FROM wp_wc_coupon_gatekeeper_usage"
   Expected: 1 row with count=1

5. Test second use (should fail):
   - Add new product to cart
   - Apply TEST27
   - Error: "You've already used this coupon this month." ❌

6. Test cancellation (decrement):
   - Cancel previous order
   - Order status → Cancelled
   - Check database: count should be 0
   
7. Test third use (should work now):
   - Apply TEST27 → ✅ Works again!
```

---

### 🧪 Test 3: Monthly Limit - Guest Checkout (10 min)

**Goal:** Verify email-based tracking

```
1. Logout (or use incognito)

2. First use:
   - Add product to cart
   - Apply TEST27
   - Proceed to checkout
   - Enter email: guest@example.com
   - Complete order → Status: Processing ✅

3. Second attempt with SAME email:
   - Add new product
   - Apply TEST27
   - Proceed to checkout
   - Enter email: guest@example.com
   - Error: "You've already used this coupon this month." ❌

4. Try with DIFFERENT email:
   - Clear cart
   - Add product
   - Apply TEST27
   - Enter email: different@example.com
   - Should work ✅ (treated as different customer)

5. Check database:
   wp db query "SELECT customer_key, count FROM wp_wc_coupon_gatekeeper_usage WHERE coupon_code='test27'"
   
   Expected: 2 rows
   - hash:abc123... (guest@example.com hashed) - count: 1
   - hash:def456... (different@example.com hashed) - count: 1
```

---

### 🧪 Test 4: Per-Coupon Override (10 min)

**Goal:** Verify VIP10 allows 3 uses

```
1. Login as testuser

2. First use:
   - Add product → Apply VIP10 → Complete order ✅
   - Database: count = 1

3. Second use:
   - Add product → Apply VIP10 → Complete order ✅
   - Database: count = 2

4. Third use:
   - Add product → Apply VIP10 → Complete order ✅
   - Database: count = 3

5. Fourth use (should fail):
   - Add product → Apply VIP10 → ❌ Blocked
   - Error: "You've already used this coupon this month."
```

---

### 🧪 Test 5: Month Reset (Optional)

**Goal:** Verify usage resets on new month

```
This requires either:
A) Waiting until next month, OR
B) Manually updating database

Option B (Quick test):
1. Use TEST27 once → Blocked on second use
2. Update database to simulate new month:
   wp db query "UPDATE wp_wc_coupon_gatekeeper_usage SET month='2023-12' WHERE coupon_code='test27'"
3. Try TEST27 again → Should work ✅ (no record for current month)
```

---

### 🧪 Test 6: Multiple Coupons (5 min)

**Goal:** Verify coupons tracked separately

```
1. Clear cart, add product
2. Apply both TEST27 and VIP10
3. Complete order
4. Check database:
   wp db query "SELECT coupon_code, count FROM wp_wc_coupon_gatekeeper_usage"
   
   Expected: 2 rows
   - test27: count = 1
   - vip10: count = 1
   
5. Try TEST27 again → ❌ Blocked (limit reached)
6. Try VIP10 again → ✅ Works (2/3 used)
```

---

## Step 5: Verify Database

### Check Usage Records

```sql
-- All current month usage
wp db query "SELECT * FROM wp_wc_coupon_gatekeeper_usage WHERE month = DATE_FORMAT(NOW(), '%Y-%m')"

-- Specific coupon
wp db query "SELECT * FROM wp_wc_coupon_gatekeeper_usage WHERE coupon_code='test27'"

-- Customer usage
wp db query "SELECT coupon_code, count FROM wp_wc_coupon_gatekeeper_usage WHERE customer_key LIKE 'user:%'"
```

### Expected Schema

```sql
+----+-------------+-----------------------+--------+-------+---------------+---------------------+
| id | coupon_code | customer_key          | month  | count | last_order_id | updated_at          |
+----+-------------+-----------------------+--------+-------+---------------+---------------------+
|  1 | test27      | user:2                | 2024-01|   1   | 123           | 2024-01-27 10:30:00 |
|  2 | vip10       | user:2                | 2024-01|   2   | 125           | 2024-01-27 11:00:00 |
|  3 | test27      | hash:a3f2b91c4e...    | 2024-01|   1   | 127           | 2024-01-27 12:00:00 |
+----+-------------+-----------------------+--------+-------+---------------+---------------------+
```

---

## Step 6: Test Admin Features

### Admin Bypass

```
1. Login as admin (with manage_woocommerce capability)
2. Go to: WooCommerce → Orders → Add Order
3. Add product
4. Apply TEST27 (even if limit reached)
5. Should work ✅ (admin bypass active)

Note: Admin bypass does NOT work:
- On frontend checkout
- During AJAX requests
- For users without manage_woocommerce capability
```

### Purge Old Logs

```
1. Go to: WooCommerce → Settings → Coupon Gatekeeper
2. Scroll to: Advanced Settings
3. Click: "Purge Old Logs Now"
4. Confirm
5. Success message: "Logs purged successfully"
```

---

## Troubleshooting

### Issue: Coupon not being blocked

**Checklist:**
```
1. Feature enabled?
   → Check: Settings → Enable Monthly Usage Limit ☑

2. Coupon managed?
   → Check: Settings → Restricted Coupons includes your coupon
   → OR: "Apply to ALL coupons" is checked

3. Database record exists?
   → Query: SELECT * FROM wp_wc_coupon_gatekeeper_usage

4. Order reached count status?
   → Check order status is "processing" or "completed"
   → NOT "pending" or "on-hold"
```

### Issue: Usage not incrementing

**Debug:**
```bash
# Check order notes
wp wc order get 123 --field=notes

# Should see: "Coupon Gatekeeper: Usage incremented for 'test27' in 2024-01"

# Check order status
wp wc order get 123 --field=status

# Should be: processing or completed
```

### Issue: Customer key not found

**Debug:**
```bash
# For logged-in users
echo "Customer key: user:" . wp_get_current_user()->ID

# For guests (check order)
wp wc order get 123 --field=billing.email

# Should generate: hash:abc123... (if anonymization ON)
#              or: email:customer@example.com (if OFF)
```

---

## Manual Database Operations

### Reset Specific Customer Usage

```sql
wp db query "UPDATE wp_wc_coupon_gatekeeper_usage SET count=0 WHERE customer_key='user:2' AND coupon_code='test27'"
```

### Delete All Usage for Coupon

```sql
wp db query "DELETE FROM wp_wc_coupon_gatekeeper_usage WHERE coupon_code='test27'"
```

### View Top Users

```sql
wp db query "SELECT customer_key, SUM(count) as total FROM wp_wc_coupon_gatekeeper_usage GROUP BY customer_key ORDER BY total DESC LIMIT 10"
```

---

## Performance Check

### Measure Validation Speed

```bash
# Enable WordPress debug
wp config set WP_DEBUG true
wp config set SAVEQUERIES true

# Check query log in /wp-content/debug.log
# Look for: SELECT count FROM wp_wc_coupon_gatekeeper_usage

# Expected: < 0.001 seconds per query
```

### Database Size

```bash
# Check table size
wp db query "SELECT 
  table_name AS 'Table',
  ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_name = 'wp_wc_coupon_gatekeeper_usage'"

# Expected: < 1MB for 10,000 records
```

---

## Unit Tests

### Run All Tests

```bash
# Install PHPUnit if needed
composer require --dev phpunit/phpunit

# Run all tests
phpunit

# Run specific test suite
phpunit tests/test-monthly-limit.php

# Run with coverage
phpunit --coverage-html ./coverage
```

### Expected Results

```
Tests: 30 tests, 30 assertions
Time: < 2 seconds
Memory: < 10MB

OK (30 tests, 30 assertions)
```

---

## Next Steps

### Option 1: Continue Development
Implement **Phase 3C: Usage Logs Screen**
- WP_List_Table for viewing logs
- Filter/search functionality
- CSV export

### Option 2: Deploy to Production
The plugin is production-ready!
- All features working
- Security validated
- Performance optimized
- Documentation complete

### Option 3: Customize Settings
Adjust configuration for your use case:
- Change allowed days
- Adjust monthly limits
- Configure per-coupon overrides
- Customize error messages

---

## Documentation Reference

| Document | Purpose |
|----------|---------|
| **MONTHLY_LIMIT_GUIDE.md** | Complete feature documentation |
| **TESTING_QUICK_REFERENCE.md** | Detailed testing scenarios |
| **SETTINGS.md** | Settings configuration guide |
| **IMPLEMENTATION_SUMMARY.md** | Full project overview |
| **PHASE3B_COMPLETE.md** | Technical implementation details |

---

## Support

**For issues:**
1. Check troubleshooting section above
2. Review error logs: `/wp-content/debug.log`
3. Check order notes for tracking info
4. Query database directly for debugging

**For questions:**
- Review documentation files
- Check inline code comments
- Refer to PHPDoc blocks

---

**🎉 You're ready to use the Monthly Limit feature!**

**Estimated testing time:** 30-45 minutes to complete all scenarios.