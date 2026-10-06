<?php
declare(strict_types=1);
$root=__DIR__; $lock=$root.'/storage/installed.lock';
if(is_file($lock)){http_response_code(403);exit('تم تثبيت سراديب مسبقًا. احذف install.php بعد التأكد من عمل الموقع.');}
$error='';$success=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $host=trim($_POST['db_host']??'localhost');$db=trim($_POST['db_name']??'');$user=trim($_POST['db_user']??'');$pass=(string)($_POST['db_pass']??'');$base=rtrim(trim($_POST['base_url']??''),'/');$adminName=trim($_POST['admin_name']??'');$adminEmail=strtolower(trim($_POST['admin_email']??''));$adminPass=(string)($_POST['admin_pass']??'');
    if(!$db||!$user||!filter_var($adminEmail,FILTER_VALIDATE_EMAIL)||strlen($adminPass)<12||!filter_var($base,FILTER_VALIDATE_URL))$error='تحقق من البيانات. كلمة مرور المدير يجب أن تكون 12 محرفًا على الأقل.';
    else try{
        $pdo=new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $schema=file_get_contents($root.'/database/schema.sql');
        foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$schema))) as $stmt){ if($stmt!=='')$pdo->exec($stmt); }
        $username='admin';$algo=in_array('argon2id',password_algos(),true)?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;$hash=password_hash($adminPass,$algo);
        $s=$pdo->prepare("INSERT INTO users(name,username,email,password_hash,role,status) VALUES(?,?,?,?, 'admin','active')");$s->execute([$adminName?:'مدير سراديب',$username,$adminEmail,$hash]);
        $defaults=['site_name'=>'سراديب','tagline'=>'ما وراء الظلام… أعظم مما تتخيل','footer_text'=>'قصص ومعرفة من خلف الأبواب المغلقة.'];$s=$pdo->prepare('INSERT INTO settings(`key`,value) VALUES(?,?)');foreach($defaults as $k=>$v)$s->execute([$k,$v]);
        $langs=[['ar','العربية','🇸🇦','rtl',1,1,0],['en','English','🇬🇧','ltr',1,0,10],['es','Español','🇪🇸','ltr',1,0,20],['ru','Русский','🇷🇺','ltr',1,0,30]]; $ls=$pdo->prepare('INSERT INTO languages(code,name,flag,dir,enabled,is_default,sort_order) VALUES(?,?,?,?,?,?,?)'); foreach($langs as $l)$ls->execute($l);
        $local="<?php\nreturn ".var_export(['db'=>['driver'=>'mysql','host'=>$host,'port'=>3306,'database'=>$db,'username'=>$user,'password'=>$pass,'charset'=>'utf8mb4'],'app'=>['base_url'=>$base,'debug'=>false]],true).";\n";
        if(!is_dir($root.'/config'))mkdir($root.'/config',0755,true);file_put_contents($root.'/config/local.php',$local,LOCK_EX);chmod($root.'/config/local.php',0600);
        if(!is_dir($root.'/storage'))mkdir($root.'/storage',0755,true);file_put_contents($lock,date('c'));$success=true;
    }catch(Throwable $e){$error='فشل التثبيت: '.$e->getMessage();}
}
$detectedScheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
$detectedBase = $detectedScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?><!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تثبيت سراديب</title><link rel="stylesheet" href="/assets/css/app.css"></head><body><section class="auth-section"><div class="auth-card wide"><span class="eyebrow">SARDEEB CUSTOM CMS</span><h1>تثبيت سراديب</h1><p>أنشئ قاعدة MariaDB من hPanel أولًا، ثم أدخل بياناتها هنا. لا ينشئ المثبّت قاعدة جديدة؛ بل يبني الجداول داخل القاعدة التي أنشأتها.</p><?php if($error):?><div class="notice error"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($success):?><div class="notice success">تم التثبيت بنجاح. احذف ملف <code>install.php</code> الآن ثم ادخل إلى <a href="/login">تسجيل الدخول</a>.</div><?php else:?><form method="post" class="form-grid"><label>رابط الموقع<input name="base_url" value="<?=htmlspecialchars($_POST['base_url'] ?? $detectedBase, ENT_QUOTES, 'UTF-8')?>" required></label><label>DB Host<input name="db_host" value="localhost" required></label><label>اسم قاعدة البيانات<input name="db_name" required></label><label>مستخدم قاعدة البيانات<input name="db_user" required></label><label class="span-2">كلمة مرور قاعدة البيانات<input type="password" name="db_pass" required></label><label>اسم المدير<input name="admin_name" value="مدير سراديب" required></label><label>بريد المدير<input type="email" name="admin_email" required></label><label class="span-2">كلمة مرور المدير<input type="password" name="admin_pass" minlength="12" required></label><button class="btn btn-primary span-2">تثبيت النسخة النهائية</button></form><?php endif;?></div></section></body></html>
