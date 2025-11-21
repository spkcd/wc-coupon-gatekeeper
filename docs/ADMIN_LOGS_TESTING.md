# Admin Logs Screen - Quick Testing Guide

**5-Minute Test Plan for Phase 3C**

---

## Prerequisites

```bash
# 1. Ensure plugin is activated
wp plugin activate wc-coupon-gatekeeper

# 2. Ensure database table exists (should auto-create)
# 3. Have some test orders with coupons (from Phase 3A/3B testing)
```

---

## Test Scenario 1: Basic Navigation (30 seconds)

**Goal:** Verify admin page loads correctly

### Steps

1. Log in as Administrator
2. Navigate to **WooCommerce → Gatekeeper Logs**
3. Verify page loads without errors
4. Check that table displays (may be empty if no usage yet)

### Expected Results

✅ Page title: "Coupon Gatekeeper Logs"  
✅ Tools section visible with two buttons  
✅ Filter inputs displayed above table  
✅ Table shows columns: Coupon Code, Month, Customer Key, Count, Last Order, Updated At

---

## Test Scenario 2: Generate Test Data (1 minute)

**Goal:** Create sample usage records for testing

### Steps

```php
// Run in WP admin or wp-cli
use WC_Coupon_Gatekeeper\Database;

// Create test records
Database::increment_usage('test27', 'user:1', 100, '2024-01');
Database::increment_usage('test27', 'user:1', 101, '2024-01'); // Count = 2
Database::increment_usage('vip10', 'user:1', 102, '2024-01');
Database::increment_usage('test27', 'user:2', 103, '2024-01');
Database::increment_usage('test27', 'user:1', 104, '2024-02');
Database::increment_usage('summer50', 'email:guest@test.com', 105, '2024-01');
```

### Expected Results

✅ 6 records created  
✅ Table now shows data  
✅ Various coupons, customers, and months represented

---

## Test Scenario 3: Filter by Month (1 minute)

**Goal:** Verify month filtering works

### Steps

1. Open dropdown **"All Months"**
2. Select **"2024-01"**
3. Click **"Apply Filters"**
4. Verify results only show January 2024

### Expected Results

✅ Only records with Month = "2024-01" displayed  
✅ URL updated with `?filter_month=2024-01`  
✅ "Clear Filters" button appears

---

## Test Scenario 4: Filter by Coupon Code (30 seconds)

**Goal:** Verify coupon code search works

### Steps

1. Clear any existing filters
2. Type **"test"** in coupon code filter
3. Click **"Apply Filters"**
4. Verify results only show TEST27

### Expected Results

✅ Only TEST27 records displayed  
✅ Partial matching works (doesn't require exact "test27")  
✅ Case-insensitive

---

## Test Scenario 5: Combined Filters (30 seconds)

**Goal:** Verify multiple filters work together

### Steps

1. Month: **"2024-01"**
2. Coupon: **"test27"**
3. Customer: **"user:1"**
4. Click **"Apply Filters"**

### Expected Results

✅ Only records matching ALL filters displayed  
✅ Should show 1 record: TEST27, 2024-01, user:1, count=2

---

## Test Scenario 6: Reset Single Count (1 minute)

**Goal:** Verify reset count action works

### Steps

1. Hover over a coupon code row
2. Click **"Reset Count"** link
3. Click **"OK"** in confirmation dialog
4. Wait for page reload

### Expected Results

✅ Confirmation dialog appears with warning message  
✅ After confirmation, success message displayed  
✅ Count for that record now shows **0**  
✅ Updated at timestamp refreshed

---

## Test Scenario 7: View Customer History (1 minute)

**Goal:** Verify history modal works

### Steps

1. Hover over TEST27 record for user:1
2. Click **"View 12-Month History"** link
3. Wait for modal to open

### Expected Results

✅ Modal window opens with overlay  
✅ Shows "12-Month Usage History" title  
✅ Displays coupon code: TEST27  
✅ Displays customer: user:1  
✅ Table shows usage by month:
   - 2024-02: Count 1
   - 2024-01: Count 2 (or 0 if you reset it)
✅ Click X or Escape to close modal

---

## Test Scenario 8: Bulk Reset (1 minute)

**Goal:** Verify bulk actions work

### Steps

1. Clear all filters
2. Check 2-3 record checkboxes
3. Select **"Reset Selected"** from bulk actions dropdown
4. Click **"Apply"** button
5. Confirm in dialog

### Expected Results

✅ Confirmation dialog appears  
✅ Success message: "N usage records reset."  
✅ Page reloads  
✅ All selected records now have count = 0

---

## Test Scenario 9: Export CSV (30 seconds)

**Goal:** Verify CSV export works

### Steps

1. Apply any filter (e.g., Month = 2024-01)
2. Click **"Export Current View as CSV"** button
3. Check downloads folder

### Expected Results

✅ File downloads: `coupon-usage-logs-YYYY-MM-DD-HHmmss.csv`  
✅ Open in Excel/Sheets  
✅ Contains headers: Coupon Code, Month, Customer Key, Count, Last Order ID, Updated At  
✅ Data matches filtered view

---

## Test Scenario 10: Purge Old Logs (1 minute)

**Goal:** Verify purge functionality works

### Steps

1. Go to **Settings → Monthly Limit**
2. Set **Data Retention** to **12 months**
3. Save settings
4. Return to **Gatekeeper Logs**
5. Click **"Purge Logs Older Than 12 Months"**
6. Confirm in dialog

### Expected Results

✅ Confirmation dialog appears with warning  
✅ Success message: "N old usage records deleted."  
✅ Only recent records remain (within 12 months)

---

## Test Scenario 11: Sorting (30 seconds)

**Goal:** Verify column sorting works

### Steps

1. Click **"Count"** column header
2. Verify records sort by count ascending
3. Click **"Count"** again
4. Verify records sort by count descending

### Expected Results

✅ First click: Lowest count first  
✅ Second click: Highest count first  
✅ Arrow indicator shows sort direction

---

## Test Scenario 12: Pagination (30 seconds)

**Goal:** Verify pagination works

### Steps (if you have 20+ records)

1. Ensure table has 20+ records
2. Scroll to bottom of table
3. Click **"2"** in pagination
4. Verify next page loads

### Expected Results

✅ Shows records 21-40  
✅ Page reloads with `?paged=2`  
✅ Pagination controls update

---

## Test Scenario 13: Permissions (30 seconds)

**Goal:** Verify capability checks work

### Steps

1. Log out as admin
2. Log in as **Customer** role
3. Try to access:
   ```
   /wp-admin/admin.php?page=wc-coupon-gatekeeper-logs
   ```

### Expected Results

✅ Access denied  
✅ Message: "You do not have permission to access this page."  
✅ No data exposed

---

## Test Scenario 14: Responsive Design (1 minute)

**Goal:** Verify mobile compatibility

### Steps

1. Open browser DevTools (F12)
2. Toggle device toolbar (Ctrl+Shift+M)
3. Select **iPhone 12** or similar
4. Navigate page

### Expected Results

✅ Table scrolls horizontally on small screens  
✅ Filters stack vertically  
✅ Buttons remain accessible  
✅ Modal resizes to 95% width  
✅ Touch targets are large enough

---

## Test Scenario 15: JavaScript Errors (30 seconds)

**Goal:** Verify no console errors

### Steps

1. Open browser DevTools Console (F12)
2. Navigate to Gatekeeper Logs page
3. Perform various actions:
   - Filter
   - Reset count
   - View history
   - Export CSV

### Expected Results

✅ No JavaScript errors in console  
✅ No 404 errors for assets  
✅ No AJAX errors

---

## Complete Test Results Template

```
Phase 3C: Admin Logs Screen Testing
Date: _______________
Tester: _______________

[ ] Scenario 1: Basic Navigation
[ ] Scenario 2: Generate Test Data
[ ] Scenario 3: Filter by Month
[ ] Scenario 4: Filter by Coupon Code
[ ] Scenario 5: Combined Filters
[ ] Scenario 6: Reset Single Count
[ ] Scenario 7: View Customer History
[ ] Scenario 8: Bulk Reset
[ ] Scenario 9: Export CSV
[ ] Scenario 10: Purge Old Logs
[ ] Scenario 11: Sorting
[ ] Scenario 12: Pagination
[ ] Scenario 13: Permissions
[ ] Scenario 14: Responsive Design
[ ] Scenario 15: JavaScript Errors

Issues Found:
_________________________________
_________________________________
_________________________________

Overall Status: [ ] PASS [ ] FAIL
```

---

## Automated Testing

```bash
# Run admin logs tests
phpunit tests/test-admin-logs.php

# Expected output:
# PHPUnit 9.x
# .....................  20 / 20 (100%)
# 
# Time: < 1 second, Memory: 20 MB
# 
# OK (20 tests, 60+ assertions)
```

---

## Common Issues & Solutions

### Issue: Page loads but no data

**Solution:** 
1. Check if monthly limit feature is enabled
2. Create test orders with coupons
3. Ensure orders are in "completed" status

### Issue: Export button does nothing

**Solution:**
1. Check browser console for errors
2. Verify file isn't blocked by popup blocker
3. Try different browser

### Issue: Modal doesn't open

**Solution:**
1. Check console for JavaScript errors
2. Verify jQuery is loaded
3. Check for plugin conflicts

### Issue: Filters return no results

**Solution:**
1. Click "Clear Filters"
2. Verify data exists in table without filters
3. Check filter values match existing data

---

## Performance Verification

### Expected Performance

| Operation | Max Time | Notes |
|-----------|----------|-------|
| Page load | < 200ms | With 1000 records |
| Apply filter | < 100ms | Single filter |
| View history | < 500ms | AJAX request |
| Reset count | < 500ms | AJAX request |
| Export CSV | < 2s | 1000 records |
| Purge logs | < 5s | 10,000 old records |

### How to Measure

```javascript
// In browser console
console.time('pageLoad');
// Navigate or perform action
console.timeEnd('pageLoad');
```

---

## Success Criteria

**Phase 3C is considered fully functional when:**

✅ All 15 test scenarios pass  
✅ No JavaScript console errors  
✅ No PHP errors in logs  
✅ Page loads in < 200ms  
✅ All AJAX actions work correctly  
✅ CSV export downloads and opens  
✅ Permissions properly restrict access  
✅ Mobile view is functional  
✅ Automated tests pass (20/20)  

**If all criteria met → Phase 3C COMPLETE! 🎉**

---

**Estimated Total Test Time:** 15 minutes  
**Recommended Frequency:** Before each deployment  
**Last Updated:** January 15, 2024