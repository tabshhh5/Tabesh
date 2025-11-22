# Quantity Discount Configuration - Visual Guide

## Overview

This guide demonstrates how to configure quantity-based discounts in the Tabesh plugin admin panel.

## Accessing the Feature

1. Log in to WordPress admin panel
2. Navigate to **تابش** → **تنظیمات** (Tabesh → Settings)
3. Click on the **قیمت‌گذاری** (Pricing) tab
4. Scroll to the **تخفیفات کمی (Quantity Discounts)** section

## Configuration Interface

### Location
The discount configuration is located at the bottom of the Pricing tab, after the Profit Margin section.

### Field Layout

```
┌─────────────────────────────────────────────────────────────┐
│ تخفیفات کمی (Quantity Discounts)                            │
├─────────────────────────────────────────────────────────────┤
│ تعریف تخفیف بر اساس تیراژ - تیراژهای بالاتر تخفیف بیشتری  │
│ دریافت می‌کنند                                              │
├─────────────────────────────────────────────────────────────┤
│ تخفیفات تیراژ (Label)                                       │
│ ┌───────────────────────────────────────────────────────┐   │
│ │ 100=10                                                │   │
│ │ 50=5                                                  │   │
│ │                                                       │   │
│ │                                                       │   │
│ │                                                       │   │
│ └───────────────────────────────────────────────────────┘   │
│ ✓ هر خط یک قاعده تخفیف (مثال: 100=10 یعنی 10% تخفیف برای  │
│   تیراژ 100 و بیشتر)                                        │
│ ✓ تیراژ=درصد تخفیف (تیراژ به عدد، تخفیف به درصد)           │
│ ✓ تخفیفات بر اساس تیراژ نزولی اعمال می‌شود (بالاترین      │
│   تخفیف اول بررسی می‌شود)                                  │
│ ✓ برای حذف همه تخفیفات، همه خطوط را پاک کنید              │
│ ✓ تعداد فیلدها: 2                                          │
└─────────────────────────────────────────────────────────────┘
```

## Configuration Examples

### Example 1: Basic Two-Tier Discount

**Input:**
```
100=10
50=5
```

**Result:**
- Orders ≥ 100 books: 10% discount
- Orders ≥ 50 books (but < 100): 5% discount
- Orders < 50 books: No discount

### Example 2: Multi-Tier Discount System

**Input:**
```
500=15
200=12
100=10
50=5
25=2
```

**Result:**
- Orders ≥ 500 books: 15% discount
- Orders ≥ 200 books (but < 500): 12% discount
- Orders ≥ 100 books (but < 200): 10% discount
- Orders ≥ 50 books (but < 100): 5% discount
- Orders ≥ 25 books (but < 50): 2% discount
- Orders < 25 books: No discount

### Example 3: Single Discount Tier

**Input:**
```
100=10
```

**Result:**
- Orders ≥ 100 books: 10% discount
- Orders < 100 books: No discount

### Example 4: Decimal Discounts

**Input:**
```
100=12.5
50=7.5
25=2.5
```

**Result:**
- Orders ≥ 100 books: 12.5% discount
- Orders ≥ 50 books (but < 100): 7.5% discount
- Orders ≥ 25 books (but < 50): 2.5% discount
- Orders < 25 books: No discount

### Example 5: No Discounts

**Input:**
```
(empty - all lines deleted)
```

**Result:**
- All orders: No discount applied

## How It Works

### 1. Admin Configuration
1. Admin enters discount rules in the textarea
2. One rule per line in format: `quantity=discount_percent`
3. Click "ذخیره تنظیمات" (Save Settings)

### 2. Storage
- Rules are saved to `wp_tabesh_settings` table as JSON
- Format: `{"100": 10, "50": 5}`
- Numeric values are automatically detected and stored correctly

### 3. Application
- When a customer places an order, the system:
  1. Loads discount rules from database
  2. Sorts rules by quantity (descending)
  3. Finds first matching rule where order quantity ≥ threshold
  4. Applies that discount percentage to subtotal

### 4. Price Breakdown Display
The discount appears in the price calculation breakdown:
```
جمع: 5,300,000 تومان
تخفیف: 10% (530,000 تومان)
بعد از تخفیف: 4,770,000 تومان
حاشیه سود: 10% (477,000 تومان)
مبلغ نهایی: 5,247,000 تومان
```

## Best Practices

### ✅ Do's

1. **Start Simple**: Begin with 2-3 discount tiers
2. **Test First**: Test with small orders before applying to production
3. **Logical Progression**: Higher quantities should have higher discounts
4. **Document Changes**: Keep notes on why discounts were changed
5. **Review Regularly**: Adjust based on business performance

### ❌ Don'ts

1. **Don't Overlap Incorrectly**: Lower quantities shouldn't have higher discounts
2. **Don't Use Too Many Tiers**: 3-5 tiers are usually sufficient
3. **Don't Forget to Save**: Always click "ذخیره تنظیمات" after changes
4. **Don't Set Extreme Values**: Keep discounts reasonable (0-50%)

## Common Scenarios

### Scenario 1: Removing a Discount Tier

**Before:**
```
100=10
50=5
25=2
```

**After (removing the 25-book tier):**
```
100=10
50=5
```

### Scenario 2: Adding a New Tier

**Before:**
```
100=10
50=5
```

**After (adding a 200-book tier):**
```
200=15
100=10
50=5
```

### Scenario 3: Changing Discount Percentages

**Before:**
```
100=10
50=5
```

**After (increasing discounts):**
```
100=15
50=8
```

### Scenario 4: Temporarily Disabling Discounts

**Method 1 - Delete All Rules:**
```
(empty textarea)
```

**Method 2 - Set All to Zero:**
```
100=0
50=0
```

## Troubleshooting

### Issue: Discounts Not Applying

**Possible Causes:**
1. Settings not saved properly
2. Incorrect format in textarea
3. WordPress cache needs clearing

**Solution:**
1. Verify settings were saved (look for success message)
2. Check format: `quantity=discount` with no spaces
3. Clear WordPress and browser cache
4. Test with a fresh order

### Issue: Wrong Discount Applied

**Possible Causes:**
1. Rules not sorted correctly
2. Quantity threshold confusion

**Solution:**
1. System automatically sorts rules (highest first)
2. Remember: discount applies when order quantity ≥ threshold
3. Check price breakdown to see which discount was applied

### Issue: Decimal Discounts Not Working

**Possible Causes:**
1. Using comma instead of period for decimals

**Solution:**
1. Use period (.) not comma (,) for decimals
2. Example: `100=12.5` not `100=12,5`

## Technical Details

### Database Storage
- **Table**: `wp_tabesh_settings`
- **Key**: `pricing_quantity_discounts`
- **Format**: JSON object
- **Example**: `{"100": 10, "50": 5, "25": 2.5}`

### Format Validation
- Keys must be numeric (quantity thresholds)
- Values must be numeric (discount percentages)
- Invalid entries are silently ignored
- Empty lines are skipped

### Performance
- Settings are loaded once per calculation
- Rules are sorted in memory (not in query)
- No performance impact on frontend
- Cached by WordPress object cache (if enabled)

## Integration with Price Calculation

The discount is applied in the following order:

1. Calculate per-book production cost
2. Multiply by quantity → **Subtotal**
3. Apply quantity discount → **Total after discount**
4. Apply profit margin → **Final price**

Formula:
```
Final Price = ((Subtotal - Discount) + Profit Margin)

Where:
  Subtotal = ProductionCost × Quantity
  Discount = Subtotal × (DiscountPercent / 100)
  Profit = (Subtotal - Discount) × (ProfitMargin / 100)
```

## Version History

- **v1.0.0** - Initial release with configurable discount system
- Replaces hardcoded 10%/5% discount logic
- Full backward compatibility with default values

---

**Last Updated**: October 2024  
**Feature Version**: 1.0.0  
**Plugin**: Tabesh - Book Printing Order Management
