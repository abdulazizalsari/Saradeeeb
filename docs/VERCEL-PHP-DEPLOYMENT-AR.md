# نشر سراديب PHP/MySQL على Vercel

يبقي هذا الإعداد التطبيق بلغة PHP كما هو. يستدعي `api/index.php` المدخل الأصلي `index.php`، ولا يستبدل المسارات أو Admin V2 أو المشروع بـ Next.js.

## متغيرات بيئة Vercel

أضف القيم من **Vercel → Project Settings → Environment Variables** لكل من Preview وProduction، ولا تحفظ أي قيمة سرية في Git أو `config/local.php`:

- `DB_DRIVER=mysql`
- `DB_HOST` و`DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME` و`DB_PASSWORD`
- `DB_CHARSET=utf8mb4`
- `APP_BASE_URL` (رابط النشر الحالي فقط؛ لا تغيّر النطاق الأساسي الآن)
- `APP_DEBUG=false`
- `APP_RUN_MIGRATIONS=false`
- `APP_SESSION_DRIVER=database`
- `CRON_SECRET` (قيمة عشوائية طويلة لمهمة النشر المجدول)

## قاعدة البيانات والمخطط

- `database/schema.sql` هو مخطط كامل لقاعدة جديدة ولا يحوي حسابات أو كلمات مرور.
- `app/migrations.php` ترحيلات إضافية متوافقة مع قاعدة قائمة؛ لا تعمل تلقائياً في Vercel.
- لقاعدة جديدة: استورد `database/schema.sql` ثم شغّل الترحيلات مرة واحدة من بيئة إدارية آمنة مع `APP_RUN_MIGRATIONS=true`، ثم أعده إلى `false`.
- لقاعدة قائمة: خذ نسخة SQL احتياطية، ثم شغّل الترحيلات مرة واحدة. لا تستورد المخطط الكامل فوق قاعدة إنتاج قائمة.
- قبل تفعيل الجلسات في قاعدة البيانات استورد `database/vercel-session-schema.sql`.

## الوسائط والملفات

نظام ملفات Vercel مؤقت، لذلك لا يصلح `/uploads` أو `/storage` للتخزين الدائم. أضيف محوّل S3-compatible في `App\\Core\\ObjectStorage` لرفع الصور والوثائق وحذفها.

أضف إلى Vercel فقط:

- `OBJECT_STORAGE_DRIVER=s3`
- `OBJECT_STORAGE_ENDPOINT`
- `OBJECT_STORAGE_REGION` (`auto` في Cloudflare R2 أو المنطقة الخاصة بـ S3)
- `OBJECT_STORAGE_BUCKET`
- `OBJECT_STORAGE_ACCESS_KEY_ID` و`OBJECT_STORAGE_SECRET_ACCESS_KEY`
- `OBJECT_STORAGE_PUBLIC_URL` (نطاق CDN/النطاق العام للحاوية)
- `OBJECT_STORAGE_PREFIX=saradeeeb` (اختياري)

اجعل الحاوية أو CDN قابلاً لقراءة الملفات العامة، وامنح المفتاح صلاحيات محدودة للحاوية (`GetObject`, `PutObject`, `DeleteObject`). الرفع الجديد يخزن رابطًا عامًا في قاعدة البيانات، وحذف الوسائط والقصص يحذف الكائن كذلك. يجب نقل الملفات القديمة ذات المسارات `/uploads/...` إلى الحاوية مرة واحدة قبل تحويل المرور؛ لا تتوفر في هذا المستودع ملفات الإنتاج أو بيانات الاتصال لتنفيذ النقل تلقائيًا.

حد Vercel لجسم الطلب 4.5MB؛ الإعداد PHP الحالي يحد الطلب عند 4MB. الملفات الأكبر تحتاج رفعًا مباشرًا موقّعًا إلى التخزين الكائني في مرحلة لاحقة.

## النشر المجدول

يوجد endpoint محمي: `GET /internal/cron/publish-scheduled`. يستدعيه Vercel Cron كل ساعة بحسب `vercel.json` ويرفض أي طلب لا يحمل `Authorization: Bearer $CRON_SECRET`. المهمة idempotent ولا تنشر إلا المقالات المستحقة ذات الترجمة العربية المعتمدة والمكتملة.

## قائمة التحقق قبل الإنتاج

1. اربط `vercel-migration` كـ Preview أولًا.
2. اضبط متغيرات MySQL والجلسات والتخزين الكائني وCron في لوحة Vercel.
3. استورد مخطط/ترحيلات قاعدة البيانات بعد أخذ النسخة الاحتياطية المناسبة.
4. انقل الوسائط القديمة ثم اختبر رفع صورة وملف وحذفهما.
5. اختبر الصفحة الرئيسية، تسجيل الدخول، Admin V2، المقالات وملف الكاتب على رابط Preview.
6. لا ترقِ إلى Production قبل نجاح هذه الاختبارات.
