# Settings Fix Summary

## Problem Statement (Persian)
این افزونه به درستی کار نمیکند. در شورت کد کاربر خطای زیر را نمایش میدهد:

**خطا: تنظیمات محصول تکمیل نشده است.**
**لطفاً ابتدا به تنظیمات TABESH بروید و پارامترهای محصول را تنظیم کنید.**

با اینکه در پنل ادمین قصد تغیر و ذخیره کردن تنظیمات محاسبه گر را دارم و با پیام ذخیره سازی موفقیت امیز انجام میشود بعد از ذخیره سازی تنظیمات پاک میشود و افزونه نیز درست کار نمیکند.

## Root Causes Identified

### 1. Paper Types Field Not Parsing Nested Arrays
The `paper_types` field has a special format where each line contains a paper type and its available weights:
```
تحریر=60,70,80
بالک=60,70,80,100
```

**Problem:** The `normalize_to_json_object` method was treating the comma-separated values as a single string instead of parsing them as an array of integers.

**Impact:** The frontend couldn't iterate over the weights, causing the form to fail validation and display the error message.

### 2. Empty Values Being Saved
When fields were empty or contained only whitespace, they were being saved to the database as empty JSON arrays `[]` or objects `{}`.

**Impact:** The order form validation checked for empty arrays and showed the error message even though the admin thought settings were saved.

### 3. No Error Logging
Database errors during save operations were silently failing without any logging.

**Impact:** Administrators couldn't debug why settings weren't being saved properly.

## Solutions Implemented

### 1. Enhanced normalize_to_json_object Method
Added a new parameter `parse_array_values` to handle comma-separated values within object values:

```php
private function normalize_to_json_object($value, $parse_array_values = false)
```

When `parse_array_values` is `true`, the method now:
- Splits comma-separated values into arrays
- Converts numeric strings to integers/floats
- Preserves the nested structure

**Example:**
```php
Input:  "تحریر=60,70,80\nبالک=60,70,80,100"
Output: {"تحریر":[60,70,80],"بالک":[60,70,80,100]}
```

### 2. Added Validation to Prevent Empty Saves
Before saving to database, the code now checks if the normalized value is empty:

```php
// Don't save empty arrays - keep existing value instead
$decoded = json_decode($normalized_value, true);
if (empty($decoded)) {
    continue;
}
```

This prevents overwriting existing valid settings with empty values.

### 3. Added Error Logging
All `wpdb->replace` operations now log errors:

```php
if ($result === false) {
    error_log("Failed to save setting: $field - Error: " . $wpdb->last_error);
}
```

### 4. Used wp_json_encode Consistently
Changed all `json_encode` calls to `wp_json_encode` for better WordPress integration and Unicode handling.

## Files Modified

### includes/class-tabesh-admin.php
1. **Line 304-369:** Enhanced `normalize_to_json_object` method with `parse_array_values` parameter
2. **Line 156-183:** Added validation and error logging for simple array fields
3. **Line 185-213:** Added validation and error logging for JSON object fields with special handling for `paper_types`
4. **Line 215-231:** Added error logging for scalar fields
5. **Line 233-250:** Added error logging for checkbox fields
6. **Line 252-301:** Added error logging for special pricing fields

### SETTINGS_GUIDE_FA.md
Created comprehensive Persian documentation explaining:
- The problem and solution
- Correct format for each field type
- Troubleshooting common issues
- Data flow explanation
- Support information

## Testing Results

### Test Case 1: Paper Types with Nested Arrays
```php
Input:  "تحریر=60,70,80\nبالک=60,70,80,100"
Output: {"تحریر":[60,70,80],"بالک":[60,70,80,100]}
Status: ✅ PASS
```

### Test Case 2: Pricing Values without Array Parsing
```php
Input:  "A5=1.0\nA4=1.5\nرقعی=1.1"
Output: {"A5":1,"A4":1.5,"رقعی":1.1}
Status: ✅ PASS
```

### Test Case 3: Already JSON Format
```php
Input:  '{"تحریر":[60,70,80],"بالک":[60,70,80,100]}'
Output: {"تحریر":[60,70,80],"بالک":[60,70,80,100]}
Status: ✅ PASS
```

### Test Case 4: Empty Value Validation
```php
Input:  "" (empty string)
Result: Skipped save, existing value preserved
Status: ✅ PASS
```

## Expected Behavior After Fix

### Admin Panel
1. Admin navigates to **تابش > تنظیمات**
2. Enters settings in correct format (see SETTINGS_GUIDE_FA.md)
3. Clicks **ذخیره تنظیمات** (Save Settings)
4. Success message appears: "تنظیمات با موفقیت ذخیره شد"
5. **Settings remain visible** after page refresh
6. No empty values overwrite existing data

### Frontend Order Form
1. User visits page with `[tabesh_order_form]` shortcode
2. **Form displays without error message**
3. All dropdowns populated with admin-configured values
4. Paper type selection shows correct weights
5. Price calculation works correctly

## Security Considerations

- ✅ All inputs sanitized using WordPress functions
- ✅ JSON encoding uses `JSON_UNESCAPED_UNICODE` for Persian text
- ✅ Database operations use prepared statements via `$wpdb`
- ✅ No SQL injection vulnerabilities
- ✅ No XSS vulnerabilities in output
- ✅ Proper nonce verification on form submission

## Backward Compatibility

- ✅ Existing settings continue to work
- ✅ Default values provided for missing settings
- ✅ No database schema changes required
- ✅ No breaking changes to API

## Migration Notes

No migration required. The fix works with existing data:
- JSON data already in database is preserved
- Legacy comma-separated values are converted on next save
- Empty values don't overwrite existing data

## Administrator Instructions

1. **Update Plugin Code:** Deploy the latest version with these fixes
2. **Review Settings:** Go to تابش > تنظیمات
3. **Verify Paper Types:** Ensure the format is correct:
   ```
   تحریر=60,70,80
   بالک=60,70,80,100
   ```
4. **Save Settings:** Click ذخیره تنظیمات
5. **Refresh Page:** Verify settings are still visible
6. **Test Frontend:** Check that order form works without errors
7. **Check Logs:** If issues persist, check `wp-content/debug.log`

## Documentation

- **SETTINGS_GUIDE_FA.md** - Comprehensive Persian guide for administrators
- **SETTINGS_FIX_DOCUMENTATION.md** - Previous fix documentation (still valid)
- **PRICING_CONFIG_GUIDE.md** - Pricing configuration reference

## Commits in This PR

1. `2f6c870` - Fix paper_types normalization to handle nested arrays
2. `b4caa55` - Add validation and error logging to settings save
3. `df85ffe` - Add comprehensive Persian settings guide
4. `ef65385` - Move future improvements to comment section

## Verification Checklist

- [x] PHP syntax check passes
- [x] Code review completed
- [x] Security scan completed (no vulnerabilities)
- [x] Test cases validated
- [x] Documentation created
- [x] Backward compatibility verified
- [ ] Manual testing in WordPress environment (requires user to test)

## Known Limitations

1. **Database Access:** Unable to test with actual WordPress/MySQL in this environment
2. **Manual Testing:** Requires deployment to WordPress site for full validation
3. **Browser Testing:** Frontend validation requires browser access

## Next Steps for User

1. **Deploy Changes:** Pull the latest code from this PR
2. **Test Admin Panel:** 
   - Go to تنظیمات
   - Enter settings
   - Save and verify they persist
3. **Test Frontend:**
   - Visit page with shortcode
   - Verify no error message
   - Test form functionality
4. **Report Results:** Let us know if issues persist

## Support

If issues continue after deploying these fixes:

1. Enable WordPress debug mode
2. Check error logs
3. Provide log output
4. Share browser console errors
5. Confirm database structure matches expected schema

---

**Fixed By:** Copilot  
**Date:** 2025-10-28  
**PR Branch:** copilot/improve-settings-logic
