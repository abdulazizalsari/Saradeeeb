# نشر سراديب على Vercel بواسطة PHP Community Runtime

هذا الإعداد يبقي تطبيق PHP/MySQL كما هو؛ المدخل `api/index.php` يستدعي `index.php` الأصلي ولا يبدّل المسارات أو Admin V2.

## متغيرات Vercel المطلوبة

أضف القيم إلى **Production** و**Preview** من لوحة Vercel، ولا تضعها في Git:

- `DB_DRIVER=mysql`
- `DB_HOST` و`DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME` و`DB_PASSWORD`
- `DB_CHARSET=utf8mb4`
- `APP_BASE_URL` (رابط نشر Vercel أو النطاق النهائي)
- `APP_DEBUG=false`
- `APP_RUN_MIGRATIONS=false`
- `APP_SESSION_DRIVER=database`

قبل تفعيل `APP_SESSION_DRIVER=database` استورد `database/vercel-session-schema.sql` في قاعدة MySQL الخارجية. الترحيل إضافي ولا يحذف بيانات.

## الصور والملفات

نظام الملفات في Vercel للقراءة فقط، ما عدا `/tmp` المؤقت؛ لذلك لا يمكن استخدام `/uploads` أو `/storage` لتخزين رفع دائم. انقل ملفات الإنتاج الموجودة إلى Object Storage/CDN (مثل S3 أو Cloudflare R2 أو Vercel Blob) وطبّق محول رفع مناسب قبل تفعيل رفع الصور والملفات في الإنتاج. لا تنشر التطبيق مع ادعاء أن الرفع المحلي دائم.

حد Vercel لجسم الطلب 4.5MB؛ أعد `api/php.ini` حد PHP إلى 4MB. الرفع الأكبر يحتاج رفعاً مباشراً إلى التخزين الخارجي.

## التشغيل المجدول

`cron/publish_scheduled.php` هو برنامج CLI ولا يعمل تلقائياً في PHP Community Runtime. شغّله من خدمة cron خارجية أو حوّله لاحقاً إلى endpoint محمي بمفتاح cron.

## خطوات آمنة قبل Production

1. أنشئ مشروع Vercel واربط فرع `vercel-migration` كـ Preview أولاً.
2. اضبط متغيرات البيئة أعلاه واسمح للـ DB الخارجي باتصالات Vercel.
3. استورد جدول الجلسات بعد أخذ نسخة SQL احتياطية من قاعدة الإنتاج.
4. تحقق من الصفحة الرئيسية، تسجيل الدخول، Admin V2، وملف الكاتب على رابط Preview.
5. لا تجعل هذا النشر Production قبل نقل الصور/الملفات إلى تخزين دائم واختبار الرفع.
