# Pricing Calculation Pipeline - Tabesh V2

## Overview

This document describes the precise calculation pipeline used in Tabesh Pricing Engine V2 (Matrix-Based Pricing). The pipeline ensures accurate, consistent pricing while maintaining proper order of operations and validation.

## Calculation Pipeline Flow

The pricing engine follows a strict 10-step pipeline to calculate the final price:

### Step 0: Input Validation & Sanitization
**Purpose**: Ensure data integrity before any calculations

```php
// Sanitize inputs
$book_size = sanitize_text_field($params['book_size'] ?? '');
$paper_type = sanitize_text_field($params['paper_type'] ?? '');
// ... other sanitizations

// Validate numeric inputs
$page_count_color = max(0, intval($params['page_count_color'] ?? 0));
$page_count_bw = max(0, intval($params['page_count_bw'] ?? 0));
$quantity = max(0, intval($params['quantity'] ?? 0));
```

**Validations**:
- ✅ Required fields present (book_size, paper_type, binding_type)
- ✅ Quantity > 0
- ✅ Total page count > 0
- ✅ Quantity constraints (min, max, step)

### Step 1: Parameter Combination Validation
**Purpose**: Verify parameter combination is allowed and configured

```php
$validation = $this->validate_parameters(
    $book_size,
    $paper_type,
    $paper_weight,
    $print_type,
    $binding_type,
    $cover_weight,
    $page_count_bw,
    $page_count_color
);
```

**Two-Stage Validation**:
1. **Restriction Check**: Is combination forbidden?
2. **Existence Check**: Is combination configured in matrix?

**See**: `PRICING_VALIDATION_LAYER.md` for detailed validation documentation.

---

### Step 2: Calculate Per-Page Cost (Base Cost)
**Purpose**: Calculate unified per-page cost (paper + print combined)

```php
$per_page_cost_bw = $this->get_page_cost($pricing_matrix, $paper_type, $paper_weight, 'bw');
$per_page_cost_color = $this->get_page_cost($pricing_matrix, $paper_type, $paper_weight, 'color');
```

**Matrix Structure**:
```json
"page_costs": {
  "تحریر": {
    "60": {
      "bw": 350,     // Cost per page for BW printing
      "color": 950   // Cost per page for color printing
    }
  }
}
```

**Fallback**: Uses 0.0 for unused print types (e.g., if all pages are BW, color cost = 0).

---

### Step 3: Calculate Total Pages Cost
**Purpose**: Sum up total cost for all pages

```php
$pages_cost_bw = $per_page_cost_bw * $page_count_bw;
$pages_cost_color = $per_page_cost_color * $page_count_color;
$total_pages_cost = $pages_cost_bw + $pages_cost_color;
```

**Example**:
- 100 BW pages @ 350 Toman = 35,000 Toman
- 50 Color pages @ 950 Toman = 47,500 Toman
- **Total**: 82,500 Toman

---

### Step 4: Get Binding Cost
**Purpose**: Get binding cost (includes cover in new structure)

```php
$binding_cost = $this->get_binding_cost($pricing_matrix, $binding_type, $cover_weight);
```

**Matrix Structure**:

**New Structure** (with cover weights):
```json
"binding_costs": {
  "شومیز": {
    "200": 5000,
    "250": 5500,
    "300": 6000
  }
}
```

**Legacy Structure** (single value):
```json
"binding_costs": {
  "شومیز": 5000
}
```

**Compatibility**: Engine supports both structures automatically.

---

### Step 5: Get Cover Cost
**Purpose**: Get legacy cover cost (for backward compatibility)

```php
$cover_cost = $this->get_cover_cost($pricing_matrix);
```

**Note**: 
- In new structure, cover cost is included in binding_costs
- Returns 0.0 for new matrices to avoid double-counting
- Only applies to legacy matrices that have separate `cover_cost` field

---

### Step 5.5: Validate Extra Services
**Purpose**: Ensure selected extras are allowed for the binding type

```php
$forbidden_extras = $pricing_matrix['restrictions']['forbidden_extras'][$binding_type] ?? array();
foreach ($extras as $extra) {
    if (in_array($extra, $forbidden_extras, true)) {
        return error_message; // Not allowed
    }
}
```

**Example**: "لب گرد" (rounded corners) might not be allowed for "سیمی" (wire binding).

---

### Step 6: Calculate Extras Cost
**Purpose**: Sum up all extra services costs

```php
$extras_cost = $this->calculate_extras_cost($pricing_matrix, $extras, $quantity, $page_count_total);
```

**Extras Types**:

1. **Fixed Cost**: Same price regardless of quantity
   ```json
   {
     "price": 5000,
     "type": "fixed",
     "step": 0
   }
   ```

2. **Per Unit Cost**: Multiplied by quantity
   ```json
   {
     "price": 1000,
     "type": "per_unit",
     "step": 0
   }
   ```
   Example: 1000 × 100 books = 100,000 Toman

3. **Page Based Cost**: Based on total pages with step
   ```json
   {
     "price": 500,
     "type": "page_based",
     "step": 100
   }
   ```
   Example: 
   - Total pages: 15,000 (150 pages × 100 books)
   - Units: ceil(15,000 / 100) = 150
   - Cost: 500 × 150 = 75,000 Toman

---

### Step 7: Calculate Production Cost Per Book
**Purpose**: Sum all per-book costs

```php
$production_cost_per_book = $total_pages_cost + $cover_cost + $binding_cost + $extras_cost;
```

**Example**:
- Pages: 82,500 Toman
- Cover: 0 Toman (included in binding)
- Binding: 5,500 Toman
- Extras: 1,000 Toman
- **Per Book**: 89,000 Toman

---

### Step 8: Calculate Subtotal (Quantity Multiplication)
**Purpose**: Multiply per-book cost by quantity

```php
$subtotal = $production_cost_per_book * $quantity;
```

**Example**: 89,000 × 100 books = 8,900,000 Toman

---

### Step 9: Apply Quantity Discounts
**Purpose**: Apply progressive discounts based on quantity

```php
$discount_info = $this->calculate_discount($quantity, $subtotal);
$discount_percent = $discount_info['percent'];
$discount_amount = $discount_info['amount'];
$total_after_discount = $subtotal - $discount_amount;
```

**Discount Configuration**:
```json
{
  "100": 10,  // 10% discount for 100+ units
  "50": 5     // 5% discount for 50+ units
}
```

**Logic**: 
- Sorted in descending order
- First matching threshold is applied
- Example: 100 books @ 10% = 890,000 Toman discount

**Result**: 8,900,000 - 890,000 = 8,010,000 Toman

---

### Step 10: Apply Profit Margin
**Purpose**: Add business profit margin

```php
$profit_margin = floatval($pricing_matrix['profit_margin'] ?? 0.0);
$profit_amount = $total_after_discount * $profit_margin;
$total_price = $total_after_discount + $profit_amount;
```

**Example**:
- After discount: 8,010,000 Toman
- Profit margin: 0.15 (15%)
- Profit: 1,201,500 Toman
- **Final Price**: 9,211,500 Toman

---

### Final Validation: Numeric Sanity Check
**Purpose**: Ensure all calculated values are valid numbers

```php
$validate_numbers = array(
    'price_per_book' => $production_cost_per_book,
    'quantity' => $quantity,
    'subtotal' => $subtotal,
    'total_price' => $total_price,
    'total_after_discount' => $total_after_discount,
);

foreach ($validate_numbers as $key => $value) {
    if (!is_numeric($value) || is_nan($value) || is_infinite($value)) {
        return error_message;
    }
}
```

**Checks**:
- ✅ Not null
- ✅ Not NaN (Not a Number)
- ✅ Not infinite
- ✅ Is numeric

---

## Complete Calculation Example

### Input Parameters
```json
{
  "book_size": "A5",
  "paper_type": "تحریر",
  "paper_weight": "70",
  "binding_type": "شومیز",
  "cover_weight": "250",
  "page_count_bw": 100,
  "page_count_color": 50,
  "quantity": 100,
  "extras": ["لب گرد", "شیرینک"]
}
```

### Calculation Steps

| Step | Operation | Value |
|------|-----------|-------|
| 2 | BW pages cost | 380 × 100 = 38,000 |
| 2 | Color pages cost | 980 × 50 = 49,000 |
| 3 | Total pages cost | 38,000 + 49,000 = 87,000 |
| 4 | Binding cost | 5,500 |
| 5 | Cover cost | 0 (included) |
| 6 | Extras cost | 1,000 + 1,500 = 2,500 |
| 7 | **Per book cost** | 87,000 + 5,500 + 2,500 = **95,000** |
| 8 | Subtotal | 95,000 × 100 = 9,500,000 |
| 9 | Discount (10%) | 950,000 |
| 9 | After discount | 9,500,000 - 950,000 = 8,550,000 |
| 10 | Profit (15%) | 8,550,000 × 0.15 = 1,282,500 |
| 10 | **Final price** | 8,550,000 + 1,282,500 = **9,832,500** |

### Response Structure

```json
{
  "price_per_book": 95000,
  "quantity": 100,
  "subtotal": 9500000,
  "discount_percent": 10,
  "discount_amount": 950000,
  "total_after_discount": 8550000,
  "profit_margin_percent": 15,
  "profit_amount": 1282500,
  "total_price": 9832500,
  "page_count_total": 150,
  "pricing_engine": "v2_matrix",
  "breakdown": {
    "book_size": "A5",
    "pages_cost_bw": 38000,
    "pages_cost_color": 49000,
    "total_pages_cost": 87000,
    "cover_cost": 0,
    "binding_cost": 5500,
    "extras_cost": 2500,
    "per_page_cost_bw": 380,
    "per_page_cost_color": 980
  }
}
```

## Pipeline Characteristics

### Strengths

1. **Transparent**: Each step is clearly documented
2. **Precise**: Fixed order prevents calculation errors
3. **Flexible**: Supports both legacy and new structures
4. **Detailed**: Comprehensive breakdown for debugging
5. **Safe**: Multiple validation layers

### Design Principles

1. **Fail Fast**: Validation happens before calculation
2. **Idempotent**: Same inputs always produce same outputs
3. **Defensive**: Sanity checks at every step
4. **Explicit**: No implicit conversions or assumptions
5. **Traceable**: Debug logging at key points

### Error Handling

At each step, if an error occurs:
1. Calculation stops immediately
2. User-friendly error message is returned
3. No zero prices are displayed
4. Debug information is logged (if WP_DEBUG enabled)

## Performance Considerations

### Caching Strategy

1. **Pricing Matrix Cache**: Static variable cache per request
   - Avoids redundant database queries
   - Cleared when settings are updated

2. **Calculation Results**: Not cached
   - Each calculation is independent
   - User-specific (quantity, extras vary)

### Optimization Points

1. **Early Validation**: Fail fast before heavy calculations
2. **Conditional Processing**: Skip unnecessary calculations (e.g., unused print types)
3. **Static Cache**: Reuse pricing matrix within same request
4. **Minimal Database Queries**: Single query loads all matrices

## Related Documentation

- **Validation Layer**: `PRICING_VALIDATION_LAYER.md`
- **V2 Architecture**: `PRICING_ENGINE_V2.md`
- **Testing Guide**: `TESTING_PRICING_V2.md`
- **Troubleshooting**: `PRICING_V2_FIXES_SUMMARY.md`

## Maintenance Guidelines

### When Modifying the Pipeline

1. **Never reorder steps** without careful analysis
2. **Maintain backward compatibility** with legacy structures
3. **Add validation** for any new parameters
4. **Update documentation** immediately
5. **Test edge cases** thoroughly

### Common Modifications

#### Adding a New Cost Component
```php
// Step X: Calculate new component cost
$new_component_cost = $this->get_new_component_cost($pricing_matrix, $param);

// Update Step 7: Include in production cost
$production_cost_per_book = $total_pages_cost + $cover_cost + 
                            $binding_cost + $extras_cost + 
                            $new_component_cost;
```

#### Changing Discount Logic
```php
// Modify Step 9 in calculate_discount() method
// Never change the step number or position in pipeline
```

---

**Last Updated**: 2025-12-18  
**Version**: 1.0.4  
**Status**: Production Ready
