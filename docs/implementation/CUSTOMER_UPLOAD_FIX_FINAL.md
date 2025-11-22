# Customer File Upload Permission Fix - Final Implementation

## Problem Statement

**Issue**: Users with the "Customer" role cannot upload files through the file manager shortcode, despite being logged in.

**Requirement**: ALL logged-in WordPress users, regardless of their role, must be able to upload files for their own orders.

## Root Cause Analysis

After extensive investigation, the issue stems from WordPress's built-in REST API cookie authentication restrictions. By default, WordPress only allows cookie-based REST API authentication for users with the `edit_posts` capability, which EXCLUDES customers.

## Solution Implemented

### 1. REST API Cookie Authentication Filter

**File**: `tabesh.php` (lines 1115-1170)

**How It Works**:
- Hooks into `rest_authentication_errors` filter at priority 100
- Returns `true` for ANY logged-in user with a valid nonce (bypasses WordPress's edit_posts restriction)
- Returns `null` (not `WP_Error`) when nonce is invalid, allowing WordPress to continue authentication
- Works for ALL user roles: admin, staff, customer, subscriber, etc.

**Critical Implementation Details**:
```php
// ✅ CORRECT: Return true for authenticated users with valid nonce
if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
    return true;  // Works for ALL roles!
}

// ✅ CORRECT: Return null to allow other auth methods
return null;  // Don't block authentication!

// ❌ WRONG: Return WP_Error blocks ALL authentication
// return new WP_Error(...);  // This would prevent customers from authenticating!
```

### 2. Permission Callback

**File**: `tabesh.php` (lines 1180-1224)

**Function**: `check_rest_api_permission()`

**Implementation**:
- Checks only if user is logged in: `is_user_logged_in()` or `get_current_user_id() > 0`
- NO capability checks (no `edit_posts`, `manage_woocommerce`, etc.)
- Returns `true` for ANY logged-in user
- Returns `WP_Error` only if user is NOT logged in

### 3. File Upload Method

**File**: `includes/class-tabesh-file-manager.php` (lines 234-263)

**Function**: `upload_file()`

**Permission Logic**:
```php
// Step 1: Verify user is logged in
if ($current_user_id <= 0 || $current_user_id != $user_id) {
    return error;  // Not authenticated
}

// Step 2: Verify order ownership OR admin
$is_admin = current_user_can('manage_woocommerce') || current_user_can('manage_options');
if (!$is_admin && !$this->verify_order_ownership($order_id, $user_id)) {
    return error;  // Not order owner and not admin
}

// User is authorized! (customer owns order, or user is admin)
```

**Result**:
- Customers can upload to their own orders ✅
- Admins can upload to any order ✅
- No role-based restrictions ✅

## Permission Model

### Who Can Upload Files?

| Role | Can Upload to Own Orders | Can Upload to Any Order |
|------|-------------------------|------------------------|
| Customer | ✅ Yes | ❌ No |
| Subscriber | ✅ Yes | ❌ No |
| Contributor | ✅ Yes | ❌ No |
| Author | ✅ Yes | ❌ No |
| Editor | ✅ Yes | ❌ No |
| Shop Manager | ✅ Yes | ✅ Yes |
| Administrator | ✅ Yes | ✅ Yes |

### How It Works

1. **Authentication**: User must be logged in (any role)
2. **Authorization**: User must own the order OR be an admin
3. **No Capability Checks**: No `edit_posts` or other capability requirements

## Debugging Guide

### Enable Debug Logging

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Test Customer Upload

1. Log in as a customer user
2. Navigate to file upload page
3. Select a file
4. Click upload
5. Check `/wp-content/debug.log`

### Log Output

You'll see:
```
Tabesh upload attempt - User ID: 123, Roles: customer, Has edit_posts: NO, Has manage_woocommerce: NO
Tabesh upload - order_id: 456, user_id: 123, category: book_content
```

If upload succeeds: File uploaded successfully ✅
If upload fails: Look for specific error message in logs

### Common Issues

**Issue**: 403 Forbidden error
- **Cause**: User not authenticated or nonce invalid
- **Check**: Are cookies being sent? Is nonce valid?

**Issue**: "سفارش متعلق به شما نیست" (Order doesn't belong to you)
- **Cause**: Customer trying to upload to someone else's order
- **Check**: Does order.user_id match current user ID?

**Issue**: "شما مجاز به آپلود فایل نیستید" (You're not authorized to upload)
- **Cause**: Authentication completely failed
- **Check**: Is user actually logged in? Check `is_user_logged_in()` and `get_current_user_id()`

## Testing Checklist

- [ ] Create a test customer user
- [ ] Create an order for that customer
- [ ] Log in as the customer
- [ ] Navigate to file upload shortcode page
- [ ] Select a valid file
- [ ] Click upload button
- [ ] Verify upload succeeds (200 OK response)
- [ ] Verify file appears in database
- [ ] Verify file is stored on disk
- [ ] Check debug logs for authentication details

## Security Considerations

### What We Changed
✅ Allowed all logged-in users to authenticate via REST API (not just users with `edit_posts`)

### What We Kept Secure
✅ Nonce verification (prevents CSRF)
✅ Order ownership verification (customers can only upload to their own orders)
✅ File type validation (only allowed file types)
✅ File size limits (prevents abuse)
✅ Input sanitization (all parameters sanitized)
✅ SQL injection prevention (prepared statements)

### No Security Risks Introduced
- Users still must be logged in
- Users still can only upload to orders they own (unless admin)
- All file validation still applies
- Nonce still required

## Code Changes Summary

### Files Modified
1. `tabesh.php` - Enhanced authentication and permission callbacks
2. `includes/class-tabesh-file-manager.php` - Clarified permission comments

### Lines Changed
- `tabesh.php` line 1115-1170: REST cookie authentication filter
- `tabesh.php` line 1180-1224: Permission callback
- `tabesh.php` line 1437-1503: Upload handler with enhanced debugging
- `class-tabesh-file-manager.php` line 234-263: Upload method comments

### No Breaking Changes
- Existing functionality preserved
- Admins still have full access
- Customers can now upload (as intended)

## Frequently Asked Questions

### Q: Will this allow customers to upload files to other customers' orders?
**A**: No. The `verify_order_ownership()` check ensures users can only upload to their own orders.

### Q: Will this work for custom user roles?
**A**: Yes. ANY logged-in user (regardless of role) can upload files to orders they own.

### Q: What if I want to restrict uploads to specific roles?
**A**: You would need to add custom capability checks in the `upload_file()` method. However, this violates the requirement that ALL logged-in users should be able to upload.

### Q: Does this affect admin/staff file uploads?
**A**: No. Admins and shop managers retain their ability to upload files to any order.

### Q: Is this secure?
**A**: Yes. Authentication, order ownership, file validation, and input sanitization are all enforced.

## Conclusion

This fix ensures that **ALL logged-in WordPress users** can upload files through the file manager shortcode, as required. The solution:

✅ Bypasses WordPress's `edit_posts` capability restriction for REST API cookie authentication  
✅ Allows customers to upload files to their own orders  
✅ Maintains security through authentication and order ownership checks  
✅ Includes enhanced debugging for troubleshooting  
✅ Works for all WordPress user roles  

The permission model is now: **If you're logged in and you own the order, you can upload files.**
