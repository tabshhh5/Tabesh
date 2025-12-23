# Cover Weight Cascade Filtering Fix

## خلاصه فارسی

### مشکل
بعد از ادغام PR #166 که فرم ثبت سفارش ادمین را به موتور قیمت‌گذاری V2 متصل کرد، فیلد گرماژ جلد (cover paper weight) از پارامترهای مجاز گره متوالی (constraint manager) پیروی نمی‌کرد. این فیلد به صورت استاتیک از تنظیمات بارگذاری می‌شد و بر اساس نوع صحافی انتخاب شده فیلتر نمی‌شد.

### راه‌حل
دو تابع JavaScript جدید اضافه شده‌اند که گرماژ جلد را بر اساس نوع صحافی انتخاب شده فیلتر می‌کنند:

1. **`updateCoverWeightsAvailability()`**: هنگام تغییر نوع صحافی، با API تماس می‌گیرد و گرماژهای مجاز را دریافت می‌کند
2. **`updateCoverWeightsDropdown()`**: منوی کشویی گرماژ جلد را با گزینه‌های مجاز بازسازی می‌کند

### رفتار جدید
هنگامی که کاربر یک نوع صحافی انتخاب می‌کند، فیلد گرماژ جلد فقط گرماژهای مجاز برای آن نوع صحافی را نمایش می‌دهد (بر اساس محدودیت‌های تعریف شده در ماتریس قیمت‌گذاری).

### نحوه تست
1. به تنظیمات قیمت‌گذاری V2 بروید
2. برای یک قطع کتاب، محدودیت `forbidden_cover_weights` برای یک نوع صحافی تعریف کنید
3. به فرم ثبت سفارش ادمین بروید (از طریق shortcode `[tabesh_admin_order_form]`)
4. قطع کتاب را انتخاب کنید
5. نوع صحافی را انتخاب کنید
6. بررسی کنید که فیلد گرماژ جلد فقط گرماژهای مجاز را نمایش می‌دهد

---

## English Summary

### Problem
After merging PR #166, which connected the admin order form to the V2 pricing engine, the cover paper weight field was not following the allowed parameters from the sequential node (constraint manager). This field was loaded statically from settings and was not filtered based on the selected binding type.

### Solution
Two new JavaScript functions have been added to filter cover weights based on the selected binding type:

1. **`updateCoverWeightsAvailability()`**: Calls the API when binding type changes to fetch allowed cover weights
2. **`updateCoverWeightsDropdown()`**: Rebuilds the cover weight dropdown with allowed options

### New Behavior
When a user selects a binding type, the cover weight field only displays the allowed weights for that binding type (based on restrictions defined in the pricing matrix).

### How to Test
1. Go to V2 pricing settings
2. For a book size, define `forbidden_cover_weights` restrictions for a binding type
3. Go to the admin order form (via shortcode `[tabesh_admin_order_form]`)
4. Select book size
5. Select binding type
6. Verify that the cover weight field only shows allowed weights

---

## Technical Details

### Files Modified
- `assets/js/admin-order-form.js`
  - Line 335: Added call to `updateCoverWeightsAvailability()` when binding type changes
  - Lines 493-522: New function `updateCoverWeightsAvailability()`
  - Lines 524-561: New function `updateCoverWeightsDropdown()`
- `ADMIN_ORDER_FORM_V2_INTEGRATION.md`
  - Updated documentation with new test scenario
  - Updated version history to v1.1
  - Added new functions to code structure section

### API Integration
The fix uses the existing `/wp-json/tabesh/v1/get-allowed-options` endpoint with:
- **Request**: `{ book_size: "...", current_selection: { binding_type: "..." } }`
- **Response**: `{ success: true, data: { allowed_cover_weights: [...] } }`

### Backend Support
The `Tabesh_Constraint_Manager` class already had the logic to filter cover weights (lines 216-231):
```php
if ( $selected_binding_type && isset( $binding_costs[ $selected_binding_type ] ) ) {
    $binding_data = $binding_costs[ $selected_binding_type ];
    $forbidden_cover_weights = $restrictions['forbidden_cover_weights'][ $selected_binding_type ] ?? array();
    
    if ( is_array( $binding_data ) ) {
        foreach ( array_keys( $binding_data ) as $weight ) {
            if ( ! in_array( $weight, $forbidden_cover_weights, true ) ) {
                $result['allowed_cover_weights'][] = array(
                    'weight' => $weight,
                    'slug'   => $this->slugify( $weight ),
                );
            }
        }
    }
}
```

No backend changes were required - only frontend integration was missing.

### Compatibility
- Works with V2 pricing engine only (checks `tabeshAdminOrderForm.v2Enabled`)
- Preserves user's previous selection when possible
- Auto-selects first valid option if no selection exists
- Gracefully handles cases where no weights are allowed

---

## Impact

### Before Fix
- Cover weight field showed all weights from settings regardless of binding type
- Users could select invalid combinations (e.g., a forbidden weight for a specific binding)
- Price calculations might fail or produce incorrect results

### After Fix
- Cover weight field dynamically filters based on binding type
- Only valid combinations are selectable
- Consistent with other cascade filtering (paper → weight → print type, binding → extras)
- Matches behavior of frontend order form (order-form-v2.js)

---

## Related Files

### Similar Implementation
The fix follows the same pattern as:
- `updateExtrasAvailability()` - filters extras based on binding type
- `updatePrintTypeAvailability()` - filters print types based on paper weight

### Affected Templates
- `templates/frontend/admin-order-form.php` - Uses the shortcode with this JS
- `templates/admin/admin-order-creator-modal.php` - NOT affected (uses different JS, no V2 integration yet)

### Configuration
Restrictions are defined in pricing matrix:
```json
{
  "restrictions": {
    "forbidden_cover_weights": {
      "شومیز": ["150", "170"],
      "گالینگور": ["250"]
    }
  }
}
```

---

## Notes

### Scope
This fix applies only to the **admin order form shortcode** (`[tabesh_admin_order_form]`) which uses `assets/js/admin-order-form.js`. The **admin order creator modal** (opened via button in admin dashboard) uses `assets/js/admin-order-creator.js` and does NOT have V2 integration yet, so this fix does not apply there.

### Future Work
To fully integrate V2 cascade filtering into the modal form:
1. Port V2 integration from `admin-order-form.js` to `admin-order-creator.js`
2. Update modal event handlers to use constraint manager API
3. Test all cascade scenarios in modal context

This is tracked as a future enhancement and not part of this fix.
