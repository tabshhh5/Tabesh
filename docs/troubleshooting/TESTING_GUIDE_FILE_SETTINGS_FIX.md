# Quick Testing Guide

## File Settings Save Test

### Steps to Test:
1. Log in to WordPress admin as administrator
2. Navigate to **Tabesh → Settings**
3. Click on the **"تنظیمات فایل"** (File Settings) tab
4. Modify any setting, for example:
   - Change "حداکثر حجم PDF" (Max PDF Size) to a different value
   - Toggle "استفاده از FTP" (Enable FTP) checkbox
   - Enter FTP server address in "آدرس سرور FTP" (FTP Host)
5. Scroll down and click **"ذخیره تنظیمات"** (Save Settings)
6. Look for success message: "تنظیمات با موفقیت ذخیره شد" (Settings saved successfully)
7. **Refresh the page** (F5 or Ctrl+R)
8. Navigate back to the File Settings tab
9. ✅ **VERIFY:** All your changes are still there (not removed)

### What to Check:
- [ ] File size limits persist
- [ ] FTP settings persist (host, port, username)
- [ ] Checkbox states persist (FTP enabled, passive mode, etc.)
- [ ] IP restrictions settings persist
- [ ] Admin access list selections persist

## File Upload Test

### Prerequisites:
- You need a customer account (not admin)
- You need an existing order with uploaded file capability

### Steps to Test:
1. Log in as a **customer** (not admin)
2. Navigate to your orders page
3. Find an order that requires file upload
4. Click on the file upload link for that order
5. In the "محتوای کتاب" (Book Content) section:
   - Click **"انتخاب فایل PDF"** (Select PDF File)
   - Choose a valid PDF file from your computer
   - ✅ **VERIFY:** File name and size appear below the file selector
   - ✅ **VERIFY:** The upload button becomes **enabled** (not greyed out)
6. Click **"آپلود فایل محتوا"** (Upload Content File)
7. ✅ **VERIFY:** Progress bar appears and shows upload progress (0% → 100%)
8. ✅ **VERIFY:** Success message appears: "فایل با موفقیت آپلود شد" (File uploaded successfully)
9. ✅ **VERIFY:** File appears in the "فایل‌های آپلود شده" (Uploaded Files) list
10. Refresh the page
11. ✅ **VERIFY:** Uploaded file is still visible in the list

### What to Check:
- [ ] File selection works (file info displays)
- [ ] Upload button enables after file selection
- [ ] Progress bar appears and updates during upload
- [ ] Upload completes successfully
- [ ] File appears in uploaded files list
- [ ] File persists after page refresh

## Expected Behavior

### File Settings:
✅ **BEFORE FIX:** Settings were removed/reset after saving
✅ **AFTER FIX:** Settings persist across page reloads

### File Upload:
✅ **BEFORE FIX:** Upload failed with nonce error or silent failure
✅ **AFTER FIX:** Upload works smoothly with progress tracking

## Troubleshooting

### If File Settings Don't Save:
1. Check WordPress error log: `wp-content/debug.log`
2. Look for database errors related to `tabesh_settings` table
3. Verify user has `manage_woocommerce` capability
4. Clear browser cache and try again

### If File Upload Doesn't Work:
1. Check browser console for JavaScript errors (F12 → Console tab)
2. Check WordPress error log: `wp-content/debug.log`
3. Verify file size is within limits
4. Verify file type is allowed (PDF, JPG, PNG, etc.)
5. Check server upload limits (`upload_max_filesize` in php.ini)

## Browser Console Check

To verify there are no JavaScript errors:
1. Press **F12** to open Developer Tools
2. Click on the **Console** tab
3. Perform the upload test
4. ✅ **VERIFY:** No red error messages appear

## Test Results

Record your test results:

**File Settings Test:**
- Date: ______________
- Tester: ______________
- Result: ☐ PASS ☐ FAIL
- Notes: ______________

**File Upload Test:**
- Date: ______________
- Tester: ______________
- Result: ☐ PASS ☐ FAIL
- Notes: ______________

## Success Criteria

Both tests must pass:
- ✅ File settings save and persist
- ✅ File upload completes successfully

If both pass, the fix is working correctly! ✨
