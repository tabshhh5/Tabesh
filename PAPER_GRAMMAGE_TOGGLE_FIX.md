# Fix: Paper Grammage Toggle Bug

## Problem Description (Persian)

زمانی که یک نوع کاغذ چند نوع گرماژ دارد و یک نوع آن را مدیر غیر فعال میکند و ذخیره میکند بعد از رفرش صفحه تعین قیمت دوباره گزینه غیر فعال شده فعال نمایش داده شده و فیلد قیمت آن 0 تومان نمایش داده میشود.

## Problem Description (English)

When a paper type has multiple grammages (weights) and the admin disables one specific grammage and saves it, after refreshing the pricing page, the disabled grammage shows as enabled again with a price field displaying 0 Toman.

## Root Cause

The issue was in the `parse_restrictions()` method in `/includes/handlers/class-tabesh-product-pricing.php`. The method was aggregating enabled/disabled state at the **paper_type level** instead of preserving granularity at the **per-weight level**.

### Example of the Bug

If you have paper type "تحریر" with weights [70, 80, 100] and you:
1. Enable bw for 70g
2. **Disable** bw for 80g  
3. Enable bw for 100g

The old logic would see that 'bw' is enabled for at least one weight (70g or 100g), so it would mark the entire paper type as having 'bw' enabled. This meant 80g would incorrectly show as enabled even though it was disabled!

### Old (Buggy) Data Structure

```php
$restrictions['forbidden_print_types']['تحریر'] = ['bw', 'color'];
// This applies to ALL weights of the paper type!
```

### New (Fixed) Data Structure

```php
$restrictions['forbidden_print_types']['تحریر']['70'] = ['color'];
$restrictions['forbidden_print_types']['تحریر']['80'] = ['bw', 'color'];
$restrictions['forbidden_print_types']['تحریر']['100'] = [];
// Each weight has independent restrictions!
```

## Solution

The fix changes the data structure to track forbidden combinations at the **per-weight level**, not per-paper-type level.

### Changes Made

1. **`class-tabesh-product-pricing.php`** - Modified `parse_restrictions()` method:
   - Changed from tracking enabled state per paper type to per weight
   - Initialize restrictions for each weight individually
   - Build forbidden list for each weight separately

2. **`product-pricing.php` template** - Updated rendering logic:
   - Check restrictions at `$restrictions['forbidden_print_types'][$paper_type][$weight]` instead of just `[$paper_type]`

3. **`class-tabesh-pricing-engine.php`** - Updated validation:
   - Validate print type restrictions per weight
   - Updated error messages to include weight information

4. **`class-tabesh-constraint-manager.php`** - Updated constraint checking:
   - Check restrictions per weight when determining available print types
   - Filter weights based on per-weight restrictions

5. **`class-tabesh-admin-order-form.php`** and **`tabesh.php`** - Updated weight filtering:
   - Only include weights that have at least one enabled print type
   - Check each weight individually instead of paper type as a whole

## Testing

A comprehensive test suite was created in `/test-per-weight-restrictions.php` that validates:

1. ✓ Multiple weights with different restrictions work correctly
2. ✓ Old vs new structure comparison demonstrates the fix
3. ✓ Available weights are correctly filtered
4. ✓ Validation correctly checks per-weight restrictions

All tests pass:

```
==================================================
Per-Weight Restriction Test Suite
Testing fix for paper grammage toggle bug
==================================================

Test 1: Multiple weights with different restrictions ✓ PASS
Test 2: Old structure vs New structure comparison ✓ PASS
Test 3: Available weights filtering ✓ PASS
Test 4: Validation check for per-weight restrictions ✓ PASS

FINAL RESULT: ✓ ALL TESTS PASSED
==================================================
```

## Manual Testing Instructions

To manually test this fix in a WordPress environment:

1. Go to the product pricing page (shortcode: `[tabesh_product_pricing]`)
2. Select a book size
3. For a paper type with multiple weights (e.g., "تحریر" with 70, 80, 100):
   - Enable both bw and color for 70g
   - **Disable both** bw and color for 80g (this is the critical test case)
   - Enable both bw and color for 100g
4. Click "Save"
5. **Refresh the page**
6. Verify that:
   - 70g shows as enabled with the prices you entered
   - **80g shows as DISABLED** (not enabled with 0 price) ← This is the bug fix!
   - 100g shows as enabled with the prices you entered

### Expected Behavior After Fix

- When you disable a specific weight, it should stay disabled after page refresh
- Disabled weights should not show with 0 price
- Each weight's enabled/disabled state is independent

### Old Buggy Behavior

- Disabling a weight would cause it to re-enable after refresh with 0 price
- If ANY weight was enabled, ALL weights would show as enabled

## Impact

This fix ensures that:
- Admins can selectively enable/disable specific weights of a paper type
- Disabled weights remain disabled after saving and refreshing
- The pricing matrix data is correctly persisted in the database
- The order form only shows weights that are actually enabled

## Backward Compatibility

The fix changes the data structure for `forbidden_print_types`. Existing pricing matrices using the old structure will need to be re-saved through the admin interface to migrate to the new structure. The system will continue to work with old data, but the per-weight granularity will not be available until re-saved.

## Security Considerations

No security issues introduced. All existing security measures (nonces, sanitization, escaping) remain in place.

## Related Files

- `/includes/handlers/class-tabesh-product-pricing.php` - Main fix
- `/templates/admin/product-pricing.php` - Template fix
- `/includes/handlers/class-tabesh-pricing-engine.php` - Validation fix
- `/includes/handlers/class-tabesh-constraint-manager.php` - Constraint checking fix
- `/includes/handlers/class-tabesh-admin-order-form.php` - Weight filtering fix
- `/tabesh.php` - Legacy code fix
- `/test-per-weight-restrictions.php` - Test suite

## Author

Fixed by GitHub Copilot on 2025-12-21
