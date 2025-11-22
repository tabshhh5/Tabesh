# Pricing Configuration User Guide

## Overview
The Tabesh plugin now supports full pricing configuration through the WordPress admin panel. All pricing parameters can be edited without modifying code.

## Accessing Pricing Settings

1. Log in to WordPress admin
2. Navigate to **تابش** → **تنظیمات** (Tabesh → Settings)
3. Click on the **قیمت‌گذاری** (Pricing) tab

## Pricing Configuration Sections

### 1. Book Size Multipliers (ضریب قطع کتاب)

Controls how book size affects material and print costs.

**Format:** `size=multiplier` (one per line)

**Example:**
```
A5=1
A4=1.5
B5=1.2
رقعی=1.1
وزیری=1.3
```

- A5 is the reference size (1.0)
- Larger sizes use more material and have higher multipliers
- Each line represents one book size

### 2. Paper Type Base Costs (قیمت پایه کاغذ)

Base cost per page for each paper type in Tomans.

**Format:** `paper_type=cost` (one per line)

**Example:**
```
glossy=250
matte=200
cream=180
تحریر=200
بالک=250
```

### 3. Print Costs (هزینه چاپ)

Cost per page for different print types.

- **Black & White (سیاه و سفید):** Cost per B&W page (e.g., 200)
- **Color (رنگی):** Cost per color page (e.g., 800)

### 4. Cover Costs (هزینه جلد)

Base cost for different cover types.

- **Soft Cover (جلد نرم/شومیز):** e.g., 8000 Tomans
- **Hard Cover (جلد سخت):** e.g., 15000 Tomans

### 5. Lamination Costs (هزینه سلفون کاری)

Additional cost for cover lamination.

**Format:** `lamination_type=cost` (one per line)

**Example:**
```
براق=2000
مات=2500
بدون سلفون=0
```

### 6. Binding Costs (هزینه صحافی)

Cost for different binding methods.

**Format:** `binding_type=cost` (one per line)

**Example:**
```
شومیز=3000
جلد سخت=8000
گالینگور=6000
سیمی=2000
```

### 7. Additional Options (هزینه آپشن‌های اضافی)

Cost for optional add-ons.

**Format:** `option=cost` (one per line)

**Example:**
```
لب گرد=1000
خط تا=500
شیرینک=1500
سوراخ=300
شماره گذاری=800
uv_coating=3000
embossing=5000
special_packaging=2000
```

### 8. Profit Margin (حاشیه سود)

Percentage markup on production cost.

**Format:** Enter as percentage (e.g., `10` for 10%)

**Examples:**
- 0 = No markup (0%)
- 10 = 10% profit margin
- 15 = 15% profit margin

### 9. Quantity Discounts (تخفیفات کمی)

**NEW FEATURE:** Configure discount tiers based on order quantity. Higher quantities automatically receive better pricing.

**Format:** `quantity=discount_percent` (one per line)

**Example:**
```
100=10
50=5
25=2
```

This means:
- Orders with 100+ books: 10% discount
- Orders with 50+ books (but less than 100): 5% discount
- Orders with 25+ books (but less than 50): 2% discount
- Orders with less than 25 books: No discount

**Features:**
- **Flexible Configuration:** Add, edit, or remove discount tiers as needed
- **Automatic Application:** System automatically applies the highest applicable discount
- **Delete All Discounts:** Clear all lines to disable quantity discounts completely
- **Supports Decimals:** You can use decimal percentages (e.g., `100=7.5` for 7.5% off)

**Important Notes:**
- Discounts are applied in descending order (highest quantity threshold first)
- Only the first matching discount is applied (not cumulative)
- To remove all discounts, delete all lines in the textarea

## Pricing Calculation Formula

The system uses this comprehensive formula:

```
FinalPrice = (
   ((PaperCost + PrintCost) × PageCount × SizeMultiplier)
   + CoverCost
   + LaminationCost
   + BindingCost
   + OptionsCost
) × Quantity × (1 + ProfitMargin) - Discount
```

### Calculation Steps:

1. **Per-Page Cost:** (Paper + Print) × Size Multiplier
2. **Total Pages Cost:** Per-Page Cost × Page Count
3. **Cover Cost:** Base Cover + Lamination
4. **Per-Book Cost:** Pages + Cover + Binding + Options
5. **Subtotal:** Per-Book Cost × Quantity
6. **Discount:** Applied based on configurable quantity tiers
   - Default: 100+ books: 10% discount, 50-99 books: 5% discount
   - Fully configurable in admin panel
7. **Profit Margin:** Added percentage markup
8. **Final Price:** Subtotal - Discount + Profit

## Examples

### Example 1: Simple A5 Book
- Book Size: A5 (multiplier: 1.0)
- Paper: تحریر (200 per page)
- Print: 100 B&W pages (200 per page)
- Quantity: 100
- Binding: شومیز (3000)
- Cover: Soft (8000) + براق lamination (2000)

**Calculation:**
- Per-page cost: (200 + 200) × 1.0 = 400
- Pages cost: 400 × 100 = 40,000
- Cover cost: 8000 + 2000 = 10,000
- Per-book: 40,000 + 10,000 + 3,000 = 53,000
- Subtotal: 53,000 × 100 = 5,300,000
- Discount (10%): 530,000
- After discount: 4,770,000
- Profit (10%): 477,000
- **Final: 5,247,000 Tomans**

### Example 2: A4 Color Book
- Book Size: A4 (multiplier: 1.5)
- Paper: بالک (250 per page)
- Print: 50 color pages (800 per page)
- Quantity: 50
- Binding: جلد سخت (8000)
- Cover: Hard (15000) + مات lamination (2500)

**Calculation:**
- Per-page cost: (250 + 800) × 1.5 = 1,575
- Pages cost: 1,575 × 50 = 78,750
- Cover cost: 15,000 + 2,500 = 17,500
- Per-book: 78,750 + 17,500 + 8,000 = 104,250
- Subtotal: 104,250 × 50 = 5,212,500
- Discount (5%): 260,625
- After discount: 4,951,875
- Profit (10%): 495,188
- **Final: 5,447,063 Tomans**

## Saving Settings

1. Make your changes in the pricing fields
2. Click **ذخیره تنظیمات** (Save Settings) at the bottom
3. Settings are saved to the database
4. Price calculations immediately use the new values

## Important Notes

### Format Requirements
- Use one entry per line
- Format: `key=value`
- Keys can be in English or Persian
- Values must be numbers
- No spaces around the equals sign (recommended)

### Database Storage
- All settings are stored in `wp_tabesh_settings` table
- Settings are cached for performance
- Default values are set on plugin activation
- Can be reset by deactivating and reactivating the plugin

### Validation
- All numeric values are validated
- Invalid entries are ignored
- Missing settings fall back to defaults

## Troubleshooting

### Changes Not Taking Effect
1. Clear WordPress cache if using a caching plugin
2. Verify settings were saved (look for success message)
3. Check browser console for JavaScript errors

### Incorrect Calculations
1. Verify all pricing fields use correct format
2. Check that multipliers and costs are reasonable numbers
3. Test with simple values first
4. Review the breakdown in the calculation response

### Form Submission Issues
1. Ensure JavaScript is enabled
2. Check for browser console errors
3. Verify WordPress nonce is valid
4. Try clearing browser cache

## Technical Details

### Database Structure
Settings are stored as JSON in the `wp_tabesh_settings` table:
- `setting_key`: pricing configuration key
- `setting_value`: JSON-encoded pricing data
- `setting_type`: always 'string'

### API Integration
Pricing configuration is read by the `calculate_price()` method in `Tabesh_Order` class through the `get_pricing_config()` method, which:
1. Queries database for pricing settings
2. Falls back to defaults if not found
3. Caches results for performance

### Profit Margin Storage
- Entered as percentage in UI (e.g., 10)
- Stored as decimal in database (e.g., 0.10)
- Converted automatically on save/load

## Best Practices

1. **Test Changes:** Use small quantities to test new pricing
2. **Keep Backups:** Export settings before major changes
3. **Document Changes:** Keep notes on why prices were changed
4. **Review Regularly:** Check if pricing matches business costs
5. **Start Simple:** Begin with basic pricing, add complexity later

## Future Enhancements

Planned features for future versions:
- Import/Export pricing configurations
- Multiple pricing profiles
- Time-based pricing rules
- Customer-specific pricing
- Bulk pricing editor
- Pricing history tracking

---

**Version:** 1.0.0  
**Last Updated:** October 2024  
**Plugin:** Tabesh - Book Printing Order Management
