# پیاده‌سازی بهبودهای سایدبار هوش مصنوعی تابش
# AI Sidebar Navigation & Tour Guide Implementation

## خلاصه تغییرات | Summary

این پیاده‌سازی سه مشکل اصلی سایدبار هوش مصنوعی را برطرف می‌کند:

1. **تغییر از Popup به Sidebar ثابت** - سایدبار دیگر به صورت overlay نیست و در کنار سایت قرار می‌گیرد
2. **تشخیص Intent هدایت** - وقتی کاربر درخواست هدایت می‌کند (مثل "میخوام سفارش ثبت کنم")، دکمه‌های هدایت نمایش داده می‌شود
3. **راهنمای تور تعاملی** - با هایلایت و انیمیشن، کاربر را به فرم مورد نظر هدایت می‌کند

---

## 1. تغییر موقعیت Sidebar (از Popup به Fixed)

### مشکل قبلی:
```css
/* قبل - اشتباه */
.tabesh-ai-browser-sidebar {
    position: fixed;
    right: -400px; /* بیرون از صفحه */
}
.tabesh-ai-browser-sidebar.active {
    right: 0; /* با انیمیشن right به داخل می‌آید */
}
```

کلیک روی overlay سایدبار را می‌بست (حتی در دسکتاپ).

### راه‌حل جدید:
```css
/* بعد - صحیح */
.tabesh-ai-browser-sidebar {
    position: fixed;
    right: 0;
    transform: translateX(100%); /* بیرون از صفحه */
}
.tabesh-ai-browser-sidebar.active {
    transform: translateX(0); /* با انیمیشن به داخل می‌آید */
}

/* محتوای سایت به چپ می‌رود */
@media screen and (min-width: 769px) {
    body.ai-browser-open {
        margin-left: 400px;
        transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-x: hidden;
    }
}

/* Overlay فقط در موبایل نمایش داده می‌شود */
.tabesh-ai-browser-overlay {
    display: none; /* پیش‌فرض: مخفی */
}

@media screen and (max-width: 768px) {
    .tabesh-ai-browser-overlay {
        display: block; /* فقط در موبایل */
    }
}
```

### تغییرات JavaScript:
```javascript
// Overlay click (mobile only)
$('#tabesh-ai-browser-overlay').on('click', function() {
    // Only close on mobile devices
    if (window.innerWidth <= 768) {
        closeSidebar();
    }
});
```

**نتیجه:**
- ✅ در دسکتاپ: سایدبار و محتوا کنار هم قابل مشاهده‌اند
- ✅ کلیک روی محتوا سایدبار را نمی‌بندد
- ✅ در موبایل: از پایین به بالا باز می‌شود (70vh)

---

## 2. تشخیص Intent و پیشنهاد هدایت

### کلمات کلیدی تشخیص داده شده:
```javascript
const navigationIntents = {
    'سفارش': 'order_form',
    'ثبت سفارش': 'order_form',
    'میخوام سفارش': 'order_form',
    'چاپ کتاب': 'order_form',
    'قیمت': 'pricing',
    'تماس': 'contact',
    'راهنما': 'help',
    'سبد خرید': 'cart',
    'حساب کاربری': 'account'
};
```

### فرآیند:
1. کاربر پیامی مثل "میخوام سفارش ثبت کنم" می‌فرستد
2. سیستم کلمه "سفارش" را تشخیص می‌دهد → `intentType = 'order_form'`
3. AI پاسخ خود را می‌دهد
4. دکمه‌های پیشنهاد هدایت نمایش داده می‌شوند:

```html
<div class="tabesh-ai-navigation-offer">
    <p>میخواهید به صفحه <strong>سفارش</strong> بروید؟</p>
    <div class="tabesh-ai-offer-buttons">
        <button class="tabesh-ai-btn-primary">بله، ببرم 🚀</button>
        <button class="tabesh-ai-btn-secondary">اول نشونم بده 👆</button>
        <button class="tabesh-ai-btn-tertiary">نه، ممنون</button>
    </div>
</div>
```

### عملکرد دکمه‌ها:

**دکمه "بله، ببرم":**
```javascript
function navigateToPage(url) {
    addMessage('در حال انتقال... ⏳', 'bot');
    setTimeout(() => window.location.href = url, 500);
}
```

**دکمه "اول نشونم بده":**
```javascript
function startTourGuide(targetUrl) {
    // اگر در همین صفحه هستیم، تور را نشان بده
    if (window.location.href.includes(targetUrl)) {
        closeSidebar();
        highlightOrderForm();
    } else {
        // اگر نه، ابتدا به صفحه برو
        sessionStorage.setItem('tabesh_show_tour', targetUrl);
        window.location.href = targetUrl;
    }
}

// بعد از بارگذاری صفحه جدید
function checkPendingTour() {
    const pendingTour = sessionStorage.getItem('tabesh_show_tour');
    if (pendingTour) {
        sessionStorage.removeItem('tabesh_show_tour');
        setTimeout(() => highlightOrderForm(), 1000);
    }
}
```

---

## 3. راهنمای تور با Highlight

### پیدا کردن فرم:
```javascript
const form = document.querySelector(
    '.tabesh-order-form, #order-form, [data-tabesh-form], .woocommerce-form, form.checkout'
);
```

### ایجاد Highlight Overlay:
```javascript
function highlightOrderForm() {
    // Scroll to form
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // Create highlight
    const highlight = document.createElement('div');
    highlight.className = 'tabesh-ai-highlight-overlay';
    
    const rect = form.getBoundingClientRect();
    highlight.style.cssText = `
        position: fixed;
        top: ${rect.top - 20}px;
        left: ${rect.left - 20}px;
        width: ${rect.width + 40}px;
        height: ${rect.height + 40}px;
        pointer-events: none;
        z-index: 999998;
    `;
    
    highlight.innerHTML = `
        <div class="tabesh-ai-spotlight"></div>
        <div class="tabesh-ai-arrow">👆</div>
        <div class="tabesh-ai-tooltip">
            اینجا میتونید سفارش ثبت کنید!<br>
            <small>روی فیلدها کلیک کنید تا راهنمایی بگیرید</small>
        </div>
    `;
    
    document.body.appendChild(highlight);
    form.classList.add('tabesh-ai-pulse-highlight');
    
    // Remove after 8 seconds or on click
    setTimeout(() => {
        highlight.remove();
        form.classList.remove('tabesh-ai-pulse-highlight');
    }, 8000);
}
```

### انیمیشن‌های CSS:
```css
/* پالس کردن border */
@keyframes pulse-border {
    0%, 100% { box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.5); }
    50% { box-shadow: 0 0 0 8px rgba(102, 126, 234, 0.3); }
}

/* پالس کردن spotlight */
@keyframes spotlight-pulse {
    0%, 100% {
        border-color: #667eea;
        box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
    }
    50% {
        border-color: #764ba2;
        box-shadow: 0 0 40px rgba(118, 75, 162, 0.5);
    }
}

/* پریدن فلش */
@keyframes bounce-arrow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}
```

---

## 4. تنظیمات مسیرها در ادمین

### مکان: `تنظیمات تابش > هوش مصنوعی > مسیرهای هدایت هوشمند`

فیلدهای اضافه شده:
```php
// templates/admin/admin-settings.php

<tr>
    <th><label>مسیرهای صفحات</label></th>
    <td>
        <table class="widefat">
            <tr>
                <td><label>صفحه ثبت سفارش</label></td>
                <td><input name="ai_nav_route_order_form" value="/order-form/"></td>
            </tr>
            <tr>
                <td><label>صفحه قیمت‌ها</label></td>
                <td><input name="ai_nav_route_pricing" value="/pricing/"></td>
            </tr>
            <!-- ... و غیره -->
        </table>
    </td>
</tr>
```

### ذخیره تنظیمات:
```php
// includes/handlers/class-tabesh-admin.php

$nav_routes = array();
if (isset($post_data['ai_nav_route_order_form'])) {
    $nav_routes['order_form'] = sanitize_text_field($post_data['ai_nav_route_order_form']);
}
// ... برای بقیه مسیرها

update_option('tabesh_ai_navigation_routes', $nav_routes);
```

### انتقال به JavaScript:
```php
// includes/ai/class-tabesh-ai-browser.php

$nav_routes = get_option('tabesh_ai_navigation_routes', array(
    'order_form' => '/order-form/',
    'pricing' => '/pricing/',
    'contact' => '/contact/',
    'help' => '/help/',
    'cart' => '/cart/',
    'account' => '/my-account/',
));

wp_add_inline_script(
    'tabesh-ai-browser',
    'window.tabeshAIRoutes = ' . wp_json_encode($nav_routes) . ';',
    'before'
);
```

---

## تست و اجرا

### 1. تست فایل HTML:
```bash
# باز کردن فایل در مرورگر
open test-ai-sidebar-navigation.html
```

این فایل شامل 4 تست است:
1. ✅ تست موقعیت sidebar (margin-left)
2. ✅ تست کلیک بیرون (نباید بسته شود)
3. ⏳ تست navigation intent (نیاز به WordPress)
4. ✅ تست راهنمای تور (demo)

### 2. تست در WordPress:
1. فعال کردن افزونه تابش
2. رفتن به `تنظیمات تابش > هوش مصنوعی`
3. فعال کردن "نوار کناری هوشمند"
4. تنظیم مسیرهای صفحات
5. ذخیره تنظیمات
6. باز کردن صفحه اصلی سایت
7. کلیک روی دکمه AI در گوشه

### 3. سناریوهای تست:

**سناریو 1: تست موقعیت**
- [ ] دکمه AI را کلیک کنید
- [ ] سایدبار از راست باز شود
- [ ] محتوای صفحه به چپ حرکت کند
- [ ] روی محتوای صفحه کلیک کنید
- [ ] سایدبار باید باز بماند (در دسکتاپ)

**سناریو 2: تست navigation intent**
- [ ] در چت بنویسید: "میخوام سفارش ثبت کنم"
- [ ] AI پاسخ می‌دهد
- [ ] دکمه‌های "بله، ببرم" و "اول نشونم بده" ظاهر می‌شوند
- [ ] کلیک روی "بله، ببرم" → به صفحه سفارش می‌رود
- [ ] کلیک روی "اول نشونم بده" → فرم هایلایت می‌شود

**سناریو 3: تست tour guide**
- [ ] فرم سفارش باید با border آبی pulse کند
- [ ] فلش 👆 باید بالا و پایین برود
- [ ] Tooltip باید بالای فرم نمایش داده شود
- [ ] بعد از 8 ثانیه یا کلیک، highlight حذف شود

---

## فایل‌های تغییر یافته

| فایل | تغییرات |
|------|---------|
| `assets/css/ai-browser.css` | ✅ تغییر از `right` به `transform`<br>✅ افزودن استایل‌های navigation offer<br>✅ افزودن انیمیشن‌های tour guide |
| `assets/js/ai-browser.js` | ✅ تغییر overlay click handler<br>✅ افزودن تابع `detectNavigationIntent()`<br>✅ افزودن تابع `highlightOrderForm()`<br>✅ افزودن تابع `checkPendingTour()` |
| `templates/admin/admin-settings.php` | ✅ افزودن جدول مسیرهای هدایت (6 فیلد) |
| `includes/handlers/class-tabesh-admin.php` | ✅ افزودن ذخیره مسیرها در `update_option()` |
| `includes/ai/class-tabesh-ai-browser.php` | ✅ افزودن `wp_add_inline_script()` برای routes |

---

## سازگاری

- ✅ **RTL Support**: تمام استایل‌ها با راست به چپ سازگار هستند
- ✅ **Responsive**: در موبایل، sidebar از پایین باز می‌شود (70vh)
- ✅ **WordPress Standards**: تمام کدها طبق استانداردهای WordPress
- ✅ **Security**: استفاده از `sanitize_text_field()` و `esc_attr()`
- ✅ **Performance**: استفاده از CSS animations به جای JavaScript

---

## نکات امنیتی

1. **Input Sanitization**:
```php
$nav_routes['order_form'] = sanitize_text_field($post_data['ai_nav_route_order_form']);
```

2. **Output Escaping**:
```php
value="<?php echo esc_attr(get_option('tabesh_ai_nav_route_order_form', '/order-form/')); ?>"
```

3. **Nonce Verification**: همه درخواست‌های AJAX از nonce استفاده می‌کنند

4. **XSS Prevention**: 
```javascript
function escapeHtml(text) {
    const map = {
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        '"': '&quot;', "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
```

---

## لینک‌های مفید

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [CSS Transform vs Position](https://www.paulirish.com/2012/why-moving-elements-with-translate-is-better-than-posabs-topleft/)
- [RTL Styling](https://rtlstyling.com/)

---

## به‌روزرسانی‌های آینده

پیشنهادات برای نسخه‌های بعدی:

1. **تشخیص Intent هوشمندتر**: استفاده از NLP برای تشخیص دقیق‌تر
2. **تورهای چند مرحله‌ای**: راهنمایی گام به گام
3. **آمار و گزارش**: ثبت اینکه کدام intent بیشترین استفاده را دارد
4. **A/B Testing**: تست رفتارهای مختلف sidebar

---

تاریخ: ۲۴ دسامبر ۲۰۲۵
نسخه: 1.0.0
