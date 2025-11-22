# 📦 راهنمای کامل پنل مدرن پیگیری سفارشات

## مقدمه

پنل مدرن پیگیری سفارشات یک رابط کاربری کاملاً بازطراحی شده برای شورتکد `[tabesh_user_orders]` است که تجربه کاربری عالی و مدرنی را ارائه می‌دهد.

---

## ویژگی‌های اصلی

### 🎨 طراحی مدرن
- **Card-based Design**: طراحی کارت محور با گوشه‌های نرم
- **Neumorphism**: سایه‌های ملایم و طبیعی
- **رنگ‌بندی گرادیانت**: آبی به طلایی
- **انیمیشن‌های روان**: Fade, Slide, Pulse
- **واکنش‌گرا**: بهینه برای موبایل، تبلت و دسکتاپ

### 🔍 جستجوی پیشرفته
- جستجوی زنده (Live Search)
- جستجو در عنوان کتاب، شماره سفارش، قطع
- نمایش ۳ مورد اول
- مرتب‌سازی بر اساس ارتباط

### 📊 خلاصه آماری
- کل سفارشات
- سفارشات تکمیل شده
- سفارشات در حال انجام
- مجموع مبلغ (با طراحی ویژه)

### 🌓 تم روشن و تاریک
- تغییر آسان با یک کلیک
- ذخیره خودکار تنظیمات
- انیمیشن روان در تغییر تم
- رنگ‌های بهینه برای هر تم

### 📈 Progress Stepper
- نمایش بصری مراحل سفارش
- مرحله فعلی با انیمیشن
- رنگ‌آمیزی مراحل تکمیل شده
- آیکون‌های واضح

### 💬 پشتیبانی آسان
- دکمه پشتیبانی در هر سفارش
- مودال با اطلاعات سفارش
- لینک‌های مستقیم تلفن
- دکمه ارسال تیکت

---

## نصب و راه‌اندازی

### پیش‌نیازها
- WordPress 6.8 یا بالاتر
- PHP 8.2.2 یا بالاتر
- افزونه Tabesh نصب شده

### استفاده

کافی است شورتکد زیر را در هر صفحه قرار دهید:

```
[tabesh_user_orders]
```

یا در فایل‌های PHP:

```php
echo do_shortcode('[tabesh_user_orders]');
```

---

## REST API Endpoints

### 1. جستجوی سفارشات
```
GET /wp-json/tabesh/v1/user-orders/search?q={query}
```

**پارامترها:**
- `q` (required): عبارت جستجو

**پاسخ:**
```json
{
  "orders": [
    {
      "id": 1,
      "order_number": "TB-2024-001",
      "book_title": "کتاب نمونه",
      "book_size": "رقعی",
      "page_count": 250,
      "quantity": 100,
      "total_price": 2500000,
      "status": "processing",
      "status_label": "در حال چاپ",
      "created_at": "2024-12-06 10:30:00"
    }
  ]
}
```

### 2. خلاصه آماری
```
GET /wp-json/tabesh/v1/user-orders/summary
```

**پاسخ:**
```json
{
  "total_orders": 12,
  "total_price": 12500000,
  "completed_orders": 8,
  "active_orders": 4
}
```

### 3. جزئیات سفارش
```
GET /wp-json/tabesh/v1/user-orders/{order_id}
```

**پاسخ:**
```json
{
  "order": {
    "id": 1,
    "order_number": "TB-2024-001",
    "book_title": "کتاب نمونه",
    "book_size": "رقعی",
    "paper_type": "تحریر",
    "paper_weight": "80",
    "print_type": "رنگی",
    "page_count_color": 250,
    "page_count_bw": 0,
    "page_count_total": 250,
    "quantity": 100,
    "binding_type": "شومیز",
    "license_type": "دارم",
    "cover_paper_weight": "300",
    "lamination_type": "براق",
    "extras": ["لب گرد", "سلفون"],
    "total_price": 2500000,
    "status": "processing",
    "status_label": "در حال چاپ",
    "status_steps": { ... },
    "created_at": "2024-12-06 10:30:00",
    "updated_at": "2024-12-06 15:45:00",
    "notes": "توضیحات سفارش"
  }
}
```

---

## ساختار CSS

### متغیرهای CSS

تم روشن:
```css
--bg-primary: #F9FAFB;
--bg-secondary: #FFFFFF;
--color-primary: #3B82F6;
--color-secondary: #F59E0B;
--text-primary: #1F2937;
```

تم تاریک:
```css
--bg-primary: #111827;
--bg-secondary: #1F2937;
--color-primary: #60A5FA;
--color-secondary: #FBBF24;
--text-primary: #F9FAFB;
```

### کلاس‌های اصلی

- `.tabesh-user-orders-modern` - کانتینر اصلی
- `.theme-toggle` - دکمه تغییر تم
- `.orders-header` - هدر با جستجو و آمار
- `.summary-cards` - کارت‌های خلاصه
- `.order-card` - کارت سفارش
- `.progress-stepper` - نوار پیشرفت
- `.order-modal` - مودال جزئیات
- `.support-modal` - مودال پشتیبانی

---

## JavaScript API

### کلاس اصلی: `TabeshUserOrdersModern`

```javascript
class TabeshUserOrdersModern {
  // متدهای عمومی
  init()                    // مقداردهی اولیه
  toggleTheme()             // تغییر تم
  loadSummary()             // بارگذاری آمار
  handleSearch(e)           // مدیریت جستجو
  showOrderDetails(id)      // نمایش جزئیات
  showSupport(info)         // نمایش مودال پشتیبانی
}
```

### رویدادها

```javascript
// تغییر تم
$('#theme-toggle').on('click', () => {...});

// جستجو
$('#order-search-input').on('input', (e) => {...});

// نمایش جزئیات
$('.btn-details').on('click', (e) => {...});

// درخواست پشتیبانی
$('.btn-support').on('click', (e) => {...});
```

---

## سفارشی‌سازی

### تغییر رنگ‌ها

در فایل CSS، متغیرهای زیر را ویرایش کنید:

```css
.tabesh-user-orders-modern[data-theme="light"] {
  --color-primary: #YOUR_COLOR;
  --color-secondary: #YOUR_COLOR;
}
```

### افزودن انیمیشن

```css
@keyframes your-animation {
  from { ... }
  to { ... }
}

.your-element {
  animation: your-animation 1s ease;
}
```

### اضافه کردن فیلد جدید

در تمپلیت PHP:

```php
<div class="detail-item">
  <div class="detail-label">عنوان فیلد</div>
  <div class="detail-value"><?php echo esc_html($order->your_field); ?></div>
</div>
```

---

## بهینه‌سازی عملکرد

### Caching
- خلاصه آماری cache می‌شود
- نتایج جستجو با debounce
- تصاویر lazy load

### Minification
برای محیط Production:

```bash
# Minify CSS
npm install -g clean-css-cli
cleancss -o user-orders-modern.min.css user-orders-modern.css

# Minify JS
npm install -g uglify-js
uglifyjs user-orders-modern.js -o user-orders-modern.min.js -c -m
```

---

## عیب‌یابی

### جستجو کار نمی‌کند
1. بررسی کنید که WordPress REST API فعال باشد
2. بررسی Console برای خطاهای JavaScript
3. اطمینان از احراز هویت کاربر

### تم تغییر نمی‌کند
1. بررسی LocalStorage مرورگر
2. پاک کردن Cache مرورگر
3. بررسی Console برای خطا

### انیمیشن‌ها کند هستند
1. غیرفعال کردن Motion Sickness در تنظیمات سیستم‌عامل
2. بررسی عملکرد GPU
3. کاهش تعداد انیمیشن‌های همزمان

---

## سازگاری مرورگرها

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Opera 76+
- ✅ Samsung Internet 14+

### Mobile Browsers
- ✅ Chrome Mobile
- ✅ Safari iOS
- ✅ Firefox Mobile
- ✅ Samsung Internet

---

## دسترسی‌پذیری (Accessibility)

- ✅ کلیدهای میانبر (ESC برای بستن مودال)
- ✅ Focus states واضح
- ✅ ARIA labels
- ✅ پشتیبانی از Screen readers
- ✅ کنتراست رنگی مناسب
- ✅ Reduced motion support

---

## امنیت

### محافظت از ورودی‌ها
```php
// Sanitization
$search = sanitize_text_field($_GET['q']);

// Escaping
echo esc_html($order->book_title);
```

### احراز هویت
```php
// REST API
'permission_callback' => array($this, 'is_user_logged_in')

// Nonce verification
wp_verify_nonce($nonce, 'wp_rest')
```

---

## پشتیبانی و تماس

### راه‌های تماس:
- 📞 تلفن: 0992-982-8425، 0912-553-8967، 025-3723-7301
- 🎫 تیکت: https://pchapco.com/panel/?p=send-ticket

### مستندات بیشتر:
- [README.md](../README.md)
- [API.md](API.md)
- [CONTRIBUTING.md](../CONTRIBUTING.md)

---

## تغییرات نسخه

### نسخه 1.0.2 (دسامبر 2024)
- ✨ بازطراحی کامل UI
- ✨ تم روشن و تاریک
- ✨ جستجوی زنده
- ✨ REST API endpoints جدید
- ✨ Progress stepper حرفه‌ای
- ✨ طراحی واکنش‌گرا
- ✨ انیمیشن‌های مدرن

---

## لایسنس

این پروژه تحت لایسنس GPL v2 یا بالاتر منتشر شده است.

---

**ساخته شده با ❤️ برای Tabesh**
