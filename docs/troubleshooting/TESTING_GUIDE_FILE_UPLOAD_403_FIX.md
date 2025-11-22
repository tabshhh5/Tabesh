# Testing Guide: File Upload 403 Fix

## Overview

This guide provides step-by-step instructions for testing the file upload 403 Forbidden error fix.

## Pre-Testing Setup

### 1. Backup Current Site
```bash
# Backup database
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# Backup plugin files
tar -czf tabesh_backup_$(date +%Y%m%d).tar.gz /path/to/wp-content/plugins/Tabesh/
```

### 2. Enable Debug Logging (Temporarily)
Edit `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### 3. Clear All Caches
- Clear WordPress object cache
- Clear page cache (if using caching plugin)
- Clear browser cache
- Clear CDN cache (if applicable)

## Test Environment

### Test Users Required

Create or identify these test users:

1. **Customer User**
   - Role: `customer`
   - Username: `test_customer`
   - Has at least one order

2. **Admin User**
   - Role: `administrator`
   - Username: `test_admin`
   - Full access to site

3. **Staff User** (if applicable)
   - Role: `shop_manager` or custom staff role
   - Username: `test_staff`

## Testing Scenarios

### Test 1: Customer File Upload (PRIMARY TEST)

**Objective**: Verify customers can upload files without 403 errors

**Steps**:
1. Log in as `test_customer`
2. Navigate to customer files panel or order page
3. Select a valid PDF file (under 50MB)
4. Click "Upload" button
5. Monitor browser console (F12)
6. Monitor network tab

**Expected Results**:
- ✅ File uploads successfully
- ✅ Progress bar shows upload progress
- ✅ Success message displayed
- ✅ No 403 errors in console
- ✅ No 403 errors in network tab
- ✅ Response status: 200 OK
- ✅ File appears in file list after reload

**Debug Log Check**:
```bash
tail -f wp-content/debug.log | grep "Tabesh"
```
Should NOT show: `Tabesh REST API auth failed` for this upload

---

### Test 2: Admin File Upload

**Objective**: Verify admins can still upload files

**Steps**:
1. Log in as `test_admin`
2. Navigate to admin dashboard or order management
3. Select a file to upload
4. Click "Upload" button
5. Monitor browser console and network tab

**Expected Results**:
- ✅ File uploads successfully
- ✅ No errors in console
- ✅ Response status: 200 OK

---

### Test 3: Unauthenticated Upload Attempt

**Objective**: Verify unauthenticated users cannot upload

**Steps**:
1. Log out completely
2. Open browser developer console (F12)
3. Navigate to a page with file upload (if accessible)
4. OR manually attempt REST API call:
   ```javascript
   fetch('/wp-json/tabesh/v1/upload-file', {
       method: 'POST',
       body: new FormData()
   }).then(r => r.json()).then(console.log);
   ```

**Expected Results**:
- ✗ Upload fails
- ✗ Response status: 403 Forbidden
- ✗ Error message: "برای دسترسی به این منبع باید وارد سیستم شوید"
- ✗ No file created on server

---

### Test 4: Expired Session Upload

**Objective**: Verify expired sessions are handled properly

**Steps**:
1. Log in as `test_customer`
2. Navigate to file upload page
3. Wait for session to expire (or clear cookies manually)
4. Attempt to upload a file

**Expected Results**:
- ✗ Upload fails
- ✗ Response status: 403 Forbidden
- ✗ Error message prompts user to log in again
- ✗ No file created on server

---

### Test 5: Invalid File Type

**Objective**: Verify file validation still works

**Steps**:
1. Log in as `test_customer`
2. Select an invalid file type (e.g., `.exe`, `.php`)
3. Attempt to upload

**Expected Results**:
- ✗ Upload rejected
- ✗ Error message about invalid file type
- ✗ No file created on server

---

### Test 6: Oversized File

**Objective**: Verify size limits are enforced

**Steps**:
1. Log in as `test_customer`
2. Select a file larger than limit (>50MB for PDF)
3. Attempt to upload

**Expected Results**:
- ✗ Upload rejected (client-side or server-side)
- ✗ Error message about file size
- ✗ No file created on server

---

### Test 7: Multiple Concurrent Uploads

**Objective**: Verify multiple uploads don't cause issues

**Steps**:
1. Log in as `test_customer`
2. Open multiple browser tabs
3. Upload different files simultaneously
4. Monitor all uploads

**Expected Results**:
- ✅ All uploads succeed
- ✅ No 403 errors
- ✅ No race conditions
- ✅ All files saved correctly

---

### Test 8: Cross-Site Request Test (Security)

**Objective**: Verify CSRF protection still works

**Steps**:
1. Create a test HTML file on different domain:
   ```html
   <form action="https://yoursite.com/wp-json/tabesh/v1/upload-file" method="POST">
       <input type="file" name="file">
       <button>Upload</button>
   </form>
   ```
2. Open this file in browser
3. Attempt to upload

**Expected Results**:
- ✗ Upload fails
- ✗ CORS error in console OR 403 error
- ✗ No file created on server

---

## Browser Testing

### Test in Multiple Browsers

**Required**:
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari (if on Mac)
- [ ] Edge

**For each browser**:
1. Clear cache and cookies
2. Run Test 1 (Customer File Upload)
3. Verify no console errors
4. Verify upload succeeds

---

## Network Analysis

### Browser Developer Tools

**Chrome/Firefox**:
1. Open DevTools (F12)
2. Go to Network tab
3. Filter: `upload-file`
4. Perform upload
5. Click on request

**Check**:
- Request Method: `POST`
- Status Code: `200 OK` (for successful uploads)
- Request Headers:
  - `X-WP-Nonce`: Should be present
  - `Cookie`: Should contain WordPress cookies
- Response:
  - `success: true`
  - `file_id`: Should be present
  - `message`: Success message in Persian

**Screenshot**: Take screenshot of successful request

---

## Performance Testing

### Upload Speed

**Test**:
1. Upload 10MB file
2. Note upload time
3. Compare with previous times (if available)

**Expected**:
- ✅ Upload time similar to before fix
- ✅ No performance degradation
- ✅ Progress bar updates smoothly

### Server Load

**Monitor**:
```bash
# During upload test
top -bn1 | grep php-fpm
vmstat 1 5
```

**Expected**:
- No unusual CPU spikes
- No memory leaks
- Normal server behavior

---

## Log Analysis

### Check Debug Log

**Location**: `wp-content/debug.log`

**Good Signs** (should see):
```
Tabesh upload - files received: Array(...)
Tabesh upload - order_id: 123, user_id: 456, category: book_content
```

**Bad Signs** (should NOT see for valid uploads):
```
Tabesh REST API auth failed - User ID: 0
rest_forbidden
rest_cookie_invalid_nonce
```

### Check Error Log

**Location**: Server error log (e.g., `/var/log/apache2/error.log`)

**Expected**:
- No PHP warnings or errors from Tabesh plugin
- No authentication failures

---

## Regression Testing

### Verify Other Features Still Work

**Test**:
1. **Price Calculation**
   - [ ] Calculate price for new order
   - [ ] Verify correct calculation

2. **Order Submission**
   - [ ] Submit new order
   - [ ] Verify order created

3. **User Login**
   - [ ] Log in as different roles
   - [ ] Verify authentication works

4. **Admin Dashboard**
   - [ ] Access admin pages
   - [ ] Verify data displays correctly

5. **REST API Endpoints**
   - [ ] Test other REST endpoints
   - [ ] Verify they still work

---

## Security Validation

### Authentication Tests

**Test Matrix**:

| User State | Upload Attempt | Expected Result |
|-----------|---------------|-----------------|
| Logged in customer | Valid file | ✅ Success |
| Logged in admin | Valid file | ✅ Success |
| Not logged in | Any file | ✗ 403 Forbidden |
| Expired session | Any file | ✗ 403 Forbidden |
| Invalid nonce | Valid file | ✅ Success (fallback auth) |

### Authorization Tests

**Test Matrix**:

| User | Order Owner | Expected Result |
|------|-------------|-----------------|
| Customer A | Customer A | ✅ Can upload |
| Customer A | Customer B | ✗ Cannot upload |
| Admin | Anyone | ✅ Can upload |
| Staff | Anyone | ✅ Can upload (if configured) |

---

## Acceptance Criteria

### Must Pass (Blocking Issues)

- [ ] ✅ Customers can upload files to their orders
- [ ] ✅ No 403 errors for authenticated uploads
- [ ] ✗ Unauthenticated users get 403 error
- [ ] ✅ File validation still works
- [ ] ✅ No console errors
- [ ] ✅ No PHP errors in logs

### Should Pass (Non-Blocking)

- [ ] Performance is acceptable
- [ ] All browsers work
- [ ] Debug logging is helpful
- [ ] Error messages are clear

---

## Rollback Criteria

**Rollback if**:
- Security vulnerability discovered
- Data loss occurs
- Performance degradation > 50%
- Critical functionality broken
- Users report authentication issues

**Rollback Command**:
```bash
cd /path/to/Tabesh
git checkout dab4d5a -- tabesh.php
# Test rollback works
# Then commit and push
```

---

## Sign-Off

### Testing Completed By

- **Name**: _______________
- **Date**: _______________
- **Role**: _______________

### Results

**Test Status**:
- [ ] ✅ All tests passed
- [ ] ⚠️ Some tests failed (document below)
- [ ] ❌ Critical failure - rollback required

**Failed Tests** (if any):
```
Test #: _____
Issue: _______________
Severity: _______________
```

### Recommendation

- [ ] ✅ Approve for production
- [ ] ⚠️ Fix issues then retest
- [ ] ❌ Do not deploy

**Signature**: _______________

---

## Post-Deployment Monitoring

### First 24 Hours

**Monitor**:
- File upload success rate
- 403 error frequency
- User complaints
- Server performance
- Error logs

**Alert if**:
- Upload success rate < 95%
- 403 errors > 5% of requests
- Multiple user complaints
- Server CPU > 80%

### First Week

**Review**:
- Total uploads processed
- Error rate trends
- User feedback
- Performance metrics

**Document**:
- Any issues found
- Workarounds applied
- Lessons learned

---

## Support Resources

### Documentation
- `FILE_UPLOAD_403_FIX_SUMMARY.md` - Technical details
- `SECURITY_SUMMARY_FILE_UPLOAD_403_FIX.md` - Security analysis

### Troubleshooting

**Issue**: Still getting 403 errors
**Check**:
1. Clear all caches
2. Verify user is logged in
3. Check debug log
4. Verify cookies are set
5. Check browser console for errors

**Issue**: Upload succeeds but file not showing
**Check**:
1. Check file was saved to database
2. Verify file permissions
3. Check upload directory path
4. Review upload handler logs

**Issue**: Performance issues
**Check**:
1. Server resources
2. File sizes
3. Network latency
4. Database queries

### Contact

**For Issues**:
- Create GitHub issue with test results
- Include debug logs
- Include browser console output
- Include network request/response

---

**Document Version**: 1.0  
**Last Updated**: 2025-11-07  
**Prepared By**: GitHub Copilot Agent
