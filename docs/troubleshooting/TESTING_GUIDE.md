# Testing Guide - Service Cost Calculation Fix

## Overview
This guide provides step-by-step instructions for testing the service cost calculation fix in the Tabesh WordPress plugin.

## Prerequisites

### Environment Setup
- WordPress 6.8+ installed
- Tabesh plugin installed and activated
- WooCommerce plugin installed (optional, for full integration)
- Browser with developer tools (Chrome, Firefox, Edge)
- Access to WordPress admin panel

### Before Testing
1. Enable debug mode in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. Clear all caches:
   - Browser cache (Ctrl+Shift+Del)
   - WordPress object cache
   - Any server-side caching (LiteSpeed, Redis, etc.)

## Test Scenarios

### Test 1: Basic Form Without Extras

**Objective**: Verify the form works without any extras selected

**Steps**:
1. Navigate to the order form page (where `[tabesh_order_form]` shortcode is placed)
2. Open browser Developer Tools (F12)
3. Go to Console tab
4. Fill in the form:
   - قطع کتاب (Book Size): A5
   - نوع کاغذ (Paper Type): تحریر
   - گرماژ (Paper Weight): 80g
   - نوع چاپ (Print Type): سیاه و سفید
   - صفحات سیاه و سفید: 100
   - صفحات رنگی: 0
   - تیراژ (Quantity): 10
   - نوع صحافی (Binding): شومیز
   - مجوز (License): دارم
   - DO NOT check any extras
5. Click through all steps until "محاسبه قیمت" (Calculate Price)
6. Click "محاسبه قیمت"

**Expected Results**:
- ✓ No JavaScript errors in console
- ✓ Console shows: "Tabesh: Found 0 checked extras"
- ✓ Price calculation succeeds
- ✓ Price breakdown displayed
- ✓ No extras cost row shown (or shows 0)
- ✓ Total price is calculated correctly

**Actual Results**:
- [ ] PASS
- [ ] FAIL - Describe issue: _______________

---

### Test 2: Form With Single Extra

**Objective**: Verify single extra selection works correctly

**Steps**:
1. Fill in the same form as Test 1
2. At Step 10 (خدمات اضافی), check ONE extra: "لب گرد"
3. Click "محاسبه قیمت"

**Expected Results**:
- ✓ Console shows: "Tabesh: Found 1 checked extras"
- ✓ Console shows: "Tabesh: Adding extra: لب گرد"
- ✓ Console shows: "Tabesh: Total extras collected: 1"
- ✓ AJAX request includes `extras: ["لب گرد"]`
- ✓ Price calculation succeeds
- ✓ Extras cost row appears with cost (1000 تومان by default)
- ✓ Console shows detailed breakdown of extras cost
- ✓ Total price includes extras cost

**Actual Results**:
- [ ] PASS
- [ ] FAIL - Describe issue: _______________

---

### Test 3: Form With Multiple Extras

**Objective**: Verify multiple extras selection works correctly

**Steps**:
1. Fill in the same form as Test 1
2. At Step 10 (خدمات اضافی), check MULTIPLE extras:
   - ✓ لب گرد
   - ✓ شیرینک
   - ✓ خط تا
3. Click "محاسبه قیمت"

**Expected Results**:
- ✓ Console shows: "Tabesh: Found 3 checked extras"
- ✓ Console shows each extra being added
- ✓ Console shows: "Tabesh: Total extras collected: 3"
- ✓ AJAX request includes all three extras in array
- ✓ Price calculation succeeds
- ✓ Extras cost row shows sum of all extras (3000 تومان: 1000+1500+500)
- ✓ Console shows breakdown: "لب گرد: 1000, شیرینک: 1500, خط تا: 500"
- ✓ Total price correctly includes all extras costs

**Actual Results**:
- [ ] PASS
- [ ] FAIL - Describe issue: _______________

---

### Test 4: Change Selection and Recalculate

**Objective**: Verify changing extras selection updates calculation

**Steps**:
1. Complete Test 3 (multiple extras selected)
2. Click "ویرایش" (Edit) button
3. Go back to Step 10
4. Uncheck "شیرینک"
5. Check "سوراخ" instead
6. Click "محاسبه قیمت" again

**Expected Results**:
- ✓ Console shows updated extras list
- ✓ New extras: ["لب گرد", "خط تا", "سوراخ"]
- ✓ Price recalculated with new extras
- ✓ Extras cost updated: 1800 تومان (1000+500+300)
- ✓ Total price updated correctly
- ✓ Previous calculation data replaced

**Actual Results**:
- [ ] PASS
- [ ] FAIL - Describe issue: _______________

---

### Test 5: All Extras Selected

**Objective**: Verify all available extras can be selected

**Steps**:
1. Fill in the form
2. At Step 10, check ALL available extras
3. Click "محاسبه قیمت"

**Expected Results**:
- ✓ All extras collected successfully
- ✓ Console shows all extras in breakdown
- ✓ Total extras cost is sum of all configured extras
- ✓ No JavaScript errors
- ✓ Price calculation succeeds

**Actual Results**:
- [ ] PASS
- [ ] FAIL - Describe issue: _______________

---

### Test 6: Network Error Handling

**Objective**: Verify graceful handling of network errors

**Steps**:
1. Fill in the form with extras selected
2. Open Browser DevTools > Network tab
3. Enable "Offline" mode (in Chrome: check "Offline" in Network tab)
4. Click "محاسبه قیمت"

**Expected Results**:
- ✓ Error message displayed to user in Persian
- ✓ Message: "خطا در برقراری ارتباط با سرور"
- ✓ Calculate button re-enabled
- ✓ Form remains intact (data not lost)
- ✓ Console shows AJAX error details
- ✓ No JavaScript exceptions

**Actual Results**:
- [ ] PASS
- [ ] FAIL - Describe issue: _______________

---

### Test 7: Server Error Handling

**Objective**: Verify handling of server-side errors

**Steps**:
1. Fill in the form
2. Leave required field empty (e.g., don't select binding type)
3. Force navigation to last step
4. Click "محاسبه قیمت"

**Expected Results**:
- ✓ Server returns 400 error
- ✓ Error message displayed to user
- ✓ Message mentions missing required fields
- ✓ Calculate button re-enabled
- ✓ User can go back and fix the issue

**Actual Results**:
- [ ] PASS
- [ ] FAIL - Describe issue: _______________

---

### Test 8: Extras Not Configured

**Objective**: Verify behavior when no extras are configured in settings

**Steps**:
1. Go to WordPress Admin > Tabesh > Settings
2. Clear all extras from the extras field
3. Save settings
4. Go to order form
5. Navigate to Step 10

**Expected Results**:
- ✓ Step 10 shows: "هیچ خدمات اضافی تنظیم نشده است"
- ✓ No checkboxes displayed
- ✓ Form can still proceed
- ✓ Price calculation works without extras
- ✓ No extras cost in breakdown

**Actual Results**:
- [ ] PASS
- [ ] FAIL - Describe issue: _______________

---

### Test 9: Browser Compatibility

**Objective**: Verify the fix works across different browsers

**Browsers to Test**:
- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if available)

**Steps for Each Browser**:
1. Repeat Test 3 (multiple extras)
2. Check console for errors
3. Verify calculation works

**Expected Results**:
- ✓ Works in all major browsers
- ✓ No browser-specific errors
- ✓ Consistent behavior

**Actual Results**:
- Chrome: [ ] PASS [ ] FAIL - _______________
- Firefox: [ ] PASS [ ] FAIL - _______________
- Safari: [ ] PASS [ ] FAIL - _______________

---

### Test 10: Mobile Responsive

**Objective**: Verify the form works on mobile devices

**Steps**:
1. Open browser DevTools
2. Toggle device toolbar (mobile view)
3. Select a mobile device (e.g., iPhone 12)
4. Complete Test 3 on mobile view

**Expected Results**:
- ✓ Form is responsive
- ✓ Checkboxes are tappable
- ✓ Price calculation works
- ✓ Results are readable
- ✓ No layout issues

**Actual Results**:
- [ ] PASS
- [ ] FAIL - Describe issue: _______________

---

## Backend Testing

### Test 11: Debug Log Verification

**Objective**: Verify server-side logging works correctly

**Steps**:
1. Ensure WP_DEBUG is enabled
2. Complete Test 3 (multiple extras)
3. Check `wp-content/debug.log`

**Expected Log Entries**:
```
Tabesh: calculate_price called with params: ...
Tabesh: Extras received: ...
Tabesh: Extras is_array: yes
Tabesh: Extras count: 3
Tabesh: Processing 3 extras
Tabesh: Extra "لب گرد" cost: 1000
Tabesh: Extra "شیرینک" cost: 1500
Tabesh: Extra "خط تا" cost: 500
Tabesh: Total options cost: 3000
Tabesh REST: calculate_price_rest called
Tabesh REST: Calculation successful
```

**Actual Results**:
- [ ] PASS - Logs match expected
- [ ] FAIL - Describe issue: _______________

---

### Test 12: REST API Direct Test

**Objective**: Test the API endpoint directly

**Steps**:
1. Use a REST client (Postman, curl, or browser extension)
2. Send POST request to: `https://yoursite.com/wp-json/tabesh/v1/calculate-price`
3. Headers:
   ```
   Content-Type: application/json
   X-WP-Nonce: [get from browser's tabeshData.nonce]
   ```
4. Body:
   ```json
   {
     "book_size": "A5",
     "paper_type": "تحریر",
     "paper_weight": "80",
     "print_type": "سیاه و سفید",
     "page_count_bw": 100,
     "page_count_color": 0,
     "quantity": 10,
     "binding_type": "شومیز",
     "license_type": "دارم",
     "cover_paper_weight": "250",
     "lamination_type": "براق",
     "extras": ["لب گرد", "شیرینک"],
     "notes": "Test"
   }
   ```

**Expected Response**:
```json
{
  "success": true,
  "data": {
    "price_per_book": ...,
    "quantity": 10,
    "subtotal": ...,
    "total_price": ...,
    "breakdown": {
      "options_cost": 2500,
      "options_breakdown": {
        "لب گرد": 1000,
        "شیرینک": 1500
      }
    }
  }
}
```

**Actual Results**:
- [ ] PASS
- [ ] FAIL - Describe issue: _______________

---

## Performance Testing

### Test 13: Calculation Speed

**Objective**: Verify calculations complete quickly

**Steps**:
1. Fill form with multiple extras
2. Note time before clicking "محاسبه قیمت"
3. Note time when results display
4. Check Network tab for request duration

**Expected Results**:
- ✓ Calculation completes in < 2 seconds
- ✓ UI remains responsive
- ✓ No lag or freezing

**Actual Results**:
- Calculation time: _____ seconds
- [ ] PASS (< 2 seconds)
- [ ] FAIL

---

## Regression Testing

### Test 14: Existing Features Still Work

**Objective**: Ensure no existing functionality was broken

**Features to Verify**:
- [ ] Form navigation (next/previous buttons)
- [ ] Form validation
- [ ] Other form fields (non-extras)
- [ ] Price calculation without extras
- [ ] Order submission after calculation
- [ ] User orders page displays correctly
- [ ] Admin orders page works

**Actual Results**:
- [ ] ALL PASS - No regressions
- [ ] FAIL - Features affected: _______________

---

## Test Summary

### Overall Results
- Total Tests: 14
- Passed: _____ / 14
- Failed: _____ / 14
- Pass Rate: _____ %

### Critical Issues Found
List any critical issues that prevent deployment:
1. 
2. 
3. 

### Minor Issues Found
List any minor issues that can be fixed later:
1. 
2. 
3. 

### Recommendations
- [ ] Ready for production deployment
- [ ] Needs minor fixes before deployment
- [ ] Needs major fixes before deployment

### Tested By
- Name: _______________
- Date: _______________
- Environment: _______________
- WordPress Version: _______________
- PHP Version: _______________

---

## Troubleshooting Common Issues

### Issue: Console shows "getOption is not defined"
**Solution**: Clear browser cache completely and reload

### Issue: Extras not appearing in form
**Solution**: Check that extras are configured in Settings > Tabesh

### Issue: Price calculation returns 400 error
**Solution**: Check debug.log for specific error message

### Issue: Extras cost showing as 0
**Solution**: Check that pricing_options_costs is configured in database settings

### Issue: Changes not taking effect
**Solution**: Clear all caches (browser, server, CDN)

---

## Notes
Add any additional observations or notes here:

