-- Saradeeeb production recovery: additive schema compatibility only.
-- Run this file ONLY after exporting a full SQL backup from Hostinger/phpMyAdmin.
-- It is safe to re-run: every ALTER is guarded by information_schema checks.
-- No DROP, TRUNCATE, DELETE, or data reset statements are included.

SET NAMES utf8mb4;
SET @schema_name := DATABASE();

-- Preflight: these must exist in the existing Saradeeeb database.
SELECT TABLE_NAME AS required_table,
       CASE WHEN COUNT(*) = 1 THEN 'present' ELSE 'MISSING - stop and deploy the complete PHP schema first' END AS state
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @schema_name
  AND TABLE_NAME IN ('users', 'articles', 'article_translations', 'writer_profiles')
GROUP BY TABLE_NAME;

-- Required by the translation publication gate.
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='article_translations' AND COLUMN_NAME='review_state');
SET @sql := IF(@exists=0, "ALTER TABLE `article_translations` ADD COLUMN `review_state` ENUM('draft','review','approved') NOT NULL DEFAULT 'draft' AFTER `status`", 'SELECT 1');
PREPARE saradeeeb_stmt FROM @sql; EXECUTE saradeeeb_stmt; DEALLOCATE PREPARE saradeeeb_stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='article_translations' AND COLUMN_NAME='completeness_state');
SET @sql := IF(@exists=0, "ALTER TABLE `article_translations` ADD COLUMN `completeness_state` ENUM('complete_ready','incomplete','needs_review','passed') NOT NULL DEFAULT 'complete_ready' AFTER `review_state`", 'SELECT 1');
PREPARE saradeeeb_stmt FROM @sql; EXECUTE saradeeeb_stmt; DEALLOCATE PREPARE saradeeeb_stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='article_translations' AND COLUMN_NAME='completeness_source_hash');
SET @sql := IF(@exists=0, 'ALTER TABLE `article_translations` ADD COLUMN `completeness_source_hash` CHAR(64) NULL AFTER `completeness_state`', 'SELECT 1');
PREPARE saradeeeb_stmt FROM @sql; EXECUTE saradeeeb_stmt; DEALLOCATE PREPARE saradeeeb_stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='article_translations' AND COLUMN_NAME='completeness_translation_hash');
SET @sql := IF(@exists=0, 'ALTER TABLE `article_translations` ADD COLUMN `completeness_translation_hash` CHAR(64) NULL AFTER `completeness_source_hash`', 'SELECT 1');
PREPARE saradeeeb_stmt FROM @sql; EXECUTE saradeeeb_stmt; DEALLOCATE PREPARE saradeeeb_stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='article_translations' AND COLUMN_NAME='completeness_report');
SET @sql := IF(@exists=0, 'ALTER TABLE `article_translations` ADD COLUMN `completeness_report` LONGTEXT NULL AFTER `completeness_translation_hash`', 'SELECT 1');
PREPARE saradeeeb_stmt FROM @sql; EXECUTE saradeeeb_stmt; DEALLOCATE PREPARE saradeeeb_stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='article_translations' AND COLUMN_NAME='completeness_checked_at');
SET @sql := IF(@exists=0, 'ALTER TABLE `article_translations` ADD COLUMN `completeness_checked_at` DATETIME NULL AFTER `completeness_report`', 'SELECT 1');
PREPARE saradeeeb_stmt FROM @sql; EXECUTE saradeeeb_stmt; DEALLOCATE PREPARE saradeeeb_stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='article_translations' AND COLUMN_NAME='completeness_checked_by');
SET @sql := IF(@exists=0, 'ALTER TABLE `article_translations` ADD COLUMN `completeness_checked_by` BIGINT UNSIGNED NULL AFTER `completeness_checked_at`', 'SELECT 1');
PREPARE saradeeeb_stmt FROM @sql; EXECUTE saradeeeb_stmt; DEALLOCATE PREPARE saradeeeb_stmt;

-- Required by the current writer language and Arabic-translation queue code.
SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='articles' AND COLUMN_NAME='original_locale');
SET @sql := IF(@exists=0, "ALTER TABLE `articles` ADD COLUMN `original_locale` VARCHAR(20) NOT NULL DEFAULT 'ar' AFTER `author_id`", 'SELECT 1');
PREPARE saradeeeb_stmt FROM @sql; EXECUTE saradeeeb_stmt; DEALLOCATE PREPARE saradeeeb_stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='writer_profiles' AND COLUMN_NAME='primary_writing_language');
SET @sql := IF(@exists=0, "ALTER TABLE `writer_profiles` ADD COLUMN `primary_writing_language` VARCHAR(20) NOT NULL DEFAULT 'ar' AFTER `contact_email`", 'SELECT 1');
PREPARE saradeeeb_stmt FROM @sql; EXECUTE saradeeeb_stmt; DEALLOCATE PREPARE saradeeeb_stmt;

CREATE TABLE IF NOT EXISTS `translation_validation_resolutions` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `article_id` BIGINT UNSIGNED NOT NULL,
  `locale` VARCHAR(20) NOT NULL,
  `issue_key` CHAR(64) NOT NULL,
  `reason` VARCHAR(1000) NOT NULL,
  `source_hash` CHAR(64) NOT NULL,
  `translation_hash` CHAR(64) NOT NULL,
  `resolved_by` BIGINT UNSIGNED NOT NULL,
  `resolved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_translation_resolution` (`article_id`,`locale`,`issue_key`),
  INDEX `idx_translation_resolution_article_locale` (`article_id`,`locale`),
  CONSTRAINT `fk_translation_resolution_article` FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_translation_resolution_user` FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `content_translation_assignments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `content_type` VARCHAR(40) NOT NULL,
  `content_id` BIGINT UNSIGNED NOT NULL,
  `target_locale` VARCHAR(20) NOT NULL,
  `translator_id` BIGINT UNSIGNED NULL,
  `reviewer_id` BIGINT UNSIGNED NULL,
  `assigned_by` BIGINT UNSIGNED NULL,
  `status` ENUM('queued','in_progress','review','approved','cancelled') NOT NULL DEFAULT 'queued',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_content_translation_assignment` (`content_type`,`content_id`,`target_locale`),
  INDEX `idx_content_translation_lookup` (`content_type`,`content_id`),
  INDEX `idx_content_translation_translator` (`translator_id`,`status`),
  INDEX `idx_content_translation_reviewer` (`reviewer_id`,`status`),
  CONSTRAINT `fk_content_translation_translator` FOREIGN KEY (`translator_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_content_translation_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_content_translation_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Postflight: every result must be present before retrying the website.
SELECT COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='article_translations'
  AND COLUMN_NAME IN ('review_state','completeness_state','completeness_source_hash','completeness_translation_hash','completeness_report','completeness_checked_at','completeness_checked_by')
ORDER BY COLUMN_NAME;


