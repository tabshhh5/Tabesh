# Testing Guide - Order Submission Fix

## راهنمای تست - رفع مشکل ارسال سفارش

### نمای کلی / Overview

این راهنما شامل دستورالعملهای جامع برای تست تغییرات اعمال شده برای رفع خطای 400 در ارسال سفارش است.

This guide contains comprehensive instructions for testing the changes made to fix the 400 error in order submission.

---

## پیش‌نیازها / Prerequisites

1. **محیط توسعه / Development Environment**
   - WordPress 6.8+
   - PHP 8.2.2+
   - WooCommerce (نسخه آخر / latest version)
   - دسترسی به لاگ‌های دیباگ / Access to debug logs

2. **تنظیمات / Settings**
   - فعال کردن WP_DEBUG در `wp-config.php` (فقط محیط توسعه / dev only)
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. **کاربران تست / Test Users**
   - یک حساب کاربری مشتری / Customer account
   - یک حساب کاربری ادمین / Admin account

---

## سناریوهای تست / Test Scenarios

### 1️⃣ تست ارسال سفارش بدون فایل / Order Submission Without File

#### مراحل / Steps:

1. **ورود به حساب کاربری مشتری / Login as Customer**
   - به `/wp-login.php` بروید
   - با حساب مشتری وارد شوید

2. **باز کردن فرم سفارش / Open Order Form**
   - به صفحه‌ای که شورتکد `[tabesh_order_form]` دارد بروید
   - اطمینان حاصل کنید فرم نمایش داده میشود

3. **پر کردن فرم / Fill Form**
   - **عنوان کتاب / Book Title**: "کتاب تست بدون فایل"
   - **قطع کتاب / Book Size**: هر کدام را انتخاب کنید
   - **نوع کاغذ / Paper Type**: هر کدام را انتخاب کنید
   - **گرماژ کاغذ / Paper Weight**: هر کدام را انتخاب کنید
   - **نوع چاپ / Print Type**: رنگی یا سیاه و سفید
   - **تعداد صفحات / Page Count**: 100 صفحه
   - **تعداد / Quantity**: 50 عدد
   - **نوع صحافی / Binding Type**: هر کدام را انتخاب کنید
   - **نوع مجوز / License Type**: "بدون مجوز" را انتخاب کنید (فایل آپلود نمیشود)

4. **محاسبه قیمت / Calculate Price**
   - روی "محاسبه قیمت" کلیک کنید
   - اطمینان حاصل کنید قیمت نمایش داده میشود

5. **ثبت سفارش / Submit Order**
   - روی "ثبت سفارش" کلیک کنید
   - منتظر پاسخ بمانید

#### نتایج مورد انتظار / Expected Results:

✅ **موفق / Success:**
- پیام "سفارش با موفقیت ثبت شد" نمایش داده شود
- پس از 2 ثانیه به صفحه سفارشات هدایت شود
- در کنسول مرورگر لاگ "Submitting without files using JSON" نمایش داده شود

❌ **خطا / Error:**
- اگر خطا رخ داد، پیام خطای فارسی مفهومی نمایش داده شود
- لاگ خطا در کنسول مرورگر نمایش داده شود

#### بررسی دیتابیس / Database Verification:

```sql
SELECT * FROM wp_tabesh_orders ORDER BY id DESC LIMIT 1;
```

**بررسی کنید / Check:**
- `book_title` = "کتاب تست بدون فایل"
- `status` = "pending"
- `files` = NULL یا خالی
- `order_number` شکل `TB-YYYYMMDD-XXXX` دارد

---

### 2️⃣ تست ارسال سفارش با فایل / Order Submission With File

#### آماده‌سازی فایل تست / Prepare Test File:

- **فایل PDF تست / Test PDF**: حجم کمتر از 5MB
- **فایل تصویر تست / Test Image**: JPG یا PNG، کمتر از 5MB

#### مراحل / Steps:

1. **ورود و باز کردن فرم / Login and Open Form**
   - مانند سناریو 1

2. **پر کردن فرم / Fill Form**
   - **عنوان کتاب / Book Title**: "کتاب تست با فایل مجوز"
   - سایر فیلدها را پر کنید
   - **نوع مجوز / License Type**: "دارای مجوز" را انتخاب کنید

3. **آپلود فایل / Upload File**
   - فیلد آپلود فایل باید نمایش داده شود
   - یک فایل PDF یا تصویر انتخاب کنید
   - اطمینان حاصل کنید فایل انتخاب شده است

4. **محاسبه و ثبت / Calculate and Submit**
   - قیمت را محاسبه کنید
   - سفارش را ثبت کنید

#### نتایج مورد انتظار / Expected Results:

✅ **موفق / Success:**
- پیام موفقیت نمایش داده شود
- در کنسول: "Submitting with files using FormData"
- فایل با موفقیت آپلود شود

#### بررسی دیتابیس / Database Verification:

```sql
SELECT book_title, files, status FROM wp_tabesh_orders ORDER BY id DESC LIMIT 1;
```

**بررسی کنید / Check:**
- `book_title` = "کتاب تست با فایل مجوز"
- `files` حاوی داده‌های serialized شامل `license` با `url` و `path`
- فایل در دایرکتوری `wp-content/uploads` ذخیره شده باشد

#### بررسی فایل آپلود شده / Verify Uploaded File:

```bash
# از داخل دایرکتوری WordPress
ls -lh wp-content/uploads/$(date +%Y)/$(date +%m)/
```

فایل آپلود شده باید در این مسیر باشد.

---

### 3️⃣ تست اعتبارسنجی فایل / File Validation Tests

#### تست 3.1: فایل خیلی بزرگ / File Too Large

**مراحل:**
- فایلی با حجم بیش از 5MB را آپلود کنید

**نتیجه مورد انتظار:**
- خطا: "حجم فایل بیش از حد مجاز (5MB) است."
- HTTP Status: 400

#### تست 3.2: نوع فایل غیرمجاز / Invalid File Type

**مراحل:**
- فایل .exe یا .zip را آپلود کنید

**نتیجه مورد انتظار:**
- خطا: "فرمت فایل مجاز نیست. فقط PDF, JPG, PNG مجاز است."
- HTTP Status: 400

---

### 4️⃣ تست احراز هویت / Authentication Tests

#### تست 4.1: کاربر وارد نشده / Not Logged In

**مراحل:**
1. از حساب خارج شوید (Logout)
2. به فرم سفارش بروید
3. سعی کنید سفارش ثبت کنید

**نتیجه مورد انتظار:**
- پیام: "لطفا ابتدا وارد حساب کاربری خود شوید"
- هدایت به صفحه ورود

---

### 5️⃣ تست خطاهای شبکه / Network Error Tests

#### تست 5.1: اتصال قطع شده / Connection Lost

**مراحل:**
1. فرم را پر کنید
2. قبل از ثبت سفارش، اتصال اینترنت را قطع کنید
3. روی "ثبت سفارش" کلیک کنید

**نتیجه مورد انتظار:**
- پیام خطای مناسب نمایش داده شود
- دکمه "ثبت سفارش" دوباره فعال شود

---

## لاگ‌های کنسول / Console Logs

### لاگ‌های مورد انتظار برای ارسال موفق / Expected Logs for Successful Submission

**بدون فایل / Without File:**
```
Tabesh: Submitting without files using JSON
```

**با فایل / With File:**
```
Tabesh: Submitting with files using FormData
```

### لاگ‌های سرور / Server Logs

در `wp-content/debug.log` (اگر WP_DEBUG فعال باشد):

```
Tabesh: submit_order_rest called
Content-Type: application/json (یا multipart/form-data)
Tabesh: Processing JSON request (یا Processing FormData request)
Tabesh: License file detected (اگر فایل آپلود شده باشد)
```

---

## نمونه پیلود‌ها / Sample Payloads

### 1. درخواست JSON (بدون فایل) / JSON Request (No File)

```json
{
  "book_title": "کتاب تست",
  "book_size": "رقعی",
  "paper_type": "گلاسه",
  "paper_weight": "80",
  "print_type": "رنگی",
  "page_count_bw": 0,
  "page_count_color": 100,
  "quantity": 50,
  "binding_type": "گالینگور",
  "license_type": "بدون مجوز",
  "cover_paper_weight": "250",
  "lamination_type": "براق",
  "extras": ["UV", "سلفون"],
  "notes": "توضیحات تست"
}
```

### 2. درخواست FormData (با فایل) / FormData Request (With File)

```
Content-Type: multipart/form-data; boundary=----WebKitFormBoundary...

------WebKitFormBoundary...
Content-Disposition: form-data; name="book_title"

کتاب تست با مجوز
------WebKitFormBoundary...
Content-Disposition: form-data; name="book_size"

رقعی
------WebKitFormBoundary...
[... سایر فیلدها ...]
------WebKitFormBoundary...
Content-Disposition: form-data; name="license_file"; filename="license.pdf"
Content-Type: application/pdf

[... محتویات فایل ...]
------WebKitFormBoundary...--
```

---

## نمونه پاسخ‌ها / Sample Responses

### پاسخ موفق / Success Response

```json
{
  "success": true,
  "order_id": 123,
  "message": "سفارش با موفقیت ثبت شد"
}
```

HTTP Status: 200

### پاسخ خطا - عنوان کتاب خالی / Error - Empty Book Title

```json
{
  "code": "missing_book_title",
  "message": "عنوان کتاب الزامی است.",
  "data": {
    "status": 400
  }
}
```

HTTP Status: 400

### پاسخ خطا - فایل خیلی بزرگ / Error - File Too Large

```json
{
  "code": "file_too_large",
  "message": "حجم فایل بیش از حد مجاز (5MB) است.",
  "data": {
    "status": 400
  }
}
```

HTTP Status: 400

### پاسخ خطا - احراز هویت / Error - Authentication

```json
{
  "code": "not_logged_in",
  "message": "شما باید وارد حساب کاربری خود شوید.",
  "data": {
    "status": 400
  }
}
```

HTTP Status: 400

---

## چک‌لیست تست / Test Checklist

- [ ] ✅ ارسال سفارش بدون فایل کار میکند
- [ ] ✅ ارسال سفارش با فایل PDF کار میکند
- [ ] ✅ ارسال سفارش با فایل تصویر (JPG/PNG) کار میکند
- [ ] ✅ اعتبارسنجی حجم فایل کار میکند (رد فایل > 5MB)
- [ ] ✅ اعتبارسنجی نوع فایل کار میکند (رد فایل‌های غیرمجاز)
- [ ] ✅ بررسی احراز هویت کار میکند
- [ ] ✅ پیام‌های خطا به فارسی و واضح هستند
- [ ] ✅ فایل‌ها در دیتابیس و دیسک ذخیره میشوند
- [ ] ✅ لاگ‌ها به درستی ثبت میشوند
- [ ] ✅ خطاهای شبکه به درستی مدیریت میشوند
- [ ] ✅ دکمه "ثبت سفارش" پس از خطا دوباره فعال میشود
- [ ] ✅ پس از ثبت موفق، به صفحه سفارشات هدایت میشود

---

## ابزارهای مفید / Useful Tools

### 1. بررسی درخواست‌های AJAX / Inspect AJAX Requests

در Chrome DevTools:
1. Network tab را باز کنید
2. فیلتر XHR را فعال کنید
3. درخواست `submit-order` را پیدا کنید
4. Headers, Payload, و Response را بررسی کنید

### 2. بررسی لاگ‌های WordPress / Check WordPress Logs

```bash
tail -f wp-content/debug.log
```

### 3. بررسی دیتابیس / Check Database

```bash
mysql -u username -p database_name

# در MySQL shell:
USE database_name;
SELECT * FROM wp_tabesh_orders ORDER BY id DESC LIMIT 5;
SELECT * FROM wp_tabesh_logs WHERE action = 'order_created' ORDER BY id DESC LIMIT 5;
```

---

## رفع مشکلات رایج / Common Issues Troubleshooting

### مشکل 1: خطای 400 همچنان وجود دارد

**راه‌حل:**
1. کش مرورگر را پاک کنید
2. فایل JS را hard refresh کنید (Ctrl+F5)
3. لاگ‌های کنسول و سرور را بررسی کنید
4. اطمینان حاصل کنید nonce معتبر است

### مشکل 2: فایل آپلود نمیشود

**راه‌حل:**
1. بررسی کنید PHP `upload_max_filesize` و `post_max_size` کافی است
2. دایرکتوری `wp-content/uploads` قابل نوشتن (writable) است
3. فرمت فایل مجاز است (PDF, JPG, PNG)
4. حجم فایل کمتر از 5MB است

### مشکل 3: پیام خطا به انگلیسی است

**راه‌حل:**
1. فایل‌های ترجمه پلاگین را بررسی کنید
2. زبان WordPress روی فارسی تنظیم شده باشد
3. در کد، همه استرینگ‌ها از `__()` استفاده کنند

---

## امنیت / Security

### چک‌لیست امنیتی / Security Checklist

- [x] ✅ همه ورودیها با `sanitize_text_field()` پاک میشوند
- [x] ✅ نوع فایل آپلود شده اعتبارسنجی میشود
- [x] ✅ حجم فایل محدود است (5MB)
- [x] ✅ فایل‌ها از طریق `wp_handle_upload()` آپلود میشوند
- [x] ✅ nonces برای احراز هویت استفاده میشوند
- [x] ✅ `is_user_logged_in` برای permission check استفاده میشود
- [x] ✅ URL فایل با `esc_url_raw()` پاک میشود
- [x] ✅ خطاهای دیتابیس فقط در WP_DEBUG لاگ میشوند

---

## نتیجه‌گیری / Conclusion

این راهنما همه سناریوهای مهم برای تست تغییرات را پوشش میدهد. لطفاً همه موارد را تست کنید و نتایج را گزارش دهید.

This guide covers all important scenarios for testing the changes. Please test all items and report the results.

---

**تاریخ ایجاد / Created:** 2025-11-09  
**نسخه / Version:** 1.0  
**نویسنده / Author:** Tabesh Development Team
