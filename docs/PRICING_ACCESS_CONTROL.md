# کنترل دسترسی شورتکد قیمت‌گذاری

## نمای کلی

به‌طور پیش‌فرض، شورتکد `[tabesh_product_pricing]` فقط برای کاربرانی با دسترسی `manage_woocommerce` (مدیران فروشگاه و ادمین‌ها) قابل مشاهده است.

این دسترسی قابل تنظیم است و می‌توانید آن را تغییر دهید.

## تنظیم دسترسی از طریق دیتابیس

در جدول `wp_tabesh_settings`، یک رکورد با کلید `pricing_access_capability` وجود دارد که مقدار آن تعیین می‌کند چه کسانی می‌توانند به این شورتکد دسترسی داشته باشند.

### مقادیر مجاز:

1. **`manage_woocommerce`** (پیش‌فرض)
   - فقط مدیران فروشگاه و ادمین‌ها
   - امن‌ترین گزینه
   - توصیه می‌شود

2. **`edit_shop_orders`**
   - مدیران فروشگاه، ادمین‌ها و کارمندان (Staff)
   - برای محیط‌هایی که کارمندان نیاز به مدیریت قیمت دارند

3. **`manage_options`**
   - فقط ادمین‌های سایت
   - محدودترین دسترسی

## تنظیم از طریق کد PHP

```php
// دریافت instance از Tabesh_Product_Pricing
$product_pricing = new Tabesh_Product_Pricing();

// تنظیم capability جدید
$product_pricing->save_pricing_access_capability( 'edit_shop_orders' );
```

## تنظیم از طریق SQL

```sql
-- بررسی تنظیم فعلی
SELECT * FROM wp_tabesh_settings WHERE setting_key = 'pricing_access_capability';

-- تنظیم دسترسی برای shop managers و staff
UPDATE wp_tabesh_settings 
SET setting_value = 'edit_shop_orders' 
WHERE setting_key = 'pricing_access_capability';

-- بازگشت به تنظیم پیش‌فرض
UPDATE wp_tabesh_settings 
SET setting_value = 'manage_woocommerce' 
WHERE setting_key = 'pricing_access_capability';
```

## نکات امنیتی

⚠️ **هشدار امنیتی:**
- این شورتکد دسترسی به تنظیمات حساس قیمت‌گذاری می‌دهد
- همیشه محدودیت دسترسی را حفظ کنید
- به کاربران عادی دسترسی ندهید
- تغییرات را در محیط تست بررسی کنید

## رفتار در صورت عدم دسترسی

اگر کاربری بدون دسترسی لازم سعی کند شورتکد را مشاهده کند:
- پیام خطای فارسی نمایش داده می‌شود: "شما دسترسی به این بخش را ندارید"
- هیچ اطلاعاتی افشا نمی‌شود
- فرم قیمت‌گذاری نمایش داده نمی‌شود

## تست دسترسی

برای تست دسترسی:

1. به عنوان کاربر با نقش مختلف وارد شوید
2. به صفحه‌ای که شورتکد در آن قرار دارد بروید
3. بررسی کنید که:
   - کاربران مجاز فرم را می‌بینند
   - کاربران غیرمجاز پیام خطا می‌بینند

## مثال‌های کاربردی

### سناریو 1: فقط ادمین
```php
$product_pricing->save_pricing_access_capability( 'manage_options' );
```

### سناریو 2: ادمین + کارمند
```php
$product_pricing->save_pricing_access_capability( 'edit_shop_orders' );
```

### سناریو 3: ادمین + مدیر فروشگاه (پیش‌فرض)
```php
$product_pricing->save_pricing_access_capability( 'manage_woocommerce' );
```

## یادداشت‌های توسعه‌دهنده

- کلاس: `Tabesh_Product_Pricing`
- متد بررسی: `get_pricing_access_capability()`
- متد ذخیره: `save_pricing_access_capability()`
- کلید دیتابیس: `pricing_access_capability`
- جدول: `wp_tabesh_settings`
