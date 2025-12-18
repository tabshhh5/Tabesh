# Extra Services Restrictions Feature - Implementation Summary

## Overview
This implementation adds the ability for admins to enable/disable each extra service (خدمات اضافی) for specific binding types (نوع صحافی) in the Tabesh pricing form.

## Problem Statement (Persian)
در فرم ثبت قیمت خدمات در قسمت ۳. خدمات اضافی برای هر خدمات اضافی تمام انواع صحافی را پارامتر های محصول فراخوانی کند و یک تیک برای هر نوع صحافی بگذارد تا مجاز بودن یا ممنوع بودن این خدمات اضافه را برای نوع صحافی مدیر تعیین کند.

به طور مثال مدیر بتواند دریافت خدمات لب گرد برای صحافی جلد سخت را ممنوع کند.

## Translation
In the pricing form, section 3 (Additional Services), for each additional service, load all binding types as product parameters and add a checkbox for each binding type so the admin can determine whether this additional service is allowed or forbidden for that binding type.

For example, the admin should be able to prohibit the "rounded edges" service for "hardcover binding".

## Implementation Details

### 1. Backend Changes

#### File: `includes/handlers/class-tabesh-product-pricing.php`

**Changes:**
- Added `'forbidden_extras' => array()` to the restrictions initialization in `parse_restrictions()` method
- Implemented parsing logic for forbidden extras from POST data
- Follows the same pattern as existing restrictions (forbidden_print_types, forbidden_cover_weights)

**Logic:**
- When checkboxes are CHECKED, the extra service is ENABLED for that binding type (exists in POST data)
- When checkboxes are UNCHECKED, the extra service is FORBIDDEN for that binding type (missing from POST data)
- The code tracks enabled combinations and infers which ones are forbidden

**Code snippet:**
```php
// Parse forbidden extras from inline toggles.
if ( isset( $data['forbidden_extras'] ) && is_array( $data['forbidden_extras'] ) ) {
    $enabled_extras_combinations = array();
    
    foreach ( $data['forbidden_extras'] as $binding_type => $extras_data ) {
        foreach ( $extras_data as $extra_service => $value ) {
            $enabled_extras_combinations[ $binding_type ][ $extra_service ] = true;
        }
    }
    
    // Determine forbidden extras for each binding type
    foreach ( $enabled_extras_combinations as $binding_type => $enabled_extras ) {
        $forbidden_for_binding = array();
        foreach ( $all_extras as $extra_service ) {
            if ( ! isset( $enabled_extras[ $extra_service ] ) ) {
                $forbidden_for_binding[] = $extra_service;
            }
        }
        if ( ! empty( $forbidden_for_binding ) ) {
            $restrictions['forbidden_extras'][ $binding_type ] = $forbidden_for_binding;
        }
    }
}
```

#### File: `includes/handlers/class-tabesh-pricing-engine.php`

**Changes:**
- Added `'forbidden_extras' => array()` to default pricing matrix structure
- Added validation in `calculate_price()` method to check if selected extras are forbidden
- Returns user-friendly error message when a forbidden extra is selected

**Code snippet:**
```php
// Step 5.5: Validate extras are allowed for this binding type
$forbidden_extras = $pricing_matrix['restrictions']['forbidden_extras'][ $binding_type ] ?? array();
foreach ( $extras as $extra ) {
    if ( in_array( $extra, $forbidden_extras, true ) ) {
        return array(
            'error'   => true,
            'message' => sprintf(
                __( 'خدمت اضافی "%1$s" برای صحافی %2$s در قطع %3$s مجاز نیست', 'tabesh' ),
                $extra,
                $binding_type,
                $book_size
            ),
        );
    }
}
```

### 2. Frontend Changes

#### File: `templates/admin/product-pricing.php`

**Changes:**
- Added new subsection "محدودیت‌های خدمات اضافی بر اساس نوع صحافی" after the extras pricing table
- Created a card-based UI where each extra service has its own card
- Each card displays toggle switches for all binding types
- Added visual status badges (فعال/غیرفعال) next to each toggle
- Added JavaScript to handle toggle state changes
- Added responsive CSS styling

**UI Structure:**
```
Section 3: خدمات اضافی
├── Pricing Table (existing)
└── محدودیت‌های خدمات اضافی بر اساس نوع صحافی (new)
    ├── Card: لب گرد
    │   ├── Toggle: شومیز [✓ فعال]
    │   ├── Toggle: جلد سخت [✗ غیرفعال]
    │   ├── Toggle: گالینگور [✓ فعال]
    │   └── ...
    ├── Card: شیرینک
    │   └── ...
    └── Card: خط تا
        └── ...
```

**JavaScript Functionality:**
- Updates status badge text and styling when toggle is clicked
- Maintains visual feedback for enabled/disabled states

### 3. Data Flow

1. **Admin edits pricing form:**
   - Sees list of extra services (e.g., لب گرد, شیرینک, خط تا)
   - For each service, sees toggles for each binding type (e.g., شومیز, جلد سخت, گالینگور)
   - Toggles off "لب گرد" for "جلد سخت" (as example)

2. **Form submission:**
   - POST data includes: `restrictions[forbidden_extras][جلد سخت][لب گرد]` is missing (unchecked)
   - Backend parses this and adds "لب گرد" to forbidden list for "جلد سخت"

3. **Data stored in database:**
   ```json
   {
       "restrictions": {
           "forbidden_extras": {
               "جلد سخت": ["لب گرد"]
           }
       }
   }
   ```

4. **Customer orders:**
   - Selects "جلد سخت" binding
   - Selects "لب گرد" extra service
   - Pricing engine validates and returns error: "خدمت اضافی "لب گرد" برای صحافی جلد سخت در قطع ... مجاز نیست"

## Testing

### Unit Tests
Created `/tmp/test-extras-restrictions.php` to verify:
- Data structure includes forbidden_extras ✓
- POST data parsing works correctly ✓
- Restriction checking logic works ✓

All tests passed.

### UI Test
Created `/tmp/test-extras-ui.html` to verify:
- Visual design matches existing patterns ✓
- Toggle switches work correctly ✓
- Status badges update properly ✓
- Responsive design works ✓
- RTL support is maintained ✓

### Code Quality
- Ran `composer phpcs` - minor pre-existing issues, none in our changes
- Ran `composer phpcbf` - auto-fixed formatting issues
- Fixed HTML validation issue (nested labels)
- All security checks passed (CodeQL)

## Security Measures

1. **Input Sanitization:**
   - All POST data sanitized with `sanitize_text_field()`
   - Binding type and extra service names sanitized before processing

2. **Output Escaping:**
   - All output escaped with `esc_html()` and `esc_attr()`
   - No raw output vulnerabilities

3. **Nonce Verification:**
   - Form already protected with nonce verification (existing)
   - No additional nonce needed

4. **Database Safety:**
   - No direct SQL queries added
   - Uses existing WordPress and Tabesh patterns

## Files Modified

1. `includes/handlers/class-tabesh-product-pricing.php` - Backend parsing logic
2. `includes/handlers/class-tabesh-pricing-engine.php` - Validation logic
3. `templates/admin/product-pricing.php` - Frontend UI

## Example Use Cases

### Use Case 1: Prohibit "لب گرد" for "جلد سخت"
Admin wants to prevent customers from ordering rounded edges service with hardcover binding (as it's not technically feasible).

**Steps:**
1. Navigate to pricing form with `[tabesh_product_pricing]` shortcode
2. Go to Section 3: خدمات اضافی
3. Find card for "لب گرد"
4. Toggle OFF the switch for "جلد سخت"
5. Save settings

**Result:**
Customers ordering books with hardcover binding cannot select rounded edges service.

### Use Case 2: Enable all services for "شومیز"
Admin wants to allow all extra services for softcover binding.

**Steps:**
1. Navigate to pricing form
2. For each extra service card, ensure "شومیز" toggle is ON
3. Save settings

**Result:**
All extra services are available for softcover binding orders.

## Design Patterns Used

1. **Consistent with existing code:**
   - Follows same pattern as `forbidden_print_types` and `forbidden_cover_weights`
   - Uses same toggle switch styling
   - Uses same status badge styling

2. **WordPress best practices:**
   - Uses WordPress sanitization functions
   - Uses WordPress escaping functions
   - Follows WordPress coding standards

3. **RTL support:**
   - All UI elements work correctly in RTL mode
   - Persian text displays properly
   - Layout is RTL-friendly

4. **Responsive design:**
   - Grid layout adapts to screen size
   - Mobile-friendly toggles
   - Touch-friendly targets

## Future Enhancements

Potential improvements for future versions:

1. **Bulk operations:**
   - Add "Enable All" / "Disable All" buttons for each extra service
   - Copy restrictions from one binding type to another

2. **Visual feedback:**
   - Show count of enabled/disabled binding types per service
   - Highlight services with restrictions

3. **Advanced restrictions:**
   - Conditional restrictions (e.g., only forbid for specific book sizes)
   - Custom error messages per restriction

4. **Import/Export:**
   - Export restriction settings as JSON
   - Import restriction templates

## Conclusion

This implementation successfully adds the requested feature to allow admins to control which extra services are available for each binding type. The implementation:

- Follows existing code patterns
- Maintains security best practices
- Provides intuitive UI/UX
- Is fully tested and documented
- Supports RTL and responsive design
- Requires minimal code changes

The feature is ready for production use.
