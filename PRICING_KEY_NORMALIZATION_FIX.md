# Pricing V2 Key Normalization Fix - Summary

## مشکل / Problem

چرخه قیمتگذاری V2 دچار اختلال بنیادی در تطابق و ذخیرهسازی کلیدهای book_size بود که منجر به وضعیت زیر شد:

1. **عدم تطابق کلیدها**: book_size در تنظیمات محصول با نام "رقعی (14×20)" ثبت می‌شد اما هنگام save و retrieve، کلیدها normalize نمی‌شدند و با هم match نمی‌کردند
2. **هیچ book_size فعالی وجود نداشت**: تمام sizes در وضعیت disabled بودند و هیچ complete matrix وجود نداشت
3. **عدم sync بین save و retrieve**: متد save کلید را normalize می‌کرد اما retrieve این کار را نمی‌کرد
4. **مشکل در migration**: مقایسه product parameters با matrix keys بدون normalization انجام می‌شد

## راه‌حل / Solution

### 1. Unified Key Normalization

تمام نقاط save و retrieve الان book_size را قبل از استفاده normalize می‌کنند:

```php
// Before (retrieve methods didn't normalize)
$safe_key = base64_encode( $book_size );

// After (all methods normalize first)
$normalized_book_size = $this->pricing_engine->normalize_book_size_key( $book_size );
$safe_key = base64_encode( $normalized_book_size );
```

**Files Changed:**
- `includes/handlers/class-tabesh-pricing-engine.php`
  - `get_pricing_matrix()`: Normalize input before lookup and cache keys
  - `get_configured_book_sizes()`: Normalize decoded keys
- `includes/handlers/class-tabesh-product-pricing.php`
  - `get_pricing_matrix_for_size()`: Normalize before base64 encoding
- `includes/handlers/class-tabesh-constraint-manager.php`
  - `get_available_book_sizes()`: Normalize both sides of comparison

### 2. Migration Fix

Migration method now normalizes product parameters before comparing:

```php
// Before
$valid_sizes = $decoded; // From product parameters

// After
foreach ( $decoded as $size ) {
    $valid_sizes[] = $this->normalize_book_size_key( $size );
}
```

This prevents healthy matrices from being marked as orphaned when product parameters have descriptions.

### 3. Enhanced Logging

Added detailed debug logging at all transformation points:

```php
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $book_size !== $normalized_book_size ) {
    error_log(
        sprintf(
            'Tabesh: Original: "%s", Normalized: "%s", Key: "%s"',
            $book_size,
            $normalized_book_size,
            $setting_key
        )
    );
}
```

## Testing

Two comprehensive test suites added:

### test-pricing-key-fix.php
Unit tests for individual functions:
- ✅ normalize_book_size_key() function
- ✅ Save and retrieve with clean name
- ✅ Save with description, retrieve with clean name
- ✅ get_configured_book_sizes() normalization

### test-e2e-pricing-cycle.php
End-to-end cycle test:
- ✅ Setup product parameters with descriptions
- ✅ Save pricing matrices
- ✅ Retrieve with original names
- ✅ Cross-retrieve with normalized names
- ✅ Check constraint manager availability
- ✅ Verify order form enabled status

## How It Works Now

### Scenario 1: Clean Names
```
Product params: ["رقعی", "A5", "B5"]
Admin saves pricing for "رقعی"
→ normalize("رقعی") = "رقعی"
→ base64("رقعی") = "2LHZgti524w="
→ Key: pricing_matrix_2LHZgti524w=

Admin retrieves pricing for "رقعی"
→ normalize("رقعی") = "رقعی"
→ base64("رقعی") = "2LHZgti524w="
→ Key: pricing_matrix_2LHZgti524w=
✓ MATCH
```

### Scenario 2: Names with Descriptions
```
Product params: ["رقعی (14×20)", "وزیری (توضیحات)"]
Admin saves pricing for "رقعی (14×20)"
→ normalize("رقعی (14×20)") = "رقعی"
→ base64("رقعی") = "2LHZgti524w="
→ Key: pricing_matrix_2LHZgti524w=

Admin retrieves pricing for "رقعی (14×20)"
→ normalize("رقعی (14×20)") = "رقعی"
→ base64("رقعی") = "2LHZgti524w="
→ Key: pricing_matrix_2LHZgti524w=
✓ MATCH
```

### Scenario 3: Cross-Retrieval
```
Saved with: "رقعی (14×20)"
→ Normalized to: "رقعی"
→ Key: pricing_matrix_2LHZgti524w=

Retrieve with: "رقعی"
→ Normalize: "رقعی"
→ Key: pricing_matrix_2LHZgti524w=
✓ MATCH
```

### Scenario 4: Cache
```
Cache keys use normalized book_size
get_pricing_matrix("رقعی (14×20)")
→ normalize → "رقعی"
→ cache["رقعی"]
→ ✓ FOUND
```

### Scenario 5: Constraint Manager
```
Product params: ["رقعی (14×20)", "A5"]
→ Normalized: ["رقعی", "A5"]

Configured sizes from DB: ["رقعی", "A5"]
→ Already normalized in get_configured_book_sizes()

Comparison: normalize("رقعی (14×20)") vs "رقعی"
→ "رقعی" === "رقعی"
→ ✓ MATCH → Size is ENABLED
```

## Impact

✅ **Before**: هیچ book_size فعال نبود، ماتریس‌ها retrieve نمی‌شدند
✅ **After**: تمام book_sizes با ماتریس کامل فعال هستند

✅ **Before**: Product parameters با توضیحات باعث mismatch می‌شد
✅ **After**: توضیحات در همه جا normalize می‌شوند، تطابق کامل

✅ **Before**: Migration ماتریس‌های سالم را orphan می‌کرد
✅ **After**: Migration فقط واقعا orphan matrices را حذف می‌کند

✅ **Before**: Cache بر اساس کلید خام بود
✅ **After**: Cache بر اساس کلید normalized است

## Backward Compatibility

✅ **تمام تغییرات backward compatible هستند:**
- Matrices قدیمی با کلید normalized همچنان کار می‌کنند
- Matrices با کلید غیر normalized توسط migration fix می‌شوند
- هیچ داده‌ای از دست نمی‌رود
- سیستم به صورت خودکار migrate می‌شود

## Debugging

برای debug مشکلات احتمالی:

1. فعال کنید `WP_DEBUG`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

2. لاگ‌ها را بررسی کنید:
```bash
tail -f wp-content/debug.log | grep "Tabesh"
```

3. تست‌ها را اجرا کنید:
```
https://your-site.com/wp-content/plugins/Tabesh/test-pricing-key-fix.php
https://your-site.com/wp-content/plugins/Tabesh/test-e2e-pricing-cycle.php
```

## Changed Files

1. **includes/handlers/class-tabesh-pricing-engine.php**
   - `get_pricing_matrix()`: +normalize input, +normalize cache keys
   - `get_configured_book_sizes()`: +normalize decoded keys
   - `migrate_mismatched_book_size_keys()`: +normalize product params

2. **includes/handlers/class-tabesh-product-pricing.php**
   - `get_pricing_matrix_for_size()`: +normalize before encoding

3. **includes/handlers/class-tabesh-constraint-manager.php**
   - `get_available_book_sizes()`: +normalize for comparison

4. **test-pricing-key-fix.php** (NEW)
   - Unit tests for normalization

5. **test-e2e-pricing-cycle.php** (NEW)
   - End-to-end cycle tests

## Security

✅ **No security concerns:**
- All normalization uses safe regex
- No user input directly used in queries
- Base64 encoding is safe
- Logging doesn't expose sensitive data

## Performance

✅ **Minimal performance impact:**
- Normalization is a simple regex operation
- Cache improves performance overall
- No additional database queries
- Migration runs only on form load (with transient throttling)

---

**Version:** 1.0
**Date:** 2024-12-19
**Author:** GitHub Copilot / Tabesh Team
