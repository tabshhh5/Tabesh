# Testing Guide: Submit Order Improvements

## Overview
This guide helps you test the improvements made to the submit-order functionality, including enhanced error logging and resolved wpdb::prepare warnings.

## Prerequisites
- WordPress 6.8+ with Tabesh plugin installed
- WooCommerce plugin active
- PHP 8.2.2+
- Access to WordPress debug logs
- At least one test user account (customer role)

## Enable Debug Logging

Add these lines to your `wp-config.php` file **temporarily for testing only**:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
```

**⚠️ SECURITY WARNING**: Never enable debug logging in production environments!

## Test Cases

### Test 1: Verify Database Migration

**Purpose**: Ensure the `book_title` column exists and migration works correctly.

**Steps**:
1. Access your WordPress admin panel
2. Navigate to any page (this triggers the `init` action)
3. Check debug log at `wp-content/debug.log`
4. Look for messages like:
   - `Tabesh: Starting database schema update check`
   - `Tabesh: book_title column already exists` (if migration ran before)
   - `Tabesh: Database schema update completed`

**Expected Result**: No error messages, migration completes successfully.

**If Migration Fails**:
- Check if `wp_tabesh_orders` table exists in database
- Verify database user has ALTER TABLE permissions
- Review error messages in debug log

### Test 2: Submit New Order (Logged-in User)

**Purpose**: Verify orders are saved correctly with all data.

**Steps**:
1. Log in as a customer
2. Navigate to the order form page (typically via `[tabesh_order_form]` shortcode)
3. Fill in ALL required fields:
   - **Book Title** (عنوان کتاب) - REQUIRED
   - Book Size (اندازه کتاب)
   - Paper Type (نوع کاغذ)
   - Paper Weight (وزن کاغذ)
   - Print Type (نوع چاپ)
   - Page Count (تعداد صفحات)
   - Quantity (تعداد)
   - Binding Type (نوع صحافی)
   - License Type (نوع مجوز)
   - Cover Options (گزینه‌های جلد)
4. Click "Submit Order" (ثبت سفارش)
5. Check for success message
6. Check debug log for:
   - `Tabesh: submit_order called`
   - `Tabesh: Generated order number: TB-...`
   - `Tabesh: Order data prepared, calling create_order`
   - `Tabesh: create_order called with data`
   - `Tabesh: Attempting to insert order`
   - `Tabesh: Order successfully inserted with ID: X`
   - `Tabesh: Order created successfully with ID: X`

**Expected Result**: 
- Success message displayed to user
- Order appears in database (`wp_tabesh_orders` table)
- Order appears in admin panel
- Debug log shows successful creation with order ID
- No error messages

**If Order Fails**:
- Check if user is logged in (check for "not logged in" error in log)
- Verify Book Title field is filled (check for "book_title missing" error)
- Check database connection and permissions
- Review full error message in debug log

### Test 3: Submit Order Without Book Title

**Purpose**: Verify validation works correctly.

**Steps**:
1. Log in as a customer
2. Navigate to order form
3. Fill in all fields EXCEPT Book Title
4. Click "Submit Order"

**Expected Result**:
- Error message: "عنوان کتاب الزامی است" (Book title is required)
- Debug log shows: `Tabesh: Order submission failed - book_title missing`
- No order created in database

### Test 4: Submit Order (Not Logged In)

**Purpose**: Verify authentication works correctly.

**Steps**:
1. Log out from WordPress
2. Navigate to order form page
3. Try to submit an order

**Expected Result**:
- Error message about authentication
- User redirected to login page
- Debug log shows: `Tabesh: Order submission failed - user not logged in`

### Test 5: Database Errors

**Purpose**: Verify error handling for database issues.

**Steps**:
1. This test requires database access
2. Temporarily rename the `book_title` column:
   ```sql
   ALTER TABLE wp_tabesh_orders CHANGE book_title book_title_backup VARCHAR(255);
   ```
3. Try to submit an order
4. Check debug log

**Expected Result**:
- Order creation uses fallback method (WordPress posts)
- Debug log shows:
  - `Tabesh: book_title column missing, using post fallback`
  - `Tabesh: Order created as post ID: X`
- Order is saved as a custom post type instead

**Cleanup**:
```sql
ALTER TABLE wp_tabesh_orders CHANGE book_title_backup book_title VARCHAR(255);
```

### Test 6: Check for wpdb::prepare Warnings

**Purpose**: Verify no WordPress coding standards warnings.

**Steps**:
1. Ensure `WP_DEBUG` is enabled
2. Deactivate then reactivate Tabesh plugin (triggers activation hook)
3. Check debug log

**Expected Result**:
- No warnings like: "Function wpdb::prepare was called incorrectly"
- Migration messages show successful column additions
- All ALTER TABLE statements execute without warnings

## Verifying Orders in Database

Connect to your database and run:

```sql
-- Check if orders table exists
SHOW TABLES LIKE '%tabesh_orders%';

-- Check table structure
DESCRIBE wp_tabesh_orders;

-- View recent orders
SELECT id, order_number, book_title, user_id, status, created_at 
FROM wp_tabesh_orders 
ORDER BY created_at DESC 
LIMIT 10;

-- Count total orders
SELECT COUNT(*) as total_orders FROM wp_tabesh_orders;
```

## Interpreting Debug Logs

### Successful Order Submission

Look for this sequence in the log:

```
Tabesh: submit_order called
Tabesh: Generated order number: TB-20251110-1234
Tabesh: Order data prepared, calling create_order
Tabesh: create_order called with data: Array...
Tabesh: Attempting to insert order into wp_tabesh_orders
Tabesh: Order successfully inserted with ID: 123
Tabesh: Order created successfully with ID: 123
```

### Failed Order Submission

Common failure patterns:

**Missing Book Title:**
```
Tabesh: submit_order called
Tabesh: Order submission failed - book_title missing
```

**Not Logged In:**
```
Tabesh: Order submission failed - user not logged in
```

**Database Error:**
```
Tabesh: create_order called with data: Array...
Tabesh: Database insert failed
Tabesh: Error message: [SQL error details]
Tabesh: Last query: INSERT INTO...
```

**Column Missing (Fallback Used):**
```
Tabesh: book_title column missing, using post fallback
Tabesh: Order created as post ID: 456
```

## Troubleshooting

### Problem: No Debug Messages

**Solution**:
- Verify `WP_DEBUG` and `WP_DEBUG_LOG` are enabled in `wp-config.php`
- Check if `wp-content/debug.log` file exists and is writable
- Try creating the file manually: `touch wp-content/debug.log && chmod 666 wp-content/debug.log`

### Problem: Orders Show Success But Not Saved

**Solution**:
1. Check debug log for database errors
2. Verify `wp_tabesh_orders` table exists
3. Check if `book_title` column exists: `SHOW COLUMNS FROM wp_tabesh_orders LIKE 'book_title';`
4. Verify database user permissions
5. Check if orders are being saved as posts instead (custom post type `tabesh_order`)

### Problem: wpdb::prepare Warnings Still Appear

**Solution**:
- Ensure you're using the latest version with phpcs:ignore comments
- These warnings are safe to ignore for ALTER TABLE statements
- The table names are not user input

### Problem: Migration Doesn't Run

**Solution**:
1. Check database version option: `SELECT * FROM wp_options WHERE option_name = 'tabesh_db_version';`
2. Manually trigger migration by deactivating and reactivating plugin
3. Or manually update version: `UPDATE wp_options SET option_value = '0.0.0' WHERE option_name = 'tabesh_db_version';`

## Security Checklist

Before deploying to production:

- [ ] Disable `WP_DEBUG` in wp-config.php
- [ ] Delete or secure debug.log file
- [ ] Verify all user inputs are properly sanitized
- [ ] Test with different user roles (admin, customer, guest)
- [ ] Verify nonce verification works for REST API endpoints
- [ ] Check that orders can only be viewed by their owners or admins

## Performance Notes

The enhanced logging adds minimal overhead:
- Only active when `WP_DEBUG` is enabled
- Uses conditional checks before logging
- No impact on production performance when debug is disabled

## Rollback Plan

If issues occur after deployment:

1. **Revert Code**:
   ```bash
   git revert HEAD
   git push origin branch-name
   ```

2. **Check Database**:
   - Orders are preserved even if code is reverted
   - No data loss occurs from logging changes

3. **Emergency Fix**:
   - Disable plugin temporarily
   - Check if orders were saved as posts (custom post type)
   - Contact support with debug log

## Success Criteria

✅ All test cases pass
✅ Orders are saved in database
✅ Book title is required and validated
✅ Authentication works correctly
✅ No wpdb::prepare warnings in debug log
✅ Error messages are user-friendly
✅ Debug logs provide actionable information

## Support

If you encounter issues not covered in this guide:

1. Collect debug logs showing the error
2. Note the exact steps to reproduce
3. Check database state
4. Review error messages in browser console (F12)
5. Contact support with all collected information
