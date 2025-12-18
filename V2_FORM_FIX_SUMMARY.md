# بازسازی فرم V2 و اتصال به چرخه سفارش - خلاصه تغییرات

## نمای کلی (Overview)

این اصلاحات مسائل اساسی در فرم سفارش V2 را برطرف می‌کند و آن را به چرخه کامل ثبت سفارش (Order Workflow) متصل می‌نماید.

## مسائل برطرف شده ✅

### 1. تبدیل داده‌ها برای محاسبه قیمت
**مسئله:** فرم V2 داده‌ها را به فرمتی که Pricing Engine V2 انتظار دارد ارسال نمی‌کرد.

**راه‌حل:**
- تقسیم `page_count` تکی به `page_count_color` و `page_count_bw` بر اساس `print_type`
- اضافه کردن فیلدهای پیش‌فرض: `license_type`, `cover_paper_weight`, `lamination_type`
- ذخیره‌سازی قیمت محاسبه شده در `formState.calculated_price`

**فایل تغییر یافته:** `assets/js/order-form-v2.js`

```javascript
// قبل:
const priceData = {
    book_size: formState.book_size,
    paper_type: formState.paper_type,
    page_count: formState.page_count,  // ❌ فرمت نادرست
    // ...
};

// بعد:
const priceData = {
    book_size: formState.book_size,
    paper_type: formState.paper_type,
    // ✅ تقسیم صحیح بر اساس نوع چاپ
    page_count_color: formState.print_type === 'color' ? formState.page_count : 0,
    page_count_bw: formState.print_type === 'bw' ? formState.page_count : 0,
    cover_paper_weight: formState.cover_weight,
    license_type: 'دارم',  // مقدار پیش‌فرض
    // ...
};
```

### 2. اتصال به چرخه ثبت سفارش
**مسئله:** دکمه "ثبت سفارش" به متد `submit_order()` متصل نبود و داده‌ها را به فرمت صحیح ارسال نمی‌کرد.

**راه‌حل:**
- تبدیل کامل داده‌های V2 به فرمت legacy که `Tabesh_Order::submit_order()` انتظار دارد
- اضافه کردن چک اجباری برای محاسبه قیمت قبل از ثبت سفارش
- بهبود مدیریت خطا با نمایش پیام‌های دقیق از سرور

**فایل تغییر یافته:** `assets/js/order-form-v2.js`

```javascript
function submitOrder() {
    // ✅ چک محاسبه قیمت
    if (!formState.calculated_price) {
        showError('لطفاً ابتدا قیمت را محاسبه کنید.');
        return;
    }
    
    // ✅ تبدیل به فرمت legacy
    const orderData = {
        book_title: formState.book_title,
        // ... تمام فیلدها به فرمت صحیح
        page_count_color: formState.print_type === 'color' ? formState.page_count : 0,
        page_count_bw: formState.print_type === 'bw' ? formState.page_count : 0,
        cover_paper_weight: formState.cover_weight,
        license_type: 'دارم',
        lamination_type: 'براق',
        // ...
    };
}
```

### 3. بهبود لاگینگ و دیباگ
**مسئله:** مشخص نبود چرا فرم کار نمی‌کند یا کجا خطا رخ می‌دهد.

**راه‌حل:**
- افزودن لاگ‌های دقیق در REST API endpoint `/get-allowed-options`
- افزودن stack trace در خطاهای template
- لاگ کردن تعداد book sizes، papers، bindings بازگشتی

**فایل‌های تغییر یافته:**
- `tabesh.php` (REST endpoint)
- `templates/frontend/order-form-v2.php`

```php
// در REST API endpoint
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'Tabesh V2 API: get_allowed_options called for book_size: ' . $book_size );
    error_log( 'Tabesh V2 API: Options returned - papers count: ' . count( $options['allowed_papers'] ?? array() ) );
}

// در template
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'Tabesh Order Form V2: Available book sizes count: ' . count( $available_sizes ) );
    if ( empty( $available_sizes ) ) {
        error_log( 'Tabesh Order Form V2: WARNING - No book sizes configured' );
    }
}
```

### 4. بهبود UX
**مسئله:** کاربر بعد از ثبت سفارش نمی‌دانست کجا برود و پیام‌های خطا واضح نبودند.

**راه‌حل:**
- افزودن `userOrdersUrl` برای redirect اتوماتیک بعد از ثبت موفق
- بهبود نمایش پیام‌های خطای دریافتی از سرور
- نمایش پیام موفقیت قبل از redirect

**فایل‌های تغییر یافته:**
- `tabesh.php` (localized script)
- `assets/js/order-form-v2.js`

```javascript
// افزودن redirect URL
wp_localize_script(
    'tabesh-order-form-v2',
    'tabeshOrderFormV2',
    array(
        'apiUrl'         => rest_url( TABESH_REST_NAMESPACE ),
        'nonce'          => wp_create_nonce( 'wp_rest' ),
        'userOrdersUrl'  => home_url( '/user-orders/' ),  // ✅ جدید
        // ...
    )
);

// در JavaScript
if (response.success) {
    showSuccess('سفارش شما با موفقیت ثبت شد!');
    setTimeout(function() {
        const redirectUrl = response.data?.redirect_url || 
            tabeshOrderFormV2.userOrdersUrl || 
            window.location.origin;
        window.location.href = redirectUrl;
    }, 2000);
}
```

## نحوه فعال‌سازی و تست

### گام 1: فعال‌سازی Pricing Engine V2

برای اینکه فرم V2 کار کند، **حتماً** باید Pricing Engine V2 را فعال کنید:

1. به پنل مدیریت وردپرس بروید
2. به `تنظیمات تابش` > `قیمت‌گذاری محصول` بروید
3. گزینه "فعال‌سازی موتور قیمت‌گذاری V2" را علامت بزنید
4. تنظیمات را ذخیره کنید

### گام 2: تنظیم ماتریس قیمت

حداقل یک قطع کتاب باید پیکربندی شده باشد:

1. در همان صفحه `قیمت‌گذاری محصول`
2. یک قطع کتاب انتخاب کنید (مثلاً A5)
3. ماتریس قیمت را پر کنید:
   - قیمت‌های کاغذ (تحریر، بالک، گلاسه)
   - قیمت‌های صحافی (شومیز، سیمی، جلد سخت)
   - خدمات اضافی (لب گرد، شیرینک، و غیره)
4. ذخیره کنید

### گام 3: افزودن شورتکد به صفحه

شورتکد فرم V2 را به یک صفحه اضافه کنید:

```
[tabesh_order_form_v2]
```

### گام 4: فعال‌سازی Debug Mode (اختیاری برای توسعه)

برای مشاهده لاگ‌های دقیق در `wp-content/debug.log`:

```php
// در wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);  // برای امنیت
```

⚠️ **هشدار:** Debug mode را در محیط تولید (production) فعال نکنید!

### گام 5: تست فرم

1. به صفحه‌ای که شورتکد را اضافه کردید بروید
2. مراحل زیر را طی کنید:
   - انتخاب قطع کتاب (مثلاً A5)
   - انتخاب نوع کاغذ → باید گزینه‌ها بارگذاری شوند
   - انتخاب گرماژ کاغذ
   - انتخاب نوع چاپ (سیاه و سفید / رنگی)
   - وارد کردن تعداد صفحات
   - وارد کردن تیراژ
   - انتخاب نوع صحافی
   - انتخاب گرماژ جلد
   - انتخاب خدمات اضافی (اختیاری)
3. روی "محاسبه قیمت" کلیک کنید
4. قیمت باید نمایش داده شود
5. روی "ثبت سفارش" کلیک کنید
6. باید به صفحه سفارشات کاربر منتقل شوید

## عیب‌یابی (Troubleshooting)

### مشکل: "هیچ قطع کتابی پیکربندی نشده است"

**علت:** Pricing Engine V2 فعال نیست یا هیچ ماتریس قیمتی تنظیم نشده.

**راه‌حل:**
1. بررسی کنید که V2 فعال باشد
2. حداقل یک ماتریس قیمت را تنظیم کنید
3. لاگ‌ها را بررسی کنید:
   ```
   Tabesh Order Form V2: Available book sizes count: 0
   Tabesh Order Form V2: WARNING - No book sizes configured
   ```

### مشکل: فیلدها پر نمی‌شوند (Cascading کار نمی‌کند)

**علت احتمالی:**
1. JavaScript به درستی لود نشده
2. AJAX request به `/get-allowed-options` با خطا مواجه شده

**راه‌حل:**
1. Console مرورگر را باز کنید (F12)
2. به تب Network بروید
3. یک قطع کتاب انتخاب کنید
4. باید یک request به `/wp-json/tabesh/v1/get-allowed-options` ببینید
5. Response را بررسی کنید - باید شامل `allowed_papers` و `allowed_bindings` باشد

### مشکل: "خطا در ثبت سفارش"

**علت احتمالی:**
1. کاربر لاگین نیست
2. فیلدهای الزامی خالی هستند
3. قیمت محاسبه نشده

**راه‌حل:**
1. مطمئن شوید کاربر لاگین است
2. مطمئن شوید همه فیلدها پر شده‌اند
3. قبل از ثبت حتماً "محاسبه قیمت" را کلیک کنید
4. لاگ‌ها را بررسی کنید برای جزئیات بیشتر

## فایل‌های تغییر یافته

```
assets/js/order-form-v2.js          - تبدیل داده‌ها و اتصال به workflow
tabesh.php                          - بهبود REST endpoint و localized script
templates/frontend/order-form-v2.php - بهبود error logging
```

## کامیت‌های مرتبط

1. `feat(v2-js): transform form data to legacy format for price calc and order submission`
   - تبدیل page_count به page_count_color/bw
   - ذخیره قیمت محاسبه شده
   - افزودن چک محاسبه قیمت

2. `fix(v2-core): improve error logging and add userOrdersUrl for redirect`
   - بهبود لاگینگ در REST API
   - افزودن userOrdersUrl
   - بهبود stack trace

## مستندات مرتبط

- `DEPENDENCY_ENGINE_V2_GUIDE.md` - راهنمای Constraint Manager
- `PRICING_ENGINE_V2.md` - راهنمای کامل Pricing Engine V2
- `ORDER_FORM_V2_GUIDE.md` - راهنمای استفاده از فرم V2

## نکات امنیتی

✅ تمام تغییرات از استانداردهای امنیتی WordPress پیروی می‌کنند:
- Sanitization: تمام ورودی‌ها با `sanitize_text_field()`, `intval()` و غیره
- Escaping: تمام خروجی‌ها با `esc_html()`, `esc_attr()` و غیره
- Nonce verification: تمام AJAX requests دارای nonce هستند
- Permission checks: تمام REST endpoints دارای `permission_callback` هستند

## سوالات متداول (FAQ)

**س: آیا این تغییرات با فرم قدیمی (V1) سازگار است؟**
ج: بله، کاملاً سازگار است. فرم V1 همچنان با همان روش قبلی کار می‌کند و هیچ تغییری در آن ایجاد نشده.

**س: آیا باید WP_DEBUG را فعال کنم؟**
ج: برای توسعه و عیب‌یابی بله، ولی در محیط production خیر.

**س: چگونه می‌فهمم V2 فعال شده؟**
ج: اگر فرم نمایش داده شد و گزینه‌ها بارگذاری شدند، V2 فعال است. در غیر این صورت پیام خطا نمایش داده می‌شود.

**س: آیا نیاز به تنظیمات اضافی دارد؟**
ج: فقط فعال‌سازی V2 و تنظیم ماتریس قیمت. بقیه به صورت خودکار کار می‌کند.

## پشتیبانی

در صورت بروز مشکل:
1. لاگ‌های debug را بررسی کنید
2. Console مرورگر را چک کنید
3. Network tab را برای AJAX requests بررسی کنید
4. مستندات را مطالعه کنید

---

**تاریخ آخرین بروزرسانی:** 2025-12-18  
**نسخه:** 1.0.4
