-- إصلاح آمن وقابل للتكرار: يضيف categories.active إن كان ناقصًا. لا يحذف ولا يغيّر بيانات.
SET @e=(SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='categories' AND COLUMN_NAME='active');
SET @q=IF(@e=0,'ALTER TABLE categories ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order','SELECT 1');
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
