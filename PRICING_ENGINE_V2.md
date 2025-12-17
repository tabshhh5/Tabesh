# Matrix-Based Pricing Engine V2 - Documentation

## Overview

This document describes the new matrix-based pricing engine (V2) implemented for the Tabesh plugin. This engine replaces the complex multiplier-based system with a clean, industry-standard approach.

## Key Improvements

### Old System (V1) Problems
- ❌ Complex multipliers (book size multipliers, paper base costs, etc.)
- ❌ Disconnected parameters that don't reflect real printing industry
- ❌ Difficult to manage and update prices
- ❌ High risk of calculation errors
- ❌ No way to restrict invalid parameter combinations

### New System (V2) Benefits
- ✅ **Matrix-based pricing** - independent pricing for each book size
- ✅ **Unified per-page costs** - combines paper + print costs directly
- ✅ **Size-specific costs** - binding and cover costs per book size
- ✅ **Parameter restrictions** - forbid invalid combinations
- ✅ **Easy to manage** - modern admin interface
- ✅ **Transparent** - clear pricing structure

## Architecture

### Core Classes

#### 1. `Tabesh_Pricing_Engine`
Location: `includes/handlers/class-tabesh-pricing-engine.php`

The main pricing calculation engine that implements matrix-based pricing.

**Key Methods:**
- `calculate_price($params)` - Calculate price using new engine
- `get_pricing_matrix($book_size)` - Get pricing matrix for specific book size
- `validate_parameters()` - Check if parameter combination is allowed
- `save_pricing_matrix()` - Save pricing configuration to database

#### 2. `Tabesh_Product_Pricing`
Location: `includes/handlers/class-tabesh-product-pricing.php`

Admin interface handler for the `[tabesh_product_pricing]` shortcode.

**Key Methods:**
- `render()` - Render the pricing management interface
- `handle_save_pricing()` - Process form submissions
- `enable_pricing_engine_v2()` - Enable the new pricing engine
- `disable_pricing_engine_v2()` - Revert to legacy engine

### Database Structure

Pricing data is stored in `wp_tabesh_settings` table with these keys:

- `pricing_engine_v2_enabled` - Flag to enable/disable V2 engine (values: '0' or '1')
- `pricing_matrix_A5` - Pricing matrix for A5 book size
- `pricing_matrix_A4` - Pricing matrix for A4 book size
- `pricing_matrix_رقعی` - Pricing matrix for رقعی book size
- ... (one key per book size)

### Pricing Matrix Structure

Each book size has its own pricing matrix stored as JSON:

```json
{
  "book_size": "A5",
  "page_costs": {
    "تحریر": {
      "60": {
        "bw": 350,
        "color": 950
      },
      "70": {
        "bw": 380,
        "color": 980
      },
      "80": {
        "bw": 400,
        "color": 1000
      }
    },
    "بالک": {
      "60": { "bw": 400, "color": 1000 },
      "70": { "bw": 430, "color": 1030 },
      "80": { "bw": 450, "color": 1050 },
      "100": { "bw": 500, "color": 1100 }
    }
  },
  "binding_costs": {
    "شومیز": 3000,
    "جلد سخت": 8000,
    "گالینگور": 6000,
    "سیمی": 2000
  },
  "cover_cost": 8000,
  "extras_costs": {
    "لب گرد": {
      "price": 1000,
      "type": "per_unit",
      "step": 0
    },
    "خط تا": {
      "price": 500,
      "type": "per_unit",
      "step": 0
    }
  },
  "profit_margin": 0.0,
  "restrictions": {
    "forbidden_paper_types": [],
    "forbidden_binding_types": [],
    "forbidden_print_types": {}
  },
  "quantity_constraints": {
    "minimum_quantity": 10,
    "maximum_quantity": 10000,
    "quantity_step": 10
  }
}
```

**New in v1.1:** Quantity constraints allow you to set minimum, maximum, and step values for order quantities on a per-book-size basis. This provides finer control over allowed order quantities based on production capabilities for each book size.

## Pricing Calculation Logic

### Old Formula (V1)
```
FinalPrice = (((PaperBaseCost + PrintCost) × SizeMultiplier × PageCount) 
              + CoverCost + LaminationCost + BindingCost + OptionsCost) 
              × Quantity × (1 + ProfitMargin) - Discount
```

### New Formula (V2)
```
FinalPrice = ((PerPageCost × PageCount) + CoverCost + BindingCost + ExtrasCost) 
              × Quantity × (1 + ProfitMargin) - Discount
```

Where:
- `PerPageCost` = Direct cost from matrix for specific paper type, weight, and print type
- No multipliers - all costs are final per book size
- `CoverCost` = Specific to this book size
- `BindingCost` = Specific to this book size and binding type

## Usage

### Admin Interface

Use the `[tabesh_product_pricing]` shortcode to display the pricing management interface:

```
[tabesh_product_pricing]
```

This shortcode provides:
- Engine status toggle (V1/V2 switching)
- Book size selector tabs
- Matrix-based pricing forms for each parameter
- Parameter restriction controls
- Real-time save functionality

### Enabling V2 Engine

1. Navigate to a page with `[tabesh_product_pricing]` shortcode
2. Click "فعال‌سازی موتور جدید" (Enable New Engine)
3. Configure pricing for each book size
4. Save settings

### Programmatic Access

```php
// Check if V2 is enabled
$pricing_engine = new Tabesh_Pricing_Engine();
if ( $pricing_engine->is_enabled() ) {
    // V2 is active
}

// Calculate price with V2
$params = array(
    'book_size' => 'A5',
    'paper_type' => 'تحریر',
    'paper_weight' => '70',
    'print_type' => 'bw',
    'page_count_bw' => 100,
    'page_count_color' => 0,
    'quantity' => 50,
    'binding_type' => 'شومیز',
    'extras' => array()
);

$result = $pricing_engine->calculate_price( $params );
```

## Backward Compatibility

The implementation maintains full backward compatibility:

1. **Automatic Fallback**: If V2 is disabled, the system automatically uses the old pricing engine (V1)
2. **Existing Orders**: Old orders continue to work without any changes
3. **Gradual Migration**: You can enable V2, test it, and disable it if needed
4. **No Data Loss**: Old pricing settings are preserved

## Parameter Restrictions

The new system allows you to forbid certain parameter combinations per book size:

### Forbidden Paper Types
Example: Forbid "گلاسه" paper for "A5" book size

### Forbidden Binding Types
Example: Forbid "جلد سخت" for "رقعی" book size

### Forbidden Print Types per Paper
Example: Forbid "color" print for "تحریر" paper in "خشتی" size

When a restricted combination is attempted, the pricing calculation returns an error with a clear message.

## Quantity Constraints (New in v1.1)

The V2 pricing engine now supports per-book-size quantity constraints, allowing you to control the minimum, maximum, and step values for order quantities.

### Configuration

In the `[tabesh_product_pricing]` shortcode interface, Section 7 allows you to configure:

1. **Minimum Quantity** - The smallest allowed order quantity for this book size (e.g., 10)
2. **Maximum Quantity** - The largest allowed order quantity for this book size (e.g., 10,000)
3. **Quantity Step** - The increment for valid quantities (e.g., 50 means only 50, 100, 150, ... are allowed)

### How It Works

**Backend Validation:**
- When `calculate_price()` is called, the engine validates the quantity against the constraints
- If the quantity is below minimum: Returns error "حداقل تیراژ مجاز برای قطع X، Y عدد است"
- If the quantity is above maximum: Returns error "حداکثر تیراژ مجاز برای قطع X، Y عدد است"
- If the quantity doesn't match the step: Returns error "تیراژ باید بر اساس گام X برای قطع Y باشد"

**Frontend Integration:**
- When a user selects a book size in `[tabesh_order_form]`, JavaScript automatically updates the quantity input
- The input's min, max, and step attributes are dynamically set based on the selected book size
- The label updates to show the constraints clearly
- Client-side validation prevents users from entering invalid values

### Use Cases

**Example 1: Different minimums for different sizes**
- A5: minimum 100, maximum 10,000, step 50
- A4: minimum 50, maximum 5,000, step 25

**Example 2: Large format restrictions**
- خشتی: minimum 10, maximum 500, step 10 (expensive, limited production)

**Example 3: Standard increments**
- All sizes: step 100 (only accept orders in multiples of 100)

## Migration from V1 to V2

### Step 1: Review Current Pricing
1. Document your current V1 pricing settings
2. Understand the multipliers and base costs you're using

### Step 2: Configure V2 Matrices
1. For each book size, configure the pricing matrix
2. Calculate the final per-page costs (old: base_cost + print_cost × multiplier → new: direct cost)
3. Set binding costs specific to each size
4. Set cover costs specific to each size

### Step 3: Enable and Test
1. Enable V2 engine
2. Test pricing calculations with various parameters
3. Compare V2 results with V1 results to ensure accuracy
4. If issues arise, disable V2 and fix configuration

### Step 4: Production Deployment
1. Once testing is complete, keep V2 enabled
2. Monitor for any pricing discrepancies
3. Update documentation and inform staff

## Troubleshooting

### V2 Pricing Seems Wrong

**Check:**
1. Verify pricing matrix is configured for the book size
2. Check that per-page costs include both paper AND print costs
3. Verify binding and cover costs are set correctly
4. Ensure profit margin is configured (default: 0%)

### Parameter Restriction Not Working

**Check:**
1. Restrictions are configured in the pricing matrix for that specific book size
2. Clear any caching (call `Tabesh_Pricing_Engine::clear_cache()`)
3. Verify the restriction is saved in database

### Engine Toggle Not Working

**Check:**
1. User has `manage_woocommerce` capability
2. Database table `wp_tabesh_settings` exists and is writable
3. Check for JavaScript errors in browser console

### V2 Doesn't Reactivate After Disabling (FIXED in v1.1)

**Issue:** After disabling V2 and then re-enabling it, the pricing form doesn't load or pricing calculations fail.

**Root Cause:** Static cache in `Tabesh_Pricing_Engine` class was not cleared when toggling the engine on/off.

**Solution (Implemented):**
- `enable_pricing_engine_v2()` now calls `Tabesh_Pricing_Engine::clear_cache()`
- `disable_pricing_engine_v2()` now calls `Tabesh_Pricing_Engine::clear_cache()`
- This ensures fresh data is loaded when the engine state changes

**If you still experience this issue:**
1. Update to the latest version of the plugin
2. Try disabling and re-enabling V2 again
3. Clear your browser cache
4. Check for any PHP errors in the WordPress debug log

### Quantity Constraints Not Applied

**Check:**
1. Verify quantity constraints are configured in Section 7 of the pricing form for the specific book size
2. Ensure V2 engine is enabled
3. Check browser console for JavaScript errors
4. Verify the order form is using the latest version of `frontend.js`
5. Clear browser cache and reload the page

**Default Values:**
- Minimum quantity: 10
- Maximum quantity: 10,000
- Quantity step: 10

**How It Works:**
- When a user selects a book size in the order form, JavaScript automatically updates the quantity input constraints
- The label updates to show "تعداد (حداقل X، حداکثر Y)"
- Backend validation in `calculate_price()` also checks these constraints and returns clear error messages in Persian

## Security Considerations

- ✅ All inputs are sanitized using WordPress functions
- ✅ Nonces are verified for all form submissions
- ✅ Permission checks (`manage_woocommerce`) before allowing edits
- ✅ Database queries use prepared statements
- ✅ Output is properly escaped

## Performance

- ✅ Pricing matrices are cached in memory to avoid repeated database queries
- ✅ Cache is cleared when settings are updated
- ✅ Single database query to load all pricing data for a book size
- ✅ No impact on frontend performance when V2 is disabled

## Future Enhancements

Potential improvements for future versions:

1. **Import/Export**: Export pricing matrices as JSON for backup/sharing
2. **Pricing History**: Track pricing changes over time
3. **Bulk Updates**: Update multiple book sizes at once
4. **Price Testing**: Calculator to test pricing before saving
5. **Migration Tool**: Automated V1 → V2 conversion
6. **Multi-Currency**: Support for different currencies

## Support

For issues, questions, or contributions:
- GitHub Repository: https://github.com/tabshhh3/Tabesh
- Documentation: See `docs/` folder
- Issues: Open a GitHub issue

---

**Version:** 1.0.0  
**Last Updated:** December 2024  
**Author:** Tabesh Development Team
