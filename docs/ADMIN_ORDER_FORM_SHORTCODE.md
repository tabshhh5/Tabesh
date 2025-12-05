# Admin Order Form Shortcode Documentation
# مستندات شورتکد فرم سفارش ویژه مدیر

## Overview / نمای کلی

The `[tabesh_admin_order_form]` shortcode provides a frontend form for administrators and authorized users to create orders on behalf of customers. This is useful for phone orders, in-person orders, or situations where administrators need to manually enter order details for customers.

شورتکد `[tabesh_admin_order_form]` یک فرم فرانت‌اند برای مدیران و کاربران مجاز فراهم می‌کند تا سفارشات را به نام مشتریان ثبت کنند. این قابلیت برای سفارشات تلفنی، حضوری یا مواردی که مدیر باید جزئیات سفارش را به صورت دستی وارد کند مفید است.

## Features / ویژگی‌ها

### 1. Access Control / کنترل دسترسی

- **Role-based access**: Configure which WordPress roles can use the form
- **User-based access**: Add specific users to the allowed list
- **Default access**: Administrators always have access
- **Settings management**: Configure access from the plugin admin panel

- **دسترسی بر اساس نقش**: تعیین نقش‌های وردپرسی مجاز برای استفاده از فرم
- **دسترسی بر اساس کاربر**: افزودن کاربران خاص به لیست مجاز
- **دسترسی پیش‌فرض**: مدیران همیشه دسترسی دارند
- **مدیریت تنظیمات**: پیکربندی دسترسی از پنل مدیریت افزونه

### 2. Customer Selection / انتخاب مشتری

- **Search existing users**: Live autocomplete search by name, mobile, or email
- **Create new users**: Register new customers with mobile number as username
- **Auto-select**: Newly created users are automatically selected

- **جستجوی کاربران موجود**: جستجوی زنده با نام، موبایل یا ایمیل
- **ایجاد کاربر جدید**: ثبت مشتریان جدید با شماره موبایل به عنوان نام کاربری
- **انتخاب خودکار**: کاربران جدید به صورت خودکار انتخاب می‌شوند

### 3. Order Form / فرم سفارش

- **All order parameters**: Book title, size, paper type, print type, page counts, quantity, binding, license, cover options, extras, notes
- **Dynamic fields**: Paper weights update based on paper type selection
- **Smart page count**: Page count fields adapt to print type selection
- **Price calculation**: Real-time price calculation with API integration
- **Price override**: Option to set custom price

- **تمام پارامترهای سفارش**: عنوان کتاب، قطع، نوع کاغذ، نوع چاپ، تعداد صفحات، تیراژ، صحافی، مجوز، گزینه‌های جلد، آپشن‌های اضافی، یادداشت
- **فیلدهای پویا**: گرماژ کاغذ بر اساس نوع کاغذ به‌روز می‌شود
- **تعداد صفحات هوشمند**: فیلدهای تعداد صفحات با نوع چاپ تطبیق می‌یابند
- **محاسبه قیمت**: محاسبه قیمت بلادرنگ با API
- **قیمت دلخواه**: امکان تعیین قیمت سفارشی

### 4. Modern UI / رابط کاربری مدرن

- **RTL support**: Full right-to-left support for Persian language
- **Responsive design**: Works on desktop, tablet, and mobile
- **Visual sections**: Organized into numbered sections
- **Animations**: Smooth transitions and feedback
- **Accessibility**: Clear labels and form structure

- **پشتیبانی RTL**: پشتیبانی کامل راست به چپ برای زبان فارسی
- **طراحی واکنش‌گرا**: کار در دسکتاپ، تبلت و موبایل
- **بخش‌های بصری**: سازماندهی در بخش‌های شماره‌دار
- **انیمیشن‌ها**: انتقال‌ها و بازخوردهای روان
- **دسترس‌پذیری**: برچسب‌ها و ساختار فرم واضح

## Usage / استفاده

### Basic Usage / استفاده ساده

Add the shortcode to any page or post:
شورتکد را در هر صفحه یا نوشته اضافه کنید:

```
[tabesh_admin_order_form]
```

### With Custom Title / با عنوان سفارشی

```
[tabesh_admin_order_form title="ثبت سفارش ویژه"]
```

### Shortcode Attributes / پارامترهای شورتکد

| Attribute | Default | Description (EN) | توضیحات (FA) |
|-----------|---------|-----------------|--------------|
| `title` | `ثبت سفارش جدید` | Form title | عنوان فرم |

## Configuration / پیکربندی

### Admin Settings / تنظیمات مدیریت

Navigate to **Tabesh → Settings → Staff Access Control** to configure:
به **تابش ← تنظیمات ← دسترسی کارمندان** بروید برای پیکربندی:

1. **Allowed Roles / نقش‌های مجاز**
   - Select WordPress roles that can access the form
   - Administrator role is always enabled and cannot be disabled
   
   - انتخاب نقش‌های وردپرسی مجاز برای دسترسی به فرم
   - نقش مدیر همیشه فعال است و غیرفعال نمی‌شود

2. **Allowed Users / کاربران مجاز**
   - Search and add specific users
   - Remove users from the list
   - Users in this list can access the form regardless of their role
   
   - جستجو و افزودن کاربران خاص
   - حذف کاربران از لیست
   - کاربران این لیست می‌توانند بدون توجه به نقش خود به فرم دسترسی داشته باشند

### Access Hierarchy / سلسله‌مراتب دسترسی

Access is granted if ANY of the following conditions are met:
دسترسی اعطا می‌شود اگر یکی از شرایط زیر برقرار باشد:

1. User has `manage_woocommerce` capability (WooCommerce administrators)
2. User's role is in the allowed roles list
3. User's ID is in the allowed users list

1. کاربر قابلیت `manage_woocommerce` داشته باشد (مدیران ووکامرس)
2. نقش کاربر در لیست نقش‌های مجاز باشد
3. شناسه کاربر در لیست کاربران مجاز باشد

## Technical Details / جزئیات فنی

### Files / فایل‌ها

| File | Description |
|------|-------------|
| `includes/handlers/class-tabesh-admin-order-form.php` | Main handler class / کلاس اصلی |
| `templates/frontend/admin-order-form.php` | Form template / قالب فرم |
| `assets/css/admin-order-form.css` | Styles / استایل‌ها |
| `assets/js/admin-order-form.js` | JavaScript / جاوااسکریپت |

### Database / پایگاه داده

Settings are stored in `wp_tabesh_settings` table:
تنظیمات در جدول `wp_tabesh_settings` ذخیره می‌شوند:

| Setting Key | Type | Description |
|-------------|------|-------------|
| `admin_order_form_allowed_roles` | JSON array | List of allowed role slugs |
| `admin_order_form_allowed_users` | JSON array | List of allowed user IDs |

### REST API Endpoints / نقاط API

The shortcode uses existing REST API endpoints:
شورتکد از نقاط API موجود استفاده می‌کند:

- `GET /wp-json/tabesh/v1/admin/search-users-live` - Search users
- `POST /wp-json/tabesh/v1/admin/create-user` - Create new user
- `POST /wp-json/tabesh/v1/calculate-price` - Calculate order price
- `POST /wp-json/tabesh/v1/admin/create-order` - Submit order

## Security / امنیت

### Access Control / کنترل دسترسی

- Login required / نیاز به ورود
- Role/user verification / تأیید نقش/کاربر
- Nonce verification for all API calls / تأیید nonce برای تمام درخواست‌های API

### Input Validation / اعتبارسنجی ورودی

- All inputs sanitized server-side / تمام ورودی‌ها در سمت سرور پاکسازی می‌شوند
- Mobile number format validation / اعتبارسنجی فرمت شماره موبایل
- Required field validation / اعتبارسنجی فیلدهای الزامی

### Output Escaping / فرار از خروجی

- All template outputs escaped / تمام خروجی‌های قالب escape می‌شوند
- XSS prevention / جلوگیری از XSS

## Troubleshooting / عیب‌یابی

### Form Not Displaying / فرم نمایش داده نمی‌شود

1. **Check login status**: User must be logged in
2. **Check access permissions**: User must have appropriate role or be in allowed list
3. **Check shortcode syntax**: Ensure correct shortcode `[tabesh_admin_order_form]`
4. **Check WooCommerce**: Plugin requires WooCommerce to be active

1. **بررسی ورود**: کاربر باید وارد شده باشد
2. **بررسی دسترسی**: کاربر باید نقش مناسب یا در لیست مجاز باشد
3. **بررسی نحو شورتکد**: اطمینان از صحت شورتکد `[tabesh_admin_order_form]`
4. **بررسی ووکامرس**: افزونه نیاز به فعال بودن ووکامرس دارد

### User Search Not Working / جستجوی کاربر کار نمی‌کند

1. Check browser console for JavaScript errors
2. Verify REST API is accessible
3. Check nonce is being sent correctly
4. Verify user has proper permissions

1. کنسول مرورگر را برای خطاهای جاوااسکریپت بررسی کنید
2. دسترسی به REST API را تأیید کنید
3. ارسال صحیح nonce را بررسی کنید
4. دسترسی‌های کاربر را تأیید کنید

### Price Not Calculating / قیمت محاسبه نمی‌شود

1. Verify all required fields are filled
2. Check pricing settings are configured
3. Check browser console for API errors
4. Verify REST API endpoint is accessible

1. تکمیل تمام فیلدهای الزامی را تأیید کنید
2. تنظیمات قیمت‌گذاری را بررسی کنید
3. کنسول مرورگر را برای خطاهای API بررسی کنید
4. دسترسی به نقطه REST API را تأیید کنید

## Examples / نمونه‌ها

### Creating a Dedicated Page / ایجاد صفحه اختصاصی

1. Create a new page in WordPress
2. Add the shortcode: `[tabesh_admin_order_form]`
3. Publish the page
4. Add page to menu for administrators

1. یک صفحه جدید در وردپرس بسازید
2. شورتکد را اضافه کنید: `[tabesh_admin_order_form]`
3. صفحه را منتشر کنید
4. صفحه را به منو برای مدیران اضافه کنید

### Restricting to Specific Role / محدود کردن به نقش خاص

1. Go to Tabesh Settings → Staff Access Control
2. Uncheck all roles except the desired one(s)
3. Save settings

1. به تنظیمات تابش ← دسترسی کارمندان بروید
2. تمام نقش‌ها به جز نقش(های) مورد نظر را غیرفعال کنید
3. تنظیمات را ذخیره کنید

## Version History / تاریخچه نسخه

### 1.0.3 (Current)
- Initial release of admin order form shortcode
- Access control via roles and users
- Customer search and creation
- Full order form with price calculation
- Modern responsive UI with RTL support

### 1.0.3 (فعلی)
- انتشار اولیه شورتکد فرم سفارش مدیر
- کنترل دسترسی با نقش‌ها و کاربران
- جستجو و ایجاد مشتری
- فرم کامل سفارش با محاسبه قیمت
- رابط کاربری مدرن واکنش‌گرا با پشتیبانی RTL

## Support / پشتیبانی

For issues, questions, or feature requests:
برای مشکلات، سؤالات یا درخواست ویژگی:

1. Check this documentation / این مستندات را بررسی کنید
2. Review browser console for errors / کنسول مرورگر را برای خطاها بررسی کنید
3. Check WordPress debug log / لاگ دیباگ وردپرس را بررسی کنید
4. Contact plugin maintainers / با نگهدارندگان افزونه تماس بگیرید

## License / مجوز

This feature is part of the Tabesh plugin and is licensed under GPL v2 or later.
این قابلیت بخشی از افزونه تابش است و تحت مجوز GPL نسخه ۲ یا بالاتر منتشر می‌شود.
