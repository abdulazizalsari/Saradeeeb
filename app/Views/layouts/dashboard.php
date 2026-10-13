<?php
$u=auth_user();
$role=$u['role']??'writer';
$fullAdmin=$role==='admin';
$contentManager=in_array($role,['admin','editor'],true);
$currentPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$roleLabels=['admin'=>'مدير النظام','editor'=>'مساعد المدير','trusted_writer'=>'كاتب','writer'=>'كاتب','member'=>'عضو منتدى'];
$roleLabel=$roleLabels[$role]??$role;
$name=trim((string)($u['name']??'مستخدم سراديب'));
$dashboardLogo=trim((string)setting('brand_logo',''));
$initial=function_exists('mb_substr')?mb_substr($name,0,1,'UTF-8'):substr($name,0,1);
$icons=[
'home'=>'<svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-5v-6h-5v6h-5A1.5 1.5 0 0 1 3 19.5v-9Z"/></svg>',
'article'=>'<svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 1.5V8h3.5M8 12h8M8 16h8M8 8h3"/></svg>',
'podcast'=>'<svg viewBox="0 0 24 24"><path d="M7 12a5 5 0 0 1 10 0v4a5 5 0 0 1-10 0v-4Zm-3 3v-3a8 8 0 0 1 16 0v3M10 21h4M12 17v4M7 15H5a2 2 0 0 1-2-2v-1M17 15h2a2 2 0 0 0 2-2v-1"/></svg>',
'quote'=>'<svg viewBox="0 0 24 24"><path d="M9.5 6H5.8A2.8 2.8 0 0 0 3 8.8v3.7h5.5V18H3.8M21 6h-3.7a2.8 2.8 0 0 0-2.8 2.8v3.7H20V18h-4.7"/></svg>',
'page'=>'<svg viewBox="0 0 24 24"><path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm2 5h6M9 12h6M9 16h4"/></svg>',
'users'=>'<svg viewBox="0 0 24 24"><path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7-1a3 3 0 0 0 0-6m1.5 11a3.5 3.5 0 0 1 1.5 2.9V20"/></svg>',
'community'=>'<svg viewBox="0 0 24 24"><path d="M7.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm9 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2.5 20v-1.5A4.5 4.5 0 0 1 7 14h1a4.5 4.5 0 0 1 4.5 4.5V20m1.5 0v-1a4 4 0 0 1 4-4h.5a3 3 0 0 1 3 3v2"/></svg>',
'category'=>'<svg viewBox="0 0 24 24"><path d="M4 5h6v6H4V5Zm10 0h6v6h-6V5ZM4 15h6v4H4v-4Zm10 0h6v4h-6v-4Z"/></svg>',
'ads'=>'<svg viewBox="0 0 24 24"><path d="M3 7.5 15 4v16L3 16.5v-9Zm12 2h3a3 3 0 0 1 0 6h-3M6 17.5 7 21h4l-1.2-2.8"/></svg>',
'settings'=>'<svg viewBox="0 0 24 24"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7-3.5 2-1-2-3-2.1.5A7.8 7.8 0 0 0 15 7.4L14.7 5h-5.4L9 7.4a7.8 7.8 0 0 0-1.9 1.1L5 8l-2 3 2 1-2 1 2 3 2.1-.5A7.8 7.8 0 0 0 9 16.6l.3 2.4h5.4l.3-2.4a7.8 7.8 0 0 0 1.9-1.1L19 16l2-3-2-1Z"/></svg>',
'import'=>'<svg viewBox="0 0 24 24"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 17v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2"/></svg>',
'plus'=>'<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
'external'=>'<svg viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/></svg>',
'logout'=>'<svg viewBox="0 0 24 24"><path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5M14 8l4 4-4 4m4-4H8"/></svg>',
'menu'=>'<svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
'apps'=>'<svg viewBox="0 0 24 24"><path d="M12 3v18M3 12h18M5 7h14M5 17h14"/></svg>',
];
if($fullAdmin){
 $navItems=[['/admin','نظرة عامة','home',true],['/admin/articles','المقالات','article',false],['/admin/story-of-night','قصة الليلة','quote',false],['/admin/podcast','البودكاست','podcast',false],['/admin/comments','تعليقات المقالات','community',false],['/admin/editorial-messages','مراسلات الكتّاب','article',false],['/admin/media','مكتبة الوسائط','apps',false],['/admin/notes','الملاحظات والمهام','apps',false],['/admin/pages','الصفحات','page',false],['/admin/navigation','القوائم والتنقل','apps',false],['/admin/story-submissions','القصص المرسلة','article',false],['/admin/messages','رسائل التواصل','article',false],['/admin/newsletter','مشتركو البريد','users',false],['/admin/writer-applications','طلبات الكتّاب','apps',false],['/admin/writers','حسابات الكتّاب','users',false],['/admin/users','الحسابات والصلاحيات','users',false],['/admin/thanks','تكريم الكتّاب','users',false],['/admin/community','المنتدى','community',false],['/admin/categories','التصنيفات','category',false],['/admin/languages','الترجمة واللغات','apps',false],['/admin/ads','مركز الإعلانات','ads',false],['/admin/blocks','أقسام الصور والإعلانات','apps',false],['/admin/site-notifications','إشعارات الموقع','settings',false],['/admin/backup','النسخ الاحتياطي','import',false],['/admin/activity','سجل النشاط','settings',false],['/admin/system','حالة النظام','settings',false],['/admin/links','الروابط و404','settings',false],['/admin/security','الحماية والأمان','settings',false],['/admin/header','تعديل الهيدر','settings',false],['/admin/footer','تعديل الفوتر','settings',false],['/admin/appearance','تصميم متقدم','settings',false],['/admin/whatsapp','واتساب','settings',false],['/admin/social','السوشيال والمشاركة','settings',false],['/admin/integrations','التكاملات والتتبع','settings',false],['/admin/import-wordpress','استيراد WordPress','import',false],['/admin/export-articles','تصدير المقالات','import',false],['/admin/settings','إعدادات الموقع','settings',false]];
}elseif($contentManager){
 $navItems=[
  ['/admin','نظرة المحتوى','home',true],
  ['/admin/articles','المقالات والمراجعة','article',false],
  ['/admin/podcast','البودكاست','podcast',false],
  ['/admin/comments','تعليقات المقالات','community',false],['/admin/editorial-messages','مراسلات الكتّاب','article',false],
  ['/admin/media','مكتبة الوسائط','apps',false],
  ['/admin/notes','الملاحظات والمهام','apps',false],
  ['/admin/writer-applications','طلبات الكتّاب','apps',false],
  ['/admin/writers','إدارة الكتّاب','users',false],
  ['/admin/community','إدارة المنتدى','community',false]
 ];
}else{
 if(!empty($u['writer_vip'])){
  $navItems=[['/writer','الرئيسية','home',true],['/writer/articles/new','استديو الكتابة','article',false],['/writer/story-submissions','قصصي ومسوداتي','article',false],['/writer/account','إدارة قسمي','page',false],['/writer#writer-insights','الإحصاءات','apps',false],['/writer/editorial-messages','مراسلات الإدارة','article',false],['/writer#writer-notifications','الإشعارات','settings',false],['/writer/account#settings','إعدادات حسابي','settings',false]];
 }else{
  $navItems=[['/writer','لوحتي','home',true],['/writer/story-submissions','قصصي المرسلة','article',false],['/writer/editorial-messages','مراسلات فريق التحرير','article',false],['/writer/card','بطاقتي','apps',false],['/writer/account','ملفي المهني والحساب','settings',false]];
 }
}
if($fullAdmin){
    $pendingBookRequests=0;try{$pendingBookRequests=(int)(\App\Core\Database::one("SELECT COUNT(*) c FROM book_service_requests WHERE status IN ('pending','reviewing','negotiating','offer_sent')")['c']??0);}catch(\Throwable){}
    array_splice($navItems,2,0,[['/admin/books/requests','طلبات نشر الكتب'.($pendingBookRequests?' ('.$pendingBookRequests.')':''),'article',false],['/admin/books','إدارة الكتب والإصدارات','article',false]]);
    $navItems[]=['/admin/typography','الخطوط والمقاسات','settings',false];
}
if($contentManager) array_splice($navItems,3,0,[['/admin/khawatir','خواطر','quote',false]]);
else array_splice($navItems,1,0,[['/writer/khawatir','خواطري','quote',false]]);
if($fullAdmin) array_splice($navItems,6,0,[['/admin/writer-cards','بطاقات الكتّاب','users',false]]);
if($fullAdmin) array_splice($navItems,1,0,[['/admin/email-templates','قوالب البريد','settings',false]]);
if($fullAdmin) array_splice($navItems,7,0,[['/admin/card-templates','قوالب البطاقات','apps',false]]);
if($fullAdmin && \App\Services\BehindPage::id()) {
    $position=array_search('/admin/pages',array_column($navItems,0),true);
    array_splice($navItems,$position===false?0:$position+1,0,[['/admin/about','التعريف بالمنصة','page',true]]);
}
$pendingWriterApplications=0;
if($contentManager){
    try{$pendingWriterApplications=(int)(\App\Core\Database::one("SELECT COUNT(*) c FROM writer_applications WHERE status IN ('pending','under_review','changes_requested')")['c']??0);}catch(\Throwable){}
}
$isActive=static function(string $href,bool $exact=false)use($currentPath):bool{if($exact)return rtrim($currentPath,'/')===rtrim($href,'/');return $currentPath===$href||str_starts_with($currentPath,rtrim($href,'/').'/');};
?>
<!doctype html><html lang="ar" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light">
<title><?=e($title??'لوحة سراديب')?></title>
<link rel="manifest" href="/admin-app.webmanifest"><meta name="theme-color" content="#172326"><meta name="apple-mobile-web-app-capable" content="yes">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@100..700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css?v=730"><script defer src="/assets/js/app.js?v=676"></script><?php if(\App\Services\CardTemplateService::active('writer-card')):?><script defer src="/assets/vendor/qrcode/qrcode.min.js"></script><script defer src="/assets/js/card-templates.js?v=1"></script><?php endif;?>
</head><body class="dashboard-body dashboard-v4">
<div class="dashboard-shell">
<aside class="sidebar" aria-label="التنقل في لوحة التحكم">
 <div class="sidebar-top"><a class="dashboard-brand" href="/" aria-label="سراديب"><?php if($dashboardLogo):?><img class="dashboard-brand-logo" src="<?=e($dashboardLogo)?>" alt="<?=e(setting('site_name','سراديب'))?>"><?php else:?><span class="dashboard-brand-mark"><?=e(setting('brand_mark_text','س')?:'س')?></span><?php endif;?><span class="dashboard-brand-copy"><strong><?=e(setting('site_name','سراديب'))?></strong><small>مركز إدارة المحتوى</small></span></a><span class="sidebar-version">SARADEEEB CMS</span></div>
 <nav class="dashboard-nav"><span class="dashboard-nav-label"><?=$fullAdmin?'الإدارة الكاملة':($contentManager?'صلاحيات مساعد المدير':'مساحة الكاتب')?></span>
 <?php foreach($navItems as [$href,$label,$iconName,$exact]):?><a class="dashboard-nav-link <?=$isActive($href,$exact)?'active':''?>" href="<?=e($href)?>"><span class="dashboard-nav-icon"><?=$icons[$iconName]?></span><span><?=e($label)?></span><?php if($href==='/admin/writer-applications' && $pendingWriterApplications>0):?><b class="dashboard-nav-badge" aria-label="<?=$pendingWriterApplications?> طلبات معلقة"><?=$pendingWriterApplications?></b><?php endif;?><?php if($isActive($href,$exact)):?><span class="nav-active-dot"></span><?php endif;?></a><?php endforeach;?>
 <span class="dashboard-nav-label nav-label-space">إجراءات سريعة</span>
 <a class="dashboard-nav-link dashboard-nav-cta" href="/writer/articles/new"><span class="dashboard-nav-icon"><?=$icons['plus']?></span><span>مقال جديد</span></a>
 <a class="dashboard-nav-link" href="/" target="_blank" rel="noopener"><span class="dashboard-nav-icon"><?=$icons['external']?></span><span>عرض الموقع</span></a>
 </nav>
 <div class="sidebar-account"><div class="sidebar-avatar"><?=e($initial?:'س')?></div><div class="sidebar-account-copy"><strong><?=e($name)?></strong><span><?=e($roleLabel)?></span></div></div>
 <form method="post" action="/logout" class="sidebar-logout-form"><?=csrf_field()?><button class="sidebar-logout" type="submit"><span class="dashboard-nav-icon"><?=$icons['logout']?></span><span>تسجيل الخروج</span></button></form>
</aside>
<button class="sidebar-backdrop" type="button" data-dashboard-backdrop aria-label="إغلاق القائمة"></button>
<main class="dashboard-main">
<header class="dash-top"><div class="dash-title-wrap"><button class="menu-btn dashboard-menu-btn" type="button" data-dashboard-menu aria-label="فتح القائمة"><?=$icons['menu']?></button><div><small class="dash-eyebrow"><?=e($roleLabel)?></small><h1><?=e($title??'')?></h1></div></div><div class="dash-top-actions"><a class="dash-icon-link" href="/" target="_blank" title="عرض الموقع"><?=$icons['external']?></a><a class="btn btn-ghost dash-new-khawatir" href="/writer/khawatir/new"><span class="btn-icon"><?=$icons['quote']?></span><span>أضف خاطرة</span></a><a class="btn btn-primary dash-new-article" href="/writer/articles/new"><span class="btn-icon"><?=$icons['plus']?></span><span>مقال جديد</span></a></div></header>
<?php if($x=flash('success')):?><div class="notice success dashboard-notice"><?=e($x)?></div><?php endif;?><?php if($x=flash('error')):?><div class="notice error dashboard-notice"><?=e($x)?></div><?php endif;?>
<?php if(\App\Core\Auth::isImpersonating()):?><div class="impersonation-banner"><div><strong>وضع الإدارة داخل حساب الكاتب</strong><span>أنت ترى مساحة الكاتب لأغراض الدعم والإدارة. لم يتم تسجيل دخول جديد باسم الكاتب ولم تُطلب كلمة مروره.</span></div><form method="post" action="/impersonation/stop"><?=csrf_field()?><button class="btn btn-primary">العودة إلى الإدارة</button></form></div><?php endif;?>
<div class="dashboard-content"><?=$content?></div>
</main></div><script>if("serviceWorker" in navigator){navigator.serviceWorker.register("/admin-sw.js").catch(()=>{});}</script></body></html>

