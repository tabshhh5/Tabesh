# Testing Guide: REST API Permission Fix

## Overview
This guide helps you test the fix for the 403 Forbidden error on file upload endpoints.

## Prerequisites
- WordPress site with Tabesh plugin installed
- User account with login credentials
- Test file (PDF, image, or other allowed file type)

## Test 1: File Upload (Primary Issue)

### Steps
1. **Login to WordPress**
   - Navigate to your WordPress site
   - Login with your user credentials

2. **Access Customer Files Panel**
   - Navigate to the page with `[customer_files_panel]` shortcode
   - OR navigate to the file upload form

3. **Upload a Test File**
   - Select an order (if required)
   - Choose a file to upload
   - Click "Upload" button

### Expected Result
✅ **SUCCESS**: File uploads without errors  
✅ **SUCCESS**: Upload progress bar shows  
✅ **SUCCESS**: Success message displayed  

### Failure Indicators
❌ **FAIL**: HTTP 403 Forbidden error  
❌ **FAIL**: "Connection issue with the server" message  
❌ **FAIL**: Upload button disabled permanently  

## Test 2: REST API Endpoint Direct Test

### Using Browser Console
```javascript
// Open browser developer console (F12)
// Run this code (replace with your values):

fetch('/wp-json/tabesh/v1/upload-file', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': tabeshData.nonce
    },
    body: formData // Your form data
})
.then(response => {
    console.log('Status:', response.status);
    console.log('Status Text:', response.statusText);
    if (response.status === 403) {
        console.error('❌ FAIL: Still getting 403 Forbidden');
    } else if (response.status === 200) {
        console.log('✅ SUCCESS: Upload endpoint is accessible');
    }
    return response.json();
})
.then(data => console.log('Response:', data))
.catch(error => console.error('Error:', error));
```

### Expected Result
✅ **Status**: 200 OK (or 400 for invalid data, but NOT 403)  
❌ **Status**: 403 Forbidden (indicates issue still exists)  

## Test 3: Check WordPress Error Logs

### Steps
1. Enable WordPress debugging (if not already enabled)
   ```php
   // In wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. Attempt file upload

3. Check debug log
   ```bash
   tail -f wp-content/debug.log
   ```

### Expected Result
✅ **NO ERRORS** related to permission callbacks  
✅ **NO ERRORS** related to "permission_callback not callable"  
✅ **NORMAL LOG** entries for upload process  

## Test 4: Multiple User Roles

### Test with Different User Types

#### A. Regular Customer User
1. Login as a customer with no special permissions
2. Try uploading a file
3. **Expected**: ✅ Upload should work

#### B. Staff User (if applicable)
1. Login as staff user
2. Try uploading a file
3. **Expected**: ✅ Upload should work

#### C. Administrator
1. Login as administrator
2. Try uploading a file
3. **Expected**: ✅ Upload should work

## Test 5: Other Affected Endpoints

Test these endpoints to ensure they also work:

### A. Submit Order
```javascript
fetch('/wp-json/tabesh/v1/submit-order', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': tabeshData.nonce,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ /* order data */ })
})
.then(response => console.log('Status:', response.status));
```
**Expected**: 200 OK (or 400 for validation, but NOT 403)

### B. Get Order Files
```javascript
fetch('/wp-json/tabesh/v1/order-files/123', {
    headers: {
        'X-WP-Nonce': tabeshData.nonce
    }
})
.then(response => console.log('Status:', response.status));
```
**Expected**: 200 OK (or 404 if order doesn't exist, but NOT 403)

### C. Generate Download Token
```javascript
fetch('/wp-json/tabesh/v1/generate-download-token', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': tabeshData.nonce,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ file_id: 1 })
})
.then(response => console.log('Status:', response.status));
```
**Expected**: 200 OK (or 400/404 for invalid file, but NOT 403)

## Test 6: Logged Out User (Security Test)

### Steps
1. **Logout** from WordPress
2. Try to access REST API endpoint directly
   ```javascript
   fetch('/wp-json/tabesh/v1/upload-file', {
       method: 'POST'
   })
   .then(response => console.log('Status:', response.status));
   ```

### Expected Result
✅ **Status**: 403 Forbidden (this is CORRECT - not logged in)  
❌ **Status**: 200 OK (would indicate security issue)  

## Test 7: Network Tab Verification

### Steps
1. Open browser Developer Tools (F12)
2. Go to **Network** tab
3. Attempt file upload
4. Find the request to `/wp-json/tabesh/v1/upload-file`
5. Check:
   - **Status Code**: Should be 200 OK (not 403)
   - **Response Headers**: Should include successful upload info
   - **Request Headers**: Should include `X-WP-Nonce`

### Expected Result
✅ **Status**: 200 OK  
✅ **Response**: Contains upload success data  
✅ **No 403 errors**  

## Troubleshooting

### If Tests Still Fail

#### 1. Clear Cache
```bash
# Clear WordPress cache
wp cache flush

# Clear browser cache
# Ctrl+Shift+Delete (or Cmd+Shift+Delete on Mac)
```

#### 2. Verify Plugin Update
```bash
# Check plugin version
wp plugin list

# Verify tabesh.php has the fix
grep -n "is_user_logged_in()" tabesh.php
# Should show the new method
```

#### 3. Check Permissions
```bash
# Verify file permissions
ls -la tabesh.php
# Should be readable by web server
```

#### 4. Check WordPress Version
```bash
wp core version
# Should be 6.8 or higher
```

#### 5. Check PHP Version
```bash
php -v
# Should be 8.2.2 or higher
```

## Success Criteria

### All Tests Should Show:
✅ File upload works without 403 errors  
✅ Logged-in users can access all endpoints  
✅ Logged-out users are properly blocked (403)  
✅ No permission callback errors in logs  
✅ All 9 affected endpoints working  
✅ Upload progress indicators working  
✅ Success messages displayed correctly  

## Reporting Issues

If tests fail, collect this information:

1. **WordPress Version**: `wp core version`
2. **PHP Version**: `php -v`
3. **Plugin Version**: Check Tabesh plugin info
4. **Error Message**: Exact error from browser console
5. **HTTP Status**: Status code from Network tab
6. **Debug Log**: Last 50 lines from wp-content/debug.log
7. **Browser**: Name and version
8. **Test That Failed**: Which test from this guide

---

## Quick Test Checklist

- [ ] Can login as regular user
- [ ] Can access Customer Files Panel
- [ ] Can upload a file successfully
- [ ] No 403 errors in browser console
- [ ] No errors in WordPress debug log
- [ ] Upload progress bar displays
- [ ] Success message shown
- [ ] File appears in order files list
- [ ] Logged-out users get 403 (security test)
- [ ] Other endpoints work (submit order, get files, etc.)

**If all items checked**: ✅ **FIX VERIFIED - WORKING CORRECTLY**
