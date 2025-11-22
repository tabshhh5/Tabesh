# Book Printing Price Calculation System - Implementation Guide

## Overview

This document describes the enhanced book printing price calculation system implemented in `includes/class-tabesh-order.php`.

## Calculation Algorithm

The system implements a comprehensive 14-step pricing algorithm:

### Formula
```
FinalPrice = (
   ((PaperCost + PrintCost) * PageCount)
   + CoverCost
   + BindingCost
   + OptionsCost
) * Quantity * (1 + ProfitMargin) - Discount
```

### Step-by-Step Breakdown

1. **Book Size Multiplier (قطع کتاب)**
   - Each book size has a multiplier affecting material costs
   - Examples: A5=1.0x, A4=1.5x, B5=1.2x

2. **Paper Type Base Cost (نوع کاغذ)**
   - Base cost per page varies by paper type
   - Examples: Glossy=250, Matte=200, Cream=180

3. **Print Cost per Page (هزینه چاپ)**
   - Separate costs for B&W (200) vs Color (800)

4. **Per-Page Cost Calculation**
   - B&W: (PaperCost + PrintCostBW) × SizeMultiplier
   - Color: (PaperCost + PrintCostColor) × SizeMultiplier

5. **Total Pages Cost**
   - Sum of B&W and Color page costs

6. **Cover Cost (جلد)**
   - Base cost: Soft=8000, Hard=15000

7. **Lamination Cost (سلفون کاری)**
   - Glossy=2000, Matte=2500, None=0

8. **Binding Cost (صحافی)**
   - Varies by type: شومیز=3000, جلد سخت=8000, etc.

9. **Additional Options (آپشنها)**
   - UV coating, embossing, foil, special packaging
   - Added as sum of selected options

10. **Production Cost per Book**
    - Sum of all above costs

11. **Quantity Multiplier (تیراژ)**
    - Multiply by order quantity

12. **Quantity Discounts**
    - 100+ books: 10% discount
    - 50-99 books: 5% discount

13. **Profit Margin (حاشیه سود)**
    - Configurable percentage markup (default: 0%)

14. **Final Price (فاکتور نهایی)**
    - Total after all calculations

## API Parameters

### Required Parameters
- `book_size`: Book size (A5, A4, B5, etc.)
- `paper_type`: Paper type (تحریر, بالک, glossy, etc.)
- `page_count_bw`: Number of B&W pages
- `page_count_color`: Number of color pages
- `quantity`: Order quantity
- `binding_type`: Binding method
- `lamination_type`: Cover lamination type

### Optional Parameters
- `cover_type`: 'soft' or 'hard' (default: 'soft')
- `paper_weight`: Paper weight specification
- `print_type`: Print type description
- `license_type`: License information
- `cover_paper_weight`: Cover paper weight
- `extras`: Array of additional options

## Response Format

```json
{
    "price_per_book": 53000,
    "quantity": 100,
    "subtotal": 5300000,
    "discount_percent": 10,
    "discount_amount": 530000,
    "total_after_discount": 4770000,
    "profit_margin_percent": 0,
    "profit_amount": 0,
    "total_price": 4770000,
    "page_count_total": 100,
    "breakdown": {
        "book_size": "A5",
        "size_multiplier": 1,
        "pages_cost_bw": 40000,
        "pages_cost_color": 0,
        "total_pages_cost": 40000,
        "cover_cost": 10000,
        "binding_cost": 3000,
        "options_cost": 0,
        "per_page_cost_bw": 400,
        "per_page_cost_color": 1000
    }
}
```

## Future Admin Panel GUI Configuration

### Planned Database Structure

The pricing configuration should be stored in one of these ways:

#### Option 1: Custom Table `{prefix}tabesh_pricing`
```sql
CREATE TABLE {prefix}tabesh_pricing (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,        -- 'book_size', 'paper_type', etc.
    item_key VARCHAR(100) NOT NULL,       -- 'A5', 'glossy', etc.
    item_value DECIMAL(10,2) NOT NULL,    -- Price or multiplier
    display_name VARCHAR(255),            -- Display name in admin
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY category_key (category, item_key)
);
```

#### Option 2: Serialized Array in `{prefix}tabesh_settings`
Store the entire pricing configuration as a serialized array in the settings table:
```php
$pricing_config = array(
    'book_sizes' => [...],
    'paper_types' => [...],
    // etc.
);
// WordPress handles serialization automatically
update_option('tabesh_pricing_config', $pricing_config);
```

### Admin Interface Sections

1. **Book Sizes (قطع کتاب)**
   - Add/Edit/Delete book sizes
   - Set multiplier for each size
   - Set display order

2. **Paper Types (نوع کاغذ)**
   - Manage paper types and base costs
   - Set cost per page for each type

3. **Print Costs (هزینه چاپ)**
   - Configure B&W cost per page
   - Configure Color cost per page

4. **Cover & Binding (جلد و صحافی)**
   - Manage cover types (soft/hard)
   - Manage binding types
   - Set costs for each

5. **Lamination (سلفون کاری)**
   - Add lamination options
   - Set costs

6. **Additional Options (آپشنها)**
   - Manage add-on services
   - UV coating, embossing, etc.
   - Set individual costs

7. **Discounts & Margins (تخفیف و حاشیه)**
   - Configure quantity-based discounts
   - Set profit margin percentage

### Implementation Steps for GUI

1. **Create Admin Menu Page**
   ```php
   add_menu_page(
       'Tabesh Pricing',
       'قیمت گذاری',
       'manage_options',
       'tabesh-pricing',
       array($this, 'render_pricing_admin_page')  // Callback method to be implemented
   );
   ```

2. **Load Configuration from Database**
   - Modify `get_pricing_config()` to read from database
   - Cache configuration for performance

3. **Create CRUD Functions**
   - Add pricing item
   - Update pricing item
   - Delete pricing item
   - List all items by category

4. **Build Admin Interface**
   - Tabbed interface for each category
   - AJAX-based updates
   - Validation and error handling

5. **Add Import/Export**
   - Export pricing as JSON
   - Import from JSON file
   - Reset to defaults

## Testing

The calculation system has been thoroughly tested with various scenarios including:

### Test Scenarios Covered
1. Basic A5 book with B&W pages
2. A4 book with color pages
3. Mixed B&W and color pages
4. Small quantity (no discount)
5. Backward compatibility with old API
6. Unknown book sizes (defaults)
7. Empty extras array

### Running Tests

To test the calculation system, you can create a test script that mocks WordPress functions:

```php
// Mock WordPress functions
define('ABSPATH', '/tmp/');
function sanitize_text_field($str) { return trim(strip_tags($str)); }
function sanitize_textarea_field($str) { return trim(strip_tags($str)); }
function maybe_serialize($data) { 
    return is_array($data) || is_object($data) ? serialize($data) : $data; 
}

// Include and test
require_once 'includes/class-tabesh-order.php';
$order = new Tabesh_Order();
$result = $order->calculate_price($params);
```

## Migration Notes

### Migrating Existing Orders
- All existing orders will continue to work
- New `cover_type` parameter defaults to 'soft'
- Unknown book sizes default to 1.0 multiplier

### Backward Compatibility
- All existing API calls remain functional
- Optional parameters use sensible defaults
- Enhanced return format includes backward-compatible fields

## Performance Considerations

- Pricing configuration should be cached
- Use WordPress transients for caching
- Consider object caching for high-traffic sites

```php
$pricing_config = get_transient('tabesh_pricing_config');
if (false === $pricing_config) {
    $pricing_config = $this->load_pricing_from_db();
    set_transient('tabesh_pricing_config', $pricing_config, HOUR_IN_SECONDS);
}
```

## Security Notes

- All inputs are sanitized using WordPress functions
- Configuration changes require `manage_options` capability
- Validate numeric values before storage
- Escape output in admin interfaces

---

**Version:** 1.0.0  
**Last Updated:** October 2024  
**Maintainer:** Tabesh Development Team
