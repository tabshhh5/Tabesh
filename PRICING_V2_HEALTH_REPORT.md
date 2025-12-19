# گزارش سلامت موتور قیمت‌گذاری V2 - Pricing V2 Health Report

> سند راهنمای جامع برای استفاده از سیستم Health Checker و رفع مشکلات موتور قیمت‌گذاری V2

## فهرست مطالب / Table of Contents

**فارسی:**
1. [معرفی](#معرفی)
2. [نحوه استفاده](#نحوه-استفاده)
3. [انواع بررسی‌ها](#انواع-بررسیها)
4. [سطوح شدت (Severity Levels)](#سطوح-شدت-severity-levels)
5. [خطاهای رایج و راه حل](#خطاهای-رایج-و-راه-حل)
6. [نمونه گزارش](#نمونه-گزارش)

**English:**
1. [Introduction](#introduction)
2. [How to Use](#how-to-use)
3. [Check Types](#check-types)
4. [Severity Levels](#severity-levels-english)
5. [Common Errors and Solutions](#common-errors-and-solutions)
6. [Sample Report](#sample-report)

---

## معرفی

Health Checker پیشرفته‌ای که برای اطمینان از سلامت و یکپارچگی کامل سیستم قیمت‌گذاری V2 طراحی شده است. این ابزار به صورت خودکار تمام اجزای سیستم قیمت‌گذاری را بررسی می‌کند و مشکلات احتمالی را **قبل از بروز خطا** در فرم سفارش شناسایی می‌نماید.

### ویژگی‌های کلیدی:
- ✅ بررسی خودکار کامل دیتابیس و ماتریس‌های قیمت
- ✅ تشخیص داده‌های ناقص یا orphan
- ✅ گزارش‌دهی با سطوح شدت (Healthy, Warning, Critical)
- ✅ توصیه‌های اصلاحی قابل اجرا
- ✅ نمایش بصری با HTML و CSS
- ✅ لاگ‌گذاری دقیق برای عیب‌یابی

---

## نحوه استفاده

### 1. نمایش گزارش سلامت در فرم ثبت قیمت

هنگامی که از شورت‌کد `[tabesh_product_pricing]` استفاده می‌کنید، گزارش سلامت **به صورت خودکار** در بالای صفحه نمایش داده می‌شود.

```
[tabesh_product_pricing]
```

### 2. استفاده از API در کد PHP

```php
// دریافت گزارش سلامت به صورت آرایه
$health_data = Tabesh_Pricing_Health_Checker::run_health_check();

// نمایش وضعیت کلی
echo $health_data['overall_status']; // 'healthy', 'warning', یا 'critical'

// دریافت گزارش HTML
$html_report = Tabesh_Pricing_Health_Checker::get_html_report();
echo $html_report;
```

### 3. زمان‌های اجرای خودکار

Health Check به صورت خودکار در موارد زیر اجرا می‌شود:
1. ✅ هنگام بارگذاری فرم ثبت قیمت
2. ✅ پس از ذخیره هر ماتریس قیمت
3. ✅ پس از فعال/غیرفعال کردن موتور V2
4. ✅ پس از تغییر پارامترهای محصول

---

## انواع بررسی‌ها

### 1. Database Check (بررسی دیتابیس)
- **هدف:** اطمینان از وجود جدول `wp_tabesh_settings`
- **Severity:** Critical اگر جدول وجود نداشته باشد
- **راه حل:** فعالسازی مجدد پلاگین

### 2. Product Parameters (پارامترهای محصول)
- **هدف:** بررسی وجود قطع‌های کتاب (book_sizes)
- **Severity:** Critical اگر هیچ قطعی تعریف نشده باشد
- **راه حل:** از تنظیمات → محصولات، قطع‌های کتاب را تعریف کنید

### 3. Pricing Engine V2 Status (وضعیت موتور)
- **هدف:** بررسی فعال بودن موتور قیمت‌گذاری V2
- **Severity:** Warning اگر غیرفعال باشد
- **راه حل:** از فرم ثبت قیمت، موتور V2 را فعال کنید

### 4. Pricing Matrices (ماتریس‌های قیمت)
- **هدف:** بررسی وجود و اعتبار ماتریس‌های قیمت
- **موارد بررسی:**
  - تعداد ماتریس‌های کامل
  - تعداد ماتریس‌های ناقص
  - ماتریس‌های مفقود (بدون قیمت)
  - ماتریس‌های نامعتبر (JSON خراب)
- **Severity:** 
  - Critical: هیچ ماتریس کاملی وجود ندارد
  - Warning: برخی ماتریس‌ها ناقص یا مفقود هستند
  - Healthy: همه ماتریس‌ها کامل هستند

### 5. Orphaned Matrices (ماتریس‌های یتیم)
- **هدف:** شناسایی ماتریس‌هایی که قطع کتاب مربوطه حذف شده
- **Severity:** Warning
- **راه حل:** به صورت خودکار هنگام ذخیره قیمت پاک می‌شوند

### 6. Parameter Consistency (سازگاری پارامترها) 🆕
- **هدف:** بررسی سازگاری بین پارامترهای محصول و ماتریس‌های قیمت
- **موارد بررسی:**
  - تعداد قطع‌های تعریف شده در product parameters
  - تعداد قطع‌هایی که قیمت‌گذاری شده‌اند
  - قطع‌های بدون ماتریس قیمت
- **Severity:**
  - Critical: هیچ قطعی قیمت‌گذاری نشده
  - Warning: برخی قطع‌ها بدون قیمت هستند
  - Healthy: همه قطع‌ها قیمت‌گذاری شده‌اند

### 7. Matrix Completeness (کامل بودن ماتریس‌ها) 🆕
- **هدف:** بررسی کامل بودن هر ماتریس قیمت
- **موارد بررسی:**
  - وجود `page_costs` (قیمت صفحات)
  - وجود `binding_costs` (قیمت صحافی)
  - وجود حداقل یک نوع کاغذ
  - وجود حداقل یک نوع صحافی
- **جزئیات خروجی:**
  - لیست قطع‌های ناقص
  - نوع مشکل هر قطع (page_costs خالی، binding_costs خالی، و غیره)
- **Severity:**
  - Warning: یک یا چند ماتریس ناقص
  - Healthy: همه ماتریس‌ها کامل

### 8. Order Form Availability (فرم سفارش)
- **هدف:** بررسی امکان استفاده از فرم سفارش V2
- **موارد بررسی:**
  - تعداد قطع‌های فعال (enabled)
  - تعداد قطع‌های غیرفعال (disabled)
  - دلایل غیرفعال بودن هر قطع
- **Severity:**
  - Critical: هیچ قطع فعالی برای فرم سفارش وجود ندارد
  - Warning: برخی قطع‌ها غیرفعال هستند
  - Healthy: همه قطع‌ها فعال و آماده استفاده

### 9. Book_Size Key Mismatch (تطابق کلیدهای قطع) 🆕
- **هدف:** شناسایی ماتریس‌های قیمت با کلیدهای نامطابق
- **موارد بررسی:**
  - ماتریس‌هایی که با قطع‌های دارای توضیحات ذخیره شده‌اند
  - تطابق بین کلیدهای ماتریس و تنظیمات محصول
  - قطع‌هایی که به دلیل عدم تطابق کلید غیرفعال هستند
- **مکانیزم اصلاح:**
  - شناسایی خودکار کلیدهای قدیمی (مثلاً "رقعی (14×20)")
  - ادغام داده‌ها در کلیدهای نرمال شده (مثلاً "رقعی")
  - حذف کلیدهای قدیمی
  - فعال‌سازی قطع‌های اصلاح شده
- **Severity:**
  - Warning: ماتریس‌هایی با کلید قدیمی یافت شد (اصلاح خودکار در بارگذاری بعدی)
  - Success: هیچ کلید نامطابقی یافت نشد
- **توصیه:**
  - از فرم ثبت قیمت بازدید کنید تا اصلاح خودکار اعمال شود
  - پس از اصلاح، قطع‌های فعال شده در فرم سفارش نمایش داده می‌شوند

### 10. Cache Status (وضعیت کش)
- **هدف:** بررسی وضعیت کش سیستم
- **Severity:** Success (معمولاً بدون مشکل)

---

## سطوح شدت (Severity Levels)

### 🟢 Healthy (سلامت کامل)
- **معنی:** همه چیز صحیح کار می‌کند
- **اقدام:** نیازی به اقدام نیست
- **رنگ:** سبز
- **آیکون:** ✓

### 🟡 Warning (هشدار)
- **معنی:** مشکلاتی وجود دارد اما سیستم کار می‌کند
- **اقدام:** توصیه به رفع مشکل
- **رنگ:** زرد
- **آیکون:** ⚠

### 🔴 Critical (حیاتی)
- **معنی:** مشکل جدی که مانع کارکرد سیستم می‌شود
- **اقدام:** نیاز به رفع فوری
- **رنگ:** قرمز
- **آیکون:** ✗

---

## خطاهای رایج و راه حل

### ❌ خطا: "هیچ قطع کتابی در تنظیمات محصول تعریف نشده"

**علت:** پارامترهای محصول (book_sizes) خالی است.

**راه حل:**
1. به تنظیمات → محصولات بروید
2. قطع‌های کتاب را تعریف کنید (مثلاً: A5، A4، وزیری، رقعی)
3. تنظیمات را ذخیره کنید

---

### ⚠️ هشدار: "ماتریس قیمت ناقص است! موارد زیر تنظیم نشده‌اند: قیمت صفحات"

**علت:** ماتریس قیمت برای یک قطع ذخیره شده اما `page_costs` خالی است.

**راه حل:**
1. از فرم ثبت قیمت، قطع مورد نظر را انتخاب کنید
2. حداقل یک نوع کاغذ با گرماژ و قیمت را تعریف کنید
3. تنظیمات را ذخیره کنید

---

### ⚠️ هشدار: "X قطع بدون ماتریس قیمت"

**علت:** قطع‌هایی در product parameters تعریف شده‌اند که برای آن‌ها ماتریس قیمت ذخیره نشده.

**راه حل:**
1. از فرم ثبت قیمت، هر کدام از قطع‌های فهرست شده را انتخاب کنید
2. قیمت‌ها را تنظیم کنید
3. تنظیمات را ذخیره کنید

---

### ⚠️ هشدار: "X ماتریس یتیم برای قطع‌های: ..."

**علت:** ماتریس قیمتی در دیتابیس وجود دارد که قطع کتاب آن در product parameters حذف شده.

**راه حل:**
- این ماتریس‌ها به صورت خودکار هنگام ذخیره بعدی پاک می‌شوند
- یا: قطع کتاب را دوباره به product parameters اضافه کنید

---

### 🔴 خطای حیاتی: "فرم سفارش V2 نمی‌تواند کار کند: هیچ قطع فعالی نیست"

**علت:** هیچ قطعی با ماتریس قیمت کامل (papers + bindings) وجود ندارد.

**راه حل:**
1. از فرم ثبت قیمت، حداقل یک قطع را انتخاب کنید
2. هم `page_costs` و هم `binding_costs` را تنظیم کنید
3. اطمینان حاصل کنید که حداقل:
   - یک نوع کاغذ با گرماژ تعریف شده
   - یک نوع صحافی تعریف شده
4. تنظیمات را ذخیره کنید

---

### ⚠️ هشدار: "X ماتریس با کلید قدیمی شناسایی شد" 🆕

**علت:** ماتریس‌های قیمت با قطع‌هایی که شامل توضیحات یا ابعاد در پرانتز هستند (مثلاً "رقعی (14×20)") ذخیره شده‌اند، در حالی که تنظیمات محصول فقط نام اصلی قطع را دارد (مثلاً "رقعی"). این عدم تطابق باعث می‌شود ماتریس‌ها شناسایی نشوند و قطع‌ها غیرفعال باشند.

**نشانه‌های این مشکل:**
- قطع‌های کتاب در فرم سفارش نمایش داده نمی‌شوند
- Health Checker نشان می‌دهد که هیچ قطع فعالی نیست
- ماتریس‌های قیمت ذخیره شده‌اند اما در فرم ویرایش لود نمی‌شوند

**راه حل خودکار:**
1. **بازدید از فرم ثبت قیمت** - سیستم به صورت خودکار مشکل را تشخیص داده و اصلاح می‌کند
2. پیام موفقیت نمایش داده می‌شود که شامل:
   - تعداد ماتریس‌های ادغام شده
   - تعداد کلیدهای قدیمی حذف شده
   - تعداد قطع‌های فعال شده
3. **هیچ اقدام دستی لازم نیست** - همه چیز به صورت خودکار انجام می‌شود

**جزئیات فنی:**
- سیستم کلیدهای قدیمی را شناسایی می‌کند (مثلاً `pricing_matrix_<base64("رقعی (14×20)")>`)
- داده‌ها را در کلید نرمال شده ادغام می‌کند (مثلاً `pricing_matrix_<base64("رقعی")>`)
- کلیدهای قدیمی را حذف می‌کند
- Cache را پاک کرده و قطع‌ها را فعال می‌کند

**پیشگیری:**
- از این پس، هنگام ثبت قیمت برای قطع‌هایی با توضیحات، سیستم به صورت خودکار کلید را نرمال می‌کند
- مثال: اگر "رقعی (14×20)" وارد کنید، به صورت خودکار به "رقعی" تبدیل می‌شود
- این تضمین می‌کند که تطابق کامل بین تنظیمات محصول و ماتریس‌های قیمت وجود دارد

---

## نمونه گزارش

### گزارش Healthy (سلامت کامل)

```
✓ گزارش سلامت سیستم قیمت‌گذاری V2    [سلامت]

📊 جزئیات بررسی‌ها

دیتابیس                    [✓ جدول تنظیمات موجود است]
پارامترهای محصول          [✓ 4 قطع کتاب تعریف شده]
موتور قیمت‌گذاری V2      [✓ موتور قیمت‌گذاری V2 فعال است]
ماتریس‌های قیمت           [✓ 4 ماتریس قیمت کامل]
ماتریس‌های یتیم            [✓ هیچ ماتریس یتیمی وجود ندارد]
سازگاری پارامترها         [✓ همه 4 قطع قیمت‌گذاری شده]
کامل بودن ماتریس‌ها       [✓ همه 4 ماتریس کامل هستند]
فرم سفارش                 [✓ 4 قطع برای فرم سفارش فعال است]
کش                        [✓ Cache در حالت عادی]

🕐 زمان بررسی: 2024-12-19 20:30:45
```

---

### گزارش Warning (هشدار)

```
⚠ گزارش سلامت سیستم قیمت‌گذاری V2    [هشدار]

⚠️ هشدارها (توصیه به رفع)
• 2 ماتریس یتیم برای قطع‌های: خشتی، B5
• ⚠️ 3 از 4 قطع قیمت‌گذاری شده
• ⚠️ 1 ماتریس ناقص، 3 کامل

💡 توصیه‌های اصلاحی
1. ماتریس‌های یتیم هنگام ذخیره فرم ثبت قیمت به صورت خودکار پاک می‌شوند
2. از فرم ثبت قیمت برای هر قطع، ماتریس قیمت تعریف کنید
3. از فرم ثبت قیمت، ماتریس‌های ناقص را تکمیل کنید
4. هر ماتریس باید حداقل یک نوع کاغذ و یک نوع صحافی داشته باشد

📊 جزئیات بررسی‌ها
...
```

---

### گزارش Critical (حیاتی)

```
✗ گزارش سلامت سیستم قیمت‌گذاری V2    [خطای حیاتی]

🚨 خطاهای حیاتی (نیاز به رفع فوری)
• هیچ قطع کتابی در تنظیمات محصول تعریف نشده
• فرم سفارش V2 نمی‌تواند کار کند: هیچ قطع فعالی نیست

💡 توصیه‌های اصلاحی
1. ابتدا به تنظیمات → محصولات بروید و قطع‌های کتاب را تعریف کنید
2. هیچ قطعی برای فرم سفارش فعال نیست
3. برای هر قطع، ماتریس قیمت کامل (با paper costs و binding costs) تنظیم کنید

🕐 زمان بررسی: 2024-12-19 20:35:12
```

---

## Introduction

An advanced Health Checker designed to ensure the complete health and integrity of the Pricing Engine V2 system. This tool automatically checks all components of the pricing system and identifies potential issues **before they cause errors** in the order form.

### Key Features:
- ✅ Automatic complete database and pricing matrix checks
- ✅ Detection of incomplete or orphaned data
- ✅ Reporting with severity levels (Healthy, Warning, Critical)
- ✅ Actionable corrective recommendations
- ✅ Visual display with HTML and CSS
- ✅ Detailed logging for debugging

---

## How to Use

### 1. Display Health Report in Pricing Form

When using the `[tabesh_product_pricing]` shortcode, the health report is **automatically displayed** at the top of the page.

```
[tabesh_product_pricing]
```

### 2. Using the API in PHP Code

```php
// Get health report as array
$health_data = Tabesh_Pricing_Health_Checker::run_health_check();

// Display overall status
echo $health_data['overall_status']; // 'healthy', 'warning', or 'critical'

// Get HTML report
$html_report = Tabesh_Pricing_Health_Checker::get_html_report();
echo $html_report;
```

### 3. Automatic Execution Times

The Health Check runs automatically in the following cases:
1. ✅ When loading the pricing form
2. ✅ After saving any pricing matrix
3. ✅ After enabling/disabling V2 engine
4. ✅ After changing product parameters

---

## Check Types

### 1. Database Check
- **Purpose:** Ensure `wp_tabesh_settings` table exists
- **Severity:** Critical if table doesn't exist
- **Solution:** Reactivate the plugin

### 2. Product Parameters
- **Purpose:** Check existence of book sizes (book_sizes)
- **Severity:** Critical if no sizes defined
- **Solution:** Define book sizes in Settings → Products

### 3. Pricing Engine V2 Status
- **Purpose:** Check if Pricing Engine V2 is enabled
- **Severity:** Warning if disabled
- **Solution:** Enable V2 engine from pricing form

### 4. Pricing Matrices
- **Purpose:** Check existence and validity of pricing matrices
- **Checks:**
  - Number of complete matrices
  - Number of incomplete matrices
  - Missing matrices (without pricing)
  - Invalid matrices (corrupted JSON)
- **Severity:** 
  - Critical: No complete matrices
  - Warning: Some incomplete or missing
  - Healthy: All matrices complete

### 5. Orphaned Matrices
- **Purpose:** Identify matrices whose book size has been deleted
- **Severity:** Warning
- **Solution:** Automatically cleaned up when saving pricing

### 6. Parameter Consistency 🆕
- **Purpose:** Check consistency between product parameters and pricing matrices
- **Checks:**
  - Number of sizes defined in product parameters
  - Number of sizes with pricing configured
  - Sizes without pricing matrices
- **Severity:**
  - Critical: No sizes have pricing
  - Warning: Some sizes without pricing
  - Healthy: All sizes have pricing

### 7. Matrix Completeness 🆕
- **Purpose:** Check completeness of each pricing matrix
- **Checks:**
  - Existence of `page_costs`
  - Existence of `binding_costs`
  - At least one paper type defined
  - At least one binding type defined
- **Output Details:**
  - List of incomplete sizes
  - Issue type for each size (empty page_costs, empty binding_costs, etc.)
- **Severity:**
  - Warning: One or more incomplete matrices
  - Healthy: All matrices complete

### 8. Order Form Availability
- **Purpose:** Check if order form V2 can be used
- **Checks:**
  - Number of enabled sizes
  - Number of disabled sizes
  - Reasons for each disabled size
- **Severity:**
  - Critical: No enabled sizes for order form
  - Warning: Some sizes disabled
  - Healthy: All sizes enabled and ready

### 9. Cache Status
- **Purpose:** Check cache system status
- **Severity:** Success (usually no issues)

---

## Severity Levels (English)

### 🟢 Healthy
- **Meaning:** Everything is working correctly
- **Action:** No action needed
- **Color:** Green
- **Icon:** ✓

### 🟡 Warning
- **Meaning:** Issues exist but system is functional
- **Action:** Recommended to fix
- **Color:** Yellow
- **Icon:** ⚠

### 🔴 Critical
- **Meaning:** Serious issue preventing system operation
- **Action:** Immediate fix required
- **Color:** Red
- **Icon:** ✗

---

## Common Errors and Solutions

### ❌ Error: "No book sizes defined in product settings"

**Cause:** Product parameters (book_sizes) is empty.

**Solution:**
1. Go to Settings → Products
2. Define book sizes (e.g., A5, A4, Vaziri, Roghei)
3. Save settings

---

### ⚠️ Warning: "Pricing matrix incomplete! Missing: page costs"

**Cause:** Pricing matrix saved for a size but `page_costs` is empty.

**Solution:**
1. From pricing form, select the size
2. Define at least one paper type with weight and price
3. Save settings

---

### ⚠️ Warning: "X sizes without pricing matrix"

**Cause:** Sizes defined in product parameters without pricing matrices.

**Solution:**
1. From pricing form, select each listed size
2. Configure prices
3. Save settings

---

### ⚠️ Warning: "X orphaned matrices for sizes: ..."

**Cause:** Pricing matrices exist in database but book size deleted from product parameters.

**Solution:**
- These are automatically cleaned up on next save
- Or: Re-add the book size to product parameters

---

### 🔴 Critical Error: "Order form V2 cannot work: no enabled sizes"

**Cause:** No size with complete pricing matrix (papers + bindings) exists.

**Solution:**
1. From pricing form, select at least one size
2. Configure both `page_costs` and `binding_costs`
3. Ensure at least:
   - One paper type with weight defined
   - One binding type defined
4. Save settings

---

## Sample Report

### Healthy Report

```
✓ Pricing System V2 Health Report    [Healthy]

📊 Check Details

Database                   [✓ Settings table exists]
Product Parameters         [✓ 4 book sizes defined]
Pricing Engine V2          [✓ Pricing engine V2 is enabled]
Pricing Matrices           [✓ 4 complete pricing matrices]
Orphaned Matrices          [✓ No orphaned matrices]
Parameter Consistency      [✓ All 4 sizes have pricing]
Matrix Completeness        [✓ All 4 matrices complete]
Order Form                 [✓ 4 sizes enabled for order form]
Cache                      [✓ Cache is normal]

🕐 Check Time: 2024-12-19 20:30:45
```

---

## نکات مهم / Important Notes

### فارسی:
1. ✅ Health check به صورت خودکار اجرا می‌شود - نیازی به اقدام دستی نیست
2. ✅ گزارش HTML شامل CSS inline است - نیازی به فایل CSS جداگانه نیست
3. ✅ تمام توصیه‌ها قابل اجرا و مشخص هستند
4. ✅ در حالت WP_DEBUG=true، لاگ‌های دقیق‌تر در debug.log ثبت می‌شود

### English:
1. ✅ Health check runs automatically - no manual action needed
2. ✅ HTML report includes inline CSS - no separate CSS file needed
3. ✅ All recommendations are actionable and specific
4. ✅ With WP_DEBUG=true, more detailed logs are written to debug.log

---

## پشتیبانی / Support

در صورت بروز مشکل یا سوال:
- مستندات کامل را در فایل README.md مطالعه کنید
- لاگ‌های WP_DEBUG را بررسی کنید
- از فرم ثبت قیمت، گزارش سلامت را مشاهده کنید

For issues or questions:
- Read complete documentation in README.md
- Check WP_DEBUG logs
- View health report from pricing form

---

**نسخه / Version:** 1.0.0  
**آخرین بروزرسانی / Last Updated:** 2024-12-19  
**توسعه‌دهنده / Developer:** Chapco - تابش Tabesh Team
