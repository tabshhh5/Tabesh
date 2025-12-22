# گزارش نهایی: رفع اشکال محاسبه خدمات اضافی در موتور قیمت‌گذاری
## Additional Services Calculation Fix - Final Report

**تاریخ / Date**: 2025-12-21  
**نسخه / Version**: 2.0.0  
**وضعیت / Status**: ✅ کامل شده / Complete

---

## خلاصه مشکل / Problem Summary

### فارسی
در سیستم محاسبه قیمت افزونه Tabesh، سه نوع خدمات اضافی تعریف شده بود که هر کدام دارای مشکلات ریاضی در محاسبات بودند:

1. **نوع ثابت (Fixed)**: قیمت خدمت به اشتباه در تیراژ ضرب می‌شد
2. **نوع بر اساس جلد (Per-Unit)**: قیمت دو بار در تیراژ ضرب می‌شد (ضرب مضاعف)
3. **نوع بر اساس صفحه (Page-Based)**: حداقل ۱ واحد کامل تضمین نمی‌شد

### English
The Tabesh plugin pricing system had three types of additional services with mathematical calculation errors:

1. **Fixed Type**: Service price was incorrectly multiplied by quantity
2. **Per-Unit Type**: Price was multiplied by quantity twice (double multiplication bug)
3. **Page-Based Type**: Did not guarantee minimum 1 complete unit

---

## مثال‌های عملی / Practical Examples

### مثال ۱: خدمت ثابت (سلفون) / Example 1: Fixed Service (Cellophane)
**قیمت خدمت / Service Price**: ۵۰٬۰۰۰ تومان  
**تیراژ / Quantity**: ۱۰ جلد

| محاسبه قبل از رفع اشکال<br>Before Fix | محاسبه بعد از رفع اشکال<br>After Fix |
|---|---|
| ❌ ۵۰٬۰۰۰ × ۱۰ = ۵۰۰٬۰۰۰ تومان | ✅ ۵۰٬۰۰۰ تومان (یکبار / once) |

**توضیح / Explanation**: خدمات ثابت باید فقط یکبار در کل فاکتور اعمال شوند، نه به ازای هر جلد.  
Fixed services should be applied once to the entire invoice, not per copy.

---

### مثال ۲: خدمت بر اساس جلد (لب گرد) / Example 2: Per-Unit Service (Round Corners)
**قیمت هر جلد / Price per Unit**: ۲٬۰۰۰ تومان  
**تیراژ / Quantity**: ۱۰ جلد

| محاسبه قبل از رفع اشکال<br>Before Fix | محاسبه بعد از رفع اشکال<br>After Fix |
|---|---|
| ❌ (۲٬۰۰۰ × ۱۰) × ۱۰ = ۲۰۰٬۰۰۰ تومان | ✅ ۲٬۰۰۰ × ۱۰ = ۲۰٬۰۰۰ تومان |

**توضیح / Explanation**: قیمت فقط یکبار در تیراژ ضرب می‌شود، نه دوبار.  
Price is multiplied by quantity only once, not twice.

---

### مثال ۳: خدمت بر اساس صفحه (طراحی) - کمتر از حد / Example 3: Page-Based Service (Design) - Below Threshold
**قیمت هر ۴٬۰۰۰ صفحه / Price per 4,000 Pages**: ۱۰۰٬۰۰۰ تومان  
**تیراژ / Quantity**: ۱۰ جلد  
**صفحات هر جلد / Pages per Copy**: ۲۰۰ صفحه  
**مجموع صفحات / Total Pages**: ۲٬۰۰۰ صفحه

| محاسبه قبل از رفع اشکال<br>Before Fix | محاسبه بعد از رفع اشکال<br>After Fix |
|---|---|
| ❌ ۱۰۰٬۰۰۰ × ceil(۲۰۰۰/۴۰۰۰) × ۱۰<br>= ۱۰۰٬۰۰۰ × ۱ × ۱۰ = ۱٬۰۰۰٬۰۰۰ تومان | ✅ ۱۰۰٬۰۰۰ × max(1, ceil(۲۰۰۰/۴۰۰۰))<br>= ۱۰۰٬۰۰۰ × ۱ = ۱۰۰٬۰۰۰ تومان |

**توضیح / Explanation**: حتی اگر مجموع صفحات کمتر از حد تعیین شده باشد، حداقل یک واحد کامل محاسبه می‌شود و دیگر در تیراژ ضرب نمی‌شود.  
Even if total pages are less than the threshold, minimum 1 complete unit is charged and not multiplied by quantity again.

---

### مثال ۴: خدمت بر اساس صفحه - بیشتر از حد / Example 4: Page-Based Service - Above Threshold
**قیمت هر ۴٬۰۰۰ صفحه / Price per 4,000 Pages**: ۱۰۰٬۰۰۰ تومان  
**تیراژ / Quantity**: ۱۰ جلد  
**صفحات هر جلد / Pages per Copy**: ۴۵۰ صفحه  
**مجموع صفحات / Total Pages**: ۴٬۵۰۰ صفحه

| محاسبه قبل از رفع اشکال<br>Before Fix | محاسبه بعد از رفع اشکال<br>After Fix |
|---|---|
| ❌ ۱۰۰٬۰۰۰ × ceil(۴۵۰۰/۴۰۰۰) × ۱۰<br>= ۱۰۰٬۰۰۰ × ۲ × ۱۰ = ۲٬۰۰۰٬۰۰۰ تومان | ✅ ۱۰۰٬۰۰۰ × max(1, ceil(۴۵۰۰/۴۰۰۰))<br>= ۱۰۰٬۰۰۰ × ۲ = ۲۰۰٬۰۰۰ تومان |

**توضیح / Explanation**: واحدها به سمت بالا گرد می‌شوند (ceil) و فقط یکبار محاسبه می‌شوند.  
Units are rounded up (ceil) and calculated only once.

---

## فرمول‌های صحیح / Correct Formulas

### 1. خدمت ثابت / Fixed Service
```
هزینه خدمت = قیمت
Service Cost = Price

مثال: ۵۰٬۰۰۰ تومان (بدون توجه به تیراژ)
Example: 50,000 Toman (regardless of quantity)
```

### 2. خدمت بر اساس جلد / Per-Unit Service
```
هزینه خدمت = قیمت × تیراژ
Service Cost = Price × Quantity

مثال: ۲٬۰۰۰ × ۱۰ = ۲۰٬۰۰۰ تومان
Example: 2,000 × 10 = 20,000 Toman
```

### 3. خدمت بر اساس صفحه / Page-Based Service
```
واحدها = max(1, ceil(مجموع_صفحات / گام))
هزینه خدمت = قیمت × واحدها

Units = max(1, ceil(Total_Pages / Step))
Service Cost = Price × Units

مثال ۱: max(1, ceil(۲۰۰۰ / ۴۰۰۰)) = max(1, 1) = 1
         ۱۰۰٬۰۰۰ × ۱ = ۱۰۰٬۰۰۰ تومان

مثال ۲: max(1, ceil(۴۵۰۰ / ۴۰۰۰)) = max(1, 2) = 2
         ۱۰۰٬۰۰۰ × ۲ = ۲۰۰٬۰۰۰ تومان
```

---

## تغییرات فنی / Technical Changes

### فایل‌های تغییر یافته / Modified Files

#### 1. `includes/handlers/class-tabesh-pricing-engine.php` (Pricing Engine V2)

**تابع `calculate_extras_cost()` - خطوط ۸۹۲-۹۷۳**

قبل / Before:
```php
private function calculate_extras_cost( $pricing_matrix, $extras, $quantity, $page_count_total ) {
    $total_cost = 0;
    
    foreach ( $extras as $extra ) {
        // ... calculations ...
        $total_cost += $extra_cost;
    }
    
    return $total_cost; // ❌ همه هزینه‌ها با هم
}
```

بعد / After:
```php
private function calculate_extras_cost( $pricing_matrix, $extras, $quantity, $page_count_total ) {
    $fixed_cost = 0;    // ✅ هزینه‌های ثابت جدا
    $variable_cost = 0; // ✅ هزینه‌های متغیر جدا
    
    foreach ( $extras as $extra ) {
        switch ( $type ) {
            case 'fixed':
                $fixed_cost += $price;
                break;
            case 'per_unit':
                $variable_cost += $price * $quantity;
                break;
            case 'page_based':
                $units = max( 1, ceil( $total_pages / $step ) ); // ✅ حداقل ۱ واحد
                $variable_cost += $price * $units;
                break;
        }
    }
    
    return array(
        'fixed'    => $fixed_cost,
        'variable' => $variable_cost
    );
}
```

**تابع `calculate_price()` - خطوط ۴۹۳-۵۰۶**

قبل / Before:
```php
$extras_cost = $this->calculate_extras_cost( ... );
$production_cost_per_book = $total_pages_cost + $cover_cost + $binding_cost + $extras_cost; // ❌
$subtotal = $production_cost_per_book * $quantity; // ❌ ضرب مضاعف
```

بعد / After:
```php
$extras_costs = $this->calculate_extras_cost( ... );
$fixed_extras_cost = $extras_costs['fixed'];
$variable_extras_cost = $extras_costs['variable'];

$production_cost_per_book = $total_pages_cost + $cover_cost + $binding_cost; // ✅ بدون extras
$subtotal_before_extras = $production_cost_per_book * $quantity;
$subtotal = $subtotal_before_extras + $fixed_extras_cost + $variable_extras_cost; // ✅ صحیح
```

#### 2. `includes/handlers/class-tabesh-order.php` (Legacy Pricing Engine V1)

**محاسبه page-based - خط ۳۰۹**

قبل / Before:
```php
$units = ceil( $total_pages / $option_step ); // ❌ بدون تضمین حداقل
```

بعد / After:
```php
$units = max( 1, ceil( $total_pages / $option_step ) ); // ✅ حداقل ۱ واحد
```

---

## نتایج آزمون / Test Results

### فایل تست / Test File: `test-extras-calculation.php`

✅ **همه ۵ آزمون موفق / All 5 Tests Passed**

1. ✅ آزمون خدمت ثابت / Fixed Service Test
2. ✅ آزمون خدمت بر اساس جلد / Per-Unit Service Test
3. ✅ آزمون خدمت بر اساس صفحه (کمتر از حد) / Page-Based Test (Below Threshold)
4. ✅ آزمون خدمت بر اساس صفحه (بیشتر از حد) / Page-Based Test (Above Threshold)
5. ✅ آزمون خدمات ترکیبی / Mixed Services Test

برای اجرای تست‌ها / To run tests:
```bash
php test-extras-calculation.php > test-results.html
```

---

## تأثیر بر کاربران / Impact on Users

### قبل از رفع اشکال / Before Fix
- ❌ قیمت‌های نادرست و بیش از حد
- ❌ عدم اعتماد به سیستم محاسبه
- ❌ شکایات مشتریان از قیمت‌های بالا

### بعد از رفع اشکال / After Fix
- ✅ قیمت‌های صحیح و دقیق
- ✅ اعتماد به سیستم محاسبه
- ✅ رضایت مشتریان از قیمت‌گذاری منصفانه

---

## سازگاری / Compatibility

این رفع اشکال بر روی هر دو موتور قیمت‌گذاری اعمال شده است:
This fix has been applied to both pricing engines:

- ✅ **Pricing Engine V2** (Matrix-based) - `class-tabesh-pricing-engine.php`
- ✅ **Pricing Engine V1** (Legacy) - `class-tabesh-order.php`

---

## نتیجه‌گیری / Conclusion

### فارسی
این رفع اشکال سه مشکل حیاتی در محاسبه خدمات اضافی را برطرف کرده است:

1. **خدمات ثابت** اکنون فقط یکبار اعمال می‌شوند
2. **خدمات بر اساس جلد** دیگر دو بار در تیراژ ضرب نمی‌شوند
3. **خدمات بر اساس صفحه** حداقل ۱ واحد کامل را تضمین می‌کنند

همه تست‌ها موفق بوده و سیستم محاسبه قیمت اکنون به درستی کار می‌کند.

### English
This fix resolves three critical issues in additional services calculation:

1. **Fixed services** are now applied only once
2. **Per-unit services** are no longer multiplied by quantity twice
3. **Page-based services** guarantee minimum 1 complete unit

All tests pass and the pricing calculation system now works correctly.

---

## پیوست‌ها / Attachments

- 📄 Test Results: `test-extras-calculation.php`
- 📝 Code Changes: See commits f417259, f61ad2f, 18c04a2
- 🔍 Code Review: 4 minor comments (non-blocking)

---

**تهیه کننده / Prepared by**: GitHub Copilot  
**بررسی شده توسط / Reviewed by**: Automated Code Review  
**تأیید شده در تاریخ / Approved on**: 2025-12-21
