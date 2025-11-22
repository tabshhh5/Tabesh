# Upload Shortcode Fix Guide

## Problem
After selecting a file and uploading it in the upload shortcode, the selected file is not uploaded and the next steps do not happen.

## Solution Overview
This fix adds comprehensive debugging, error handling, and validation to identify and resolve upload issues in the file upload shortcode.

## Changes Made

### 1. JavaScript Improvements (`assets/js/file-upload.js`)
- **Enhanced Error Handling**: Added validation checks at every step
- **Defensive Programming**: Check for element existence before accessing properties
- **User Feedback**: Display clear error messages when operations fail
- **Console Logging**: Comprehensive logging for debugging
- **Consolidated Code**: Removed duplicate variable declarations

### 2. PHP Improvements (`tabesh.php`)
- **Script Dependencies**: Fixed dependency chain to ensure `tabeshData` is available
- **Debug Logging**: Added server-side logging when `WP_DEBUG` is enabled
- **Error Details**: Enhanced error responses with debug information

## How to Test

### Prerequisites
1. WordPress installation with Tabesh plugin activated
2. WooCommerce installed and activated
3. At least one order created in the system

### Testing Steps

#### Step 1: Enable Debug Mode
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### Step 2: Add Shortcode to Page
Create or edit a page and add:
```
[tabesh_file_upload order_id="1"]
```
Replace `1` with an actual order ID from your database.

#### Step 3: Open Browser Console
- Chrome/Edge: Press F12 or Ctrl+Shift+I
- Firefox: Press F12 or Ctrl+Shift+K
- Safari: Enable Developer Menu, then Cmd+Opt+I

#### Step 4: Test File Selection
1. Click on any file upload button
2. Select a file from your computer
3. Check console - should see: `File selected: filename.ext Size: 12345`
4. Upload button should become enabled (no longer gray)

#### Step 5: Test File Upload
1. Click the upload button
2. Watch the console - should see multiple log entries:
   ```
   Upload button clicked for category: book_content
   File input found: 1
   File input has files: 1
   Starting upload: {filename, size, category, orderId, url}
   Upload response received: {success, message, file_id}
   ```
3. Progress bar should appear and show upload progress
4. After completion, should see success or error message

#### Step 6: Check Server Logs
1. Open `wp-content/debug.log`
2. Look for entries like:
   ```
   Tabesh upload - files received: Array(...)
   Tabesh upload - order_id: 1, user_id: 1, category: book_content
   ```

### What to Look For

#### Success Indicators
- ✅ Console shows all expected log messages
- ✅ Progress bar animates from 0% to 100%
- ✅ Success message displayed: "فایل با موفقیت آپلود شد"
- ✅ Page reloads after 2 seconds
- ✅ Uploaded file appears in the list

#### Failure Indicators
- ❌ Console shows error messages
- ❌ Red error message displayed to user
- ❌ Upload button stays disabled
- ❌ No progress bar appears
- ❌ AJAX errors in console

## Common Issues and Solutions

### Issue: "tabeshData is not defined"
**Cause**: Scripts not loading in correct order
**Solution**: Already fixed - `tabesh-file-upload` now depends on `tabesh-frontend`
**Verification**: Check that both scripts are loaded in page source

### Issue: "No file input found for category"
**Cause**: jQuery selector not finding the file input
**Solution**: Verify file input has correct `data-category` attribute
**Check**: Inspect element and look for `<input class="tabesh-file-input" data-category="book_content">`

### Issue: "File input element not found"
**Cause**: File input doesn't exist or jQuery failed to find it
**Solution**: Check page HTML structure matches template
**Check**: Look for `<input type="file" class="tabesh-file-input">`

### Issue: "No file selected" after clicking upload
**Cause**: File input was cleared or file reference lost
**Solution**: Try selecting file again
**Check**: Look at `File input has files:` log - should show 1

### Issue: Upload starts but fails
**Cause**: Multiple possible causes (server, permissions, size, etc.)
**Solution**: Check error response in console and debug.log
**Common Errors**:
- "فایلی انتخاب نشده است" - File not received by server
- "حجم فایل بیش از حد مجاز است" - File too large
- "فرمت فایل مجاز نیست" - File type not allowed
- "سفارش متعلق به شما نیست" - Order doesn't belong to user

### Issue: REST API returns 401 Unauthorized
**Cause**: User not logged in or nonce validation failed
**Solution**: 
- Verify user is logged in
- Check `tabeshData.nonce` is defined
- Clear browser cache and reload page

### Issue: REST API returns 404 Not Found
**Cause**: REST API endpoint not registered
**Solution**:
- Check plugin is activated
- Try deactivating and reactivating plugin
- Verify WordPress rewrite rules are flushed

## File Upload Flow

```
1. User clicks file input label
   ↓
2. Browser opens file picker
   ↓
3. User selects file
   ↓
4. 'change' event fires on input
   ↓
5. handleFileSelect() called
   ↓
6. File info displayed, upload button enabled
   ↓
7. User clicks upload button
   ↓
8. Click event handler finds file input
   ↓
9. uploadFile() called with input element
   ↓
10. FormData created with file and parameters
   ↓
11. AJAX POST to /wp-json/tabesh/v1/upload-file
   ↓
12. WordPress REST API receives request
   ↓
13. rest_upload_file() validates request
   ↓
14. file_manager->upload_file() processes file
   ↓
15. File saved to disk and database
   ↓
16. Response sent back to browser
   ↓
17. Success callback displays message
   ↓
18. Page reloads to show updated file list
```

## Debugging Checklist

When upload doesn't work, check each step:

- [ ] Scripts loaded? (Check Network tab)
- [ ] tabeshData defined? (Type `tabeshData` in console)
- [ ] File input exists? (Inspect element)
- [ ] File selected? (Check `File selected:` log)
- [ ] Upload button enabled? (Should not be gray)
- [ ] Click handler fires? (Check `Upload button clicked` log)
- [ ] File input found? (Check `File input found: 1` log)
- [ ] AJAX request sent? (Check Network tab)
- [ ] Server receives request? (Check debug.log)
- [ ] Response received? (Check `Upload response received` log)
- [ ] Error displayed? (Check page for error message)

## Rollback Instructions

If this fix causes issues, you can rollback:

```bash
git revert HEAD
git push
```

Or manually restore these files from the previous commit:
- `assets/js/file-upload.js`
- `tabesh.php`

## Additional Resources

- WordPress REST API Documentation: https://developer.wordpress.org/rest-api/
- jQuery AJAX Documentation: https://api.jquery.com/jquery.ajax/
- Browser Console Guide: https://developer.chrome.com/docs/devtools/console/

## Support

If issues persist after applying this fix:

1. Collect the following information:
   - Console logs (entire output)
   - Debug.log contents (last 50 lines)
   - Browser and version
   - WordPress version
   - PHP version
   - Error messages displayed to user

2. Create a GitHub issue with:
   - Detailed description of the problem
   - Steps to reproduce
   - Expected behavior
   - Actual behavior
   - Collected information from step 1

## Security Summary

All changes maintain security best practices:
- ✅ Input sanitization maintained
- ✅ Output escaping maintained
- ✅ Nonce verification in place
- ✅ Permission checks active
- ✅ File type validation enforced
- ✅ File size limits enforced
- ✅ No security vulnerabilities introduced
- ✅ CodeQL analysis passed with 0 alerts

## Performance Impact

- Minimal: Only adds lightweight console logging
- Debug logging only active when WP_DEBUG is enabled
- No impact on production with WP_DEBUG disabled
- AJAX request unchanged, same performance
- No additional database queries
