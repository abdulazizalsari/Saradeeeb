<?php
declare(strict_types=1);

$root = __DIR__;
$storageDir = $root . '/storage';
$installedLock = $storageDir . '/installed.lock';
$localConfig = $root . '/config/local.php';
$installGuard = $storageDir . '/install-in-progress.lock';
$error = '';
$success = false;
$blocked = false;
$guardCreated = false;
$guardHandle = null;

/** Escape text before placing it in the installer HTML. */
function installer_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * The base schema deliberately contains the stable core tables.  The
 * application migrations add newer, non-destructive modules.  Keep this list
 * here so an installer can never report success after only importing a
 * bootstrap subset of the database.
 *
 * @return list<string>
 */
function installer_required_tables(): array
{
    return [
        'users', 'settings', 'languages', 'articles', 'article_translations',
        'pages', 'page_translations', 'media', 'writer_profiles',
        'writer_applications', 'notifications', 'audit_logs', 'auth_throttles',
        'community_topics', 'community_replies', 'editorial_threads',
        'editorial_messages', 'podcast_episodes', 'khawatir', 'books',
        'card_templates', 'translation_imports', 'media_folders',
    ];
}

// Either marker means this app has already been configured. Do not offer a form
// that might encourage someone to point the installer at the existing database.
if (is_file($installedLock) || is_file($localConfig)) {
    http_response_code(403);
    $blocked = true;
}

if (!$blocked && is_file($installGuard)) {
    http_response_code(409);
    $blocked = true;
}

if (!$blocked && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $host = trim((string)($_POST['db_host'] ?? 'localhost'));
    $database = trim((string)($_POST['db_name'] ?? ''));
    $dbUser = trim((string)($_POST['db_user'] ?? ''));
    $dbPassword = (string)($_POST['db_pass'] ?? '');
    $baseUrl = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
    $adminName = trim((string)($_POST['admin_name'] ?? ''));
    $adminEmail = strtolower(trim((string)($_POST['admin_email'] ?? '')));
    $adminPassword = (string)($_POST['admin_pass'] ?? '');

    // An exclusive create prevents two simultaneous POSTs from racing through
    // the empty-database check.
    if (!is_dir($storageDir) && !@mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
        $error = 'تعذر إنشاء مجلد التخزين. أنشئ storage واجعله قابلًا للكتابة بواسطة PHP.';
    } else {
        $guardHandle = @fopen($installGuard, 'x');
        if ($guardHandle === false) {
            $error = 'تعذر بدء التثبيت؛ قد تكون هناك عملية تثبيت أخرى أو أن مجلد storage غير قابل للكتابة.';
            http_response_code(409);
        } else {
            $guardCreated = true;
            try {
                // Recheck markers after acquiring the guard.
                if (is_file($installedLock) || is_file($localConfig)) {
                    throw new RuntimeException('يبدو أن التطبيق قد ثُبّت بالفعل. لم تُجرَ أي تغييرات.');
                }

                $validHost = preg_match('/^[A-Za-z0-9.-]+$/', $host) === 1;
                $validDbName = preg_match('/^[A-Za-z0-9_]+$/', $database) === 1;
                $validDbUser = preg_match('/^[A-Za-z0-9_]+$/', $dbUser) === 1;
                $validBaseUrl = filter_var($baseUrl, FILTER_VALIDATE_URL)
                    && in_array((string)parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true);

                if (!$validHost || !$validDbName || !$validDbUser || !$validBaseUrl
                    || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)
                    || strlen($adminPassword) < 12
                    || mb_strlen($adminName, 'UTF-8') > 120
                    || strlen($adminEmail) > 190) {
                    throw new InvalidArgumentException('تحقق من المدخلات. كلمة مرور المدير يجب أن تكون 12 محرفًا على الأقل، واسم قاعدة البيانات ومستخدمها أحرفًا وأرقامًا وشرطة سفلية فقط.');
                }

                $requiredFiles = [
                    $root . '/app/bootstrap.php',
                    $root . '/app/helpers.php',
                    $root . '/app/Core/Router.php',
                    $root . '/app/Core/Database.php',
                    $root . '/app/Core/Auth.php',
                    $root . '/app/Core/View.php',
                    $root . '/app/Controllers/PublicController.php',
                    $root . '/app/Controllers/AuthController.php',
                    $root . '/app/Controllers/AdminController.php',
                    $root . '/app/migrations.php',
                ];
                $missingFiles = array_filter($requiredFiles, static fn(string $file): bool => !is_file($file));
                if ($missingFiles !== []) {
                    throw new RuntimeException('ملفات التطبيق الأساسية غير مكتملة؛ استعد نسخة PHP الكاملة قبل التثبيت.');
                }

                $schemaPath = $root . '/database/schema.sql';
                if (!is_file($schemaPath) || !is_readable($schemaPath)) {
                    throw new RuntimeException('ملف database/schema.sql غير موجود أو غير قابل للقراءة.');
                }

                $pdo = new PDO(
                    "mysql:host={$host};dbname={$database};charset=utf8mb4",
                    $dbUser,
                    $dbPassword,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );

                // Critical protection: never run CREATE/INSERT statements against
                // a database that already contains tables or views.
                $existingObjects = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
                if ($existingObjects !== []) {
                    throw new RuntimeException('قاعدة البيانات المحددة ليست فارغة. لم تُعدّل أي جداول. أنشئ قاعدة جديدة فارغة للتثبيت، ولا تحذف الجداول الموجودة.');
                }

                $schema = file_get_contents($schemaPath);
                if ($schema === false || trim($schema) === '') {
                    throw new RuntimeException('تعذر قراءة مخطط قاعدة البيانات.');
                }
                $statements = array_filter(
                    array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: []),
                    static fn(string $statement): bool => $statement !== ''
                );
                foreach ($statements as $statement) {
                    $pdo->exec($statement);
                }

                // The migration layer is additive and is also the safe upgrade
                // path for installations created by older releases.  Running it
                // here completes a new installation without modifying a database
                // that was not empty (the check above rejects those first).
                require_once $root . '/app/bootstrap.php';
                \App\Core\Database::init([
                    'driver' => 'mysql', 'host' => $host, 'port' => 3306,
                    'database' => $database, 'username' => $dbUser,
                    'password' => $dbPassword, 'charset' => 'utf8mb4',
                ]);
                require_once $root . '/app/migrations.php';
                run_schema_migrations();

                $tableStatement = $pdo->prepare(
                    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
                );
                $missingTables = [];
                foreach (installer_required_tables() as $table) {
                    $tableStatement->execute([$table]);
                    if ((int)$tableStatement->fetchColumn() !== 1) $missingTables[] = $table;
                }
                if ($missingTables !== []) {
                    throw new RuntimeException('لم يكتمل مخطط قاعدة البيانات. لم تُنشأ كل الجداول المطلوبة.');
                }

                $algorithm = in_array('argon2id', password_algos(), true)
                    ? PASSWORD_ARGON2ID
                    : PASSWORD_DEFAULT;
                $passwordHash = password_hash($adminPassword, $algorithm);
                if ($passwordHash === false) {
                    throw new RuntimeException('تعذر تشفير كلمة مرور المدير.');
                }

                $statement = $pdo->prepare(
                    "INSERT INTO users(name,username,email,password_hash,role,status) VALUES(?, 'admin', ?, ?, 'admin', 'active')"
                );
                $statement->execute([$adminName !== '' ? $adminName : 'مدير سراديب', $adminEmail, $passwordHash]);

                $defaults = [
                    'site_name' => 'سراديب',
                    'tagline' => 'ما وراء الظلام… أعظم مما تتخيل',
                    'footer_text' => 'قصص ومعرفة من خلف الأبواب المغلقة.',
                ];
                $settingStatement = $pdo->prepare(
                    'INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)'
                );
                foreach ($defaults as $key => $value) {
                    $settingStatement->execute([$key, $value]);
                }

                $languages = [
                    ['ar', 'العربية', 'sy', 'rtl', 1, 1, 0],
                    ['en', 'English', 'gb', 'ltr', 1, 0, 10],
                    ['es', 'Español', 'es', 'ltr', 1, 0, 20],
                    ['fr', 'Français', 'fr', 'ltr', 1, 0, 30],
                    ['ru', 'Русский', 'ru', 'ltr', 1, 0, 40],
                ];
                $languageStatement = $pdo->prepare(
                    'INSERT INTO languages(code,name,flag,dir,enabled,is_default,sort_order) VALUES(?,?,?,?,?,?,?) '
                    . 'ON DUPLICATE KEY UPDATE name=VALUES(name),flag=VALUES(flag),dir=VALUES(dir),'
                    . 'enabled=VALUES(enabled),is_default=VALUES(is_default),sort_order=VALUES(sort_order)'
                );
                foreach ($languages as $language) {
                    $languageStatement->execute($language);
                }

                // Save config atomically and only after schema/admin creation succeeds.
                // The migration include runs in this file's scope and may reuse
                // generic variable names, so restore the installer-owned path.
                $localConfig = $root . '/config/local.php';
                $config = "<?php\nreturn " . var_export([
                    'db' => [
                        'driver' => 'mysql',
                        'host' => $host,
                        'port' => 3306,
                        'database' => $database,
                        'username' => $dbUser,
                        'password' => $dbPassword,
                        'charset' => 'utf8mb4',
                    ],
                    'app' => [
                        'base_url' => $baseUrl,
                        'debug' => false,
                    ],
                ], true) . ";\n";

                $configTemp = $localConfig . '.installing';
                if (file_put_contents($configTemp, $config, LOCK_EX) === false) {
                    throw new RuntimeException('تعذر حفظ config/local.php.');
                }
                @chmod($configTemp, 0600);
                if (!@rename($configTemp, $localConfig)) {
                    @unlink($configTemp);
                    throw new RuntimeException('تعذر تثبيت ملف إعدادات الاتصال.');
                }
                @chmod($localConfig, 0600);

                $lockTemp = $installedLock . '.installing';
                if (file_put_contents($lockTemp, date(DATE_ATOM) . PHP_EOL, LOCK_EX) === false
                    || !@rename($lockTemp, $installedLock)) {
                    @unlink($lockTemp);
                    throw new RuntimeException('أُنشئ الإعداد المحلي، لكن تعذر حفظ علامة التثبيت. ملف config/local.php يمنع تشغيل المثبّت مجددًا.');
                }

                $success = true;
            } catch (Throwable $exception) {
                // Keep detailed diagnostics server-side; never echo SQL errors or
                // credentials to a public page.
                $logLine = date(DATE_ATOM) . ' ' . $exception->getMessage() . PHP_EOL;
                @file_put_contents($storageDir . '/install-error.log', $logLine, FILE_APPEND | LOCK_EX);
                if ($exception instanceof PDOException) {
                    $error = 'تعذر الاتصال بقاعدة البيانات أو تنفيذ المخطط. راجع سجل التثبيت المحمي أو سجل PHP في لوحة Hostinger.';
                } elseif ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) {
                    $error = $exception->getMessage();
                } else {
                    $error = 'فشل التثبيت. راجع سجل التثبيت المحمي أو سجل PHP في لوحة Hostinger.';
                }
            } finally {
                if (is_resource($guardHandle)) {
                    fclose($guardHandle);
                }
                if ($guardCreated) {
                    @unlink($installGuard);
                }
            }
        }
    }
}

$detectedScheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
$detectedBase = $detectedScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>تثبيت سراديب</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<section class="auth-section">
    <div class="auth-card wide">
        <span class="eyebrow">SARDEEB CUSTOM CMS</span>
        <h1>تثبيت سراديب</h1>
        <?php if ($blocked): ?>
            <div class="notice error">
                <?php if (is_file($installedLock) || is_file($localConfig)): ?>
                    تم إعداد الموقع مسبقًا. لم يُشغّل المثبّت ولم يجرِ أي تعديل. احذف install.php بعد التأكد من عمل الموقع.
                <?php else: ?>
                    توجد علامة لعملية تثبيت سابقة أو متزامنة. لم تُجرَ أي تغييرات. افحص قاعدة البيانات والحالة قبل أي محاولة جديدة.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>أنشئ قاعدة MariaDB فارغة من hPanel أولًا. يفحص المثبّت القاعدة، ويرفض المتابعة إذا وجد فيها أي جدول أو عرض؛ لن يحذف بيانات موجودة.</p>
            <?php if ($error !== ''): ?><div class="notice error"><?= installer_h($error) ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="notice success">تم التثبيت بنجاح. احذف ملف <code>install.php</code> الآن، ثم افتح <a href="/login">تسجيل الدخول</a>. عند أول تشغيل سيبدأ التطبيق ترحيلاته الإضافية؛ افحص سجل الترحيل بعد ذلك.</div>
            <?php else: ?>
                <form method="post" class="form-grid" autocomplete="off">
                    <label>رابط الموقع<input name="base_url" value="<?= installer_h((string)($_POST['base_url'] ?? $detectedBase)) ?>" required></label>
                    <label>DB Host<input name="db_host" value="<?= installer_h((string)($_POST['db_host'] ?? 'localhost')) ?>" required></label>
                    <label>اسم قاعدة البيانات<input name="db_name" value="<?= installer_h((string)($_POST['db_name'] ?? '')) ?>" required></label>
                    <label>مستخدم قاعدة البيانات<input name="db_user" value="<?= installer_h((string)($_POST['db_user'] ?? '')) ?>" required></label>
                    <label class="span-2">كلمة مرور قاعدة البيانات<input type="password" name="db_pass" autocomplete="new-password" required></label>
                    <label>اسم المدير<input name="admin_name" value="<?= installer_h((string)($_POST['admin_name'] ?? 'مدير سراديب')) ?>" maxlength="120" required></label>
                    <label>بريد المدير<input type="email" name="admin_email" value="<?= installer_h((string)($_POST['admin_email'] ?? '')) ?>" maxlength="190" required></label>
                    <label class="span-2">كلمة مرور المدير<input type="password" name="admin_pass" minlength="12" autocomplete="new-password" required></label>
                    <button class="btn btn-primary span-2" type="submit">تثبيت النسخة النهائية</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
</body>
</html>
