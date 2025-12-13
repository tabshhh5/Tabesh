# Dynamic Pricing Implementation Guide

## Overview
This document describes the implementation of the dynamic pricing mechanism that automatically synchronizes pricing fields with product parameters in the Tabesh plugin.

## Problem Statement
Previously, there was no direct connection between product parameters (defined in the Product Parameters tab) and pricing fields (defined in the Pricing tab). When an administrator added a new paper type, binding type, or other parameter, they had to manually add the corresponding pricing field.

## Solution
The new implementation creates a dynamic mapping where:
1. Pricing fields are automatically generated based on product parameters
2. Each pricing field uses the same key/slug as the product parameter
3. The calculator uses parameter-based lookup instead of static keys

## Key Changes

### 1. Product Parameters → Pricing Fields Mapping

| Product Parameter | Pricing Field | Structure |
|------------------|---------------|-----------|
| `book_sizes` | `pricing_book_sizes` | Simple array → key-value object |
| `binding_types` | `pricing_binding_costs` | Simple array → key-value object |
| `lamination_types` | `pricing_lamination_costs` | Simple array → key-value object |
| `extras` | `pricing_options_costs` | Simple array → key-value object |
| `paper_types` | `pricing_paper_weights` | Nested object → nested object |

### 2. Data Structures

#### Old Format (Static)
```php
// Settings
'pricing_book_sizes' => 'A5=1\nA4=1.5\nرقعی=1.1'

// Stored as
array(
    'A5' => 1,
    'A4' => 1.5,
    'رقعی' => 1.1
)
```

#### New Format (Dynamic)
```php
// Product Parameters
'book_sizes' => ['A5', 'A4', 'رقعی', 'وزیری']

// Auto-generated pricing fields:
<input name="pricing_book_sizes[A5]" value="1">
<input name="pricing_book_sizes[A4]" value="1.5">
<input name="pricing_book_sizes[رقعی]" value="1.1">
<input name="pricing_book_sizes[وزیری]" value="1.3">

// Stored as
array(
    'A5' => 1,
    'A4' => 1.5,
    'رقعی' => 1.1,
    'وزیری' => 1.3
)
```

### 3. Weight-Based Pricing for Paper Types

#### Old Format
```php
'pricing_paper_types' => array(
    'تحریر' => 200,  // Single price per type
    'بالک' => 250
)
```

#### New Format
```php
// Product Parameters
'paper_types' => array(
    'تحریر' => [60, 70, 80],
    'بالک' => [60, 70, 80, 100]
)

// Auto-generated pricing fields:
<input name="pricing_paper_weights[تحریر][60]" value="150">
<input name="pricing_paper_weights[تحریر][70]" value="180">
<input name="pricing_paper_weights[تحریر][80]" value="200">
<input name="pricing_paper_weights[بالک][60]" value="200">
...

// Stored as
'pricing_paper_weights' => array(
    'تحریر' => array(
        '60' => 150,
        '70' => 180,
        '80' => 200
    ),
    'بالک' => array(
        '60' => 200,
        '70' => 230,
        '80' => 250,
        '100' => 300
    )
)
```

### 4. Calculator Updates

#### Old Code
```php
$paper_base_cost = $pricing_config['paper_types'][$paper_type] ?? 250;
```

#### New Code
```php
// Try weight-based pricing first
if (isset($pricing_config['paper_weights'][$paper_type][$paper_weight])) {
    $paper_base_cost = $pricing_config['paper_weights'][$paper_type][$paper_weight];
} else {
    // Fallback to old format for backward compatibility
    $paper_base_cost = $pricing_config['paper_types'][$paper_type] ?? 250;
}
```

## Testing Scenarios

### Scenario 1: Add New Paper Type
1. Go to Settings → Product Parameters
2. Add new paper type "کرافت" with weights [60, 80, 100]
3. Save settings
4. Go to Settings → Pricing
5. **Expected Result**: Three new price input fields appear automatically:
   - کرافت - گرماژ 60
   - کرافت - گرماژ 80
   - کرافت - گرماژ 100

### Scenario 2: Add New Binding Type
1. Go to Settings → Product Parameters
2. Add new binding type "زین دوزی"
3. Save settings
4. Go to Settings → Pricing
5. **Expected Result**: New price input field "زین دوزی" appears in binding costs section

### Scenario 3: Remove Parameter
1. Go to Settings → Product Parameters
2. Remove a paper type (e.g., delete one of the weights)
3. Save settings
4. Go to Settings → Pricing
5. **Expected Result**: Corresponding pricing field disappears
6. **Orphan Handling**: Old pricing data remains in database but is ignored

### Scenario 4: Price Calculation
1. Create an order with:
   - Book size: رقعی
   - Paper type: تحریر
   - Paper weight: 70
   - Binding: شومیز
2. **Expected Result**: 
   - Calculator uses `pricing_book_sizes['رقعی']` for size multiplier
   - Calculator uses `pricing_paper_weights['تحریر']['70']` for paper cost
   - Calculator uses `pricing_binding_costs['شومیز']` for binding cost

## Files Modified

1. **templates/admin/admin-settings.php**
   - Lines 347-369: Dynamic book sizes pricing fields
   - Lines 372-395: Dynamic paper weights pricing fields (nested)
   - Lines 443-463: Dynamic lamination costs pricing fields
   - Lines 466-486: Dynamic binding costs pricing fields
   - Lines 489-509: Dynamic options costs pricing fields

2. **includes/handlers/class-tabesh-admin.php**
   - Lines 219-226: Updated field type definitions
   - Lines 420-467: Added handlers for array-based pricing inputs
   - Lines 469-498: Added handler for nested pricing_paper_weights

3. **includes/handlers/class-tabesh-order.php**
   - Lines 124-141: Updated paper cost lookup to use weights
   - Lines 329-339: Added pricing_paper_weights to query
   - Lines 367-383: Added default values for paper_weights
   - Lines 412-422: Added paper_weights to config array

## Backward Compatibility

The implementation maintains backward compatibility:
- Old `pricing_paper_types` format is still supported as fallback
- If weight-based pricing is not found, falls back to type-based pricing
- Existing data continues to work without migration

## Security Considerations

All inputs are properly sanitized:
- `sanitize_text_field()` for keys
- `floatval()` for numeric values
- `wp_json_encode()` with `JSON_UNESCAPED_UNICODE` for storage
- Array validation before processing

## Benefits

1. **Automatic Synchronization**: Adding a parameter automatically creates pricing field
2. **Reduced Errors**: No manual field creation needed
3. **Granular Control**: Weight-based pricing for paper types
4. **Better UX**: Clear grouping and organization of pricing fields
5. **Maintainability**: Single source of truth for parameters

## Migration Notes

No database migration is required. The system will:
1. Use new format if available
2. Fall back to old format if needed
3. Gradually transition as settings are updated

## Future Enhancements

Potential improvements:
1. Bulk pricing updates (e.g., increase all prices by 10%)
2. Pricing templates/presets
3. Price validation rules (e.g., weight 80 must be > weight 70)
4. Price history/versioning
5. Import/export pricing configurations
