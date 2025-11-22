# Testing Guide: File Upload Permissions Fix

## Overview
This guide provides step-by-step instructions for testing the file upload permissions fix that allows Customer role users to upload files for their orders.

## What Was Fixed
The permission check in the file upload functionality has been updated to explicitly allow all logged-in users, regardless of their role, to upload files for orders they own.

## Prerequisites

### Required Test Users

Create the following test users if they don't exist:

1. **Test Customer**
   - Username: `test-customer`
   - Role: Customer
   - Must have at least one order in the system

2. **Test Administrator**
   - Username: `test-admin`
   - Role: Administrator

3. **Test Shop Manager**
   - Username: `test-shop-manager`
   - Role: Shop Manager

4. **Test Custom Role** (Optional)
   - Username: `test-staff`
   - Role: Custom role without `manage_woocommerce` capability

### Required Test Orders

Each test user (except admin/shop manager) should have:
- At least 1 order they own
- Access to order ID of an order they don't own (for negative testing)

## Test Scenarios

### Scenario 1: Customer Uploads to Own Order ✅
**Expected Result**: Upload succeeds

**Steps**:
1. Log in as `test-customer`
2. Navigate to the customer files panel (use shortcode `[customer_files_panel]`)
3. Locate one of your orders in the list
4. Click to expand the order details
5. In the "Book Content" or "Book Cover" section, click "Select PDF File" or "Select File"
6. Choose a valid file (PDF for content, image for cover)
7. Click "Upload and Validate"

**Expected Behavior**:
- ✅ File upload completes successfully
- ✅ Success message displayed
- ✅ File appears in the order's file list
- ✅ File status shows as "Pending Review"
- ✅ No 403 or permission errors

**How to Verify**:
- Check browser console: No 403 errors
- Check upload response: Status 200, success: true
- Check database: New record in `wp_tabesh_files` table with correct `user_id` and `order_id`

### Scenario 2: Customer Uploads to Other's Order ❌
**Expected Result**: Upload blocked

**Steps**:
1. Remain logged in as `test-customer`
2. Try to upload a file to an order that belongs to another user
   - This requires manually crafting a request or modifying the order_id in the browser

**Expected Behavior**:
- ❌ Upload fails with error message
- ❌ Message: "سفارش متعلق به شما نیست" (Order doesn't belong to you)
- ❌ HTTP Status: 400

**How to Test Manually**:
```javascript
// Open browser console on customer files panel page
// Replace ORDER_ID with an order that doesn't belong to this customer
const formData = new FormData();
formData.append('file', fileInput.files[0]); // fileInput is the file input element
formData.append('order_id', 'OTHER_USERS_ORDER_ID');
formData.append('file_category', 'book_content');

fetch(tabeshData.restUrl + '/upload-file', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': tabeshData.nonce
    },
    body: formData
}).then(r => r.json()).then(console.log);
```

### Scenario 3: Administrator Uploads to Any Order ✅
**Expected Result**: Upload succeeds for any order

**Steps**:
1. Log in as `test-admin`
2. Navigate to admin panel or customer files panel
3. Select any order (doesn't need to belong to admin)
4. Upload a file

**Expected Behavior**:
- ✅ Upload succeeds regardless of order ownership
- ✅ Admin can upload to any customer's order
- ✅ File is attributed to admin user (for audit trail)

### Scenario 4: Shop Manager Uploads to Any Order ✅
**Expected Result**: Upload succeeds for any order

**Steps**:
1. Log in as `test-shop-manager`
2. Navigate to file management interface
3. Select any order
4. Upload a file

**Expected Behavior**:
- ✅ Upload succeeds (shop manager has `manage_woocommerce` capability)
- ✅ Can upload to any customer's order

### Scenario 5: Custom Role User Uploads to Own Order ✅
**Expected Result**: Upload succeeds

**Steps**:
1. Log in as `test-staff` (custom role without admin capabilities)
2. Navigate to customer files panel
3. Upload file to an order owned by this user

**Expected Behavior**:
- ✅ Upload succeeds
- ✅ No role-based discrimination
- ✅ Order ownership is the only requirement

### Scenario 6: Unauthenticated User Attempts Upload ❌
**Expected Result**: Upload blocked

**Steps**:
1. Log out completely
2. Try to access the upload endpoint directly

**Expected Behavior**:
- ❌ 403 Forbidden error
- ❌ Message: "برای دسترسی به این منبع باید وارد سیستم شوید" (You must be logged in to access this resource)

**How to Test**:
```javascript
// In browser console (while logged out)
fetch(tabeshData.restUrl + '/upload-file', {
    method: 'POST',
    body: new FormData()
}).then(r => r.json()).then(console.log);
```

### Scenario 7: Expired Session ❌
**Expected Result**: Upload blocked

**Steps**:
1. Log in as any user
2. Wait for session to expire (or manually clear cookies)
3. Try to upload without refreshing page

**Expected Behavior**:
- ❌ 403 Forbidden error
- ❌ Helpful error message asking to refresh page

## Automated API Testing

### Test 1: Valid Customer Upload
```bash
# Log in as customer first to get cookies
# Then make upload request

curl -X POST "https://yoursite.com/wp-json/tabesh/v1/upload-file" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Cookie: wordpress_logged_in_XXX=..." \
  -F "file=@/path/to/test.pdf" \
  -F "order_id=123" \
  -F "file_category=book_content"
```

**Expected Response**:
```json
{
  "success": true,
  "message": "فایل با موفقیت آپلود شد",
  "file_id": 456,
  "version": 1,
  "filename": "test.pdf"
}
```

### Test 2: Customer Upload to Other's Order
```bash
curl -X POST "https://yoursite.com/wp-json/tabesh/v1/upload-file" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Cookie: wordpress_logged_in_XXX=..." \
  -F "file=@/path/to/test.pdf" \
  -F "order_id=999" \
  -F "file_category=book_content"
```

**Expected Response**:
```json
{
  "success": false,
  "message": "سفارش متعلق به شما نیست"
}
```

### Test 3: Unauthenticated Upload
```bash
curl -X POST "https://yoursite.com/wp-json/tabesh/v1/upload-file" \
  -F "file=@/path/to/test.pdf" \
  -F "order_id=123" \
  -F "file_category=book_content"
```

**Expected Response**:
```json
{
  "code": "rest_forbidden",
  "message": "برای دسترسی به این منبع باید وارد سیستم شوید. لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.",
  "data": {
    "status": 403
  }
}
```

## Browser Console Testing

### Check for Errors
```javascript
// Monitor console for any 403 or permission errors
console.clear();
// Perform upload...
// Check console output
```

### Check Network Tab
1. Open browser DevTools (F12)
2. Go to Network tab
3. Filter by "upload-file"
4. Perform upload
5. Check response:
   - Status should be 200 for successful uploads
   - Status should be 400/403 for blocked uploads
   - Response body should have clear error messages

### Check Request Headers
Verify the nonce is being sent:
```javascript
// Should see these headers in the request:
X-WP-Nonce: xxxxxxxxxxxxx
Cookie: wordpress_logged_in_xxxxx=...
```

## Database Verification

### Check Files Table
```sql
-- After successful upload
SELECT * FROM wp_tabesh_files 
WHERE order_id = YOUR_ORDER_ID 
ORDER BY created_at DESC 
LIMIT 1;

-- Verify:
-- - file_id exists
-- - user_id matches the uploader
-- - order_id is correct
-- - status = 'pending'
-- - file_path exists
-- - deleted_at IS NULL
```

### Check Logs Table
```sql
-- Check upload was logged
SELECT * FROM wp_tabesh_logs 
WHERE action = 'file_uploaded' 
AND order_id = YOUR_ORDER_ID 
ORDER BY created_at DESC 
LIMIT 5;
```

## WordPress Debug Log

Enable debugging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check for Errors
```bash
# View recent log entries
tail -f wp-content/debug.log
```

### Expected Log Entries
**Successful upload**:
```
[07-Nov-2024 12:34:56 UTC] Tabesh upload - files received: Array(...)
[07-Nov-2024 12:34:56 UTC] Tabesh upload - order_id: 123, user_id: 5, category: book_content
```

**Failed authentication**:
```
[07-Nov-2024 12:34:56 UTC] Tabesh REST API auth failed - User ID: 0, Nonce: present, Cookie: missing
```

## Security Testing

### Test File Type Validation
Upload files with various extensions:
- ✅ `.pdf` - Should succeed (if content)
- ✅ `.jpg` - Should succeed (if cover)
- ❌ `.php` - Should be blocked
- ❌ `.exe` - Should be blocked
- ❌ `.sh` - Should be blocked

### Test File Size Validation
Upload files of various sizes:
- ✅ 1 MB PDF - Should succeed
- ✅ 49 MB PDF - Should succeed (if limit is 50MB)
- ❌ 51 MB PDF - Should be blocked

### Test Double Extension
- ❌ `file.pdf.php` - Should be blocked (only last extension matters)

## Common Issues and Solutions

### Issue 1: Still Getting 403 Error
**Possible Causes**:
- Browser cache not cleared
- Old nonce in use
- Session expired

**Solutions**:
1. Clear browser cache
2. Refresh page to get new nonce
3. Log out and log back in
4. Check that cookies are enabled

### Issue 2: Upload Succeeds But File Not Showing
**Possible Causes**:
- File was uploaded but to wrong order
- Database record created but file not displayed in UI

**Solutions**:
1. Check database directly
2. Verify order_id in request
3. Check browser console for JavaScript errors

### Issue 3: Admin Can't Upload to Other Orders
**Possible Causes**:
- Admin user doesn't have required capabilities
- Capability check failing

**Solutions**:
1. Verify user has `manage_woocommerce` OR `manage_options` capability
2. Check user role assignments in WordPress admin

## Rollback Procedure

If the fix causes issues, rollback via Git:

```bash
cd /path/to/plugin
git checkout HEAD~1 -- includes/class-tabesh-file-manager.php
```

Or manually restore line 254:
```php
// Change from:
$is_admin = current_user_can('manage_woocommerce') || current_user_can('manage_options');

// Back to:
$is_admin = current_user_can('manage_woocommerce');
```

## Test Results Template

Use this template to document test results:

```
## Test Results - [Date]

### Environment
- WordPress Version: _____
- PHP Version: _____
- Plugin Version: _____
- Browser: _____

### Scenario 1: Customer Upload to Own Order
- Status: [ ] PASS  [ ] FAIL
- Notes: _____

### Scenario 2: Customer Upload to Other's Order
- Status: [ ] PASS  [ ] FAIL
- Notes: _____

### Scenario 3: Admin Upload to Any Order
- Status: [ ] PASS  [ ] FAIL
- Notes: _____

### Scenario 4: Shop Manager Upload
- Status: [ ] PASS  [ ] FAIL
- Notes: _____

### Scenario 5: Custom Role Upload
- Status: [ ] PASS  [ ] FAIL
- Notes: _____

### Scenario 6: Unauthenticated Upload
- Status: [ ] PASS  [ ] FAIL
- Notes: _____

### Scenario 7: Expired Session
- Status: [ ] PASS  [ ] FAIL
- Notes: _____

### Overall Result
- [ ] All tests passed
- [ ] Some tests failed (see notes)
- [ ] Fix needs revision

### Notes
_____
```

## Success Criteria

The fix is considered successful when:

- ✅ All 7 test scenarios pass
- ✅ No 403 errors for valid uploads
- ✅ Clear error messages for blocked uploads
- ✅ No console errors
- ✅ Database records created correctly
- ✅ Files stored in correct location
- ✅ Audit trail maintained (user_id recorded)
- ✅ No security vulnerabilities introduced

## Contact

If you encounter issues during testing:
1. Check the security summary: `SECURITY_SUMMARY_FILE_UPLOAD_PERMISSIONS_FIX.md`
2. Review the code changes in the pull request
3. Check WordPress debug log for detailed error messages
4. Document the issue with test results template above
