# File Download and SMS Configuration Fix Documentation

## Overview

This document describes the fixes implemented to resolve two critical issues:
1. Admin file download blocked by CDN/Firewall
2. SMS sending errors with MelliPayamak API

## Problem 1: File Download Issue

### Issue Description
Admins using the `[tabesh_admin_dashboard]` shortcode were unable to download order files. The download would generate a token and URL, but clicking the download button resulted in a CDN/Firewall error (403 Forbidden).

### Root Cause
The JavaScript code was using `window.open()` to open the download URL in a new tab. Many CDN/Firewall systems (like the one in use) detect this as a potentially malicious popup and block it.

### Solution Implemented

#### 1. JavaScript Changes (`assets/js/admin-dashboard.js`)
- Replaced `window.open()` with `fetch()` API
- Download file as Blob
- Create temporary object URL
- Trigger download using hidden `<a>` element with `download` attribute
- Clean up resources after download
- Added fallback to `window.open()` for older browsers or CORS issues

**Code Changes:**
```javascript
// Before (line 642):
window.open(response.download_url, '_blank');

// After:
fetch(response.download_url)
    .then(fetchResponse => fetchResponse.blob())
    .then(blob => {
        const blobUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = `file-${fileId}`;
        document.body.appendChild(a);
        a.click();
        // Cleanup...
    })
    .catch(error => {
        // Fallback to window.open()
    });
```

#### 2. Server-Side Changes (`includes/utils/class-tabesh-file-security.php`)
Added CDN-bypass headers to the `output_file()` method:
- `X-Content-Type-Options: nosniff`
- `Cache-Control: private, no-cache, no-store, must-revalidate`
- `X-Download-Options: noopen`
- `X-Robots-Tag: noindex, nofollow`

These headers signal to CDN/Firewall systems that this is a legitimate file download, not a malicious operation.

### Benefits
- Files download directly without opening new tabs/windows
- Bypasses CDN/Firewall restrictions
- More reliable and secure
- Better user experience
- Works on modern browsers with fallback for older ones

---

## Problem 2: SMS Sending Issue

### Issue Description
SMS sending via MelliPayamak was failing with error:
```
Cannot convert to System.Int32.
Parameter name: type ---> Input string was not in a correct format.
```

### Root Cause
The code was using the wrong API endpoint and method:
- Using REST API: `https://console.melipayamak.com/api/send/shared/{bodyId}`
- Should use SOAP API: `https://api.payamak-panel.com/post/Send.asmx?wsdl`
- Should use `SendByBaseNumber2` method for template-based SMS

### Solution Implemented

#### 1. Changed API Endpoint (`includes/handlers/class-tabesh-sms.php`)
```php
// Before:
const API_BASE_URL = 'https://console.melipayamak.com/api/send/shared/';

// After:
const SOAP_WSDL_URL = 'https://api.payamak-panel.com/post/Send.asmx?wsdl';
```

#### 2. Rewrote `send_template_sms()` Method
Complete rewrite to use PHP SOAP client:

```php
// Initialize SOAP client
$client = new SoapClient(self::SOAP_WSDL_URL, $soap_options);

// Prepare parameters
$soap_params = array(
    'username' => $username,
    'password' => $password,
    'text' => array_values($parameters),  // Array of values in order
    'to' => $phone,
    'bodyId' => intval($pattern_code),
);

// Call SOAP method
$response = $client->SendByBaseNumber2($soap_params);

// Handle response
if ($response->SendByBaseNumber2Result > 0) {
    // Success - result is message ID
    return true;
} else {
    // Error - result is error code
    return WP_Error with Persian message
}
```

#### 3. Added Error Code Translation
Created `get_melipayamak_error_message()` method to translate MelliPayamak error codes to Persian messages:

| Error Code | Persian Message |
|------------|----------------|
| -1 | پارامترها ناقص است |
| -2 | نام کاربری یا رمز عبور اشتباه است |
| -3 | امکان ارسال روزانه شما به پایان رسیده |
| -6 | اعتبار کافی نیست |
| -11 | کد الگو پیدا نشد یا متعلق به شما نیست |
| -12 | پارامترهای ارسالی با الگوی تعریف شده مطابقت ندارد |
| ... | (and more) |

### Settings Configuration
The settings page already has all required fields (no changes needed):
- `sms_enabled` - Enable/disable SMS system
- `sms_username` - MelliPayamak username
- `sms_password` - MelliPayamak password
- `sms_status_{status}_enabled` - Enable SMS for each status
- `sms_status_{status}_pattern` - Pattern code (bodyId) for each status

### Template Variables
SMS templates support these variables (sent in order):
1. `order_number` - Order number (e.g., TB-00001)
2. `customer_name` - Customer name
3. `status` - Order status in Persian
4. `date` - Date (format: 1402/01/01)

### Example Pattern
```
مشتری گرامی %order_number%
سفارش شماره %customer_name% شما به وضعیت "%status%" تغییر یافت.
تاریخ: %date%
چاپخانه تابش
```

### Benefits
- Correct API usage according to MelliPayamak documentation
- Proper error handling with Persian messages
- SOAP client is more reliable than REST for this service
- Better debugging with detailed error codes
- Follows MelliPayamak best practices

---

## Testing Recommendations

### File Download Testing
1. Login as admin
2. Go to page with `[tabesh_admin_dashboard]` shortcode
3. Find an order with attached files
4. Click download button
5. Verify file downloads without CDN error
6. Test on multiple browsers (Chrome, Firefox, Safari)

### SMS Testing
1. Login as admin
2. Go to Settings → SMS tab
3. Configure MelliPayamak credentials
4. Set up a pattern code for a status
5. Use "Test SMS" feature to verify
6. Create/update an order to trigger status change
7. Verify SMS is received with correct information
8. Check logs for any errors

### Error Scenarios to Test
1. Invalid MelliPayamak credentials (should show error -2)
2. Invalid pattern code (should show error -11)
3. Insufficient credit (should show error -6)
4. Download file that doesn't exist (should show proper error)
5. Download without proper permissions (should be blocked)

---

## Files Modified

### 1. `assets/js/admin-dashboard.js`
- Line 639-658: Changed download method from `window.open()` to `fetch()` with Blob

### 2. `includes/utils/class-tabesh-file-security.php`
- Line 512-533: Added CDN-bypass headers in `output_file()` method

### 3. `includes/handlers/class-tabesh-sms.php`
- Line 22-26: Changed API endpoint constant
- Line 145-283: Completely rewrote `send_template_sms()` to use SOAP API
- Line 424-444: Added `get_melipayamak_error_message()` method

### 4. `CHANGELOG.md`
- Added entry documenting the fixes

---

## Security Considerations

### File Download Security
- Token-based authentication still in place
- File access permissions verified before download
- Download tokens expire after 24 hours
- Security headers prevent malicious use
- All file operations logged

### SMS Security
- Credentials stored securely in database
- No sensitive data logged (passwords masked)
- Phone numbers validated before sending
- Rate limiting through MelliPayamak
- All SMS operations logged without exposing personal data

---

## Backward Compatibility

### File Download
- ✅ Existing download tokens continue to work
- ✅ Fallback to `window.open()` for older browsers
- ✅ No database schema changes
- ✅ No breaking changes to REST API

### SMS
- ✅ All existing settings preserved
- ✅ No database schema changes
- ✅ Settings page UI unchanged
- ⚠️ **Action Required:** Admin must re-save SMS settings if using old REST API configuration

---

## Troubleshooting

### File Download Issues
**Problem:** File still not downloading
- Check browser console for errors
- Verify download token is valid (check expiry)
- Check server error logs
- Verify CDN/Firewall settings allow downloads
- Test in different browser

**Problem:** Download works but filename is generic
- This is expected - filename is `file-{id}`
- Original filename is set in Content-Disposition header
- Browser should use original name, but some may use generic name

### SMS Issues
**Problem:** SMS not sending
1. Check if SMS is enabled in settings
2. Verify credentials are correct
3. Check pattern code exists in MelliPayamak panel
4. Verify pattern code is numeric
5. Check MelliPayamak account has credit
6. Review error logs for specific error codes

**Problem:** SMS received but variables are wrong
1. Verify pattern variables match expected order
2. Check pattern definition in MelliPayamak panel
3. Review `get_order_variables()` method for data format

---

## References

- [MelliPayamak SOAP API Documentation](https://github.com/melipayamak)
- [MelliPayamak Error Codes](https://panel.melipayamak.com/Webservice)
- [WordPress SOAP Client](https://www.php.net/manual/en/class.soapclient.php)
- [Fetch API Documentation](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API)
- [Blob API Documentation](https://developer.mozilla.org/en-US/docs/Web/API/Blob)

---

## Support

For issues or questions:
1. Check this documentation first
2. Review error logs (`wp-content/debug.log` if WP_DEBUG is enabled)
3. Check database logs (`wp_tabesh_logs` table)
4. Review MelliPayamak panel for SMS status
5. Contact Chapco support: https://chapco.ir

---

**Last Updated:** December 2, 2024
**Version:** 1.0.2+
