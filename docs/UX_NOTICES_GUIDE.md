# UX Notices Guide - WC Coupon Gatekeeper

## Overview

The plugin now includes **intelligent customer notices** that provide clear feedback during the checkout/cart experience. These notices use WooCommerce's native notice API for consistency and accessibility.

---

## 📢 Notice Types

### 1. **Error Notices** (Red)

Shown when coupon validation fails.

#### Not Allowed Day
- **When**: Coupon applied on non-allowed day
- **Default Message**: "This coupon can only be used on the allowed day(s) each month."
- **Customizable**: Yes (Settings → Messages)

#### Monthly Limit Reached
- **When**: Customer exceeded monthly usage limit
- **Default Message**: "You've already used this coupon this month."
- **Customizable**: Yes (Settings → Messages)

---

### 2. **Success Notices** (Green) - Optional

Shown when coupon is successfully applied on an allowed day.

#### Nice Timing Message
- **When**: Coupon applied successfully on configured day
- **Default Message**: "Nice timing! This coupon is valid today."
- **Enable**: Settings → Messages → "Show Success Message" ☑
- **Customizable**: Yes (Settings → Messages → "Success Message")

**Use Cases:**
- Encourage positive reinforcement for customers
- Confirm coupon is working as expected
- Build trust with transparent messaging

---

### 3. **Info Notices** (Blue) - Automatic

Shown when edge cases occur.

#### Fallback Day Notice
- **When**: Configured day doesn't exist this month, fallback to last day
- **Message**: "Coupon valid today because the configured day doesn't occur this month."
- **Automatic**: Shows when "Use Last Valid Day" is enabled
- **Example**: Coupon set for 31st, applied on Feb 28th

**Benefits:**
- Prevents customer confusion
- Explains why coupon works on "unexpected" day
- Improves transparency

---

## 🎛️ Configuration

### Settings Location
**WooCommerce → Settings → Coupon Gatekeeper → Messages**

### Available Options

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| **Error: Not Allowed Day** | Text | "This coupon can only be used on the allowed day(s) each month." | Message for day restriction violations |
| **Error: Monthly Limit Reached** | Text | "You've already used this coupon this month." | Message when limit exceeded |
| **Show Success Message** | Checkbox | Disabled | Enable positive feedback on allowed days |
| **Success Message** | Text | "Nice timing! This coupon is valid today." | Custom success message |

---

## 🎨 Notice Priority

When multiple conditions exist, notices follow this priority:

1. **Error Notices** (always shown first, stop validation)
2. **Fallback Day Notice** (shown if applicable)
3. **Success Notice** (only if no fallback notice)

**Example Scenarios:**

```
Scenario 1: Wrong Day
→ ❌ Error: "This coupon can only be used on the allowed day(s) each month."

Scenario 2: Allowed Day + Success Enabled
→ ✅ Success: "Nice timing! This coupon is valid today."

Scenario 3: Fallback Day (31st → Feb 28)
→ ℹ️ Info: "Coupon valid today because the configured day doesn't occur this month."
   (Success notice suppressed to avoid confusion)

Scenario 4: Limit Reached
→ ❌ Error: "You've already used this coupon this month."
```

---

## 🌍 Accessibility

All notices use WooCommerce's native `wc_add_notice()` API, which ensures:

✅ **Screen Reader Support** - Notices are announced via ARIA live regions  
✅ **Keyboard Navigation** - Notices are accessible without mouse  
✅ **Consistent Styling** - Matches WooCommerce theme styling  
✅ **Mobile Responsive** - Works on all device sizes  

---

## 🔧 Customization Examples

### Example 1: Friendly Success Message
```
Setting: Show Success Message ☑
Message: "🎉 Perfect! Your coupon is active today."
```

**Result:**  
Customer sees green success notice with emoji when coupon applies.

---

### Example 2: Specific Day Messaging
```
Setting: Error: Not Allowed Day
Message: "This coupon is only valid on the 27th of each month. Please come back then!"
```

**Result:**  
Clear, actionable error message telling customer when to return.

---

### Example 3: Urgency Messaging
```
Setting: Error: Monthly Limit Reached
Message: "You've used your monthly discount. Come back next month for more savings!"
```

**Result:**  
Positive spin on limit enforcement, encourages return visit.

---

### Example 4: Brand Voice
```
Setting: Success Message
Message: "Woohoo! Your VIP discount is unlocked today! 🎊"
```

**Result:**  
Fun, branded messaging that matches store personality.

---

## 🧪 Testing Guide

### Manual Testing

#### Test 1: Error Notice - Wrong Day
1. Set allowed days to `27`
2. Change system date to `15th` (use WordPress date/time)
3. Add managed coupon to cart
4. **Expected**: Red error notice: "This coupon can only be used on the allowed day(s) each month."

#### Test 2: Success Notice (When Enabled)
1. Enable "Show Success Message" in settings
2. Set allowed days to today's date
3. Add managed coupon to cart
4. **Expected**: Green success notice: "Nice timing! This coupon is valid today."

#### Test 3: Success Notice (When Disabled)
1. Disable "Show Success Message" in settings
2. Set allowed days to today's date
3. Add managed coupon to cart
4. **Expected**: No success notice (silent validation pass)

#### Test 4: Fallback Day Notice
1. Set allowed days to `31`
2. Enable "Use Last Valid Day"
3. Change system date to February 28 (non-leap year)
4. Add managed coupon to cart
5. **Expected**: Blue info notice: "Coupon valid today because the configured day doesn't occur this month."

#### Test 5: Limit Reached Error
1. Manually add usage record for current month
2. Set allowed days to today's date
3. Add managed coupon to cart
4. **Expected**: Red error notice: "You've already used this coupon this month."

#### Test 6: Custom Messages
1. Set custom error message: "Custom error text"
2. Trigger error condition
3. **Expected**: Notice shows custom text

#### Test 7: Admin Context (No Notices)
1. Enable "Show Success Message"
2. Go to WP Admin → Orders → Add New
3. Add coupon manually
4. **Expected**: No notices shown in admin (silent pass)

---

## 📱 Device Testing

### Desktop
- ✅ Full-width notices at top of cart/checkout
- ✅ Dismissible close button (×)
- ✅ Icon indicators (✓, ✗, ℹ️)

### Tablet
- ✅ Responsive layout, stacked notices
- ✅ Touch-friendly dismiss buttons

### Mobile
- ✅ Full-width mobile notices
- ✅ Readable text size (14px minimum)
- ✅ Thumb-friendly tap targets

---

## 🛠️ Troubleshooting

### Issue: Notices not showing
**Possible Causes:**
1. Theme overrides WooCommerce notice templates
2. JavaScript conflict clearing notices
3. Cache plugin preventing notice display

**Solutions:**
1. Test with default WooCommerce theme (Storefront)
2. Disable other plugins temporarily
3. Clear cache (browser + server)

---

### Issue: Notices showing twice
**Cause:** Multiple validation hooks firing

**Solution:** Check for conflicting coupon validation plugins

---

### Issue: Success message showing on wrong day
**Cause:** Server timezone mismatch

**Solution:** Verify WordPress timezone in Settings → General → Timezone

---

## 🎯 Best Practices

### ✅ DO

- **Keep messages short** - 10-15 words maximum
- **Be specific** - Tell customer exactly what happened
- **Use positive tone** - Even for errors, be helpful
- **Include action** - What should customer do next?
- **Test on mobile** - Most customers shop on phones
- **Use brand voice** - Match your store's personality

### ❌ DON'T

- **Don't use jargon** - Avoid technical terms
- **Don't blame customer** - "You did it wrong" → "Coupon not valid today"
- **Don't use ALL CAPS** - Comes across as shouting
- **Don't make it too long** - Customers won't read paragraphs
- **Don't confuse with multiple notices** - One clear message per issue

---

## 🌟 Message Examples by Industry

### E-commerce / Retail
```
Success: "Your discount is active! Enjoy your savings today."
Error: "This deal unlocks on the 27th. Mark your calendar!"
```

### Subscription / SaaS
```
Success: "Monthly discount applied successfully!"
Error: "Your monthly coupon resets on the 1st. Stay tuned!"
```

### Food / Restaurant
```
Success: "Yum! Your special offer is ready to use."
Error: "This tasty deal is only available on Tuesdays!"
```

### Beauty / Wellness
```
Success: "Perfect timing! Your beauty discount is active."
Error: "Come back on the 15th for your monthly glow-up discount!"
```

### Technology / Electronics
```
Success: "Tech discount unlocked! Save big today."
Error: "Monthly tech deal refreshes on the 1st. Be ready!"
```

---

## 🔌 Developer Hooks

### Filter: Modify Success Message
```php
add_filter( 'wcgk_success_message', function( $message, $coupon_code ) {
    return "Special offer for $coupon_code is active!";
}, 10, 2 );
```

### Filter: Modify Fallback Notice
```php
add_filter( 'wcgk_fallback_day_notice', function( $message, $configured_day, $actual_day ) {
    return "Using fallback: Day $configured_day → Day $actual_day";
}, 10, 3 );
```

### Action: Before Adding Notice
```php
add_action( 'wcgk_before_add_notice', function( $notice_type, $message ) {
    // Log notice for analytics
    error_log( "Notice: [$notice_type] $message" );
}, 10, 2 );
```

---

## 📊 Analytics Integration

Track notice effectiveness with Google Analytics or Klaviyo:

```javascript
// Track coupon error events
jQuery(document).on('checkout_error', function() {
    if (jQuery('.woocommerce-error:contains("allowed day")').length) {
        gtag('event', 'coupon_wrong_day', {
            'event_category': 'Coupons',
            'event_label': 'Day Restriction'
        });
    }
});

// Track coupon success events
jQuery(document).on('applied_coupon', function(event, coupon_code) {
    if (jQuery('.woocommerce-message:contains("Nice timing")').length) {
        gtag('event', 'coupon_success_notice', {
            'event_category': 'Coupons',
            'event_label': coupon_code
        });
    }
});
```

---

## 🚀 Performance

### Impact
- **Negligible** - Notices added during existing validation flow
- **No database queries** - Messages pulled from cached settings
- **No JavaScript** - Pure server-side rendering
- **No AJAX** - Uses WooCommerce native notice system

### Caching
- Messages cached via WordPress options API
- No additional cache invalidation needed
- Settings changes take immediate effect

---

## 📝 Translation Support

All messages are translation-ready using WordPress i18n:

```php
__( 'Nice timing! This coupon is valid today.', 'wc-coupon-gatekeeper' )
```

### Translation Files
- Location: `/languages/`
- Format: `.po` / `.mo` files
- Tool: Poedit, Loco Translate, or WPML

### Example Translation (Spanish)
```
msgid "Nice timing! This coupon is valid today."
msgstr "¡Perfecto! Tu cupón es válido hoy."
```

---

## ✅ Checklist: Going Live

- [ ] Customize error messages to match brand voice
- [ ] Decide if success message should be enabled
- [ ] Test all notice types on staging site
- [ ] Verify notices on mobile devices
- [ ] Check screen reader compatibility
- [ ] Translate messages if multilingual site
- [ ] Monitor customer feedback for 1 week
- [ ] Adjust messaging based on customer response
- [ ] Document custom messages in internal wiki

---

## 📞 Support

**Issue with notices?**
1. Check Settings → Messages configuration
2. Test with WooCommerce Storefront theme
3. Review server error logs
4. Contact support with specific scenario

**Feature requests?**
- Custom notice types
- Conditional messaging based on customer segment
- A/B testing for message effectiveness

---

## 🎓 Summary

**UX Notices provide:**
✅ Clear feedback for customers  
✅ Reduced support tickets  
✅ Improved customer trust  
✅ Professional checkout experience  
✅ Accessibility compliance  
✅ Flexible customization  

**Enable success messages to:**
- Encourage positive reinforcement
- Build customer confidence
- Stand out from competitors
- Create memorable experience

**Use custom messages to:**
- Match brand personality
- Clarify policy clearly
- Guide customer behavior
- Increase return visits

---

**Ready to enhance your customer experience? Enable success messages today!** 🚀