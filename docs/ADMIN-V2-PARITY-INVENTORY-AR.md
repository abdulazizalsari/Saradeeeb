# مصفوفة التكافؤ بين Admin V2 واللوحة القديمة

تاريخ الفحص: 13 أكتوبر 2026

المستودع المفحوص: `C:\Users\Digital\OneDrive\Desktop\ملفات الموقع\مشروع مدونة سراديبphp\saradeeeb`

الخادم المطابق للمستودع أثناء الاختبار: `http://127.0.0.1:8001` (المسارات `/writers` و`/admin-v2` استجابت من التطبيق الحالي). تم استخدام `8010` مؤقتاً في اختبارات سابقة.

لم يتم إيقاف عملية `8001` أو قتلها؛ تم التحقق سلوكياً من أن المسارات الحالية تصل إلى Saradeeeb.

## الحالة الفعلية

| الوحدة | Admin V2 | الدليل | الحالة |
|---|---|---|---|
| لوحة التحكم والإحصائيات | `/admin-v2` | `AdminV2DashboardController` | IMPLEMENTED BUT NOT FULLY TESTED |
| البحث الإداري | `/admin-v2/search` | بحث المستخدمين والمقالات والطلبات | IMPLEMENTED BUT NOT FULLY TESTED |
| سجل الإجراءات | `/admin-v2/audit` | `audit_logs` مع pagination | IMPLEMENTED AND TESTED |
| طلبات الكتاب | `/admin-v2/applications` | `AdminV2AccountsController` | IMPLEMENTED BUT NOT FULLY TESTED |
| حسابات الكتاب | `/admin-v2/writers` | إيقاف/حظر/إخفاء/سلة | IMPLEMENTED BUT NOT FULLY TESTED |
| إدارة المقالات | `/admin-v2/articles` | قائمة وتغيير حالات أساسي | PARTIALLY IMPLEMENTED |
| إدارة الخواطر | `/admin-v2/thoughts` | قائمة وتغيير حالات أساسي | PARTIALLY IMPLEMENTED |
| الأدوار والصلاحيات | `/admin-v2/roles` | أدوار RBAC وقوالب صلاحيات | IMPLEMENTED BUT NOT FULLY TESTED |
| الفريق والصلاحيات الفردية | `/admin-v2/team` | إنشاء مساعد/مدير وتخصيص منح/منع | IMPLEMENTED BUT NOT FULLY TESTED |
| المالك الرئيسي | bootstrap CLI فقط | لا يوجد رفع تلقائي للحسابات | IMPLEMENTED BUT NOT FULLY TESTED |
| الترجمة واللغات | `/admin/languages` فقط | `AdminToolsController` القديم | PARTIALLY IMPLEMENTED |
| التعليقات | `/admin/comments` | `AdminCommentsController` القديم | PARTIALLY IMPLEMENTED |
| مكتبة الوسائط | `/admin/media` | `AdminToolsController` القديم | PARTIALLY IMPLEMENTED |
| الإعلانات | `/admin/ads` | يعتمد على `AdminController` الفارغ | BLOCKED |
| الصفحات والتنقل والمظهر | `/admin/pages` وغيرها | يعتمد على `AdminController` الفارغ | BLOCKED |
| البودكاست | `/admin/podcast` | يعتمد على `AdminController` الفارغ | BLOCKED |
| التصنيفات والوسوم | `/admin/categories` | يعتمد على `AdminController` الفارغ | BLOCKED |
| الاستيراد والتصدير | `/admin/import-wordpress` | يعتمد على `AdminController` الفارغ | BLOCKED |
| النسخ الاحتياطي والإعدادات | `/admin/backup`, `/admin/settings` | يعتمد جزئياً على controllers قديمة | PARTIALLY IMPLEMENTED |
| المجتمع والرسائل | `/admin/community`, `/admin/messages` | controllers مستقلة موجودة | IMPLEMENTED BUT NOT FULLY TESTED |

## قرار الترحيل

- لم يتم حذف أو إعادة توجيه `/admin`.
- لم يتم تعديل `app/Controllers/AdminController.php` وحجمه ما زال صفراً.
- لم يتم تغيير الحسابات أو المقالات أو الترجمات أو كلمات المرور.
- لا يمكن إعلان التكافؤ الكامل قبل نقل الوحدات المصنفة `BLOCKED` والتحقق منها.
- لا يمكن تنفيذ القطع النهائي إلا بعد موافقة المالك صراحةً.

## الخطوة التالية الآمنة

نقل كل وحدة blocked إلى controller مستقل داخل Admin V2 بعد تحديد منطقها من مصدر موثوق، ثم اختبار القراءة والكتابة والصلاحيات وCSRF وسجل الإجراءات قبل أي تغيير في `/admin`.
