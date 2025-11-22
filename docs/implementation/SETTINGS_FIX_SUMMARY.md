# Settings Fix Summary

## Problem Statement
After changing settings for book sizes (and other comma-separated list settings) in the admin panel and saving, the plugin did not display any book sizes on the user side (frontend).

## Root Cause Analysis

The issue was a disconnect between how settings were saved and how they were retrieved:

### 1. JavaScript Processing (admin.js)
**Before Fix:**
```javascript
const items = value.split(',').map(item => item.trim()).filter(item => item);
$field.val(items.join(','));  // Returned: "A5,A4,رقعی"
```

**After Fix:**
```javascript
const items = value.split(',').map(item => item.trim()).filter(item => item);
$field.val(JSON.stringify(items));  // Returns: ["A5","A4","رقعی"]
```

### 2. PHP Save Logic (class-tabesh-admin.php)
The PHP code expected either:
- A JSON string (which it would save as-is)
- An array (which it would convert to JSON)
- Plain text (which it would sanitize)

With the old JavaScript, it received a comma-separated string like "A5,A4,رقعی", which failed JSON decode and was saved as a plain string.

### 3. PHP Retrieve Logic
```php
// Try to decode JSON
$decoded = json_decode($value, true);
if (json_last_error() === JSON_ERROR_NONE) {
    return $decoded;
}
return $value;  // Returns the string if not JSON
```

When the setting was a plain string instead of JSON, it returned the string directly.

### 4. Template Usage (order-form.php)
```php
<?php foreach ($book_sizes as $size): ?>
    <option value="<?php echo esc_attr($size); ?>"><?php echo esc_html($size); ?></option>
<?php endforeach; ?>
```

When `$book_sizes` is a string instead of an array, the foreach fails silently, displaying no options.

## Additional Issues Fixed

### Checkbox Settings
**Problem:** Unchecked checkboxes don't appear in POST data, so they were never updated to '0'.

**Solution:** Added special handling to explicitly set checkbox values to '0' when not present in POST:
```php
foreach ($checkbox_fields as $field => $key) {
    $value = isset($post_data[$field]) ? '1' : '0';
    $wpdb->replace(...);
}
```

### Cleanup
Removed `paper_types` from `settings_map` as it's not editable through the UI.

## Files Modified

1. **assets/js/admin.js**
   - Line 48: Changed `$field.val(items.join(','))` to `$field.val(JSON.stringify(items))`
   - Added comment to clarify the conversion to JSON array

2. **includes/class-tabesh-admin.php**
   - Removed `paper_types` from settings_map (line 142)
   - Moved checkbox fields to separate array
   - Added special handling for checkbox fields (lines 203-214)

## Affected Settings

### Fixed Array Settings:
- book_sizes (Book sizes/قطع‌های کتاب)
- print_types (Print types/انواع چاپ)
- binding_types (Binding types/انواع صحافی)
- license_types (License types/انواع مجوز)
- cover_paper_weights (Cover paper weights/گرماژ کاغذ جلد)
- lamination_types (Lamination types/انواع سلفون)
- extras (Extra services/خدمات اضافی)

### Fixed Checkbox Settings:
- sms_on_order_submit (Send SMS on order submit)
- sms_on_status_change (Send SMS on status change)

### Pricing Settings (Already Working):
- pricing_book_sizes
- pricing_paper_types
- pricing_lamination_costs
- pricing_binding_costs
- pricing_options_costs

These were already properly handled by the JavaScript key-value parser.

## Testing

Created verification script (`/tmp/test_settings.php`) that confirms:
1. ✅ JavaScript converts comma-separated values to JSON arrays
2. ✅ PHP save logic recognizes JSON and stores it correctly
3. ✅ PHP retrieve logic decodes JSON back to arrays
4. ✅ Templates can iterate over arrays with foreach
5. ✅ Checkboxes are properly saved as '1' or '0'

## Security
- Ran CodeQL security scan: No vulnerabilities found
- Code review completed: No issues found
- All user inputs are properly sanitized
- JSON encoding/decoding is safe with proper error checking

## Impact

**Before Fix:**
- After saving settings, book sizes and other options would not appear on the order form
- Checkboxes could not be unchecked once checked

**After Fix:**
- All settings save and display correctly
- Users can now modify book sizes and other array settings
- Checkboxes work bidirectionally (can check and uncheck)

## Recommendations

1. **User Action Required:** After deploying this fix, administrators should:
   - Go to Admin > Tabesh > Settings
   - Re-save each tab to ensure all settings are converted to the new format
   
2. **Future Enhancement:** Consider adding a settings migration script to automatically convert any existing plain string settings to JSON arrays on plugin update.

3. **Documentation:** Update the plugin documentation to clarify the settings format expectations.
