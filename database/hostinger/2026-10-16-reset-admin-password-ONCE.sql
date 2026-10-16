-- إجراء يدوي لمرة واحدة فقط على قاعدة بيانات الموقع الصحيح بعد أخذ نسخة احتياطية.
-- لا تشغله عند تثبيت موقع جديد، ولا تضعه ضمن schema.sql.
-- يعيد تعيين كلمة مرور حساب admin فقط إلى كلمة المرور المختارة سابقاً في المحادثة.
-- يتطلب وجود عمودي password_reset_token_hash و password_reset_expires_at.
START TRANSACTION;
UPDATE users
SET password_hash = '$2y$12$TTPUuhq9maJWUG7NLsR9uONFPJgQ2Bl5OxYp8Z/cVSFslacykIEOm',
    password_reset_token_hash = NULL,
    password_reset_expires_at = NULL
WHERE username = 'admin' AND role = 'admin'
LIMIT 1;
SELECT ROW_COUNT() AS updated_admin_accounts;
COMMIT;
