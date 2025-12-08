# Order Status Fix: serial_number Format Mismatch

## Problem Summary

After PR #102 added the `serial_number` column, new orders were being saved with `status = '0.000000'` instead of `status = 'pending'`. This was caused by a mismatch between the order of fields in the `$data` array and their corresponding format specifiers in the `$formats` array.

## Root Cause Analysis

### The Issue Chain

1. **PR #102** added `serial_number` column to track official order numbers
   - Added `serial_number` to **END** of `$data` array (line 541)
   - Added `serial_number` format to **BEGINNING** of `$formats` array (line 548)
   - This created a misalignment

2. **PR #105** attempted to fix by reordering `$data`
   - Used `array_merge()` to move `serial_number` to BEGINNING of `$data`
   - This aligned with the format being at the beginning of `$formats`
   - However, this approach was complex and error-prone

### How the Bug Manifested

When `$wpdb->insert()` is called with misaligned arrays:

```php
// Misaligned example:
$data = array(
    'user_id' => 123,
    'order_number' => 'TB-001',
    // ... other fields ...
    'total_price' => 50000.00,
    'status' => 'pending',    // Position 19
    'notes' => 'test',
    'serial_number' => 5      // Position 21
);

$formats = array(
    '%d', // serial_number  <- WRONG! This is position 0
    '%d', // user_id
    '%s', // order_number
    // ... other formats ...
    '%f', // total_price
    '%s', // status         <- Actually applied to 'pending'!
    '%s'  // notes
);
```

The result:
- `status` value ('pending') gets formatted with `%f` (float format)
- PHP converts the string 'pending' to float → `0.000000`
- Database stores `0.000000` instead of `'pending'`

## Solution

The fix ensures both arrays have `serial_number` at the **same position** (the END):

```php
// 1. Build formats array for standard fields (21 fields)
$formats = array(
    '%d', // user_id
    '%s', // order_number
    // ... all standard fields ...
    '%s', // status         <- Position 19
    '%s'  // notes          <- Position 20
);

// 2. Add serial_number to END of BOTH arrays
if (column_exists('serial_number')) {
    $data['serial_number'] = $next_serial;  // Position 21
    $formats[] = '%d'; // serial_number     // Position 21
}
```

Now both arrays are perfectly aligned:
- Position 19: `status` → `%s` ✓
- Position 20: `notes` → `%s` ✓
- Position 21: `serial_number` → `%d` ✓

## Changes Made

### File: `includes/handlers/class-tabesh-order.php`

**Lines 543-586:** Restructured the serial_number handling

**Before (PR #105 approach):**
```php
// Get serial number first
if (column_exists('serial_number')) {
    $next_serial = get_next_serial();
    unset($data['serial_number']);
    $data = array_merge(array('serial_number' => $next_serial), $data);
}

// Build formats with serial_number first
$formats = array();
if (isset($data['serial_number'])) {
    $formats[] = '%d'; // serial_number
}
$formats = array_merge($formats, array(
    '%d', // user_id
    // ... other formats
));
```

**After (Current fix):**
```php
// Build formats for standard fields first
$formats = array(
    '%d', // user_id
    '%s', // order_number
    // ... all standard formats ...
    '%s', // status
    '%s'  // notes
);

// Add serial_number to END of both arrays
if (column_exists('serial_number')) {
    $next_serial = get_next_serial();
    $data['serial_number'] = $next_serial;
    $formats[] = '%d'; // serial_number
}
```

## Verification

### Test Script

A verification script (`/tmp/verify_fix.php`) was created to test the fix:

```bash
php /tmp/verify_fix.php
```

**Results:**
```
✓ ALL CHECKS PASSED!
  - status field correctly formatted as string
  - total_price field correctly formatted as float
  - serial_number field correctly formatted as integer
  - All arrays properly aligned
```

### Manual Testing Steps

1. Enable WordPress debug mode:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. Create a new order through the order form

3. Check the database:
   ```sql
   SELECT id, serial_number, order_number, status, total_price, created_at
   FROM wp_tabesh_orders
   ORDER BY id DESC
   LIMIT 1;
   ```

4. Verify results:
   - ✓ `status` should be `'pending'` (not `0.000000`)
   - ✓ `serial_number` should be an integer
   - ✓ `total_price` should be a valid decimal number
   - ✓ All other fields should have correct values

## Impact

### Fixed
- ✅ Orders now save with correct status (`'pending'`)
- ✅ All field values are properly formatted
- ✅ No more data corruption

### Compatibility
- ✅ Backward compatible with existing orders
- ✅ Works with or without `serial_number` column
- ✅ No breaking changes to the API

### Performance
- ✅ Simpler code (removed complex `array_merge()` logic)
- ✅ No performance impact
- ✅ Maintains existing functionality

## Database Repair

If you have orders that were created with the bug (status = `0.000000`), you can repair them:

```sql
-- Identify affected orders
SELECT id, order_number, status, created_at
FROM wp_tabesh_orders
WHERE status REGEXP '^[0-9]+\\.?[0-9]*$'
  AND created_at >= '2025-12-07 00:00:00'
ORDER BY id DESC;

-- Fix status to 'pending'
UPDATE wp_tabesh_orders
SET status = 'pending'
WHERE status REGEXP '^[0-9]+\\.?[0-9]*$'
  AND created_at >= '2025-12-07 00:00:00'
  AND status != 'pending';

-- Verify the fix
SELECT COUNT(*) as corrected_orders
FROM wp_tabesh_orders
WHERE status = 'pending'
  AND created_at >= '2025-12-07 00:00:00';
```

**⚠️ Warning:** If other fields were also corrupted, you may need to delete those orders as data recovery may not be possible.

## Lessons Learned

1. **Array Order Matters**: When using `$wpdb->insert()`, the order of values in `$data` must match the order of formats in `$formats`

2. **Simplicity Wins**: Using `array_merge()` to reorder arrays is complex. Adding fields at the end is simpler and more maintainable

3. **Test Alignment**: Always verify that field positions match format positions, especially when adding new fields

4. **Debug Logging**: The existing debug logging (`WP_DEBUG`) helped identify the issue quickly

## Related PRs

- **PR #102**: Added `serial_number` column (introduced the bug)
- **PR #105**: First attempt to fix (used `array_merge()` approach)
- **Current PR**: Final fix (adds fields at the end instead of beginning)

## References

- WordPress `$wpdb->insert()` documentation: https://developer.wordpress.org/reference/classes/wpdb/insert/
- PHP `array_merge()` behavior: https://www.php.net/manual/en/function.array-merge.php
- Problem statement: See issue description in GitHub

## Credits

- **Issue Reporter**: tabshhh3
- **Analysis**: Detailed problem analysis in Persian
- **Fix Implementation**: GitHub Copilot
- **Verification**: Automated test script
