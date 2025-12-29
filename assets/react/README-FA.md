# Tabesh Admin Dashboard - React SPA

این پوشه حاوی کد React برای داشبورد مدیریت سفارشات افزونه تابش است.

## نصب و راه‌اندازی

### پیش‌نیازها
- Node.js 18.x یا بالاتر
- npm یا yarn

### نصب وابستگی‌ها
```bash
cd assets/react
npm install
```

### اجرا در حالت توسعه
```bash
npm run dev
```
برنامه روی پورت 3000 اجرا می‌شود: http://localhost:3000

### ساخت نسخه نهایی
```bash
npm run build
```
فایل‌های نهایی در پوشه `../dist/admin-dashboard` ساخته می‌شوند.

### اجرای تست‌ها
```bash
# اجرای همه تست‌ها
npm test

# اجرای تست‌ها در حالت watch
npm run test:watch

# گزارش پوشش کد
npm run test:coverage
```

### بررسی کیفیت کد
```bash
# بررسی ESLint
npm run lint

# اصلاح خودکار مشکلات ESLint
npm run lint:fix

# بررسی TypeScript
npm run type-check
```

## ساختار پروژه

```
src/
├── components/         # کامپوننت‌های React
│   ├── Dashboard/      # کامپوننت اصلی داشبورد
│   ├── OrderTable/     # جدول سفارشات
│   ├── OrderDetails/   # جزئیات سفارش
│   ├── Statistics/     # کارت‌های آماری
│   ├── Filters/        # فیلترهای سفارش
│   ├── FTPStatus/      # وضعیت FTP
│   ├── Notifications/  # نوتیفیکیشن‌های Toast
│   └── UI/            # کامپوننت‌های پایه (Button, Modal, etc.)
├── contexts/          # Context API برای state management
├── hooks/            # Custom hooks
├── services/         # سرویس‌های API
├── types/            # تعریف TypeScript types
├── utils/            # توابع کمکی
├── styles/           # فایل‌های CSS
├── App.tsx           # کامپوننت اصلی
└── main.tsx          # Entry point
```

## ویژگی‌ها

### فنی
- ✅ React 18 با TypeScript
- ✅ Vite برای bundling سریع
- ✅ React Query برای data fetching و caching
- ✅ Context API برای state management
- ✅ Axios برای ارتباط با REST API
- ✅ Jest و React Testing Library برای تست
- ✅ ESLint برای کیفیت کد

### UI/UX
- ✅ پشتیبانی کامل RTL
- ✅ تم روشن و تیره
- ✅ طراحی Responsive
- ✅ نوتیفیکیشن‌های Toast
- ✅ Modal برای جزئیات سفارش
- ✅ فیلترهای پیشرفته
- ✅ Pagination

### قابلیت‌های کسب‌وکار
- ✅ مشاهده و مدیریت سفارشات
- ✅ آمار و گزارش‌گیری
- ✅ فیلتر و جستجوی سفارشات
- ✅ به‌روزرسانی وضعیت سفارش
- ✅ مشاهده جزئیات کامل سفارش
- ✅ نمایش وضعیت FTP

## ادغام با WordPress

داشبورد React از طریق shortcode `[tabesh_admin_dashboard]` در WordPress بارگذاری می‌شود.

### تنظیمات WordPress
1. فایل‌های build شده در `assets/dist/admin-dashboard` قرار می‌گیرند
2. PHP handler باید فایل‌های JS و CSS را enqueue کند
3. تنظیمات از طریق `window.tabeshConfig` به React منتقل می‌شوند

مثال تنظیمات:
```javascript
window.tabeshConfig = {
  nonce: 'wp-nonce-here',
  restUrl: '/wp-json/tabesh/v1',
  restNamespace: 'tabesh/v1',
  currentUserId: 1,
  currentUserRole: 'administrator',
  isAdmin: true,
  canEditOrders: true,
  avatarUrl: 'https://...',
  userName: 'نام کاربر',
  userEmail: 'email@example.com'
}
```

## امنیت

- ✅ نوع Nonce برای همه درخواست‌های API
- ✅ بررسی دسترسی کاربر
- ✅ Sanitization ورودی‌ها
- ✅ Escape خروجی‌ها

## مشارکت

برای افزودن ویژگی جدید:
1. کامپوننت جدید را در پوشه مناسب ایجاد کنید
2. تست برای کامپوننت بنویسید
3. مستندات را به‌روز کنید
4. ESLint را اجرا کنید

## لایسنس

GPL v2 or later
