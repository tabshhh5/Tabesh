# Visual Guide: Extras Cost Display

## Before the Fix

When users selected additional services, the price breakdown looked like this:

```
┌─────────────────────────────────────┐
│       پیش‌فاکتور (Invoice)          │
├─────────────────────────────────────┤
│ قیمت هر جلد:      53,000 تومان      │
│ تعداد:           100 عدد             │
│ جمع:             5,300,000 تومان     │
│ مبلغ نهایی:      5,300,000 تومان     │
└─────────────────────────────────────┘
```

**Problem:** No indication that extras were selected or included in price!

## After the Fix

Now when users select additional services (e.g., "لب گرد", "خط تا", "شیرینک"), they see:

```
┌─────────────────────────────────────┐
│       پیش‌فاکتور (Invoice)          │
├─────────────────────────────────────┤
│ قیمت هر جلد:      56,000 تومان      │
│ تعداد:           100 عدد             │
│ جمع:             5,600,000 تومان     │
│ هزینه خدمات اضافی: 3,000 تومان      │  ← NEW!
│ مبلغ نهایی:      5,600,000 تومان     │
└─────────────────────────────────────┘
```

**Benefits:**
- ✅ Clear visibility of extras cost
- ✅ Users can verify extras are included
- ✅ Transparent pricing breakdown

## Browser Console (for debugging)

When extras are selected, developers can see in the console:

```javascript
Tabesh: Found 3 checked extras
Tabesh: Adding extra: لب گرد
Tabesh: Adding extra: خط تا
Tabesh: Adding extra: شیرینک
Tabesh: Total extras collected: 3 ['لب گرد', 'خط تا', 'شیرینک']
Tabesh: Sending AJAX request to: /wp-json/tabesh/v1/calculate-price
Tabesh: Extras breakdown: {
  'لب گرد': 1000,
  'خط تا': 500,
  'شیرینک': 1500
}
```

## WordPress Debug Log (when WP_DEBUG enabled)

In `wp-content/debug.log`:

```
Tabesh: Extras received: Array([0] => لب گرد, [1] => خط تا, [2] => شیرینک)
Tabesh: Processing 3 extras
Tabesh: Available options_costs keys: Array(لب گرد, خط تا, شیرینک, سوراخ, شماره گذاری)
Tabesh: Extras values: Array([0] => لب گرد, [1] => خط تا, [2] => شیرینک)
Tabesh: Extra "لب گرد" cost: 1000
Tabesh: Extra "خط تا" cost: 500
Tabesh: Extra "شیرینک" cost: 1500
Tabesh: Total options cost: 3000
Tabesh: Options breakdown: Array(لب گرد => 1000, خط تا => 500, شیرینک => 1500)
```

## Example Calculation Breakdown

### Scenario: Book with Multiple Extras

**Order Details:**
- Book Size: A5
- Paper Type: تحریر (80g)
- Pages: 100 B&W
- Quantity: 100
- Binding: شومیز
- Cover: 250g with براق lamination
- Extras: لب گرد, خط تا, شیرینک

**Price Calculation:**

```
Pages Cost:
  - Paper cost per page: 200 (تحریر base)
  - Print cost per page: 200 (B&W)
  - Size multiplier: 1.0 (A5)
  - Per page cost: (200 + 200) × 1.0 = 400
  - Total pages cost: 400 × 100 = 40,000

Cover Cost:
  - Base cover: 8,000
  - Lamination (براق): 2,000
  - Total cover cost: 10,000

Binding Cost:
  - شومیز: 3,000

Extras Cost:  ← THIS IS THE FIX!
  - لب گرد: 1,000
  - خط تا: 500
  - شیرینک: 1,500
  - Total extras: 3,000

PRODUCTION COST PER BOOK:
  40,000 (pages) + 10,000 (cover) + 3,000 (binding) + 3,000 (extras)
  = 56,000 تومان

TOTAL FOR 100 COPIES:
  56,000 × 100 = 5,600,000 تومان
```

## Error Scenarios Handled

### 1. Extra Not in Pricing Config

If user selects "custom_service" but it's not in pricing_options_costs:

**Debug Log:**
```
Tabesh WARNING: Extra "custom_service" not found in pricing_config, defaulting to 0
```

**User Impact:** Order still processes, but that extra costs 0

### 2. Empty Extras Array

No extras selected:

**Debug Log:**
```
Tabesh: Extras is empty array, no additional options selected
```

**Display:** No extras row shown in price breakdown

### 3. Invalid Data Type

If extras is not an array (coding error):

**Debug Log:**
```
Tabesh: Extras is not an array, skipping options cost calculation
Tabesh: Extras type: string
```

**User Impact:** Order processes with 0 extras cost, error logged for developer

## Configuration Example

### Admin Settings Location
Navigate to: **تابش > تنظیمات > قیمت‌گذاری**

### Pricing Options Field
```
Field Label: قیمت آپشن‌ها
Format: One option per line as name=cost

لب گرد=1000
خط تا=500
شیرینک=1500
سوراخ=300
شماره گذاری=800
```

### Extras Field
Navigate to: **تابش > تنظیمات > محصول**
```
Field Label: خدمات اضافی (Extras)
Format: Comma-separated list

لب گرد, خط تا, شیرینک, سوراخ, شماره گذاری
```

**Important:** Names must match exactly between these two settings!

## Summary

The fix ensures:
1. ✅ Extras cost is calculated
2. ✅ Extras cost is included in total
3. ✅ Extras cost is displayed separately
4. ✅ Comprehensive logging for debugging
5. ✅ Graceful error handling
6. ✅ User-friendly experience

Users can now confidently see that their selected additional services are properly priced and included in their order total.
