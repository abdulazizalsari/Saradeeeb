ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(60) NULL AFTER email;
ALTER TABLE pages ADD COLUMN IF NOT EXISTS template ENUM('standard','wide','landing') NOT NULL DEFAULT 'standard' AFTER sort_order;
ALTER TABLE page_translations ADD COLUMN IF NOT EXISTS content_mode ENUM('visual','html') NOT NULL DEFAULT 'visual' AFTER content;
ALTER TABLE page_translations ADD COLUMN IF NOT EXISTS custom_css LONGTEXT NULL AFTER content_mode;
ALTER TABLE page_translations ADD COLUMN IF NOT EXISTS custom_js LONGTEXT NULL AFTER custom_css;

CREATE TABLE IF NOT EXISTS writer_applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(60) NOT NULL,
  country VARCHAR(120) NULL,
  city VARCHAR(120) NULL,
  topics VARCHAR(500) NULL,
  experience TEXT NULL,
  sample_url VARCHAR(500) NULL,
  bio TEXT NOT NULL,
  status ENUM('new','reviewed','approved','rejected') NOT NULL DEFAULT 'new',
  admin_note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(status), INDEX(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integrations (
  service_key VARCHAR(80) PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  config_json MEDIUMTEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(enabled), INDEX(updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_tracking_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  location ENUM('head','body_start','body_end') NOT NULL DEFAULT 'body_end',
  consent_category ENUM('essential','analytics','marketing','ads') NOT NULL DEFAULT 'marketing',
  code MEDIUMTEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(active,location), INDEX(consent_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings(`key`,value) VALUES
('tracking_consent_enabled','0'),
('tracking_consent_version','1'),
('tracking_consent_title','خصوصيتك تهمنا'),
('tracking_consent_message','نستخدم أدوات قياس وإعلانات غير ضرورية فقط بعد موافقتك. يمكنك تغيير اختيارك في أي وقت.'),
('tracking_privacy_url','/page/privacy-policy'),
('ad_slot_enabled_below_header','1'),
('ad_slot_enabled_home_top','1'),
('ad_slot_enabled_home_after_featured','1'),
('ad_slot_enabled_home_between_sections','1'),
('ad_slot_enabled_article_top','1'),
('ad_slot_enabled_article_sidebar','1'),
('ad_slot_enabled_inside_article_3','1'),
('ad_slot_enabled_inside_article_7','1'),
('ad_slot_enabled_inside_article_end','1'),
('ad_slot_enabled_footer_top','1');
