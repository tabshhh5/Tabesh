# راهنمای رفع مشکلات تنظیمات قیمت‌گذاری
# Price Configuration Troubleshooting Guide

## نسخه فارسی (Persian Version)

### مشکل
تنظیمات قیمت‌گذاری در پنل مدیریت افزونه تابش به درستی ذخیره نمی‌شوند یا نمایش داده نمی‌شوند.

### بخش‌های تحت تأثیر
1. **ضریب قطع کتاب** (Book cutting coefficients)
2. **قیمت انواع کاغذ** (Price of paper types)
3. **قیمت انواع سلفون** (Price of cellophane types)
4. **قیمت انواع صحافی** (Price of binding types)
5. **قیمت آپشن‌ها** (Price of options)

### راه‌حل‌های پیاده‌سازی شده

#### 1. لاگ‌گذاری پیشرفته
افزونه اکنون تمام عملیات ذخیره‌سازی را ثبت می‌کند. برای مشاهده لاگ‌ها:

```php
// در فایل wp-config.php اضافه کنید:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

سپس فایل `wp-content/debug.log` را بررسی کنید.

#### 2. ابزار تشخیص مشکلات
یک ابزار تشخیصی جامع ایجاد شده است که می‌تواند مشکلات را شناسایی کند.

**نحوه استفاده:**
1. فایل `tabesh-diagnostic.php` را در ریشه وردپرس قرار دهید
2. به آدرس `http://yoursite.com/tabesh-diagnostic.php` بروید
3. گزارش تشخیصی را بررسی کنید
4. **مهم:** پس از استفاده، فایل را حذف کنید!

#### 3. رابط کاربری بهبود یافته
تنظیمات قیمت‌گذاری اکنون شامل:
- راهنمای دقیق‌تر برای هر فیلد
- متن کمکی inline
- نمونه مقادیر پیش‌فرض
- شمارنده تعداد ورودی‌ها
- Placeholder برای راهنمایی بهتر

### دستورالعمل گام به گام

#### مرحله 1: فعال کردن حالت دیباگ
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### مرحله 2: اجرای ابزار تشخیص
1. فایل `tabesh-diagnostic.php` را آپلود کنید
2. در مرورگر به آن دسترسی پیدا کنید
3. بررسی کنید که همه بخش‌ها PASS باشند
4. اگر FAIL است، دلیل را در گزارش ببینید

#### مرحله 3: تست تنظیمات
1. به **تابش > تنظیمات** بروید
2. تب **قیمت‌گذاری** را باز کنید
3. کنسول مرورگر را باز کنید (F12)
4. در فیلد "ضرایب قطع کتاب" وارد کنید:
   ```
   A5=1
   A4=1.5
   Test=2.0
   ```
5. روی "ذخیره تنظیمات" کلیک کنید
6. کنسول را بررسی کنید - باید پیام‌های "Tabesh:" ببینید
7. صفحه را رفرش کنید
8. بررسی کنید که مقادیر همچنان نمایش داده می‌شوند

#### مرحله 4: بررسی لاگ‌ها
```bash
# مشاهده آخرین خطوط لاگ
tail -f wp-content/debug.log | grep Tabesh
```

باید پیام‌های شبیه این ببینید:
```
Tabesh: Saving pricing_book_sizes with 3 entries
Tabesh: Successfully saved setting: pricing_book_sizes
```

### فرمت صحیح داده‌ها

#### ضرایب قطع کتاب
```
A5=1
A4=1.5
B5=1.2
رقعی=1.1
وزیری=1.3
خشتی=1.4
```

#### قیمت انواع کاغذ
```
تحریر=200
بالک=250
glossy=250
matte=200
cream=180
```

#### قیمت سلفون
```
براق=2000
مات=2500
بدون سلفون=0
```

#### قیمت صحافی
```
شومیز=3000
جلد سخت=8000
گالینگور=6000
سیمی=2000
```

#### قیمت آپشن‌ها
```
لب گرد=1000
خط تا=500
شیرینک=1500
سوراخ=300
شماره گذاری=800
uv_coating=3000
embossing=5000
```

### مشکلات رایج و راه‌حل‌ها

#### مشکل 1: تنظیمات پس از ذخیره پاک می‌شوند
**علت:** JavaScript موفق به تبدیل داده‌ها به JSON نشده است

**راه‌حل:**
1. کنسول مرورگر را باز کنید (F12)
2. پیام‌های خطا را بررسی کنید
3. مطمئن شوید که فرمت داده صحیح است (هر خط: `key=value`)
4. از علامت `=` تنها یک بار در هر خط استفاده کنید

#### مشکل 2: خطای JSON در لاگ
```
Tabesh: JSON decode error for pricing_book_sizes: Syntax error
```

**راه‌حل:**
1. بررسی کنید که خطوط خالی نباشند
2. از کاراکترهای خاص استفاده نکنید
3. از فرمت درست استفاده کنید: `key=value` (بدون فاصله اضافی)

#### مشکل 3: فیلد در POST موجود نیست
```
Tabesh: Field not present in POST data: pricing_book_sizes
```

**راه‌حل:**
1. بررسی کنید که `name` attribute در HTML وجود دارد
2. کنسول مرورگر را بررسی کنید - باید "Field not found in DOM" نبینید
3. کش مرورگر را پاک کنید

#### مشکل 4: جدول دیتابیس موجود نیست
**راه‌حل:**
1. افزونه را غیرفعال کنید
2. افزونه را فعال کنید
3. از طریق phpMyAdmin بررسی کنید که جدول `wp_tabesh_settings` وجود دارد

### ابزارهای کمکی

#### بررسی کنسول مرورگر
```javascript
// در کنسول (Console) تایپ کنید:
console.log(tabeshData);
```

این باید تمام تنظیمات را نشان دهد.

#### بررسی دیتابیس
```sql
-- Note: Replace 'wp_' with your actual WordPress table prefix
SELECT * FROM wp_tabesh_settings 
WHERE setting_key LIKE 'pricing_%';

-- Or use this to get the prefix dynamically:
-- SELECT * FROM {your_prefix}_tabesh_settings 
-- WHERE setting_key LIKE 'pricing_%';
```

باید 5 ردیف ببینید (یکی برای هر فیلد قیمت‌گذاری).

### پشتیبانی بیشتر

اگر مشکل همچنان ادامه دارد:

1. **فایل لاگ را جمع‌آوری کنید:**
   ```bash
   tail -100 wp-content/debug.log > tabesh-debug.txt
   ```

2. **اسکرین‌شات کنسول مرورگر را تهیه کنید**

3. **خروجی ابزار تشخیص را ذخیره کنید**

4. **اطلاعات محیط را ارائه دهید:**
   - نسخه وردپرس
   - نسخه PHP
   - تنظیمات WP_DEBUG

---

## English Version

### Problem
Price configuration settings in the Tabesh plugin admin panel are not saving or displaying correctly.

### Affected Sections
1. **Book cutting coefficients** (ضریب قطع کتاب)
2. **Price of paper types** (قیمت انواع کاغذ)
3. **Price of cellophane types** (قیمت انواع سلفون)
4. **Price of binding types** (قیمت انواع صحافی)
5. **Price of options** (قیمت آپشن‌ها)

### Implemented Solutions

#### 1. Enhanced Logging
The plugin now logs all save operations. To see logs:

```php
// Add to wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Then check `wp-content/debug.log`.

#### 2. Diagnostic Tool
A comprehensive diagnostic tool has been created to identify issues.

**How to use:**
1. Upload `tabesh-diagnostic.php` to WordPress root
2. Access it at `http://yoursite.com/tabesh-diagnostic.php`
3. Review the diagnostic report
4. **Important:** Delete the file after use!

#### 3. Improved UI
Pricing settings now include:
- More detailed guidance for each field
- Inline helper text
- Default example values
- Entry count indicators
- Placeholders for better guidance

### Step-by-Step Instructions

#### Step 1: Enable Debug Mode
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### Step 2: Run Diagnostic Tool
1. Upload `tabesh-diagnostic.php`
2. Access it in browser
3. Check that all sections show PASS
4. If FAIL, see the reason in report

#### Step 3: Test Settings
1. Go to **Tabesh > Settings**
2. Open **Pricing** tab
3. Open browser console (F12)
4. In "Book Size Multipliers" field, enter:
   ```
   A5=1
   A4=1.5
   Test=2.0
   ```
5. Click "Save Settings"
6. Check console - should see "Tabesh:" messages
7. Refresh the page
8. Verify values are still displayed

#### Step 4: Check Logs
```bash
# View recent log entries
tail -f wp-content/debug.log | grep Tabesh
```

Should see messages like:
```
Tabesh: Saving pricing_book_sizes with 3 entries
Tabesh: Successfully saved setting: pricing_book_sizes
```

### Correct Data Format

See Persian version above for exact formats.

### Common Issues and Solutions

See Persian version for detailed troubleshooting steps.

### Additional Support

If issues persist:

1. **Collect log file:**
   ```bash
   tail -100 wp-content/debug.log > tabesh-debug.txt
   ```

2. **Take browser console screenshot**

3. **Save diagnostic tool output**

4. **Provide environment info:**
   - WordPress version
   - PHP version
   - WP_DEBUG settings

---

**Last Updated:** 2025-10-28  
**Version:** 1.0.0
