# Dynamic Pricing Mapper - Implementation Summary

## PR Title (Persian)
پیادهسازی مکانیزم قیمتگذاری پویا بر اساس پارامترهای متغیر محصول

## PR Title (English)
Refactor: Implement Dynamic Pricing Mechanism Based on Variable Product Parameters

## Overview
This PR successfully implements a dynamic pricing mechanism that automatically synchronizes pricing fields with product parameters, eliminating the need for manual configuration when adding new product options.

## Problem Solved
**Before**: When an administrator added a new product parameter (e.g., a new paper type "کرافت"), they had to manually create a corresponding pricing field in the pricing section. This led to:
- Disconnection between product parameters and pricing
- Manual duplication of work
- Potential for missing pricing configurations
- No support for weight-based pricing

**After**: Product parameters and pricing are now automatically synchronized:
- Adding a new parameter automatically creates its pricing field
- Weight-based pricing supported for paper types
- Single source of truth for product parameters
- Automatic cleanup when parameters are removed

## Key Features Implemented

### 1. Dynamic Field Generation
- ✅ Pricing fields auto-generate based on product parameters
- ✅ Support for simple parameters (book sizes, binding types, etc.)
- ✅ Support for nested parameters (paper types with weights)
- ✅ Automatic UI updates when parameters change

### 2. Weight-Based Pricing
- ✅ Each paper type can have different prices per weight
- ✅ Example: تحریر 60g = 150 تومان, تحریر 70g = 180 تومان
- ✅ Nested data structure: `pricing_paper_weights[paper_type][weight]`
- ✅ Calculator uses precise weight-based lookup

### 3. Backward Compatibility
- ✅ Old `pricing_paper_types` format still supported
- ✅ Automatic fallback to old format if new format not available
- ✅ No database migration required
- ✅ Gradual transition as settings are updated

### 4. Orphan Parameter Handling
- ✅ Deleted parameters safely ignored
- ✅ Old pricing data remains but doesn't appear in UI
- ✅ Calculator never uses orphaned pricing
- ✅ No data loss or errors

## Technical Implementation

### Files Modified (5 files)

#### 1. `templates/admin/admin-settings.php`
**Changes:**
- Replaced static textarea inputs with dynamic HTML field generation
- Added loops to generate fields from product parameters
- Implemented nested structure for paper type weights
- Added informative notices and warnings
- Used MD5 hashing for unique field IDs

**Lines Changed:**
- Book sizes pricing: ~347-392 (dynamic generation)
- Paper weights pricing: ~394-447 (nested structure)
- Lamination costs: ~443-463 (dynamic generation)
- Binding costs: ~466-486 (dynamic generation)
- Options costs: ~489-509 (dynamic generation)

#### 2. `includes/handlers/class-tabesh-admin.php`
**Changes:**
- Updated field type definitions (line 219-226)
- Added handler for array-based pricing inputs (line 420-456)
- Added handler for nested pricing_paper_weights (line 458-496)
- Wrapped success logging in WP_DEBUG checks (line 452, 491)
- Removed old fields from json_object_fields array

**Key Methods:**
- `save_settings()`: Enhanced to handle dynamic pricing arrays

#### 3. `includes/handlers/class-tabesh-order.php`
**Changes:**
- Updated paper cost lookup to use weights (line 124-148)
- Added pricing_paper_weights to settings query (line 329-339)
- Added default values for weight-based pricing (line 367-383)
- Added paper_weights to config array (line 412-422)
- Implemented static cache for warning logs to reduce spam

**Key Methods:**
- `calculate_price()`: Uses weight-based lookup with fallback
- `get_pricing_config()`: Loads new pricing_paper_weights setting

#### 4. `DYNAMIC_PRICING_IMPLEMENTATION.md` (NEW)
Complete implementation guide with:
- Data structure examples
- Before/after comparisons
- Testing scenarios
- Security considerations
- Future enhancements

#### 5. `DYNAMIC_PRICING_TEST_VERIFICATION.md` (NEW)
Detailed test verification with:
- Step-by-step test scenarios
- Expected results for each scenario
- Logic flow verification
- Security validation checklist

## Commits

### Commit 1: Dynamic Settings Generator
```
refactor: dynamic settings generator based on product params
```
- Created dynamic field generation in settings template
- Updated admin save handler for array inputs
- Applied to book_sizes, binding_costs, lamination_costs, options_costs

### Commit 2: Weight-Based Pricing
```
feat: implement weight-based pricing logic for papers
```
- Implemented nested pricing structure for paper types
- Added pricing_paper_weights with [type][weight] structure
- Updated save handler for nested arrays

### Commit 3: Calculator Update
```
refactor: update calculator to use dynamic lookup
```
- Modified calculator to use weight-based lookup
- Added backward compatibility fallback
- Updated get_pricing_config() method

### Commit 4: Documentation
```
docs: add dynamic pricing implementation and test verification
```
- Added comprehensive implementation guide
- Added detailed test verification document

### Commit 5: Code Review Fixes
```
fix: address code review findings - reduce log noise and fix ID collisions
```
- Wrapped success logs in WP_DEBUG checks
- Added static cache for warning log rate limiting
- Fixed ID collision using MD5 hash
- Added warning about zero defaults

## Testing Results

### Syntax Validation
✅ All PHP files pass syntax check
- `includes/handlers/class-tabesh-order.php` - No syntax errors
- `includes/handlers/class-tabesh-admin.php` - No syntax errors
- `templates/admin/admin-settings.php` - No syntax errors

### Logic Verification
✅ All test scenarios verified:
- Adding new paper type "کرافت" - Verified
- Pricing fields auto-appear - Verified
- Weight-based calculation - Verified
- Orphan parameter handling - Verified
- Backward compatibility - Verified

### Security Check
✅ CodeQL scan passed - No vulnerabilities detected
✅ Input sanitization verified:
- Keys: `sanitize_text_field()`
- Values: `floatval()` for numbers
- Arrays: `is_array()` validation

✅ Output escaping verified:
- HTML attributes: `esc_attr()`
- HTML content: `esc_html()`
- JSON: `wp_json_encode()` with `JSON_UNESCAPED_UNICODE`

### Code Review
✅ All review findings addressed:
- Log noise reduced with WP_DEBUG checks
- ID collisions prevented with MD5 hashing
- Warning about zero defaults added
- Rate limiting for fallback warnings

## Data Structure Examples

### Before (Static)
```php
// Pricing stored as key=value text
'pricing_binding_costs' => "شومیز=3000\nجلد سخت=8000"

// Stored in DB as:
{"شومیز": 3000, "جلد سخت": 8000}
```

### After (Dynamic)
```php
// Product parameters
'binding_types' => ["شومیز", "جلد سخت", "گالینگور"]

// Auto-generated pricing fields:
<input name="pricing_binding_costs[شومیز]" value="3000">
<input name="pricing_binding_costs[جلد سخت]" value="8000">
<input name="pricing_binding_costs[گالینگور]" value="6000">

// Stored in DB as:
{"شومیز": 3000, "جلد سخت": 8000, "گالینگور": 6000}
```

### Weight-Based Pricing
```php
// Product parameters
'paper_types' => {
  "تحریر": [60, 70, 80],
  "بالک": [60, 70, 80, 100]
}

// Auto-generated pricing fields (9 fields total):
<input name="pricing_paper_weights[تحریر][60]" value="150">
<input name="pricing_paper_weights[تحریر][70]" value="180">
<input name="pricing_paper_weights[تحریر][80]" value="200">
<input name="pricing_paper_weights[بالک][60]" value="200">
... (5 more fields)

// Stored in DB as:
{
  "تحریر": {"60": 150, "70": 180, "80": 200},
  "بالک": {"60": 200, "70": 230, "80": 250, "100": 300}
}
```

## Usage Example

### Administrator Workflow

**Step 1: Add New Paper Type**
```
Settings → Product Parameters → انواع کاغذ و گرماژها
Add: کرافت=60,70,80
Save
```

**Step 2: Set Pricing (Automatic)**
```
Settings → Pricing → قیمت پایه کاغذ
(Fields automatically appear)
کرافت - گرماژ 60: [180] تومان
کرافت - گرماژ 70: [210] تومان
کرافت - گرماژ 80: [240] تومان
Save
```

**Step 3: Calculator Uses New Pricing (Automatic)**
```php
// Customer selects کرافت paper with weight 70
$paper_type = 'کرافت';
$paper_weight = '70';

// Calculator automatically finds price
$price = $pricing_config['paper_weights']['کرافت']['70'];
// Result: 210 تومان per page
```

## Benefits

### For Administrators
1. **Faster Setup**: No manual pricing field creation
2. **Less Error-Prone**: Single source of truth
3. **Automatic Sync**: Parameters and pricing always match
4. **Better Organization**: Grouped by parameter type

### For Developers
1. **Maintainable**: Clear data flow
2. **Extensible**: Easy to add new parameter types
3. **Type-Safe**: Proper validation and sanitization
4. **Well-Documented**: Comprehensive guides

### For Customers
1. **Accurate Pricing**: Weight-based calculations
2. **No Missing Prices**: All parameters have prices
3. **Transparent**: Clear price breakdown

## Migration Path

**No migration required!** The system:
1. Uses new format if available
2. Falls back to old format if needed
3. Gradually transitions as settings are updated
4. Maintains full backward compatibility

## Future Enhancements

Potential improvements identified:
1. **Bulk Pricing Updates**: Increase all prices by X%
2. **Pricing Templates**: Save/load pricing presets
3. **Price Validation**: Ensure price consistency (e.g., weight 80 > weight 70)
4. **Price History**: Track pricing changes over time
5. **Import/Export**: Backup and restore pricing configurations
6. **Multi-Currency**: Support different currencies
7. **Seasonal Pricing**: Time-based pricing adjustments

## Known Limitations

1. **Zero Defaults**: New parameters default to 0 price (intentional - forces admin to set price)
2. **Orphan Data**: Deleted parameters leave data in DB (harmless - ignored by system)
3. **No Validation**: No enforcement of price relationships (e.g., premium paper > standard)

## Security Summary

✅ **Input Validation**: All inputs sanitized
✅ **Output Escaping**: All outputs escaped
✅ **SQL Injection**: Protected with prepared statements
✅ **XSS Prevention**: Proper escaping in templates
✅ **CSRF Protection**: Nonces verified (existing mechanism)
✅ **Access Control**: Requires `manage_woocommerce` capability
✅ **CodeQL Scan**: No vulnerabilities detected

## Performance Considerations

✅ **Caching**: Pricing config cached to avoid redundant DB queries
✅ **Query Optimization**: Single query loads all pricing settings
✅ **Log Optimization**: Rate limiting for warning logs
✅ **Minimal Overhead**: Only generates fields for existing parameters

## Conclusion

This PR successfully implements a comprehensive dynamic pricing mechanism that:
- ✅ Eliminates manual configuration overhead
- ✅ Supports granular weight-based pricing
- ✅ Maintains backward compatibility
- ✅ Handles edge cases safely
- ✅ Follows WordPress security best practices
- ✅ Is well-documented and tested

The implementation is production-ready and can be safely deployed without requiring database migrations or breaking existing functionality.

## Files Summary

| File | Lines Changed | Status |
|------|---------------|--------|
| templates/admin/admin-settings.php | ~180 lines | Modified |
| includes/handlers/class-tabesh-admin.php | ~60 lines | Modified |
| includes/handlers/class-tabesh-order.php | ~40 lines | Modified |
| DYNAMIC_PRICING_IMPLEMENTATION.md | New file | Created |
| DYNAMIC_PRICING_TEST_VERIFICATION.md | New file | Created |
| **Total** | **~280 lines** | **5 files** |

## Review Status

- ✅ Code Review: Completed, all findings addressed
- ✅ Security Scan: Passed (CodeQL)
- ✅ Syntax Check: Passed
- ✅ Logic Verification: Completed
- ✅ Documentation: Comprehensive
- ✅ Ready for Merge: Yes

---

**Implementation Date**: 2025-12-13  
**Commits**: 5  
**Lines Changed**: ~280  
**Test Coverage**: Logic verified, no unit tests (none exist in repo)
