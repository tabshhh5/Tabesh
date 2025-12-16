# Cron Job URL Fix - Testing Guide

## 🎯 Overview

This document explains how to test that the Doomsday Firewall emergency mode cron job URLs are now working correctly.

## 🐛 The Bug (Before Fix)

The cron job URLs were not working:
```
https://yourdomain.com/?tabesh_firewall_action=lockdown&key=YOUR_SECRET_KEY
https://yourdomain.com/?tabesh_firewall_action=unlock&key=YOUR_SECRET_KEY
```

**Root Cause:** The `Tabesh_Doomsday_Firewall` class was never instantiated during plugin initialization, so the `check_emergency_actions()` hook was never registered.

## ✅ The Fix

**File Modified:** `tabesh.php`

1. Added `public $firewall` property to the main `Tabesh` class
2. Added firewall initialization in the `init()` method: `$this->firewall = new Tabesh_Doomsday_Firewall();`

This ensures the firewall instance is created during plugin initialization, which registers the `init` action hook that processes emergency action URLs.

## 🧪 Testing Instructions

### Prerequisites

1. WordPress installation with Tabesh plugin activated
2. WooCommerce plugin activated
3. Access to WordPress admin panel

### Step 1: Configure Firewall

1. Log in to WordPress admin panel
2. Navigate to: **تابش** (Tabesh) → **تنظیمات** (Settings)
3. Scroll to: **فایروال روز رستاخیز** (Doomsday Firewall) section
4. Click **Enable Firewall** toggle
5. Click **Generate Secret Key** button (or copy the existing key)
6. Save the secret key somewhere safe (you'll need it for testing)
7. Click **Save Settings**

### Step 2: Create a Test WAR Order

1. Create a test order through the Tabesh order form
2. In the **Notes** field, include the text: `@WAR#` (this marks it as confidential)
3. Submit the order
4. Note the order ID

### Step 3: Test Lockdown URL (Activate Emergency Mode)

1. Copy the lockdown URL template:
   ```
   https://yourdomain.com/?tabesh_firewall_action=lockdown&key=YOUR_SECRET_KEY
   ```

2. Replace:
   - `yourdomain.com` with your actual WordPress site URL
   - `YOUR_SECRET_KEY` with the secret key from Step 1

3. Open the URL in a browser (you can be logged out - this is a cron job URL)

4. **Expected Result:**
   - You should see a success message: "Firewall action completed successfully"
   - HTTP status: 200

5. **Verify Lockdown Mode is Active:**
   - Log in to WordPress admin
   - Go to Tabesh → Settings → Doomsday Firewall section
   - You should see: **Current Status: 🔒 Lockdown Mode Active**
   - Try to view the WAR order in admin panel - it should be hidden

### Step 4: Test Unlock URL (Deactivate Emergency Mode)

1. Copy the unlock URL template:
   ```
   https://yourdomain.com/?tabesh_firewall_action=unlock&key=YOUR_SECRET_KEY
   ```

2. Replace with your actual values (same as Step 3)

3. Open the URL in a browser

4. **Expected Result:**
   - You should see a success message: "Firewall action completed successfully"
   - HTTP status: 200

5. **Verify Normal Mode is Active:**
   - Refresh the Tabesh settings page
   - You should see: **Current Status: ✅ Normal Mode**
   - WAR orders should now be visible to admins again

### Step 5: Test Invalid Key (Security Verification)

1. Try accessing the lockdown URL with an invalid key:
   ```
   https://yourdomain.com/?tabesh_firewall_action=lockdown&key=INVALID_KEY
   ```

2. **Expected Result:**
   - You should see an error message: "Firewall action failed - Invalid key or error"
   - HTTP status: 401
   - Lockdown mode should NOT be activated

### Step 6: Test Invalid Action

1. Try accessing with an invalid action:
   ```
   https://yourdomain.com/?tabesh_firewall_action=invalid&key=YOUR_SECRET_KEY
   ```

2. **Expected Result:**
   - You should see an error message: "Firewall action failed - Invalid key or error"
   - HTTP status: 401

### Step 7: Verify Activity Logs

1. Go to Tabesh → Settings → Doomsday Firewall section
2. Scroll to **Activity Log** at the bottom
3. You should see entries for:
   - `firewall_lockdown_activated` - when you used the lockdown URL
   - `firewall_lockdown_deactivated` - when you used the unlock URL
   - `firewall_lockdown_activation_failed` - when you used invalid key (if you tested that)

## 🔧 Automated Testing with Curl

You can also test using curl commands from the terminal:

```bash
# Test lockdown (replace with your URL and key)
curl -v "https://yourdomain.com/?tabesh_firewall_action=lockdown&key=YOUR_SECRET_KEY"

# Test unlock
curl -v "https://yourdomain.com/?tabesh_firewall_action=unlock&key=YOUR_SECRET_KEY"

# Test invalid key (should fail)
curl -v "https://yourdomain.com/?tabesh_firewall_action=lockdown&key=INVALID"
```

## 📊 Expected Behavior Summary

| Scenario | URL Parameters | Expected HTTP Status | Expected Result |
|----------|---------------|---------------------|-----------------|
| Valid lockdown | `action=lockdown&key=VALID` | 200 | Lockdown activated |
| Valid unlock | `action=unlock&key=VALID` | 200 | Lockdown deactivated |
| Invalid key | `action=lockdown&key=INVALID` | 401 | Error message, no change |
| Invalid action | `action=invalid&key=VALID` | 401 | Error message, no change |
| Missing key | `action=lockdown` | No response | Normal page load |
| Missing action | `key=VALID` | No response | Normal page load |

## 🔐 Security Notes

1. **Secret Key Security:**
   - The secret key must be at least 32 characters
   - Keys are verified using `hash_equals()` to prevent timing attacks
   - Never share your secret key publicly

2. **Cron Job Setup:**
   - These URLs are designed for automated cron jobs
   - They work without user login (unlike admin panel actions)
   - Use HTTPS in production to protect the secret key in transit

3. **Activity Logging:**
   - All firewall actions are logged to `wp_tabesh_logs` table
   - Logs include user_id (null for cron jobs), action, and timestamp
   - Check logs regularly for suspicious activity

## ✅ Success Criteria

The fix is successful if:

- ✅ Lockdown URL activates emergency mode and returns HTTP 200
- ✅ Unlock URL deactivates emergency mode and returns HTTP 200
- ✅ Invalid key returns HTTP 401 and doesn't change status
- ✅ Activity logs show all firewall actions
- ✅ WAR orders are hidden when lockdown is active
- ✅ URLs work without requiring user login

## 📝 Troubleshooting

### URL returns 404
- Check that Tabesh plugin is activated
- Check that WordPress permalinks are set up correctly
- Try using the full URL with `index.php`: `https://yourdomain.com/index.php?tabesh_firewall_action=lockdown&key=YOUR_KEY`

### URL returns blank page
- Enable WordPress debug mode in `wp-config.php`:
  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  define('WP_DEBUG_DISPLAY', false);
  ```
- Check `/wp-content/debug.log` for errors

### Action doesn't seem to work
- Verify the secret key is correct (compare with admin panel)
- Check activity logs to see if action was attempted
- Ensure firewall is enabled in settings

### Still getting 401 errors with valid key
- Regenerate the secret key in admin panel
- Make sure you're copying the complete key (should be 32 characters)
- Check that no special characters are URL-encoded incorrectly

## 🚀 Deployment Notes

**Files Changed:**
- `tabesh.php` - Added firewall initialization

**No Breaking Changes:**
- Backward compatible
- No database changes required
- Existing functionality unchanged

**Version:**
- This fix is part of version 1.0.4+

---

**Date:** December 16, 2024
**Status:** ✅ Ready for Testing
