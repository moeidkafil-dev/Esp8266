# Digital Orders Manager (DOM)

یک پلاگین وردپرس پیشرفته برای مدیریت سفارشات محصولات دیجیتال.

## 📋 ویژگی‌ها

- **جدول سفارشی دیتابیس**: استفاده از جدول اختصاصی برای عملکرد بهینه
- **پنل مدیریت کامل**: نمایش، جستجو، فیلتر و مدیریت سفارشات
- **REST API**: endpointهای امن برای دسترسی برنامه‌نویسی
- **Shortcodeها**: 
  - `[dom_create_order]` - فرم ثبت سفارش
  - `[dom_order_status]` - نمایش وضعیت سفارش
- **سیستم دانلود امن**: بررسی اعتبار قبل از ارائه فایل
- **تنظیمات پیکربندی**: تنظیم تعداد دانلود مجاز و مدت انقضا

## 🚀 نصب

1. آپلود پوشه `digital-orders-manager` به `/wp-content/plugins/`
2. اجرای `composer install` در پوشه پلاگین
3. فعال‌سازی پلاگین از پیشخوان وردپرس

## 📦 وابستگی‌ها

- PHP >= 7.4
- WordPress >= 5.0
- Composer

## 🔧 توسعه

### نصب وابستگی‌ها

```bash
composer install
npm install
```

### بیلد فایل‌های JS/CSS

```bash
npm run build
```

### اجرای تست‌ها

```bash
composer test
```

## 📁 ساختار فایل‌ها

```
digital-orders-manager/
├── assets/              # فایل‌های CSS و JS
├── includes/            # کلاس‌های اصلی
│   ├── Admin/           # کلاس‌های مدیریت
│   ├── API/             # REST API
│   ├── Core/            # هسته پلاگین
│   ├── Database/        # مدیریت دیتابیس
│   └── Frontend/        # shortcodeها
├── tests/               # تست‌های واحد
├── composer.json
├── package.json
└── digital-orders-manager.php
```

## 🔐 امنیت

- استفاده از Nonce برای تمام فرم‌ها
- Sanitization ورودی‌ها
- Escaping خروجی‌ها
- Capability Check برای دسترسی‌ها
- Permission Callback در REST API

## 📝 Shortcodeها

### ثبت سفارش جدید

```
[dom_create_order]
```

### نمایش وضعیت سفارش

```
[dom_order_status order_key="YOUR_ORDER_KEY"]
```

یا از طریق URL:

```
?order_key=YOUR_ORDER_KEY
```

## 🌐 REST API

### دریافت اطلاعات سفارش

```
GET /wp-json/dom/v1/order/{order_key}
```

**مجوزها:**
- ادمین سایت
- کاربر مالک سفارش
- کاربر با ایمیل ثبت‌شده در سفارش

## ⚙️ تنظیمات

- **حداکثر تعداد دانلود**: تعداد دفعات مجاز دانلود هر فایل
- **مدت انقضا**: تعداد روزهای اعتبار لینک دانلود

## 🧪 تست

پلاگین شامل ۳ دسته تست واحد است:

1. تست ایجاد سفارش
2. تست مجوزهای API
3. تست پاک‌سازی uninstall

## 📄 مجوز

GPL v2 or later

## 👨‍💻 توسعه‌دهنده

توسعه یافته با رعایت استانداردهای وردپرس و بهترین روش‌های کدنویسی PHP.
