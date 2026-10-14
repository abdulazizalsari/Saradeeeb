<?php
use App\Core\Database;

function schema_table_exists(string $table): bool {
    try {
        $row=Database::one('SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
        return (int)($row['c']??0)>0;
    } catch(Throwable) { return false; }
}
function schema_column_exists(string $table,string $column): bool {
    try {
        $row=Database::one('SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$column]);
        return (int)($row['c']??0)>0;
    } catch(Throwable) { return false; }
}
function schema_add_column(string $table,string $column,string $definition): void {
    if(schema_table_exists($table) && !schema_column_exists($table,$column)) Database::exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
}
function schema_put_setting(string $key,string $value): void {
    if(!schema_table_exists('settings')) return;
    $row=Database::one('SELECT `key` FROM settings WHERE `key`=?',[$key]);
    if($row) Database::exec('UPDATE settings SET value=? WHERE `key`=?',[$value,$key]);
    else Database::insert('INSERT INTO settings(`key`,value) VALUES(?,?)',[$key,$value]);
}
function schema_put_setting_default(string $key,string $value): void {
    if(!schema_table_exists('settings')) return;
    $row=Database::one('SELECT `key` FROM settings WHERE `key`=?',[$key]);
    if(!$row) Database::insert('INSERT INTO settings(`key`,value) VALUES(?,?)',[$key,$value]);
}

function run_schema_migrations(): void {
    if(!schema_table_exists('settings')) return;
    // Stateless hosts such as Vercel require shared session storage. This table
    // is additive and is used only when APP_SESSION_DRIVER=database is enabled.
    Database::exec("CREATE TABLE IF NOT EXISTS app_sessions (
        id VARCHAR(128) PRIMARY KEY,
        payload MEDIUMBLOB NOT NULL,
        expires_at DATETIME NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_app_sessions_expiry(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Google identities are additive and separate from password credentials.
    // The table is safe to create on every request and never stores secrets.
    try {
        Database::exec("CREATE TABLE IF NOT EXISTS oauth_identities (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(40) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_oauth_provider_subject(provider,subject),
            UNIQUE KEY uq_oauth_provider_user(provider,user_id),
            INDEX idx_oauth_user(user_id),
            CONSTRAINT fk_oauth_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch(Throwable $e) { try { @file_put_contents(BASE_PATH.'/storage/migration-error.log',date('c').' oauth identities: '.$e->getMessage().PHP_EOL,FILE_APPEND); } catch(Throwable) {} }
    // Additive state used only by the isolated administration dashboard.
    try {
        schema_add_column('users','login_banned_until','DATETIME NULL AFTER `status`');
        schema_add_column('users','login_ban_reason','VARCHAR(500) NULL AFTER `login_banned_until`');
        schema_add_column('users','writer_suspended_until','DATETIME NULL AFTER `writer_vip`');
        schema_add_column('users','writer_suspension_reason','VARCHAR(500) NULL AFTER `writer_suspended_until`');
        schema_add_column('users','account_trashed_at','DATETIME NULL AFTER `writer_suspension_reason`');
        schema_add_column('users','account_trashed_by','BIGINT UNSIGNED NULL AFTER `account_trashed_at`');
        schema_add_column('users','account_trash_reason','VARCHAR(500) NULL AFTER `account_trashed_by`');
        schema_add_column('users','account_trash_previous_status','VARCHAR(20) NULL AFTER `account_trash_reason`');
        Database::exec("CREATE TABLE IF NOT EXISTS admin_v2_preferences (
            user_id BIGINT UNSIGNED PRIMARY KEY, theme VARCHAR(10) NOT NULL DEFAULT 'dark',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_admin_v2_pref_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS admin_v2_restriction_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
            admin_id BIGINT UNSIGNED NOT NULL, action VARCHAR(60) NOT NULL, reason VARCHAR(500) NULL,
            starts_at DATETIME NOT NULL, ends_at DATETIME NULL, previous_state LONGTEXT NULL,
            new_state LONGTEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id,created_at), INDEX(action,created_at),
            CONSTRAINT fk_admin_v2_restrict_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_admin_v2_restrict_admin FOREIGN KEY(admin_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch(Throwable $e) { try { @file_put_contents(BASE_PATH.'/storage/migration-error.log',date('c').' admin v2: '.$e->getMessage().PHP_EOL,FILE_APPEND); } catch(Throwable) {} }
    // Admin V2 RBAC foundation. No real user is assigned automatically.
    try {
        Database::exec("CREATE TABLE IF NOT EXISTS admin_roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,slug VARCHAR(80) NOT NULL UNIQUE,name VARCHAR(160) NOT NULL,description VARCHAR(500) NULL,is_system TINYINT(1) NOT NULL DEFAULT 1,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(is_system)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS admin_permissions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,slug VARCHAR(120) NOT NULL UNIQUE,name VARCHAR(160) NOT NULL,group_name VARCHAR(80) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(group_name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS admin_role_permissions (role_id BIGINT UNSIGNED NOT NULL,permission_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(role_id,permission_id),FOREIGN KEY(role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,FOREIGN KEY(permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS admin_user_roles (user_id BIGINT UNSIGNED NOT NULL,role_id BIGINT UNSIGNED NOT NULL,assigned_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,role_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS admin_user_permissions (user_id BIGINT UNSIGNED NOT NULL,permission_id BIGINT UNSIGNED NOT NULL,effect ENUM('allow','deny') NOT NULL,assigned_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(user_id,permission_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE,FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $roles=[['super_admin','المالك الرئيسي','صلاحيات الإدارة الكاملة'],['administrator','المدير التنفيذي','إدارة التشغيل والمحتوى دون تغيير أدوار الإدارة العليا'],['assistant','مساعد','عضو فريق بصلاحيات فردية قابلة للتخصيص'],['editor','Editor','إدارة المحتوى التحريري'],['content_moderator','Content Moderator','مراجعة المحتوى والتعليقات']];
        foreach($roles as $role) Database::exec('INSERT INTO admin_roles(slug,name,description,is_system) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description)', $role);
        $perms=['dashboard.view'=>'لوحة التحكم','audit.view'=>'سجل الإجراءات','writers.view'=>'عرض الكتاب','writers.manage'=>'إدارة الكتاب','writers.approve'=>'اعتماد طلبات الكتاب','writers.suspend'=>'تعليق الكتاب','writers.visibility.manage'=>'إدارة ظهور الملفات','applications.view'=>'عرض الطلبات','applications.review'=>'مراجعة الطلبات','applications.approve'=>'اعتماد الطلبات','articles.view'=>'عرض المقالات','articles.create'=>'إنشاء المقالات','articles.edit'=>'تحرير المقالات','articles.publish'=>'نشر المقالات','articles.archive'=>'أرشفة المقالات','articles.delete'=>'حذف المقالات','thoughts.view'=>'عرض الخواطر','thoughts.create'=>'إنشاء الخواطر','thoughts.edit'=>'تحرير الخواطر','thoughts.publish'=>'نشر الخواطر','thoughts.archive'=>'أرشفة الخواطر','thoughts.delete'=>'حذف الخواطر','pages.view'=>'عرض الصفحات','pages.manage'=>'إدارة الصفحات','advertisements.view'=>'عرض الإعلانات','advertisements.manage'=>'إدارة الإعلانات','settings.view'=>'عرض الإعدادات','settings.manage'=>'إدارة الإعدادات','administrators.view'=>'عرض المدراء','administrators.create'=>'إنشاء مدير','administrators.edit'=>'تحرير مدير','administrators.disable'=>'تعطيل مدير','roles.view'=>'عرض الأدوار','roles.manage'=>'إدارة الأدوار','permissions.manage'=>'إدارة الصلاحيات','team.manage'=>'إدارة الفريق','accounts.trash'=>'نقل الحسابات إلى المهملات','accounts.restore'=>'استعادة الحسابات','accounts.permanently_delete'=>'الحذف النهائي للحسابات'];
        foreach($perms as $slug=>$name){$group=(string)strtok($slug,'.'); Database::exec('INSERT INTO admin_permissions(slug,name,group_name) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),group_name=VALUES(group_name)',[$slug,$name,$group]);}
        foreach(['translations.view'=>'عرض طابور الترجمة','translations.assign'=>'تعيين مهام الترجمة','translations.edit'=>'تحرير الترجمات المعيّنة','translations.review'=>'مراجعة واعتماد الترجمات'] as $slug=>$name){$group=(string)strtok($slug,'.');Database::exec('INSERT INTO admin_permissions(slug,name,group_name) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)',[$slug,$name,$group]);}
        $allRoles=Database::all('SELECT id,slug FROM admin_roles'); $allPerms=Database::all('SELECT id,slug FROM admin_permissions'); $permIds=[]; foreach($allPerms as $p)$permIds[$p['slug']]=(int)$p['id'];
        foreach($allRoles as $role){
            $roleSlug=(string)$role['slug'];
            if($roleSlug==='super_admin') $allowed=array_keys($permIds);
            elseif($roleSlug==='administrator') $allowed=array_keys(array_filter($permIds,static fn($s)=>!in_array(explode('.',$s,2)[0],['roles','permissions','team'],true)));
            elseif($roleSlug==='editor') $allowed=array_keys(array_filter($permIds,static fn($s)=>in_array($s,['dashboard.view','articles.view','articles.create','articles.edit','articles.publish','thoughts.view','thoughts.create','thoughts.edit','thoughts.publish'],true)));
            elseif($roleSlug==='assistant') $allowed=array_keys(array_filter($permIds,static fn($s)=>in_array($s,['dashboard.view','articles.view','articles.edit','thoughts.view','thoughts.edit','applications.view','applications.review'],true)));
            else $allowed=array_keys(array_filter($permIds,static fn($s)=>in_array($s,['dashboard.view','audit.view','articles.view','thoughts.view','applications.view','applications.review','writers.view'],true)));
            foreach($allowed as $slug) Database::exec('INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) VALUES(?,?)',[(int)$role['id'],$permIds[$slug]]);
        }
    } catch(Throwable $e) { try { @file_put_contents(BASE_PATH.'/storage/migration-error.log',date('c').' admin rbac: '.$e->getMessage().PHP_EOL,FILE_APPEND); } catch(Throwable) {} }
    // Structured, versioned content for the existing "Behind Saradeeeb" page.
    // The page URL remains owned by pages/page_translations; only its editable
    // content snapshots live here so a draft can never replace live content.
    try {
        Database::exec("CREATE TABLE IF NOT EXISTS about_page_versions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_id BIGINT UNSIGNED NOT NULL,
            locale VARCHAR(20) NOT NULL,
            status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
            content_json LONGTEXT NOT NULL,
            seo_title VARCHAR(255) NULL,
            seo_description VARCHAR(320) NULL,
            image_path VARCHAR(500) NULL,
            image_alt VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            published_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at DATETIME NULL,
            INDEX idx_about_page_locale_status(page_id,locale,status),
            INDEX idx_about_page_published(published_at),
            CONSTRAINT fk_about_page FOREIGN KEY(page_id) REFERENCES pages(id) ON DELETE CASCADE,
            CONSTRAINT fk_about_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_about_publisher FOREIGN KEY(published_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch(Throwable $e) { try { @file_put_contents(BASE_PATH.'/storage/migration-error.log',date('c').' about page: '.$e->getMessage().PHP_EOL,FILE_APPEND); } catch(Throwable) {} }
    // Book commerce is administered here; no payment or sale is processed by the site.
    try {
        Database::exec("CREATE TABLE IF NOT EXISTS book_service_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,writer_id BIGINT UNSIGNED NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'pending',active_slot TINYINT UNSIGNED NULL DEFAULT 1,admin_note TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_book_request_active(writer_id,active_slot),INDEX(status,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS book_agreements (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id BIGINT UNSIGNED NOT NULL,writer_id BIGINT UNSIGNED NOT NULL,version INT UNSIGNED NOT NULL,terms LONGTEXT NOT NULL,fee DECIMAL(12,2) NOT NULL DEFAULT 0,currency VARCHAR(8) NOT NULL DEFAULT 'USD',payment_terms TEXT NULL,promo_scope TEXT NULL,rights_terms TEXT NULL,cancellation_terms TEXT NULL,service_start DATE NULL,service_end DATE NULL,listing_after_expiry TINYINT(1) NOT NULL DEFAULT 0,status VARCHAR(20) NOT NULL DEFAULT 'offered',payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',accepted_snapshot LONGTEXT NULL,accepted_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_book_agreement_version(request_id,version),INDEX(writer_id,status),INDEX(service_end)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS books (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,writer_id BIGINT UNSIGNED NOT NULL,agreement_id BIGINT UNSIGNED NOT NULL,title VARCHAR(240) NOT NULL,slug VARCHAR(260) NOT NULL,subtitle VARCHAR(240) NULL,genre VARCHAR(120) NULL,publisher VARCHAR(160) NULL,publication_year SMALLINT UNSIGNED NULL,isbn VARCHAR(32) NULL,book_language VARCHAR(40) NULL,page_count INT UNSIGNED NULL,book_format VARCHAR(80) NULL,description LONGTEXT NULL,front_media_id BIGINT UNSIGNED NULL,back_media_id BIGINT UNSIGNED NULL,purchase_url VARCHAR(1000) NULL,purchase_label VARCHAR(120) NULL,status VARCHAR(20) NOT NULL DEFAULT 'draft',is_featured TINYINT(1) NOT NULL DEFAULT 0,promo_directory TINYINT(1) NOT NULL DEFAULT 1,promo_profile TINYINT(1) NOT NULL DEFAULT 1,promo_home TINYINT(1) NOT NULL DEFAULT 0,published_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_book_slug(slug),INDEX(writer_id,status,published_at),INDEX(status,is_featured)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_add_column('book_agreements','payment_reference','VARCHAR(255) NULL AFTER `payment_status`');
        schema_add_column('book_agreements','allow_directory','TINYINT(1) NOT NULL DEFAULT 0 AFTER `listing_after_expiry`');
        schema_add_column('book_agreements','allow_profile','TINYINT(1) NOT NULL DEFAULT 0 AFTER `allow_directory`');
        schema_add_column('book_agreements','allow_home','TINYINT(1) NOT NULL DEFAULT 0 AFTER `allow_profile`');
    } catch(Throwable $e) { try { @file_put_contents(BASE_PATH.'/storage/migration-error.log',date('c').' books: '.$e->getMessage().PHP_EOL,FILE_APPEND); } catch(Throwable) {} }
    // Isolated, idempotent repair: categories.active is required by the home page,
    // sitemap and admin. Kept outside the main try so an earlier failure cannot skip it.
    try { schema_add_column('categories','active','TINYINT(1) NOT NULL DEFAULT 1 AFTER `sort_order`'); }
    catch(Throwable $e) { try { @file_put_contents(BASE_PATH.'/storage/migration-error.log',date('c').' categories.active: '.$e->getMessage().PHP_EOL,FILE_APPEND); } catch(Throwable) {} }
    // Uploaded card templates are data, not executable application code. The
    // type is intentionally a VARCHAR so new template families need no schema change.
    try { Database::exec("CREATE TABLE IF NOT EXISTS card_templates (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,type VARCHAR(64) NOT NULL,name VARCHAR(190) NOT NULL,version VARCHAR(60) NOT NULL,path VARCHAR(500) NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 0,uploaded_by BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY card_template_type_version(type,version),INDEX(type,is_active),INDEX(uploaded_by,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); }
    catch(Throwable $e) { try { @file_put_contents(BASE_PATH.'/storage/migration-error.log',date('c').' card templates: '.$e->getMessage().PHP_EOL,FILE_APPEND); } catch(Throwable) {} }
    try { Database::exec('ALTER TABLE card_templates ADD UNIQUE KEY card_template_type_version(type,version)'); } catch(Throwable) {}
    // Writer applications were added after some installations had already set a
    // high schema version. Repair their additive columns before the larger
    // migration block, whose early-return path intentionally skips old upgrades.
    try {
        if(schema_table_exists('writer_applications')){
            try { Database::exec('ALTER TABLE writer_applications MODIFY email VARCHAR(190) NULL'); } catch(Throwable) {}
            schema_add_column('writer_applications','user_id','BIGINT UNSIGNED NULL AFTER `id`');
            schema_add_column('writer_applications','username','VARCHAR(80) NULL AFTER `email`');
            schema_add_column('writer_applications','terms_accepted_at','DATETIME NULL AFTER `bio`');
            schema_add_column('writer_applications','submitted_at','DATETIME NULL AFTER `email_verified_at`');
            schema_add_column('writer_applications','last_submitted_at','DATETIME NULL AFTER `submitted_at`');
            schema_add_column('writer_applications','last_reminded_at','DATETIME NULL AFTER `last_submitted_at`');
            schema_add_column('writer_applications','reminder_count','INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_reminded_at`');
            Database::exec("CREATE TABLE IF NOT EXISTS writer_application_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,application_id BIGINT UNSIGNED NOT NULL,from_status VARCHAR(40) NULL,to_status VARCHAR(40) NOT NULL,admin_id BIGINT UNSIGNED NULL,note TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(application_id,created_at),INDEX(admin_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch(Throwable $e) { try { @file_put_contents(BASE_PATH.'/storage/migration-error.log',date('c').' writer applications: '.$e->getMessage().PHP_EOL,FILE_APPEND); } catch(Throwable) {} }
    try { if(schema_table_exists('users')) Database::exec('ALTER TABLE users MODIFY email VARCHAR(190) NULL'); } catch(Throwable) {}
    try {
        // Always-on repair for translation exchange tables. This runs before the
        // schema-version shortcut so upgraded installations receive the feature.
        Database::exec("CREATE TABLE IF NOT EXISTS translation_entry_states (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,translation_key VARCHAR(255) NOT NULL,locale VARCHAR(20) NOT NULL,state ENUM('untranslated','draft','translated','needs_review','approved') NOT NULL DEFAULT 'draft',source_hash CHAR(64) NULL,updated_by BIGINT UNSIGNED NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_translation_entry_state(translation_key,locale),INDEX(locale,state)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS translation_imports (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,file_name VARCHAR(255) NOT NULL,file_format VARCHAR(12) NOT NULL,status ENUM('preview','confirmed','failed') NOT NULL DEFAULT 'preview',total_rows INT UNSIGNED NOT NULL DEFAULT 0,new_count INT UNSIGNED NOT NULL DEFAULT 0,updated_count INT UNSIGNED NOT NULL DEFAULT 0,unknown_count INT UNSIGNED NOT NULL DEFAULT 0,error_count INT UNSIGNED NOT NULL DEFAULT 0,report_json LONGTEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,confirmed_at DATETIME NULL,INDEX(user_id,created_at),INDEX(status,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS translation_import_rows (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,import_id BIGINT UNSIGNED NOT NULL,`row_number` INT UNSIGNED NOT NULL,translation_key VARCHAR(255) NULL,entity_type VARCHAR(40) NULL,entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,field_name VARCHAR(80) NULL,source_locale VARCHAR(20) NULL,source_text LONGTEXT NULL,source_hash CHAR(64) NULL,translations_json LONGTEXT NULL,action_name VARCHAR(30) NOT NULL DEFAULT 'skip',validation_state ENUM('valid','warning','error','unknown') NOT NULL DEFAULT 'valid',messages_json LONGTEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(import_id,`row_number`),INDEX(translation_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Compatibility repair for installations created before the media metadata
        // fields were added. Keep this before the schema-version short circuit so
        // older databases are repaired even when they already report a newer version.
        Database::exec("CREATE TABLE IF NOT EXISTS media (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NULL,path VARCHAR(500) NOT NULL,mime VARCHAR(120) NOT NULL,alt_text VARCHAR(255) NULL,title VARCHAR(190) NULL,caption VARCHAR(500) NULL,description TEXT NULL,original_name VARCHAR(255) NULL,width INT UNSIGNED NULL,height INT UNSIGNED NULL,size_bytes BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(user_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_add_column('media','alt_text','VARCHAR(255) NULL AFTER `mime`');
        schema_add_column('media','title','VARCHAR(190) NULL AFTER `alt_text`');
        schema_add_column('media','caption','VARCHAR(500) NULL AFTER `title`');
        schema_add_column('media','description','TEXT NULL AFTER `caption`');
        schema_add_column('media','original_name','VARCHAR(255) NULL AFTER `description`');
        schema_add_column('media','width','INT UNSIGNED NULL AFTER `original_name`');
        schema_add_column('media','height','INT UNSIGNED NULL AFTER `width`');
        schema_add_column('media','size_bytes','BIGINT UNSIGNED NULL AFTER `height`');
        schema_add_column('media','updated_at','TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');
        // Media Library v2: virtual organization and a reversible trash.  These
        // additions are additive so existing installations keep every file.
        schema_add_column('media','folder_id','BIGINT UNSIGNED NULL AFTER `user_id`');
        schema_add_column('media','storage_key','VARCHAR(700) NULL AFTER `path`');
        schema_add_column('media','file_hash','CHAR(64) NULL AFTER `size_bytes`');
        schema_add_column('media','kind','VARCHAR(20) NULL AFTER `mime`');
        schema_add_column('media','duration_seconds','INT UNSIGNED NULL AFTER `height`');
        schema_add_column('media','metadata_json','LONGTEXT NULL AFTER `file_hash`');
        schema_add_column('media','deleted_at','DATETIME NULL AFTER `updated_at`');
        schema_add_column('media','deleted_by','BIGINT UNSIGNED NULL AFTER `deleted_at`');
        Database::exec("CREATE TABLE IF NOT EXISTS media_folders (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,parent_id BIGINT UNSIGNED NULL,name VARCHAR(160) NOT NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_media_folder(parent_id,name),INDEX(parent_id),INDEX(created_by),CONSTRAINT fk_media_folder_parent FOREIGN KEY(parent_id) REFERENCES media_folders(id) ON DELETE CASCADE,CONSTRAINT fk_media_folder_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS media_tags (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,slug VARCHAR(120) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_media_tag_slug(slug)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS media_tag_map (media_id BIGINT UNSIGNED NOT NULL,tag_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(media_id,tag_id),CONSTRAINT fk_media_tag_map_media FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE CASCADE,CONSTRAINT fk_media_tag_map_tag FOREIGN KEY(tag_id) REFERENCES media_tags(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS media_usages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,media_id BIGINT UNSIGNED NOT NULL,entity_type VARCHAR(60) NOT NULL,entity_id BIGINT UNSIGNED NOT NULL,field_name VARCHAR(80) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_media_usage(media_id,entity_type,entity_id,field_name),INDEX(entity_type,entity_id),CONSTRAINT fk_media_usage_media FOREIGN KEY(media_id) REFERENCES media(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_add_column('writer_card_preferences','show_photo','TINYINT(1) NOT NULL DEFAULT 1 AFTER `show_socials`');
        schema_add_column('writer_card_preferences','style_json','LONGTEXT NULL AFTER `quote_text`');

        // v6.6: complete the existing multilingual model without changing source records.
        // Translation rows stay independent, while media/audio and entity IDs remain shared.
        schema_add_column('categories','active','TINYINT(1) NOT NULL DEFAULT 1 AFTER `sort_order`');
        schema_add_column('article_translations','image_alt','VARCHAR(255) NULL AFTER `og_image`');
        schema_add_column('article_translations','image_caption','VARCHAR(500) NULL AFTER `image_alt`');
        schema_add_column('article_translations','og_title','VARCHAR(255) NULL AFTER `image_caption`');
        schema_add_column('article_translations','og_description','VARCHAR(320) NULL AFTER `og_title`');
        schema_add_column('article_translations','review_state',"ENUM('draft','review','approved') NOT NULL DEFAULT 'draft' AFTER `status`");
        schema_add_column('category_translations','seo_title','VARCHAR(255) NULL AFTER `description`');
        schema_add_column('category_translations','seo_description','VARCHAR(320) NULL AFTER `seo_title`');
        schema_add_column('category_translations','status',"ENUM('draft','review','published') NOT NULL DEFAULT 'published' AFTER `seo_description`");
        schema_add_column('page_translations','status',"ENUM('draft','review','published') NOT NULL DEFAULT 'published' AFTER `content_width`");
        schema_add_column('page_translations','review_state',"ENUM('draft','review','approved') NOT NULL DEFAULT 'draft' AFTER `status`");
        schema_add_column('story_submissions','category_id','BIGINT UNSIGNED NULL AFTER `title`');
        schema_add_column('story_submissions','submission_type',"VARCHAR(40) NOT NULL DEFAULT 'experience' AFTER `category_id`");
        schema_add_column('story_submissions','terms_accepted_at','DATETIME NULL AFTER `status`');
        schema_add_column('story_submissions','author_user_id','BIGINT UNSIGNED NULL AFTER `id`');
        schema_add_column('editorial_threads','story_submission_id','BIGINT UNSIGNED NULL AFTER `article_id`');
        // Community moderation must also reach databases that already passed the
        // historical schema-version checkpoint below.
        schema_add_column('community_topics','moderation_status',"ENUM('approved','pending','blocked') NOT NULL DEFAULT 'approved' AFTER `status`");
        schema_add_column('community_replies','moderation_status',"ENUM('approved','pending','blocked') NOT NULL DEFAULT 'approved' AFTER `status`");
        if (schema_table_exists('community_topics') && schema_table_exists('users')) {
            Database::exec("CREATE TABLE IF NOT EXISTS community_moderation_terms (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,phrase VARCHAR(255) NOT NULL,normalized_phrase VARCHAR(255) NOT NULL,exception_phrase VARCHAR(255) NULL,severity ENUM('warning','review','block') NOT NULL DEFAULT 'review',active TINYINT(1) NOT NULL DEFAULT 1,notes VARCHAR(500) NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_community_moderation_term(normalized_phrase),INDEX(active,severity),CONSTRAINT fk_cmoderation_term_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            Database::exec("CREATE TABLE IF NOT EXISTS community_moderation_events (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,content_type ENUM('topic','reply') NOT NULL,content_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,severity ENUM('warning','review','block') NOT NULL,status ENUM('open','approved','rejected','dismissed') NOT NULL DEFAULT 'open',matched_terms LONGTEXT NULL,content_excerpt VARCHAR(1000) NULL,reviewed_by BIGINT UNSIGNED NULL,reviewed_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(status,severity,created_at),INDEX(content_type,content_id),INDEX(user_id,created_at),CONSTRAINT fk_cmoderation_event_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_cmoderation_event_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // Seed the built-in Energy Myths category once, keeping every locale in
        // the existing category translation model.
        if (schema_table_exists('category_translations')) {
            $energy = Database::one("SELECT c.id FROM categories c JOIN category_translations t ON t.category_id=c.id AND t.locale='en' AND t.slug='energy-myths' LIMIT 1");
            if (!$energy) {
                $categoryId = Database::insert('INSERT INTO categories(sort_order,active) VALUES(0,1)', []);
                $insertEnergy = 'INSERT INTO category_translations(category_id,locale,name,slug,description,status) VALUES(?,?,?,?,?,?)';
                foreach ([
                    ['ar','زيف الطاقة','زيف-الطاقة'],
                    ['en','Energy Myths','energy-myths'],
                    ['es','Mitos de la energía','mitos-de-la-energia'],
                    ['fr',"Mythes de l'énergie",'mythes-de-lenergie'],
                    ['ru','Мифы об энергии','mify-ob-energii'],
                ] as $energyTranslation) Database::insert($insertEnergy,[$categoryId,$energyTranslation[0],$energyTranslation[1],$energyTranslation[2],'','published']);
            }
        }



        // Parent tables must exist before their translation tables add foreign keys.
        Database::exec("CREATE TABLE IF NOT EXISTS podcast_episodes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            article_id BIGINT UNSIGNED NULL,
            category_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            title VARCHAR(220) NOT NULL,
            slug VARCHAR(220) NOT NULL,
            excerpt TEXT NULL,
            content MEDIUMTEXT NULL,
            cover_image VARCHAR(500) NULL,
            og_image VARCHAR(500) NULL,
            episode_number INT UNSIGNED NULL,
            duration VARCHAR(40) NULL,
            audio_source ENUM('external','upload') NOT NULL DEFAULT 'external',
            audio_url VARCHAR(1000) NOT NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            seo_title VARCHAR(255) NULL,
            seo_description VARCHAR(320) NULL,
            views BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_podcast_slug(slug),
            INDEX(status,published_at),
            INDEX(category_id,status),
            INDEX(article_id),
            CONSTRAINT fk_podcast_article FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE SET NULL,
            CONSTRAINT fk_podcast_category FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL,
            CONSTRAINT fk_podcast_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::exec("CREATE TABLE IF NOT EXISTS khawatir_categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL UNIQUE,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::exec("CREATE TABLE IF NOT EXISTS khawatir (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            author_id BIGINT UNSIGNED NOT NULL,
            category_id BIGINT UNSIGNED NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            body VARCHAR(1200) NOT NULL,
            slug VARCHAR(220) NOT NULL UNIQUE,
            ownership ENUM('original','quoted') NOT NULL DEFAULT 'original',
            original_author VARCHAR(190) NULL,
            source_name VARCHAR(255) NULL,
            source_url VARCHAR(700) NULL,
            image_path VARCHAR(500) NULL,
            status ENUM('draft','review','changes','rejected','published') NOT NULL DEFAULT 'draft',
            review_note TEXT NULL,
            published_at DATETIME NULL,
            views BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(author_id,status,updated_at),
            INDEX(category_id,status,published_at),
            CONSTRAINT fk_khawatir_author FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_khawatir_category FOREIGN KEY(category_id) REFERENCES khawatir_categories(id) ON DELETE SET NULL,
            CONSTRAINT fk_khawatir_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::exec("CREATE TABLE IF NOT EXISTS tag_translations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tag_id BIGINT UNSIGNED NOT NULL,
            locale VARCHAR(20) NOT NULL,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            description TEXT NULL,
            seo_title VARCHAR(255) NULL,
            seo_description VARCHAR(320) NULL,
            status ENUM('draft','review','published') NOT NULL DEFAULT 'published',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tag_translation(tag_id,locale),
            UNIQUE KEY uq_tag_translation_slug(locale,slug),
            INDEX(locale,status),
            CONSTRAINT fk_tag_translation_tag FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS podcast_episode_translations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            episode_id BIGINT UNSIGNED NOT NULL,
            locale VARCHAR(20) NOT NULL,
            title VARCHAR(220) NOT NULL,
            slug VARCHAR(220) NOT NULL,
            excerpt TEXT NULL,
            content MEDIUMTEXT NULL,
            seo_title VARCHAR(255) NULL,
            seo_description VARCHAR(320) NULL,
            og_title VARCHAR(255) NULL,
            og_description VARCHAR(320) NULL,
            status ENUM('draft','review','published') NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_podcast_translation(episode_id,locale),
            UNIQUE KEY uq_podcast_translation_slug(locale,slug),
            INDEX(locale,status),
            CONSTRAINT fk_podcast_translation_episode FOREIGN KEY(episode_id) REFERENCES podcast_episodes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS khawatir_category_translations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id BIGINT UNSIGNED NOT NULL,
            locale VARCHAR(20) NOT NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(160) NOT NULL,
            description TEXT NULL,
            status ENUM('draft','review','published') NOT NULL DEFAULT 'published',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_khawatir_category_translation(category_id,locale),
            UNIQUE KEY uq_khawatir_category_slug(locale,slug),
            INDEX(locale,status),
            CONSTRAINT fk_khawatir_category_translation FOREIGN KEY(category_id) REFERENCES khawatir_categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS khawatir_translations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            khawatir_id BIGINT UNSIGNED NOT NULL,
            locale VARCHAR(20) NOT NULL,
            title VARCHAR(220) NULL,
            slug VARCHAR(220) NOT NULL,
            body VARCHAR(1600) NOT NULL,
            description TEXT NULL,
            seo_title VARCHAR(255) NULL,
            seo_description VARCHAR(320) NULL,
            status ENUM('draft','review','published') NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_khawatir_translation(khawatir_id,locale),
            UNIQUE KEY uq_khawatir_translation_slug(locale,slug),
            INDEX(locale,status),
            CONSTRAINT fk_khawatir_translation FOREIGN KEY(khawatir_id) REFERENCES khawatir(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS writer_profile_translations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            locale VARCHAR(20) NOT NULL,
            display_name VARCHAR(190) NULL,
            bio TEXT NULL,
            professional_title VARCHAR(190) NULL,
            cv_summary TEXT NULL,
            status ENUM('draft','review','published') NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_writer_profile_translation(user_id,locale),
            INDEX(locale,status),
            CONSTRAINT fk_writer_profile_translation_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_put_setting_default('translation_fallback_enabled','0');
        Database::exec("INSERT IGNORE INTO languages(code,name,flag,dir,enabled,is_default,sort_order) VALUES('ru','Русский','ru','ltr',1,0,40)");

        // Preserve every existing Arabic source row as the Arabic translation.
        Database::exec("INSERT IGNORE INTO podcast_episode_translations(episode_id,locale,title,slug,excerpt,content,seo_title,seo_description,og_title,og_description,status)
            SELECT id,'ar',title,slug,excerpt,content,seo_title,seo_description,title,seo_description,IF(status='published','published','draft') FROM podcast_episodes");
        Database::exec("INSERT IGNORE INTO khawatir_category_translations(category_id,locale,name,slug,status)
            SELECT id,'ar',name,slug,'published' FROM khawatir_categories");
        Database::exec("INSERT IGNORE INTO khawatir_translations(khawatir_id,locale,title,slug,body,status)
            SELECT id,'ar',NULL,slug,body,IF(status='published','published',IF(status='review','review','draft')) FROM khawatir");
        Database::exec("INSERT IGNORE INTO tag_translations(tag_id,locale,name,slug,status)
            SELECT id,'ar',name,slug,'published' FROM tags");
        Database::exec("INSERT IGNORE INTO writer_profile_translations(user_id,locale,display_name,bio,professional_title,cv_summary,status)
            SELECT u.id,'ar',u.name,u.bio,p.professional_title,p.cv_summary,'published' FROM users u LEFT JOIN writer_profiles p ON p.user_id=u.id WHERE u.role IN ('writer','trusted_writer','editor','admin')");
        // Article approval is explicit per language; migrations must not approve drafts or reviews.
        // Page publication and approval are explicit per language.

        // v6.1.1 repair: these feature tables are critical and must exist even when
        // an older installation already stored schema_version=600 before all CREATEs completed.
        Database::exec("CREATE TABLE IF NOT EXISTS article_comments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,article_id BIGINT UNSIGNED NOT NULL,parent_id BIGINT UNSIGNED NULL,user_id BIGINT UNSIGNED NULL,guest_name VARCHAR(120) NULL,guest_email VARCHAR(190) NULL,content TEXT NOT NULL,status ENUM('pending','approved','spam','trash') NOT NULL DEFAULT 'pending',ip_hash CHAR(64) NULL,user_agent VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(article_id,status,created_at),INDEX(parent_id),INDEX(user_id),CONSTRAINT fk_ac_article FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE,CONSTRAINT fk_ac_parent FOREIGN KEY(parent_id) REFERENCES article_comments(id) ON DELETE CASCADE,CONSTRAINT fk_ac_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS editorial_threads (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,writer_id BIGINT UNSIGNED NOT NULL,article_id BIGINT UNSIGNED NULL,story_submission_id BIGINT UNSIGNED NULL,subject VARCHAR(190) NOT NULL,created_by BIGINT UNSIGNED NOT NULL,status ENUM('open','archived') NOT NULL DEFAULT 'open',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(writer_id,status,updated_at),INDEX(article_id),INDEX(story_submission_id),FOREIGN KEY(writer_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE SET NULL,FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS editorial_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,thread_id BIGINT UNSIGNED NOT NULL,sender_id BIGINT UNSIGNED NOT NULL,body TEXT NOT NULL,read_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(thread_id,id),INDEX(sender_id),INDEX(read_at),FOREIGN KEY(thread_id) REFERENCES editorial_threads(id) ON DELETE CASCADE,FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS writer_profiles (user_id BIGINT UNSIGNED PRIMARY KEY,professional_title VARCHAR(190) NULL,cv_summary TEXT NULL,location VARCHAR(190) NULL,website VARCHAR(500) NULL,linkedin VARCHAR(500) NULL,portfolio_url VARCHAR(500) NULL,contact_email VARCHAR(190) NULL,experience_json LONGTEXT NULL,education_json LONGTEXT NULL,skills_json LONGTEXT NULL,certificates_json LONGTEXT NULL,languages_json LONGTEXT NULL,achievements_json LONGTEXT NULL,portfolio_json LONGTEXT NULL,is_public TINYINT(1) NOT NULL DEFAULT 0,show_contact TINYINT(1) NOT NULL DEFAULT 0,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_writer_profile_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // The interface locale is not the author's language. Keep one durable,
        // server-enforced authoring language per writer; existing writers remain Arabic.
        schema_add_column('writer_profiles','primary_writing_language',"VARCHAR(20) NOT NULL DEFAULT 'ar' AFTER `contact_email`");
        schema_add_column('articles','original_locale',"VARCHAR(20) NOT NULL DEFAULT 'ar' AFTER `author_id`");
        Database::exec("UPDATE writer_profiles SET primary_writing_language='ar' WHERE primary_writing_language IS NULL OR primary_writing_language='' ");
        Database::exec("UPDATE articles SET original_locale='ar' WHERE original_locale IS NULL OR original_locale='' ");
        Database::exec("CREATE TABLE IF NOT EXISTS content_translation_assignments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,content_type VARCHAR(40) NOT NULL,content_id BIGINT UNSIGNED NOT NULL,target_locale VARCHAR(20) NOT NULL,translator_id BIGINT UNSIGNED NULL,reviewer_id BIGINT UNSIGNED NULL,assigned_by BIGINT UNSIGNED NULL,status ENUM('queued','in_progress','review','approved','cancelled') NOT NULL DEFAULT 'queued',notes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_content_translation_assignment(content_type,content_id,target_locale),INDEX(content_type,content_id),INDEX(translator_id,status),INDEX(reviewer_id,status),CONSTRAINT fk_translation_assignment_translator FOREIGN KEY(translator_id) REFERENCES users(id) ON DELETE SET NULL,CONSTRAINT fk_translation_assignment_reviewer FOREIGN KEY(reviewer_id) REFERENCES users(id) ON DELETE SET NULL,CONSTRAINT fk_translation_assignment_assigner FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_add_column('users','writer_directory_hidden','TINYINT(1) NOT NULL DEFAULT 0 AFTER `writer_honored`');
        Database::exec("CREATE TABLE IF NOT EXISTS writer_card_preferences (user_id BIGINT UNSIGNED PRIMARY KEY,template VARCHAR(30) NOT NULL DEFAULT 'classic',card_size VARCHAR(30) NOT NULL DEFAULT 'post',show_qr TINYINT(1) NOT NULL DEFAULT 1,show_stats TINYINT(1) NOT NULL DEFAULT 1,show_socials TINYINT(1) NOT NULL DEFAULT 1,quote_text VARCHAR(180) NULL,style_json LONGTEXT NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_writer_card_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach(['writer_cards_enabled','writer_cards_download','writer_cards_pdf','writer_cards_share','writer_cards_qr','writer_cards_stats'] as $key) schema_put_setting_default($key,'1');
        schema_put_setting_default('comments_enabled','1');
        schema_put_setting_default('comments_require_moderation','1');
        schema_put_setting_default('comments_registered_autoapprove','1');
        schema_put_setting_default('comments_allow_guests','1');
        schema_put_setting_default('comments_allow_replies','1');
        schema_put_setting_default('comments_max_links','2');
        schema_put_setting_default('comments_rate_seconds','45');
        schema_put_setting_default('comments_blocked_words','');
        schema_put_setting_default('header_writer_cta_enabled','1');
        schema_put_setting_default('header_writer_cta_label','انضم ككاتب');
        schema_put_setting_default('header_writer_cta_url','/writer-application');
        schema_put_setting_default('header_writer_cta_mobile','1');
        Database::exec("CREATE TABLE IF NOT EXISTS podcast_episodes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            article_id BIGINT UNSIGNED NULL,
            category_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            title VARCHAR(220) NOT NULL,
            slug VARCHAR(220) NOT NULL,
            excerpt TEXT NULL,
            content MEDIUMTEXT NULL,
            cover_image VARCHAR(500) NULL,
            og_image VARCHAR(500) NULL,
            episode_number INT UNSIGNED NULL,
            duration VARCHAR(40) NULL,
            audio_source ENUM('external','upload') NOT NULL DEFAULT 'external',
            audio_url VARCHAR(1000) NOT NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            seo_title VARCHAR(255) NULL,
            seo_description VARCHAR(320) NULL,
            views BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_podcast_slug(slug),
            INDEX(status,published_at),
            INDEX(category_id,status),
            INDEX(article_id),
            CONSTRAINT fk_podcast_article FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE SET NULL,
            CONSTRAINT fk_podcast_category FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL,
            CONSTRAINT fk_podcast_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_put_setting_default('podcast_enabled','1');
        schema_add_column('podcast_episodes','source_type',"VARCHAR(30) NOT NULL DEFAULT 'direct' AFTER `audio_source`");
        schema_add_column('podcast_episodes','cover_template',"VARCHAR(30) NOT NULL DEFAULT 'editorial' AFTER `cover_image`");
        schema_add_column('podcast_episodes','cover_template_version','SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `cover_template`');

        Database::exec("CREATE TABLE IF NOT EXISTS khawatir_categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL UNIQUE,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS khawatir (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            author_id BIGINT UNSIGNED NOT NULL,
            category_id BIGINT UNSIGNED NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            body VARCHAR(1200) NOT NULL,
            slug VARCHAR(220) NOT NULL UNIQUE,
            ownership ENUM('original','quoted') NOT NULL DEFAULT 'original',
            original_author VARCHAR(190) NULL,
            source_name VARCHAR(255) NULL,
            source_url VARCHAR(700) NULL,
            image_path VARCHAR(500) NULL,
            status ENUM('draft','review','changes','rejected','published') NOT NULL DEFAULT 'draft',
            review_note TEXT NULL,
            published_at DATETIME NULL,
            views BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(author_id,status,updated_at),
            INDEX(category_id,status,published_at),
            CONSTRAINT fk_khawatir_author FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_khawatir_category FOREIGN KEY(category_id) REFERENCES khawatir_categories(id) ON DELETE SET NULL,
            CONSTRAINT fk_khawatir_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_put_setting_default('khawatir_enabled','1');
        schema_put_setting_default('khawatir_max_chars','360');
        schema_put_setting_default('khawatir_warn_chars','260');
        if(setting('khawatir_text_only_migrated','0')!=='1'){
            schema_put_setting('khawatir_max_chars','360');
            schema_put_setting('khawatir_warn_chars','260');
            schema_put_setting('khawatir_text_only_migrated','1');
        }
        schema_put_setting_default('khawatir_show_qr','1');
        schema_put_setting_default('khawatir_show_username','1');
        schema_put_setting_default('khawatir_show_site_url','1');
        schema_put_setting_default('khawatir_allow_download','1');
        schema_put_setting_default('khawatir_allow_share','1');
        schema_add_column('khawatir','template_key',"VARCHAR(50) NOT NULL DEFAULT 'template_khawatira' AFTER `category_id`");
        schema_add_column('khawatir','template_version','SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `template_key`');
        schema_add_column('khawatir','text_alignment',"VARCHAR(12) NOT NULL DEFAULT 'center' AFTER `template_version`");
        schema_add_column('khawatir','text_emphasis',"VARCHAR(12) NOT NULL DEFAULT 'normal' AFTER `text_alignment`");
        schema_add_column('khawatir','text_size_mode',"VARCHAR(12) NOT NULL DEFAULT 'auto' AFTER `text_emphasis`");
        schema_add_column('khawatir','template_variant',"VARCHAR(40) NOT NULL DEFAULT 'default' AFTER `text_size_mode`");
        schema_add_column('khawatir_categories','template_key',"VARCHAR(50) NOT NULL DEFAULT 'template_khawatira' AFTER `slug`");
        schema_add_column('khawatir_categories','template_active','TINYINT(1) NOT NULL DEFAULT 1 AFTER `template_key`');
        schema_add_column('writer_card_preferences','show_photo','TINYINT(1) NOT NULL DEFAULT 1 AFTER `show_socials`');
        schema_add_column('writer_card_preferences','style_json','LONGTEXT NULL AFTER `quote_text`');
        if((int)(Database::one('SELECT COUNT(*) c FROM khawatir_categories')['c']??0)===0){
            foreach(['خاطرة','حكمة','شعر','فلسفة','تأمل','مقولة','اقتباس'] as $i=>$name){
                Database::insert('INSERT INTO khawatir_categories(name,slug,sort_order,active) VALUES(?,?,?,1)',[$name,slugify($name),($i+1)*10]);
            }
        }
        foreach(['حكمة'=>'template_hikma','مقولة'=>'template_hikma','شعر'=>'template_poetry','فلسفة'=>'template_philosophy','تأمل'=>'template_philosophy','رعب'=>'template_horror','غموض'=>'template_horror','حضارة'=>'template_history','تاريخ'=>'template_history','نص قصير'=>'template_khawatira'] as $name=>$template){
            $cat=Database::one('SELECT id FROM khawatir_categories WHERE name=?',[$name]);
            if($cat) Database::exec('UPDATE khawatir_categories SET template_key=? WHERE id=? AND template_key="template_khawatira"',[$template,$cat['id']]);
            else Database::insert('INSERT INTO khawatir_categories(name,slug,template_key,sort_order,active,template_active) VALUES(?,?,?,?,1,1)',[$name,slugify($name),$template,90]);
        }
        if(setting('khawatir_header_nav_migrated','0')!=='1'){
            $headerRaw=setting('header_menu_json',''); $headerMenu=json_decode($headerRaw,true);
            if(!is_array($headerMenu)) $headerMenu=['home','categories','podcast','community','story'];
            if(!in_array('khawatir',$headerMenu,true)){
                $position=array_search('podcast',$headerMenu,true);
                if($position===false)$position=array_search('categories',$headerMenu,true);
                $position=$position===false?count($headerMenu):$position+1;
                array_splice($headerMenu,$position,0,['khawatir']);
                schema_put_setting('header_menu_json',json_encode(array_values(array_unique($headerMenu)),JSON_UNESCAPED_UNICODE));
            }
            schema_put_setting('khawatir_header_nav_migrated','1');
        }

        // Add the new section once to existing header configurations while
        // leaving future menu ordering and removal under administrator control.
        if(setting('podcast_header_nav_migrated','0')!=='1'){
            $headerRaw=setting('header_menu_json','');
            $headerMenu=json_decode($headerRaw,true);
            if(!is_array($headerMenu)) $headerMenu=['home','categories','community','story'];
            if(!in_array('podcast',$headerMenu,true)){
                $position=array_search('categories',$headerMenu,true);
                $position=$position===false?count($headerMenu):$position+1;
                array_splice($headerMenu,$position,0,['podcast']);
                schema_put_setting('header_menu_json',json_encode(array_values(array_unique($headerMenu)),JSON_UNESCAPED_UNICODE));
            }
            schema_put_setting('podcast_header_nav_migrated','1');
        }

        // v6.3 writer identity, editorial attachments and award system.
        Database::exec("CREATE TABLE IF NOT EXISTS writer_thanks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,title VARCHAR(190) NULL,message TEXT NULL,locale VARCHAR(20) NOT NULL DEFAULT 'ar',award_type VARCHAR(40) NOT NULL DEFAULT 'featured',announce_on_site TINYINT(1) NOT NULL DEFAULT 0,starts_at DATETIME NULL,ends_at DATETIME NULL,notification_id BIGINT UNSIGNED NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(user_id,active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_add_column('users','writer_verified','TINYINT(1) NOT NULL DEFAULT 0 AFTER `avatar`');
        schema_add_column('users','writer_honored','TINYINT(1) NOT NULL DEFAULT 0 AFTER `writer_verified`');
        Database::exec("CREATE TABLE IF NOT EXISTS writer_username_redirects (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,old_username VARCHAR(120) NOT NULL UNIQUE,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS editorial_message_attachments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,message_id BIGINT UNSIGNED NOT NULL,path VARCHAR(500) NOT NULL,original_name VARCHAR(240) NOT NULL,mime_type VARCHAR(120) NOT NULL,size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(message_id),FOREIGN KEY(message_id) REFERENCES editorial_messages(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_add_column('writer_thanks','locale',"VARCHAR(20) NOT NULL DEFAULT 'ar' AFTER `message`");
        schema_add_column('writer_thanks','award_type',"VARCHAR(40) NOT NULL DEFAULT 'featured' AFTER `locale`");
        schema_add_column('writer_thanks','announce_on_site','TINYINT(1) NOT NULL DEFAULT 0 AFTER `award_type`');
        schema_add_column('writer_thanks','starts_at','DATETIME NULL AFTER `announce_on_site`');
        schema_add_column('writer_thanks','ends_at','DATETIME NULL AFTER `starts_at`');
        schema_add_column('writer_thanks','notification_id','BIGINT UNSIGNED NULL AFTER `ends_at`');
        schema_add_column('writer_thanks','display_mode',"VARCHAR(30) NOT NULL DEFAULT 'session' AFTER `announce_on_site`");
        schema_add_column('writer_thanks','display_interval','INT UNSIGNED NOT NULL DEFAULT 60 AFTER `display_mode`');
        schema_add_column('writer_thanks','display_duration','INT UNSIGNED NOT NULL DEFAULT 7 AFTER `display_interval`');
        schema_add_column('writer_thanks','snapshot_json','LONGTEXT NULL AFTER `notification_id`');

        // Keep writer applications usable on installations that already have a
        // newer schema version. These are additive changes only, so old requests
        // and user accounts remain untouched.
        if(schema_table_exists('writer_applications')){
            schema_add_column('writer_applications','user_id','BIGINT UNSIGNED NULL AFTER `id`');
            schema_add_column('writer_applications','username','VARCHAR(80) NULL AFTER `email`');
            schema_add_column('writer_applications','terms_accepted_at','DATETIME NULL AFTER `bio`');
            schema_add_column('writer_applications','submitted_at','DATETIME NULL AFTER `email_verified_at`');
            schema_add_column('writer_applications','last_submitted_at','DATETIME NULL AFTER `submitted_at`');
            try { Database::exec("ALTER TABLE writer_applications MODIFY status ENUM('pending','under_review','changes_requested','approved','rejected','new','reviewed') NOT NULL DEFAULT 'pending'"); } catch(Throwable) {}
            Database::exec("CREATE TABLE IF NOT EXISTS writer_application_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,application_id BIGINT UNSIGNED NOT NULL,from_status VARCHAR(40) NULL,to_status VARCHAR(40) NOT NULL,admin_id BIGINT UNSIGNED NULL,note TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(application_id,created_at),INDEX(admin_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        // Additive daily-story storage must run before the version fast path below.
        Database::exec("CREATE TABLE IF NOT EXISTS story_of_night_selections (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,feature_date DATE NOT NULL,locale VARCHAR(20) NOT NULL DEFAULT 'ar',article_id BIGINT UNSIGNED NULL,selection_mode ENUM('automatic','manual') NOT NULL DEFAULT 'automatic',selected_by BIGINT UNSIGNED NULL,selected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_story_night_date_locale(feature_date,locale),INDEX(article_id,feature_date),CONSTRAINT fk_story_night_article FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE SET NULL,CONSTRAINT fk_story_night_selector FOREIGN KEY(selected_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS story_of_night_exclusions (article_id BIGINT UNSIGNED PRIMARY KEY,excluded_by BIGINT UNSIGNED NULL,reason VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_story_night_excluded_article FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE,CONSTRAINT fk_story_night_excluder FOREIGN KEY(excluded_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS story_of_night_overrides (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,article_id BIGINT UNSIGNED NOT NULL,starts_at DATETIME NOT NULL,ends_at DATETIME NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(active,starts_at,ends_at),CONSTRAINT fk_story_night_override_article FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE,CONSTRAINT fk_story_night_override_admin FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_put_setting_default('story_of_night_enabled','1');
        schema_put_setting_default('story_of_night_timezone',(string)config('timezone','Europe/Istanbul'));
        schema_put_setting_default('story_of_night_categories','[]');
        // Explicit, administrator-controlled entitlement for the premium writer studio.
        schema_add_column('users','writer_vip','TINYINT(1) NOT NULL DEFAULT 0 AFTER `writer_honored`');
        // Translation completeness gate. Additive fields preserve every existing translation.
        schema_add_column('article_translations','completeness_state',"ENUM('complete_ready','incomplete','needs_review','passed') NOT NULL DEFAULT 'complete_ready' AFTER `review_state`");
        schema_add_column('article_translations','completeness_source_hash','CHAR(64) NULL AFTER `completeness_state`');
        schema_add_column('article_translations','completeness_translation_hash','CHAR(64) NULL AFTER `completeness_source_hash`');
        schema_add_column('article_translations','completeness_report','LONGTEXT NULL AFTER `completeness_translation_hash`');
        schema_add_column('article_translations','completeness_checked_at','DATETIME NULL AFTER `completeness_report`');
        schema_add_column('article_translations','completeness_checked_by','BIGINT UNSIGNED NULL AFTER `completeness_checked_at`');
        Database::exec("CREATE TABLE IF NOT EXISTS translation_validation_resolutions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,article_id BIGINT UNSIGNED NOT NULL,locale VARCHAR(20) NOT NULL,issue_key CHAR(64) NOT NULL,reason VARCHAR(1000) NOT NULL,source_hash CHAR(64) NOT NULL,translation_hash CHAR(64) NOT NULL,resolved_by BIGINT UNSIGNED NOT NULL,resolved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_translation_resolution(article_id,locale,issue_key),INDEX(article_id,locale),FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE,FOREIGN KEY(resolved_by) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $row=Database::one("SELECT value FROM settings WHERE `key`='schema_version'");
        $schemaVersion=(int)($row['value']??0);
        if($schemaVersion>=630){
            if($schemaVersion<631){
                schema_put_setting('tracking_consent_enabled','0');
                $schemaVersion=631;
            }
            if($schemaVersion<640){
                // v6.4: richer page editor/display settings. Media uses the existing table safely.
                schema_add_column('page_translations','page_bg_color',"VARCHAR(20) NOT NULL DEFAULT '#ffffff' AFTER `show_title`");
                schema_add_column('page_translations','page_text_color',"VARCHAR(20) NOT NULL DEFAULT '#202522' AFTER `page_bg_color`");
                schema_add_column('page_translations','content_width',"VARCHAR(20) NOT NULL DEFAULT 'standard' AFTER `page_text_color`");
                schema_put_setting('schema_version','640');
            }
            // Some installations were already marked as v6.3+ before the
            // recoverable-page-trash column was introduced. Keep the pages
            // manager usable by applying this additive compatibility change
            // before the early return; it never deletes or changes pages.
            schema_add_column('pages','deleted_at','DATETIME NULL AFTER `template`');
            if(schema_table_exists('writer_applications')){
                try { Database::exec("ALTER TABLE writer_applications MODIFY status ENUM('pending','under_review','changes_requested','approved','rejected','suspended','new','reviewed') NOT NULL DEFAULT 'pending'"); } catch(Throwable) {}
                Database::exec("CREATE TABLE IF NOT EXISTS writer_application_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,application_id BIGINT UNSIGNED NOT NULL,sender_id BIGINT UNSIGNED NULL,visibility ENUM('applicant','internal') NOT NULL DEFAULT 'applicant',body TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(application_id,visibility,created_at),INDEX(sender_id),CONSTRAINT fk_wapp_message_application FOREIGN KEY(application_id) REFERENCES writer_applications(id) ON DELETE CASCADE,CONSTRAINT fk_wapp_message_sender FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                Database::exec("CREATE TABLE IF NOT EXISTS email_notification_templates (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_key VARCHAR(100) NOT NULL UNIQUE,subject VARCHAR(255) NOT NULL,body_html LONGTEXT NULL,body_text LONGTEXT NULL,enabled TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                Database::exec("CREATE TABLE IF NOT EXISTS email_delivery_attempts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_key VARCHAR(100) NOT NULL,recipient VARCHAR(255) NOT NULL,subject VARCHAR(255) NOT NULL,status ENUM('queued','accepted','failed') NOT NULL DEFAULT 'queued',attempts INT NOT NULL DEFAULT 0,error TEXT NULL,related_type VARCHAR(80) NULL,related_id BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,sent_at DATETIME NULL,INDEX(event_key,status),INDEX(related_type,related_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                /* default email seed disabled until the deployment encoding is normalized.
                Database::exec("INSERT IGNORE INTO email_notification_templates(event_key,subject,body_html,body_text) VALUES ('application_approved','تمت الموافقة على عضويتك ككاتب في سراديب','<p>مرحبًا {{name}}،</p><p>يسعدنا إبلاغك بأن طلب انضمامك ككاتب قد تمت الموافقة عليه.</p><p>اسم المستخدم: {{username}}</p><p><a href=\"{{login_url}}\">تسجيل الدخول</a></p><p>مع التحية،<br>فريق سراديب</p>','مرحبًا {{name}}،\n\nتمت الموافقة على طلب انضمامك ككاتب في سراديب.\nاسم المستخدم: {{username}}\nتسجيل الدخول: {{login_url}}\n\nمع التحية،\nفريق سراديب'),('application_received','تم استلام طلب الانضمام إلى سراديب','<p>شكرًا لتقديم طلبك يا {{name}}. سنراجعه ونراسلك عند تحديث حالته.</p>','شكرًا لتقديم طلبك يا {{name}}. سنراجعه ونراسلك عند تحديث حالته.');
                */
            }
            schema_put_setting_default('writer_application_require_verified_email','1');
            schema_put_setting_default('writer_application_resubmission','1');
            if($schemaVersion<655) schema_put_setting('schema_version','655');
            return;
        }

        schema_add_column('users','phone','VARCHAR(60) NULL AFTER `email`');
        schema_add_column('users','email_verified_at','DATETIME NULL AFTER `status`');
        schema_add_column('users','activation_token_hash','CHAR(64) NULL AFTER `email_verified_at`');
        schema_add_column('users','activation_expires_at','DATETIME NULL AFTER `activation_token_hash`');
        schema_add_column('users','password_reset_token_hash','CHAR(64) NULL AFTER `activation_expires_at`');
        schema_add_column('users','password_reset_expires_at','DATETIME NULL AFTER `password_reset_token_hash`');
        schema_add_column('users','two_factor_enabled','TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_reset_expires_at`');
        schema_add_column('users','two_factor_secret','VARCHAR(128) NULL AFTER `two_factor_enabled`');
        schema_add_column('users','two_factor_recovery_codes','TEXT NULL AFTER `two_factor_secret`');
        schema_add_column('users','last_login_at','DATETIME NULL AFTER `avatar`');
        schema_add_column('users','updated_at','TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');
        try { Database::exec("ALTER TABLE users MODIFY role ENUM('member','writer','trusted_writer','editor','admin') NOT NULL DEFAULT 'member'"); } catch(Throwable) {}

        schema_add_column('articles','category_id','BIGINT UNSIGNED NULL AFTER `author_id`');
        schema_add_column('articles','status',"ENUM('draft','review','changes','published','archived') NOT NULL DEFAULT 'draft' AFTER `category_id`");
        schema_add_column('articles','is_featured','TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`');
        schema_add_column('articles','featured_image','VARCHAR(500) NULL AFTER `is_featured`');
        schema_add_column('articles','legacy_path','VARCHAR(255) NULL AFTER `featured_image`');
        schema_add_column('articles','views','BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `legacy_path`');
        schema_add_column('articles','published_at','DATETIME NULL AFTER `views`');
        schema_add_column('articles','updated_at','TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');

        schema_add_column('article_translations','excerpt','TEXT NULL AFTER `slug`');
        schema_add_column('article_translations','seo_title','VARCHAR(255) NULL AFTER `content`');
        schema_add_column('article_translations','seo_description','VARCHAR(320) NULL AFTER `seo_title`');
        schema_add_column('article_translations','seo_keywords','VARCHAR(500) NULL AFTER `seo_description`');
        schema_add_column('article_translations','hashtags','VARCHAR(500) NULL AFTER `seo_keywords`');
        schema_add_column('article_translations','og_image','VARCHAR(500) NULL AFTER `hashtags`');
        schema_add_column('article_translations','status',"ENUM('draft','published') NOT NULL DEFAULT 'draft' AFTER `og_image`");
        schema_add_column('article_translations','updated_at','TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');

        schema_add_column('pages','template',"ENUM('standard','wide','landing') NOT NULL DEFAULT 'standard' AFTER `sort_order`");
        // Pages are removed to a recoverable trash first; do not destroy page
        // translations or their historical links when an editor removes a footer item.
        schema_add_column('pages','deleted_at','DATETIME NULL AFTER `template`');
        schema_add_column('page_translations','content_mode',"ENUM('visual','html') NOT NULL DEFAULT 'visual' AFTER `content`");
        schema_add_column('page_translations','custom_css','LONGTEXT NULL AFTER `content_mode`');
        schema_add_column('page_translations','custom_js','LONGTEXT NULL AFTER `custom_css`');
        schema_add_column('page_translations','featured_image','VARCHAR(500) NULL AFTER `custom_js`');
        schema_add_column('page_translations','canonical_url','VARCHAR(700) NULL AFTER `seo_description`');
        schema_add_column('page_translations','show_title','TINYINT(1) NOT NULL DEFAULT 1 AFTER `canonical_url`');
        schema_add_column('page_translations','page_bg_color',"VARCHAR(20) NOT NULL DEFAULT '#ffffff' AFTER `show_title`");
        schema_add_column('page_translations','page_text_color',"VARCHAR(20) NOT NULL DEFAULT '#202522' AFTER `page_bg_color`");
        schema_add_column('page_translations','content_width',"VARCHAR(20) NOT NULL DEFAULT 'standard' AFTER `page_text_color`");

        Database::exec("CREATE TABLE IF NOT EXISTS auth_throttles (throttle_key CHAR(64) PRIMARY KEY,attempts INT UNSIGNED NOT NULL DEFAULT 0,last_attempt_at DATETIME NOT NULL,locked_until DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS notifications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,type VARCHAR(80) NOT NULL DEFAULT 'info',title VARCHAR(190) NOT NULL,message TEXT NULL,link VARCHAR(500) NULL,is_read TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id,is_read,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS writer_thanks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,title VARCHAR(190) NULL,message TEXT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(user_id,active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NULL,action VARCHAR(120) NOT NULL,target_type VARCHAR(80) NULL,target_id BIGINT UNSIGNED NULL,details TEXT NULL,ip_address VARCHAR(64) NULL,user_agent VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id,created_at),INDEX(action,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS admin_notes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,assigned_to BIGINT UNSIGNED NULL,title VARCHAR(190) NOT NULL,body TEXT NULL,priority ENUM('normal','important','urgent') NOT NULL DEFAULT 'normal',status ENUM('new','doing','done') NOT NULL DEFAULT 'new',visibility ENUM('admin','management') NOT NULL DEFAULT 'admin',due_at DATETIME NULL,pinned TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(status,priority,due_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS site_notifications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,message TEXT NOT NULL,locale VARCHAR(20) NOT NULL DEFAULT '*',button_label VARCHAR(100) NULL,button_url VARCHAR(500) NULL,starts_at DATETIME NULL,ends_at DATETIME NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(active,locale,starts_at,ends_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS languages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,code VARCHAR(20) NOT NULL UNIQUE,name VARCHAR(120) NOT NULL,flag VARCHAR(255) NULL,dir ENUM('rtl','ltr') NOT NULL DEFAULT 'ltr',enabled TINYINT(1) NOT NULL DEFAULT 1,is_default TINYINT(1) NOT NULL DEFAULT 0,sort_order INT NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(enabled,sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_add_column('ads','advertiser_name','VARCHAR(190) NULL AFTER `label`');
        schema_add_column('media','caption','VARCHAR(500) NULL AFTER `title`');
        schema_add_column('media','description','TEXT NULL AFTER `caption`');
        Database::exec("CREATE TABLE IF NOT EXISTS translation_strings (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,namespace VARCHAR(80) NOT NULL,text_key VARCHAR(120) NOT NULL,locale VARCHAR(20) NOT NULL,value TEXT NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_translation_string(namespace,text_key,locale),INDEX(locale,namespace)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS translation_entry_states (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            translation_key VARCHAR(255) NOT NULL,
            locale VARCHAR(20) NOT NULL,
            state ENUM('untranslated','draft','translated','needs_review','approved') NOT NULL DEFAULT 'draft',
            source_hash CHAR(64) NULL,
            updated_by BIGINT UNSIGNED NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_translation_entry_state(translation_key,locale),
            INDEX(locale,state)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS translation_imports (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_format VARCHAR(12) NOT NULL,
            status ENUM('preview','confirmed','failed') NOT NULL DEFAULT 'preview',
            total_rows INT UNSIGNED NOT NULL DEFAULT 0,
            new_count INT UNSIGNED NOT NULL DEFAULT 0,
            updated_count INT UNSIGNED NOT NULL DEFAULT 0,
            unknown_count INT UNSIGNED NOT NULL DEFAULT 0,
            error_count INT UNSIGNED NOT NULL DEFAULT 0,
            report_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            confirmed_at DATETIME NULL,
            INDEX(user_id,created_at), INDEX(status,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS translation_import_rows (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            import_id BIGINT UNSIGNED NOT NULL,
            `row_number` INT UNSIGNED NOT NULL,
            translation_key VARCHAR(255) NULL,
            entity_type VARCHAR(40) NULL,
            entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            field_name VARCHAR(80) NULL,
            source_locale VARCHAR(20) NULL,
            source_text LONGTEXT NULL,
            source_hash CHAR(64) NULL,
            translations_json LONGTEXT NULL,
            action_name VARCHAR(30) NOT NULL DEFAULT 'skip',
            validation_state ENUM('valid','warning','error','unknown') NOT NULL DEFAULT 'valid',
            messages_json LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(import_id,`row_number`), INDEX(translation_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS not_found_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,path VARCHAR(700) NOT NULL,locale VARCHAR(20) NULL,referrer VARCHAR(700) NULL,hits BIGINT UNSIGNED NOT NULL DEFAULT 1,last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_404_path(path(255)),INDEX(last_seen_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_put_setting_default('security_login_throttle','1');
        schema_put_setting_default('security_form_rate_limit','1');
        schema_put_setting_default('security_spam_honeypot','1');
        schema_put_setting_default('security_audit_login','1');
        schema_put_setting_default('security_community_antispam','1');
        schema_put_setting_default('security_content_protection','1');
        schema_put_setting_default('security_upload_validation','1');
        schema_add_column('ads','price_note','VARCHAR(190) NULL AFTER `advertiser_name`');
        schema_add_column('ads','admin_note','TEXT NULL AFTER `price_note`');

        Database::exec("CREATE TABLE IF NOT EXISTS contact_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(190) NOT NULL,
            subject VARCHAR(220) NULL,
            message TEXT NOT NULL,
            locale VARCHAR(20) NOT NULL DEFAULT 'ar',
            status ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
            admin_note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(status,created_at), INDEX(email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_add_column('contact_messages','category','VARCHAR(80) NULL AFTER `subject`');
        schema_add_column('contact_messages','report_details','TEXT NULL AFTER `message`');
        schema_add_column('contact_messages','case_reference','VARCHAR(40) NULL AFTER `status`');


        if(!schema_table_exists('writer_applications')){
            Database::exec("CREATE TABLE writer_applications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(160) NOT NULL,email VARCHAR(190) NOT NULL,phone VARCHAR(60) NOT NULL,country VARCHAR(120) NULL,city VARCHAR(120) NULL,topics VARCHAR(500) NULL,experience TEXT NULL,sample_url VARCHAR(500) NULL,bio TEXT NOT NULL,status ENUM('new','reviewed','approved','rejected') NOT NULL DEFAULT 'new',admin_note TEXT NULL,verification_token_hash CHAR(64) NULL,verification_expires_at DATETIME NULL,email_verified_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(status),INDEX(email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            schema_add_column('writer_applications','verification_token_hash','CHAR(64) NULL AFTER `admin_note`');
            schema_add_column('writer_applications','verification_expires_at','DATETIME NULL AFTER `verification_token_hash`');
            schema_add_column('writer_applications','email_verified_at','DATETIME NULL AFTER `verification_expires_at`');
        }
        // Writer application workflow extensions (idempotent, preserves legacy rows).
        schema_add_column('writer_applications','user_id','BIGINT UNSIGNED NULL AFTER `id`');
        schema_add_column('writer_applications','username','VARCHAR(80) NULL AFTER `email`');
        schema_add_column('writer_applications','terms_accepted_at','DATETIME NULL AFTER `bio`');
        schema_add_column('writer_applications','submitted_at','DATETIME NULL AFTER `email_verified_at`');
        schema_add_column('writer_applications','last_submitted_at','DATETIME NULL AFTER `submitted_at`');
        try { Database::exec("ALTER TABLE writer_applications MODIFY status ENUM('pending','under_review','changes_requested','approved','rejected','new','reviewed') NOT NULL DEFAULT 'pending'"); } catch(Throwable) {}
        try { Database::exec("UPDATE writer_applications SET status='pending' WHERE status='new'"); Database::exec("UPDATE writer_applications SET status='under_review' WHERE status='reviewed'"); } catch(Throwable) {}
        Database::exec("CREATE TABLE IF NOT EXISTS writer_application_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,application_id BIGINT UNSIGNED NOT NULL,from_status VARCHAR(40) NULL,to_status VARCHAR(40) NOT NULL,admin_id BIGINT UNSIGNED NULL,note TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(application_id,created_at),INDEX(admin_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS writer_application_notes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,application_id BIGINT UNSIGNED NOT NULL,author_id BIGINT UNSIGNED NULL,body TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(application_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_put_setting_default('writer_applications_enabled','1');
        schema_put_setting_default('writer_application_resubmission','1');
        Database::exec("CREATE TABLE IF NOT EXISTS article_revisions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,article_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,locale VARCHAR(20) NOT NULL,title VARCHAR(255) NULL,excerpt TEXT NULL,content LONGTEXT NULL,seo_title VARCHAR(255) NULL,seo_description VARCHAR(320) NULL,seo_keywords VARCHAR(500) NULL,hashtags VARCHAR(500) NULL,featured_image VARCHAR(500) NULL,category_id BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(article_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS ads (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,slot VARCHAR(80) NOT NULL,locale VARCHAR(20) NOT NULL DEFAULT '*',type ENUM('image','code') NOT NULL DEFAULT 'image',label VARCHAR(120) NULL,image_url VARCHAR(500) NULL,target_url VARCHAR(500) NULL,code MEDIUMTEXT NULL,active TINYINT(1) NOT NULL DEFAULT 1,starts_at DATETIME NULL,ends_at DATETIME NULL,device VARCHAR(20) NOT NULL DEFAULT 'all',views BIGINT UNSIGNED NOT NULL DEFAULT 0,clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(slot,locale,active)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_add_column('ads','starts_at','DATETIME NULL AFTER `active`');
        schema_add_column('ads','ends_at','DATETIME NULL AFTER `starts_at`');
        schema_add_column('ads','device',"VARCHAR(20) NOT NULL DEFAULT 'all' AFTER `ends_at`");
        schema_add_column('ads','views','BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `device`');
        schema_add_column('ads','clicks','BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `views`');
        Database::exec("CREATE TABLE IF NOT EXISTS media (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NULL,path VARCHAR(500) NOT NULL,mime VARCHAR(120) NOT NULL,alt_text VARCHAR(255) NULL,title VARCHAR(190) NULL,original_name VARCHAR(255) NULL,width INT UNSIGNED NULL,height INT UNSIGNED NULL,size_bytes BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(user_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS site_blocks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,type ENUM('image','ad') NOT NULL DEFAULT 'image',slot VARCHAR(80) NOT NULL,locale VARCHAR(20) NOT NULL DEFAULT '*',title VARCHAR(190) NULL,image_url VARCHAR(500) NULL,target_url VARCHAR(500) NULL,ad_id BIGINT UNSIGNED NULL,sort_order INT NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(slot,locale,active,sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS integrations (service_key VARCHAR(80) PRIMARY KEY,enabled TINYINT(1) NOT NULL DEFAULT 0,config_json MEDIUMTEXT NULL,updated_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(enabled),INDEX(updated_by)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS custom_tracking_codes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(160) NOT NULL,location ENUM('head','body_start','body_end') NOT NULL DEFAULT 'body_end',consent_category ENUM('essential','analytics','marketing','ads') NOT NULL DEFAULT 'marketing',code MEDIUMTEXT NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,updated_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(active,location),INDEX(consent_category)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::exec("CREATE TABLE IF NOT EXISTS story_submissions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,author_user_id BIGINT UNSIGNED NULL,name VARCHAR(160) NOT NULL,email VARCHAR(190) NOT NULL,phone VARCHAR(60) NULL,title VARCHAR(220) NOT NULL,story MEDIUMTEXT NOT NULL,image VARCHAR(500) NULL,locale VARCHAR(20) NOT NULL DEFAULT 'ar',status ENUM('new','reviewed','accepted','rejected') NOT NULL DEFAULT 'new',admin_note TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(status,created_at),INDEX(email),INDEX(locale),INDEX(author_user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_add_column('story_submissions','tracking_hash','CHAR(64) NULL');
        try { Database::exec("ALTER TABLE story_submissions MODIFY status ENUM('new','reviewed','accepted','rejected','changes_requested') NOT NULL DEFAULT 'new'"); } catch(Throwable) {}
        Database::exec("CREATE TABLE IF NOT EXISTS story_submission_revisions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,submission_id BIGINT UNSIGNED NOT NULL,revision_no INT UNSIGNED NOT NULL,title VARCHAR(220) NOT NULL,name VARCHAR(160) NOT NULL,story MEDIUMTEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(submission_id,revision_no)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) NOT NULL UNIQUE,locale VARCHAR(20) NOT NULL DEFAULT 'ar',status ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',source VARCHAR(80) NOT NULL DEFAULT 'footer',consented_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(status,created_at),INDEX(locale)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_put_setting_default('footer_show_email','1');
        schema_put_setting_default('footer_show_newsletter','1');
        schema_put_setting_default('footer_newsletter_title','القصص القادمة إلى بريدك');
        schema_put_setting_default('footer_newsletter_text','اشترك ليصلك جديد سراديب دون إزعاج.');

        schema_put_setting_default('tracking_consent_enabled','0');
        schema_put_setting_default('tracking_consent_version','1');
        schema_put_setting_default('tracking_consent_title','خصوصيتك تهمنا');
        schema_put_setting_default('tracking_consent_message','نستخدم أدوات قياس وإعلانات غير ضرورية فقط بعد موافقتك. يمكنك تغيير اختيارك في أي وقت.');
        schema_put_setting_default('tracking_privacy_url','/page/privacy-policy');
        foreach(['below_header','home_top','home_after_featured','home_between_sections','article_top','article_sidebar','inside_article_3','inside_article_7','inside_article_end','footer_top'] as $slot) schema_put_setting_default('ad_slot_enabled_'.$slot,'1');

        foreach(['category_translations','article_translations','page_translations'] as $table){
            if(schema_table_exists($table) && schema_column_exists($table,'locale')){
                try { Database::exec("ALTER TABLE `{$table}` MODIFY `locale` VARCHAR(20) NOT NULL"); } catch(Throwable) {}
            }
        }
        try {
            $launchLanguages=[
                ['ar','العربية','sy','rtl',1,1,0],
                ['en','English','gb','ltr',1,0,10],
                ['es','Español','es','ltr',1,0,20],
                ['fr','Français','fr','ltr',1,0,30],
                ['ru','Русский','ru','ltr',1,0,40],
            ];
            foreach($launchLanguages as $lang){
                $row=Database::one('SELECT id FROM languages WHERE code=?',[$lang[0]]);
                if($row) Database::exec('UPDATE languages SET name=?,flag=?,dir=?,enabled=1,is_default=?,sort_order=? WHERE code=?',[$lang[1],$lang[2],$lang[3],$lang[5],$lang[6],$lang[0]]);
                else Database::insert('INSERT INTO languages(code,name,flag,dir,enabled,is_default,sort_order) VALUES(?,?,?,?,?,?,?)',$lang);
            }
            Database::exec("UPDATE languages SET enabled=0,is_default=0 WHERE code NOT IN ('ar','en','es','fr','ru')");
            schema_put_setting('default_locale','ar');
            schema_put_setting_default('translation_fallback_enabled','0');
        } catch(Throwable) {}
        try { Database::exec("UPDATE users SET email_verified_at=COALESCE(email_verified_at,CURRENT_TIMESTAMP) WHERE status='active'"); } catch(Throwable) {}

        // Saradeeeb Community — native discussion layer sharing the existing users, languages and admin.
        Database::exec("CREATE TABLE IF NOT EXISTS community_categories (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sort_order INT NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(active,sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS community_category_translations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,category_id BIGINT UNSIGNED NOT NULL,locale VARCHAR(20) NOT NULL,name VARCHAR(160) NOT NULL,slug VARCHAR(190) NOT NULL,description TEXT NULL,UNIQUE KEY uq_ccat_locale(category_id,locale),UNIQUE KEY uq_ccat_slug(locale,slug),INDEX(locale),CONSTRAINT fk_ccat_category FOREIGN KEY(category_id) REFERENCES community_categories(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS community_topics (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,author_id BIGINT UNSIGNED NOT NULL,category_id BIGINT UNSIGNED NULL,locale VARCHAR(20) NOT NULL,article_id BIGINT UNSIGNED NULL,title VARCHAR(190) NOT NULL,slug VARCHAR(220) NOT NULL,excerpt VARCHAR(500) NULL,content MEDIUMTEXT NOT NULL,image VARCHAR(500) NULL,tags VARCHAR(500) NULL,status ENUM('open','closed','hidden') NOT NULL DEFAULT 'open',pinned TINYINT(1) NOT NULL DEFAULT 0,featured_week TINYINT(1) NOT NULL DEFAULT 0,views BIGINT UNSIGNED NOT NULL DEFAULT 0,replies_count INT UNSIGNED NOT NULL DEFAULT 0,likes_count INT UNSIGNED NOT NULL DEFAULT 0,last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_ctopic_slug(locale,slug),UNIQUE KEY uq_ctopic_article(article_id,locale),INDEX(locale,status,last_activity_at),INDEX(category_id,locale),INDEX(author_id,created_at),INDEX(featured_week,locale),CONSTRAINT fk_ctopic_author FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_ctopic_category FOREIGN KEY(category_id) REFERENCES community_categories(id) ON DELETE SET NULL,CONSTRAINT fk_ctopic_article FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS community_replies (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,topic_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,reply_to_reply_id BIGINT UNSIGNED NULL,content MEDIUMTEXT NOT NULL,status ENUM('visible','hidden','deleted') NOT NULL DEFAULT 'visible',likes_count INT UNSIGNED NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(topic_id,status,id),INDEX(user_id,created_at),INDEX(reply_to_reply_id),CONSTRAINT fk_creply_topic FOREIGN KEY(topic_id) REFERENCES community_topics(id) ON DELETE CASCADE,CONSTRAINT fk_creply_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_creply_parent FOREIGN KEY(reply_to_reply_id) REFERENCES community_replies(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS community_likes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,target_type ENUM('topic','reply') NOT NULL,target_id BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_clike(user_id,target_type,target_id),INDEX(target_type,target_id),CONSTRAINT fk_clike_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS community_follows (user_id BIGINT UNSIGNED NOT NULL,topic_id BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,topic_id),INDEX(topic_id),CONSTRAINT fk_cfollow_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_cfollow_topic FOREIGN KEY(topic_id) REFERENCES community_topics(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS community_saves (user_id BIGINT UNSIGNED NOT NULL,topic_id BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,topic_id),INDEX(topic_id),CONSTRAINT fk_csave_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_csave_topic FOREIGN KEY(topic_id) REFERENCES community_topics(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS community_reports (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,target_type ENUM('topic','reply') NOT NULL,target_id BIGINT UNSIGNED NOT NULL,reason ENUM('abuse','spam','offtopic','copyright','other') NOT NULL DEFAULT 'other',details VARCHAR(1000) NULL,status ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',reviewed_by BIGINT UNSIGNED NULL,reviewed_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(status,created_at),INDEX(target_type,target_id),INDEX(user_id,target_type,target_id,status),CONSTRAINT fk_creport_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_creport_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS community_bans (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL UNIQUE,reason VARCHAR(500) NULL,expires_at DATETIME NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(expires_at),CONSTRAINT fk_cban_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_cban_admin FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS community_topic_activity (topic_id BIGINT UNSIGNED NOT NULL,activity_date DATE NOT NULL,views INT UNSIGNED NOT NULL DEFAULT 0,replies INT UNSIGNED NOT NULL DEFAULT 0,likes INT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY(topic_id,activity_date),INDEX(activity_date),CONSTRAINT fk_cactivity_topic FOREIGN KEY(topic_id) REFERENCES community_topics(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS community_user_preferences (user_id BIGINT UNSIGNED PRIMARY KEY,email_replies TINYINT(1) NOT NULL DEFAULT 0,email_mentions TINYINT(1) NOT NULL DEFAULT 0,email_follows TINYINT(1) NOT NULL DEFAULT 0,email_admin TINYINT(1) NOT NULL DEFAULT 1,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_cpref_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        schema_put_setting_default('community_enabled','1');
        schema_put_setting_default('community_topics_per_page','15');
        schema_put_setting_default('community_replies_per_page','30');
        schema_put_setting_default('community_max_topics_hour','5');
        schema_put_setting_default('community_max_replies_hour','30');
        schema_put_setting_default('community_max_links_topic','5');
        schema_put_setting_default('community_heat_days','7');
        schema_put_setting_default('community_heat_warm','30');
        schema_put_setting_default('community_heat_hot','100');
        schema_put_setting_default('community_heat_blazing','300');
        schema_put_setting_default('community_email_notifications','0');

        try {
            $cc=(int)(Database::one('SELECT COUNT(*) c FROM community_categories')['c']??0);
            if($cc===0){
                $defs=[
                    ['ar'=>['نقاشات عامة','نقاشات-عامة','مواضيع ونقاشات عامة بين أعضاء سراديب.']],
                    ['ar'=>['الكتابة والتدوين','الكتابة-والتدوين','كل ما يتعلق بالكتابة والتدوين وتجارب الكتّاب.']],
                    ['ar'=>['حول مقالات سراديب','حول-مقالات-سراديب','نقاشات مرتبطة بالمقالات المنشورة على الموقع.']],
                    ['ar'=>['أسئلة وتجارب','أسئلة-وتجارب','الأسئلة وطلب الآراء والتجارب الشخصية والخبرات.']],
                    ['ar'=>['اقتراحات المنتدى','اقتراحات-المنتدى','اقتراحات الأعضاء لتطوير سراديب أو اقتراح محتوى جديد.']],
                ];
                foreach($defs as $i=>$translations){
                    $cid=Database::insert('INSERT INTO community_categories(sort_order,active) VALUES(?,1)',[($i+1)*10]);
                    foreach($translations as $loc=>$v) Database::insert('INSERT INTO community_category_translations(category_id,locale,name,slug,description) VALUES(?,?,?,?,?)',[$cid,$loc,$v[0],$v[1],$v[2]]);
                }
            }
        } catch(Throwable) {}

        // Keep important navigation items visible without destroying the administrator's ordering.
        try {
            $raw=setting('header_menu_json','');
            if($raw!==''){
                $menu=json_decode($raw,true);
                if(is_array($menu)){
                    if(!in_array('community',$menu,true)){
                        $pos=array_search('categories',$menu,true); if($pos===false)$pos=count($menu)-1;
                        array_splice($menu,max(0,$pos+1),0,['community']);
                    }
                    if(!in_array('story',$menu,true)){
                        $pos=array_search('community',$menu,true); if($pos===false)$pos=count($menu)-1;
                        array_splice($menu,max(0,$pos+1),0,['story']);
                    }
                    schema_put_setting('header_menu_json',json_encode(array_values(array_unique($menu)),JSON_UNESCAPED_UNICODE));
                }
            }
        } catch(Throwable) {}

        schema_put_setting_default('smtp_enabled','0');
        schema_put_setting_default('smtp_host','');
        schema_put_setting_default('smtp_port','587');
        schema_put_setting_default('smtp_username','');
        schema_put_setting_default('smtp_password','');
        schema_put_setting_default('smtp_encryption','tls');
        schema_put_setting_default('smtp_from_email','');
        schema_put_setting_default('smtp_from_name','سراديب');
        schema_put_setting_default('contact_form_email','');
        schema_put_setting_default('story_submission_email','');
        schema_put_setting_default('footer_designer_enabled','1');
        schema_put_setting_default('footer_designer_prefix_ar','تم تصميم هذا الموقع بواسطة');
        schema_put_setting_default('footer_designer_prefix_en','Site designed by');
        schema_put_setting_default('footer_designer_prefix_es','Sitio diseñado por');
        schema_put_setting_default('footer_designer_prefix_fr','Site conçu par');
        schema_put_setting_default('footer_designer_name','عبدالعزيز');
        schema_put_setting_default('footer_designer_url','');
        schema_put_setting_default('footer_designer_new_tab','1');
        Database::exec("CREATE TABLE IF NOT EXISTS writer_profiles (user_id BIGINT UNSIGNED PRIMARY KEY,professional_title VARCHAR(190) NULL,cv_summary TEXT NULL,location VARCHAR(190) NULL,website VARCHAR(500) NULL,linkedin VARCHAR(500) NULL,portfolio_url VARCHAR(500) NULL,contact_email VARCHAR(190) NULL,experience_json LONGTEXT NULL,education_json LONGTEXT NULL,skills_json LONGTEXT NULL,certificates_json LONGTEXT NULL,languages_json LONGTEXT NULL,achievements_json LONGTEXT NULL,portfolio_json LONGTEXT NULL,is_public TINYINT(1) NOT NULL DEFAULT 0,show_contact TINYINT(1) NOT NULL DEFAULT 0,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_writer_profile_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS article_comments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,article_id BIGINT UNSIGNED NOT NULL,parent_id BIGINT UNSIGNED NULL,user_id BIGINT UNSIGNED NULL,guest_name VARCHAR(120) NULL,guest_email VARCHAR(190) NULL,content TEXT NOT NULL,status ENUM('pending','approved','spam','trash') NOT NULL DEFAULT 'pending',ip_hash CHAR(64) NULL,user_agent VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(article_id,status,created_at),INDEX(parent_id),INDEX(user_id),CONSTRAINT fk_ac_article FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE,CONSTRAINT fk_ac_parent FOREIGN KEY(parent_id) REFERENCES article_comments(id) ON DELETE CASCADE,CONSTRAINT fk_ac_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_put_setting_default('comments_enabled','1');
        schema_put_setting_default('comments_require_moderation','1');
        schema_put_setting_default('comments_registered_autoapprove','1');
        schema_put_setting_default('comments_allow_guests','1');
        schema_put_setting_default('comments_allow_replies','1');
        schema_put_setting_default('comments_max_links','2');
        schema_put_setting_default('comments_rate_seconds','45');
        schema_put_setting_default('comments_blocked_words','');
        Database::exec("CREATE TABLE IF NOT EXISTS editorial_threads (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,writer_id BIGINT UNSIGNED NOT NULL,article_id BIGINT UNSIGNED NULL,story_submission_id BIGINT UNSIGNED NULL,subject VARCHAR(190) NOT NULL,created_by BIGINT UNSIGNED NOT NULL,status ENUM('open','archived') NOT NULL DEFAULT 'open',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(writer_id,status,updated_at),INDEX(article_id),INDEX(story_submission_id),FOREIGN KEY(writer_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE SET NULL,FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::exec("CREATE TABLE IF NOT EXISTS editorial_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,thread_id BIGINT UNSIGNED NOT NULL,sender_id BIGINT UNSIGNED NOT NULL,body TEXT NOT NULL,read_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(thread_id,id),INDEX(sender_id),INDEX(read_at),FOREIGN KEY(thread_id) REFERENCES editorial_threads(id) ON DELETE CASCADE,FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        schema_put_setting_default('footer_show_languages','1');
        schema_put_setting('tracking_consent_enabled','0');
        schema_add_column('users','writer_directory_hidden','TINYINT(1) NOT NULL DEFAULT 0 AFTER `writer_honored`');
        Database::exec("CREATE TABLE IF NOT EXISTS writer_card_preferences (user_id BIGINT UNSIGNED PRIMARY KEY,template VARCHAR(30) NOT NULL DEFAULT 'classic',card_size VARCHAR(30) NOT NULL DEFAULT 'post',show_qr TINYINT(1) NOT NULL DEFAULT 1,show_stats TINYINT(1) NOT NULL DEFAULT 1,show_socials TINYINT(1) NOT NULL DEFAULT 1,quote_text VARCHAR(180) NULL,style_json LONGTEXT NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_writer_card_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach(['writer_cards_enabled','writer_cards_download','writer_cards_pdf','writer_cards_share','writer_cards_qr','writer_cards_stats'] as $key) schema_put_setting_default($key,'1');
        // Writer-membership additions are additive: existing accounts and profiles stay intact.
        if(schema_table_exists('writer_applications')){
            try { Database::exec("ALTER TABLE writer_applications MODIFY status ENUM('pending','under_review','changes_requested','approved','rejected','suspended','new','reviewed') NOT NULL DEFAULT 'pending'"); } catch(Throwable) {}
            Database::exec("CREATE TABLE IF NOT EXISTS writer_application_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,application_id BIGINT UNSIGNED NOT NULL,sender_id BIGINT UNSIGNED NULL,visibility ENUM('applicant','internal') NOT NULL DEFAULT 'applicant',body TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(application_id,visibility,created_at),INDEX(sender_id),CONSTRAINT fk_wapp_message_application FOREIGN KEY(application_id) REFERENCES writer_applications(id) ON DELETE CASCADE,CONSTRAINT fk_wapp_message_sender FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        schema_put_setting_default('writer_application_require_verified_email','1');
        schema_put_setting_default('writer_application_resubmission','1');
        schema_put_setting('schema_version','655');
    } catch(Throwable $e) {
        try { @file_put_contents(BASE_PATH.'/storage/migration-error.log',date('c').' '.$e->getMessage().PHP_EOL,FILE_APPEND); } catch(Throwable) {}
    }
    // Keep the configured language list in sync even when an older installation
    // stopped a previous migration before reaching the language seed block.
    try {
        if(schema_table_exists('languages')){
            Database::exec("INSERT INTO languages(code,name,flag,dir,enabled,is_default,sort_order) VALUES('ru','Русский','ru','ltr',1,0,40) ON DUPLICATE KEY UPDATE name=VALUES(name),flag=VALUES(flag),dir='ltr',enabled=1,sort_order=40");
        }
    } catch(Throwable) {}
}
