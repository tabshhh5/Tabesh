# Testing Guide: Admin Settings Fix

## Quick Test (5 minutes)

### Test the Critical Bug Fix:
1. Go to WordPress admin → تابش → تنظیمات
2. Enter in book_sizes: `A5, A4, رقعی`
3. Click **ذخیره تنظیمات** (Save)
4. Refresh the page
5. **EXPECTED:** You should see `A5, A4, رقعی` (same as entered)
6. **NOT:** `["A5","A4","رقعی"]` or `[\"A5\",\"A4\",\"رقعی\"]`
7. Save again
8. Refresh again
9. **EXPECTED:** Still see `A5, A4, رقعی` (no extra slashes!)

**✅ PASS:** Values stay the same after multiple saves
**❌ FAIL:** Values get escaped/corrupted

---

## Complete Test Suite

### Test 1: Parameter Format Preservation

**Objective:** Verify settings don't get double-encoded

**Steps:**
1. Go to تابش > تنظیمات > پارامترهای محصول
2. Enter test data:
   ```
   قطع‌های کتاب: A5, A4, رقعی, وزیری
   انواع چاپ: سیاه و سفید, رنگی, ترکیبی
   ```
3. Save settings
4. Refresh page
5. Verify values appear exactly as entered
6. Save again (without changes)
7. Refresh again
8. Verify NO extra characters added

**Expected Result:**
- ✅ Values preserved exactly
- ✅ No backslashes added
- ✅ No JSON strings visible
- ✅ No corruption after multiple saves

**Failure Signs:**
- ❌ See: `["value"]` instead of `value`
- ❌ See: `[\"value\"]` or `[\\\"value\\\"]`
- ❌ Values change on each save

---

### Test 2: Live Parameter Counting

**Objective:** Verify live counting works as user types

**Steps:**
1. Go to تابش > تنظیمات > پارامترهای محصول
2. Look at "تعداد فعلی" under قطع‌های کتاب
3. Start typing: `A5`
4. **Check:** Count should show **1**
5. Add comma and space: `A5, A4`
6. **Check:** Count should show **2**
7. Continue: `A5, A4, رقعی`
8. **Check:** Count should show **3**
9. Delete some text
10. **Check:** Count updates downward

**Expected Result:**
- ✅ Count updates as you type
- ✅ Count updates on backspace/delete
- ✅ Count is accurate (matches comma-separated items)

**Failure Signs:**
- ❌ Count doesn't update
- ❌ Count shows wrong number
- ❌ Count is always 0

---

### Test 3: Paper Types (Special Format)

**Objective:** Verify nested array format works

**Steps:**
1. Go to انواع کاغذ و گرماژها field
2. Enter:
   ```
   تحریر=60,70,80
   بالک=60,70,80,100
   گلاسه=70,80
   ```
3. **Check:** Live count shows **3**
4. Save settings
5. Refresh page
6. Verify all three lines preserved exactly
7. Check database (if possible):
   ```sql
   SELECT setting_value FROM wp_tabesh_settings WHERE setting_key='paper_types';
   ```
8. Should see: `{"تحریر":[60,70,80],"بالک":[60,70,80,100],"گلاسه":[70,80]}`

**Expected Result:**
- ✅ Each line preserved
- ✅ Weights parsed as array of numbers
- ✅ Format correct in database

---

### Test 4: Pricing Configuration

**Objective:** Verify key=value fields work

**Steps:**
1. Go to تنظیمات > تنظیمات قیمت‌گذاری
2. In ضرایب قطع کتاب, enter:
   ```
   A5=1.0
   A4=1.5
   رقعی=1.1
   وزیری=1.3
   ```
3. **Check:** Live count shows **4**
4. Save settings
5. Refresh page
6. Verify all four lines preserved
7. Verify decimal numbers preserved (1.5 not 1 or 2)

**Expected Result:**
- ✅ All entries preserved
- ✅ Decimal values maintained
- ✅ Persian text handled correctly

---

### Test 5: Empty Fields

**Objective:** Verify empty fields don't overwrite existing data

**Steps:**
1. Save settings with some data
2. Verify data exists in database
3. Clear one field completely
4. Save settings
5. Check that field in database
6. **Expected:** Old value should remain (not overwritten with empty)

---

### Test 6: Frontend Integration

**Objective:** Verify settings work with order form

**Steps:**
1. Configure all settings in admin
2. Go to a page with `[tabesh_order_form]` shortcode
3. Check that form displays without errors
4. Verify all dropdowns populated:
   - قطع کتاب dropdown has correct options
   - نوع کاغذ dropdown has correct options
   - After selecting paper type, weight dropdown appears with correct weights
5. Try submitting a test order
6. Verify price calculation works

**Expected Result:**
- ✅ No error message about incomplete settings
- ✅ All dropdowns populated from admin settings
- ✅ Order submission works
- ✅ Price calculation accurate

---

### Test 7: Browser Console Validation

**Objective:** Verify JavaScript logging works

**Steps:**
1. Open browser console (F12)
2. Go to تابش > تنظیمات
3. Enter some test data
4. Click save
5. Watch console output

**Expected Log Messages:**
```
Tabesh: book_sizes has 5 items (will be processed by PHP)
Tabesh: paper_types has 2 valid entries (will be processed by PHP)
Tabesh: pricing_book_sizes has 4 valid entries (will be processed by PHP)
```

**Expected Result:**
- ✅ See validation messages for each field
- ✅ Counts are correct
- ✅ No JavaScript errors

**Failure Signs:**
- ❌ JavaScript errors in console
- ❌ "Field not found" errors
- ❌ No logging output

---

### Test 8: Database Integrity

**Objective:** Verify data stored correctly in database

**SQL Queries to Run:**

```sql
-- Check if settings exist
SELECT setting_key, LENGTH(setting_value) as value_length 
FROM wp_tabesh_settings 
WHERE setting_key IN ('book_sizes', 'paper_types', 'pricing_book_sizes');

-- Check specific values
SELECT setting_key, setting_value 
FROM wp_tabesh_settings 
WHERE setting_key = 'book_sizes';

SELECT setting_key, setting_value 
FROM wp_tabesh_settings 
WHERE setting_key = 'paper_types';

-- Verify JSON validity
SELECT setting_key, 
       JSON_VALID(setting_value) as is_valid_json,
       setting_value
FROM wp_tabesh_settings 
WHERE setting_key IN ('book_sizes', 'paper_types');
```

**Expected Results:**
- ✅ `is_valid_json` should be `1` (true)
- ✅ Values should be clean JSON (no extra escaping)
- ✅ Arrays are arrays: `["item1","item2"]`
- ✅ Objects are objects: `{"key":"value"}`

---

### Test 9: Multiple Users

**Objective:** Verify no conflicts with concurrent edits

**Steps:**
1. User A opens settings
2. User B opens settings
3. User A saves changes
4. User B saves different changes
5. Refresh and check final state

**Expected Result:**
- ✅ Last save wins (standard WordPress behavior)
- ✅ No corruption from concurrent access
- ✅ No merge conflicts

---

### Test 10: Stress Test

**Objective:** Verify stability with many saves

**Steps:**
1. Enter test data
2. Save settings
3. Make small change
4. Save again
5. Repeat 20 times
6. Check if data still correct

**Expected Result:**
- ✅ Data preserved after 20 saves
- ✅ No progressive corruption
- ✅ No performance degradation

---

## Automated Testing (Optional)

### PHP Unit Test Example:

```php
<?php
class Test_Tabesh_Settings extends WP_UnitTestCase {
    
    public function test_book_sizes_no_double_encoding() {
        $admin = new Tabesh_Admin();
        
        // Simulate POST data
        $_POST['book_sizes'] = 'A5, A4, رقعی';
        $_POST['tabesh_save_settings'] = true;
        
        // Save settings
        $admin->save_settings($_POST);
        
        // Retrieve settings
        $result = $admin->get_setting('book_sizes');
        
        // Assert it's an array
        $this->assertIsArray($result);
        
        // Assert it has correct values
        $this->assertEquals(['A5', 'A4', 'رقعی'], $result);
        
        // Assert no JSON strings
        $this->assertNotContains('["A5"', $result);
    }
    
    public function test_multiple_saves_no_corruption() {
        $admin = new Tabesh_Admin();
        
        $_POST['book_sizes'] = 'A5, A4';
        $_POST['tabesh_save_settings'] = true;
        
        // Save 5 times
        for ($i = 0; $i < 5; $i++) {
            $admin->save_settings($_POST);
        }
        
        $result = $admin->get_setting('book_sizes');
        
        // Should still be clean array
        $this->assertEquals(['A5', 'A4'], $result);
    }
}
```

---

## Browser Testing Checklist

Test in multiple browsers:

- [ ] **Chrome** (Latest)
- [ ] **Firefox** (Latest)
- [ ] **Safari** (Latest)
- [ ] **Edge** (Latest)
- [ ] **Mobile Safari** (iOS)
- [ ] **Chrome Mobile** (Android)

**Test in each:**
1. Settings display correctly
2. Live counting works
3. Save functions properly
4. No JavaScript errors
5. UI looks good (RTL support)

---

## Performance Testing

### Load Time Test:
1. Clear browser cache
2. Navigate to تنظیمات page
3. Measure load time (should be < 2 seconds)

### Save Time Test:
1. Fill in all settings
2. Click save
3. Measure time to success message (should be < 1 second)

### Memory Test:
1. Open browser dev tools > Memory
2. Navigate to settings
3. Use "Take snapshot"
4. Interact with page
5. Take another snapshot
6. Check for memory leaks (should be minimal)

---

## Regression Testing

Ensure these still work:

- [ ] Order form displays correctly
- [ ] Price calculation accurate
- [ ] SMS notifications send
- [ ] Staff panel functions
- [ ] User dashboard works
- [ ] Archive feature operates
- [ ] Export/import (if implemented)

---

## Error Scenarios

Test error handling:

1. **Invalid JSON in database:**
   - Manually corrupt a setting
   - Check if fallback works

2. **Missing settings:**
   - Delete a setting from database
   - Check if defaults load

3. **SQL errors:**
   - Simulate database connection issue
   - Check error logging

4. **Permission errors:**
   - Test as non-admin user
   - Verify access denied

---

## Reporting Issues

If you find issues, report with:

1. **Browser & Version:** Chrome 120, Firefox 121, etc.
2. **WordPress Version:** 6.8 or higher
3. **PHP Version:** 8.2.2 or higher
4. **Steps to Reproduce:** Exact sequence
5. **Expected Result:** What should happen
6. **Actual Result:** What actually happened
7. **Screenshots:** If applicable
8. **Console Logs:** JavaScript errors
9. **PHP Logs:** From wp-content/debug.log
10. **Database State:** Relevant SQL query results

---

## Success Criteria

All tests pass if:

✅ **No parameter corruption** after multiple saves
✅ **Live counting** works accurately
✅ **All field types** save correctly
✅ **Frontend integration** works without errors
✅ **No JavaScript errors** in console
✅ **Database values** are valid JSON
✅ **Performance** is acceptable
✅ **Cross-browser** compatibility confirmed
✅ **No regressions** in other features
✅ **Security** maintained (no new vulnerabilities)

---

## Test Result Template

```
Test Date: YYYY-MM-DD
Tester: [Name]
Environment: WordPress X.X, PHP X.X.X, [Browser]

Test Results:
- [ ] Test 1: Parameter Format Preservation
- [ ] Test 2: Live Parameter Counting
- [ ] Test 3: Paper Types (Special Format)
- [ ] Test 4: Pricing Configuration
- [ ] Test 5: Empty Fields
- [ ] Test 6: Frontend Integration
- [ ] Test 7: Browser Console Validation
- [ ] Test 8: Database Integrity
- [ ] Test 9: Multiple Users
- [ ] Test 10: Stress Test

Issues Found:
1. [Describe issue if any]

Overall Status: PASS / FAIL
Notes: [Additional comments]
```

---

## Quick Reference

### Where to Look:

**Admin Settings:**
`/wp-admin/admin.php?page=tabesh-settings`

**Database Tables:**
- `wp_tabesh_settings` - Settings storage
- `wp_tabesh_orders` - Order data
- `wp_tabesh_logs` - Activity logs

**Files to Check:**
- `assets/js/admin.js` - JavaScript logic
- `templates/admin-settings.php` - UI template
- `includes/class-tabesh-admin.php` - PHP logic

**Logs:**
- `wp-content/debug.log` - PHP errors (if WP_DEBUG enabled)
- Browser Console - JavaScript errors

---

**Ready to Test?** Start with the Quick Test at the top, then work through the complete suite as time allows.
