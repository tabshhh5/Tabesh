# Pricing Validation Layer - Documentation

## Overview

This document describes the enhanced validation layer added to the Tabesh Pricing Engine V2 to prevent calculation errors and improve user experience when pricing matrix configurations are incomplete or invalid.

## Problem Statement

### Issues in Previous Version
- ❌ **Zero Price Display**: When a parameter combination wasn't configured in the pricing matrix, calculations would proceed and return zero or incorrect prices
- ❌ **Poor Error Messages**: Generic error messages didn't help users understand what was wrong
- ❌ **Late Validation**: Errors were only caught during calculation steps, not upfront
- ❌ **Race Conditions**: Multiple simultaneous AJAX requests could interfere with each other

## Solution Architecture

### Two-Stage Validation Process

The new validation system operates in two stages before any price calculation begins:

#### Stage 1: Restriction Validation
Checks if the parameter combination is explicitly forbidden:
- Forbidden paper types for the book size
- Forbidden binding types for the book size
- Forbidden print types for specific paper types
- Forbidden cover weights for specific binding types
- Forbidden extra services for specific binding types

#### Stage 2: Existence Validation
Checks if the parameter combination actually exists in the pricing matrix:
- Paper type exists in configured options
- Paper weight exists for the selected paper type
- Print types (BW/Color) are configured for the paper combination
- Binding type exists in configured options
- Cover weight exists for the selected binding type (if applicable)

## Implementation Details

### New Methods

#### `validate_combination_exists()`

```php
/**
 * Validate that a specific combination exists in the pricing matrix.
 *
 * This method checks if all required pricing data is configured for the
 * requested combination. This prevents returning zero or incorrect prices
 * when parameters are missing from the matrix.
 *
 * @param array  $pricing_matrix Pricing matrix for book size.
 * @param string $book_size Book size.
 * @param string $paper_type Paper type.
 * @param string $paper_weight Paper weight.
 * @param string $binding_type Binding type.
 * @param string $cover_weight Cover weight (optional).
 * @param int    $page_count_bw Black & white page count.
 * @param int    $page_count_color Color page count.
 * @return array Result with 'exists' boolean and 'message' string.
 */
private function validate_combination_exists( ... )
```

**Location**: `includes/handlers/class-tabesh-pricing-engine.php`

**Purpose**: Validates that all required pricing data exists in the matrix before calculation starts.

**Returns**:
- `exists` (boolean): Whether the combination is fully configured
- `message` (string): Helpful error message if combination doesn't exist

### Enhanced `validate_parameters()` Method

The existing `validate_parameters()` method was enhanced to:
1. Accept additional parameters: `$page_count_bw` and `$page_count_color`
2. Call `validate_combination_exists()` after restriction checks
3. Return detailed error messages with helpful guidance

### Improved Error Messages

The new validation layer provides context-aware error messages in Persian:

#### Example 1: Missing Paper Type
```
"نوع کاغذ "گلاسه" برای قطع A5 در سیستم قیمت‌گذاری تنظیم نشده است. لطفا با مدیر سیستم تماس بگیرید."
```
Translation: "Paper type 'Glossy' is not configured for A5 size. Please contact the system administrator."

#### Example 2: Missing Paper Weight
```
"گرماژ 100 برای کاغذ تحریر در قطع A5 تنظیم نشده است. لطفا گرماژ دیگری را انتخاب کنید."
```
Translation: "Weight 100 for writing paper in A5 size is not configured. Please select another weight."

#### Example 3: Missing Cover Weight (with helpful list)
```
"گرماژ جلد 400 برای صحافی شومیز در قطع A5 تنظیم نشده است. گرماژهای موجود: 200، 250، 300، 350"
```
Translation: "Cover weight 400 for perfect binding in A5 is not configured. Available weights: 200, 250, 300, 350"

## AJAX Request Cancellation

### Problem
Multiple rapid price calculations (e.g., user changing form values quickly) could create race conditions where older responses overwrite newer ones.

### Solution
Implemented request cancellation in all JavaScript files that call the pricing API:

1. **frontend.js** - Customer order form
2. **admin-order-creator.js** - Admin order creation modal
3. **admin-order-form.js** - Admin order form shortcode

### Implementation Pattern

```javascript
// Store current request reference
this.priceCalculationRequest = null;

calculatePrice() {
    // Cancel any pending request
    if (this.priceCalculationRequest && this.priceCalculationRequest.abort) {
        console.log('Cancelling previous price calculation request');
        this.priceCalculationRequest.abort();
        this.priceCalculationRequest = null;
    }

    // Make new request
    this.priceCalculationRequest = $.ajax({
        // ... AJAX configuration
        error: function(xhr, status) {
            // Ignore aborted requests
            if (status === 'abort') {
                return;
            }
            // Handle actual errors
        },
        complete: function() {
            // Clear the request reference
            this.priceCalculationRequest = null;
        }
    });
}
```

## Validation Flow Diagram

```
User submits form
       ↓
Sanitize inputs
       ↓
Check required fields (quantity > 0, page count > 0)
       ↓
Get pricing matrix for book size
       ↓
[STAGE 1: Restriction Validation]
  ├─ Paper type forbidden? → Error
  ├─ Binding type forbidden? → Error
  ├─ Print type forbidden for paper? → Error
  └─ Cover weight forbidden for binding? → Error
       ↓
[STAGE 2: Existence Validation]
  ├─ Paper type exists in matrix? → Error
  ├─ Paper weight exists for paper type? → Error
  ├─ BW print configured (if needed)? → Error
  ├─ Color print configured (if needed)? → Error
  ├─ Binding type exists in matrix? → Error
  └─ Cover weight exists for binding? → Error (with suggestions)
       ↓
✓ All validations passed
       ↓
Proceed with price calculation
```

## Benefits

### 1. Better User Experience
- ✅ Clear, actionable error messages
- ✅ Suggestions for alternative options (e.g., available weights)
- ✅ Early error detection before calculation

### 2. Improved Data Integrity
- ✅ Prevents zero price display
- ✅ Ensures all pricing data is configured before use
- ✅ Validates cross-dependencies (e.g., binding + cover weight)

### 3. Easier Troubleshooting
- ✅ Specific error messages identify missing configuration
- ✅ Administrators can quickly fix pricing matrix gaps
- ✅ Debug logging shows validation steps

### 4. Performance
- ✅ Request cancellation prevents wasted calculations
- ✅ Early validation fails fast
- ✅ No processing of invalid combinations

## Testing Scenarios

### Edge Cases to Test

1. **Missing Paper Type**
   - Select a book size with incomplete paper type configuration
   - Expected: Specific error message naming the missing paper type

2. **Missing Paper Weight**
   - Select a paper type with incomplete weight configuration
   - Expected: Error message suggesting available weights

3. **Missing Print Type**
   - Configure only BW prices, try to order with color pages
   - Expected: Error message specifying color printing is not configured

4. **Missing Binding Type**
   - Select a binding type not configured for the book size
   - Expected: Error message with binding type and book size

5. **Missing Cover Weight**
   - Select a cover weight not configured for the binding type
   - Expected: Error message with list of available weights

6. **Rapid Form Changes**
   - Quickly change multiple form fields in succession
   - Expected: Only the most recent calculation is displayed, old requests are cancelled

## Backward Compatibility

### Maintained Compatibility
- ✅ No changes to public API methods
- ✅ No changes to database schema
- ✅ No changes to pricing matrix structure
- ✅ Existing validation logic preserved
- ✅ All new code is additive (no breaking changes)

### Safe Failure
If validation fails:
- System returns user-friendly error message
- No price calculation is attempted
- No zero prices are displayed
- User is guided to fix the issue

## Code Quality

### Standards Compliance
- ✅ WordPress Coding Standards (WPCS)
- ✅ PHP CodeSniffer validation
- ✅ PHPDoc comments for all methods
- ✅ Translatable strings with proper i18n

### Security
- ✅ All inputs sanitized before validation
- ✅ Nonce verification for AJAX requests (existing)
- ✅ Permission checks for pricing calculations (existing)
- ✅ No SQL injection vulnerabilities

## Future Enhancements

Potential improvements for future versions:

1. **Validation API Endpoint**
   - Add dedicated REST endpoint for real-time validation
   - Enable client-side validation before submission

2. **Configuration Wizard**
   - Guide administrators through pricing matrix setup
   - Highlight missing configurations

3. **Validation Reports**
   - Admin dashboard showing incomplete configurations
   - Automated alerts for missing pricing data

4. **Smart Suggestions**
   - Automatically suggest similar configured options
   - Machine learning for price estimation (advanced)

## References

- **Main Implementation**: `includes/handlers/class-tabesh-pricing-engine.php`
- **Frontend JS**: `assets/js/frontend.js`
- **Admin Order Creator**: `assets/js/admin-order-creator.js`
- **Admin Order Form**: `assets/js/admin-order-form.js`
- **Related Documentation**: `PRICING_ENGINE_V2.md`

## Changelog

### Version 1.0.4
- Added `validate_combination_exists()` method
- Enhanced `validate_parameters()` with page count parameters
- Implemented AJAX request cancellation
- Improved error messages with helpful suggestions
- Added comprehensive validation flow

---

**Last Updated**: 2025-12-18
**Author**: Tabesh Development Team
**Status**: Implemented and Tested
