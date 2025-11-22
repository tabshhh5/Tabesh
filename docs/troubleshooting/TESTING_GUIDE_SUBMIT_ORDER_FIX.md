# Testing Guide - Submit Order 400 Error Fix

## Overview
This guide provides comprehensive testing instructions for the submit-order endpoint fix that addresses the "Unknown column 'book_title'" database error and improves REST API error handling.

## Prerequisites

### Before Testing
1. **CRITICAL: Create full database backup**
   ```bash
   # Using WP-CLI
   wp db export backup-before-submit-order-fix.sql
   
   # Or using phpMyAdmin/Adminer
   # Export all tables with structure and data
   ```

2. **Enable debug mode** (development environment only)
   ```php
   // Add to wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. **Check current database version**
   ```sql
   SELECT * FROM wp_options WHERE option_name = 'tabesh_db_version';
   ```

## Migration Testing

### Test 1: Fresh Installation (Table Exists)
**Scenario:** Plugin installed with latest schema (book_title column already exists)

**Steps:**
1. Deactivate and reactivate the plugin
2. Check debug.log for migration messages:
   ```
   Tabesh: Starting database schema update check
   Tabesh: book_title column already exists
   Tabesh: Database schema update completed. Version: 1.1.0
   ```
3. Verify database version:
   ```sql
   SELECT option_value FROM wp_options WHERE option_name = 'tabesh_db_version';
   -- Expected: 1.1.0
   ```

### Test 2: Legacy Installation (Missing Column)
**Scenario:** Older plugin version without book_title column

**Steps:**
1. Simulate legacy database (if needed):
   ```sql
   -- ONLY FOR TESTING - removes book_title column
   ALTER TABLE wp_tabesh_orders DROP COLUMN IF EXISTS book_title;
   DELETE FROM wp_options WHERE option_name = 'tabesh_db_version';
   ```

2. Reactivate plugin or visit any admin page to trigger migration

3. Check debug.log:
   ```
   Tabesh: Adding book_title column to orders table
   Tabesh: SUCCESS - Added book_title column to orders table
   ```

4. Verify column was added:
   ```sql
   SHOW COLUMNS FROM wp_tabesh_orders LIKE 'book_title';
   -- Should return: book_title | varchar(255) | YES | | NULL
   ```

### Test 3: Missing Table Scenario
**Scenario:** Orders table doesn't exist yet

**Steps:**
1. Migration should skip gracefully
2. Check debug.log:
   ```
   Tabesh: Orders table does not exist, skipping migration
   ```

## Order Submission Testing

### Prerequisites for Order Tests
- User must be logged in
- WooCommerce must be active
- Order form should be displayed via `[tabesh_order_form]` shortcode

### Test 4: Submit Order Without Files (JSON)
**Expected:** Order saved to database, returns 201 Created

**Steps:**
1. Navigate to order form page
2. Fill all required fields:
   - Book Title: "کتاب تست" (required)
   - Book Size: "A5"
   - Paper Type: "تحریر"
   - Paper Weight: "80g"
   - Print Type: "سیاه و سفید"
   - Page Count (B&W): 100
   - Page Count (Color): 0
   - Quantity: 50
   - Binding: "شومیز"
   - License Type: "دارم"

3. Click "محاسبه قیمت" (Calculate Price)
4. Verify price displays correctly
5. Click "ثبت سفارش" (Submit Order)

**Expected Results:**
- Browser console shows: `Tabesh: Submitting without files using JSON`
- Request Content-Type: `application/json; charset=utf-8`
- Response status: `201 Created`
- Response body:
  ```json
  {
    "success": true,
    "data": {
      "order_id": 123
    },
    "message": "سفارش با موفقیت ثبت شد"
  }
  ```
- Success notification appears: "سفارش با موفقیت ثبت شد"
- Page redirects to `/my-account/orders/`
- Order appears in database:
  ```sql
  SELECT id, order_number, book_title, status FROM wp_tabesh_orders ORDER BY id DESC LIMIT 1;
  ```

### Test 5: Submit Order With License File (FormData)
**Expected:** Order and file saved, returns 201 Created

**Steps:**
1. Fill order form as in Test 4
2. For License Type: Select "انتشارات چاپکو" or "سفیر سلامت"
3. Upload a test PDF file (< 5MB)
4. Click "محاسبه قیمت"
5. Click "ثبت سفارش"

**Expected Results:**
- Browser console shows: `Tabesh: Submitting with files using FormData`
- Request Content-Type: `multipart/form-data`
- Response status: `201 Created`
- File uploaded to `/wp-content/uploads/`
- Order saved with files metadata in database:
  ```sql
  SELECT id, book_title, files FROM wp_tabesh_orders WHERE id = [last_order_id];
  -- files column should contain serialized data with license file info
  ```

### Test 6: Missing Required Field (book_title)
**Expected:** 400 Bad Request with error message

**Steps:**
1. Fill order form but leave Book Title empty
2. Submit order (skip calculate if possible, or use browser dev tools to remove required attribute)

**Expected Results:**
- Response status: `400 Bad Request`
- Response body:
  ```json
  {
    "code": "missing_book_title",
    "message": "عنوان کتاب الزامی است.",
    "data": {
      "status": 400
    }
  }
  ```
- Error notification: "عنوان کتاب الزامی است."
- Submit button re-enabled

### Test 7: Unauthenticated User
**Expected:** 403 Forbidden or redirect to login

**Steps:**
1. Log out
2. Visit order form page
3. Try to submit order

**Expected Results:**
- Either blocked at REST API level (403) or
- Frontend detects missing nonce and redirects to login

### Test 8: Invalid File Type
**Expected:** 400 Bad Request with validation error

**Steps:**
1. Fill order form
2. Upload a .txt or .exe file
3. Submit order

**Expected Results:**
- Response status: `400 Bad Request`
- Error message: "فرمت فایل مجاز نیست. فقط PDF, JPG, PNG مجاز است."

### Test 9: File Too Large
**Expected:** 400 Bad Request with size error

**Steps:**
1. Fill order form
2. Upload a file > 5MB
3. Submit order

**Expected Results:**
- Response status: `400 Bad Request`
- Error message: "حجم فایل بیش از حد مجاز (5MB) است."

### Test 10: Fallback to Post System
**Scenario:** Orders table or column missing during runtime

**Steps:**
1. Temporarily rename orders table:
   ```sql
   RENAME TABLE wp_tabesh_orders TO wp_tabesh_orders_backup;
   ```

2. Submit an order
3. Check if post was created:
   ```sql
   SELECT * FROM wp_posts WHERE post_type = 'tabesh_order' ORDER BY ID DESC LIMIT 1;
   ```

4. Verify post meta:
   ```sql
   SELECT * FROM wp_postmeta WHERE post_id = [post_id] AND meta_key LIKE '_tabesh_%';
   ```

5. Check debug.log:
   ```
   Tabesh: Orders table does not exist, using post fallback
   Tabesh: Order created as post ID: 456
   ```

6. Restore table:
   ```sql
   RENAME TABLE wp_tabesh_orders_backup TO wp_tabesh_orders;
   ```

## Browser Compatibility Testing

Test in the following browsers:
- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest, macOS/iOS)
- ✅ Mobile browsers (Chrome, Safari on iOS/Android)

Check:
- No JavaScript console errors
- AJAX requests complete successfully
- Proper error messages display
- Form submission works in both JSON and FormData modes

## Network Testing

### Test Different Network Conditions
1. **Normal connection:** Order submission should complete in < 3 seconds
2. **Slow 3G:** Order submission may take longer, ensure timeout is adequate
3. **Offline:** Should show connection error, not crash

### Test Proxy/CDN Scenarios
- LiteSpeed Cache enabled
- Cloudflare or other reverse proxy
- Verify X-WP-Nonce header passes through
- Check for "Upstream Error" or similar proxy errors

## Debug Log Monitoring

### Expected Log Entries (WP_DEBUG enabled)

**Successful submission:**
```
Tabesh: submit_order_rest called
Tabesh: Content-Type: application/json
Tabesh: Processing JSON request
Tabesh: JSON params: Array(...)
Tabesh: Order submitted successfully with ID: 123
```

**Missing book_title:**
```
Tabesh: Missing or empty book_title
Tabesh: All params: Array(...)
Tabesh: Order submission failed: عنوان کتاب الزامی است.
```

**Database fallback:**
```
Tabesh: Orders table does not exist, using post fallback
Tabesh: Order created as post ID: 456
```

## Performance Testing

### Metrics to Check
- Order submission response time: < 2 seconds (normal network)
- Database query time: < 100ms
- File upload time: depends on file size, < 10 seconds for 5MB
- No N+1 query problems

### Load Testing (Optional)
Use Apache Bench or similar:
```bash
# Test calculate-price endpoint
ab -n 100 -c 10 -T 'application/json' -H 'X-WP-Nonce: [nonce]' \
  -p test-data.json \
  https://example.com/wp-json/tabesh/v1/calculate-price

# Monitor server resources during test
```

## Cleanup After Testing

1. **Disable debug mode** (production):
   ```php
   // Remove or set to false in wp-config.php
   define('WP_DEBUG', false);
   define('WP_DEBUG_LOG', false);
   ```

2. **Remove test orders:**
   ```sql
   DELETE FROM wp_tabesh_orders WHERE book_title LIKE '%تست%' OR book_title LIKE '%test%';
   DELETE FROM wp_tabesh_logs WHERE order_id NOT IN (SELECT id FROM wp_tabesh_orders);
   ```

3. **Remove test files:**
   ```bash
   # Check wp-content/uploads/ for test files and remove manually
   ```

## Rollback Procedure

If testing reveals issues, see [ROLLBACK_PLAN.md](ROLLBACK_PLAN.md) for detailed rollback instructions.

## Success Criteria

All tests must pass:
- ✅ Migration runs successfully without errors
- ✅ book_title column added to existing installations
- ✅ Orders can be submitted without files (JSON)
- ✅ Orders can be submitted with files (FormData)
- ✅ Proper 201/400 status codes returned
- ✅ Error messages are user-friendly in Persian
- ✅ Fallback to post system works if needed
- ✅ No JavaScript errors in console
- ✅ No PHP errors in debug.log
- ✅ Database queries use prepared statements
- ✅ All inputs sanitized, outputs escaped

## Reporting Issues

If tests fail, please provide:
1. Which test failed
2. Debug.log entries (relevant lines)
3. Browser console errors (if applicable)
4. Database error messages
5. Network request/response (from browser DevTools)
6. WordPress version, PHP version, theme name
7. Active plugins list

---

**Document Version:** 1.0  
**Last Updated:** 2025-11-10  
**Related:** ROLLBACK_PLAN.md, MIGRATION_GUIDE.md
