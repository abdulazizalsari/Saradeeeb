<?php
$currentPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$isActive=static fn(string $path):bool=>$currentPath===$path||str_starts_with($currentPath,rtrim($path,'/').'/');
$name=trim((string)($u['name']??'مدير سراديب')); $initial=function_exists('mb_substr')?mb_substr($name,0,1,'UTF-8'):substr($name,0,1);
$nav=[
 ['href'=>'/admin-v2','label'=>'نظرة عامة','icon'=>'⌂'],
 ['href'=>'/admin-v2/articles','label'=>'المقالات','icon'=>'▤'],
 ['href'=>'/admin-v2/thoughts','label'=>'الخواطر','icon'=>'✦'],
 ['href'=>'/admin-v2/applications','label'=>'طلبات انتساب الكتّاب','icon'=>'✎'],
 ['href'=>'/admin-v2/writers','label'=>'حسابات الكتّاب','icon'=>'♙'],
 ['href'=>'/admin-v2/administrators','label'=>'حسابات الإدارة','icon'=>'◆'],
 ['href'=>'/admin-v2/audit','label'=>'سجل الإجراءات','icon'=>'◷'],
 ['href'=>'/admin-v2/roles','label'=>'الأدوار والصلاحيات','icon'=>'▣'],
 ['href'=>'/admin-v2/pages','label'=>'الصفحات','icon'=>'▣'],
 ['href'=>'/admin-v2/whatsapp','label'=>'واتساب','icon'=>'◉'],
];
$nav[]=['href'=>'/admin-v2/translations','label'=>'طابور الترجمة العربية','icon'=>'⇄'];
if(\App\Core\AdminV2Rbac::isOwner($u)) $nav[]=['href'=>'/admin-v2/team','label'=>'الفريق والصلاحيات','icon'=>'◆'];
$nav[]=['href'=>'/admin-v2/modules','label'=>'الوحدات المتقدمة','icon'=>'▦'];
?><!doctype html><html lang="ar" dir="rtl" data-theme="<?=e($adminTheme??'dark')?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark light"><meta name="csrf-token" content="<?=e(App\Core\Csrf::token())?>">
<title><?=e($title??'مركز الإدارة')?> | سراديب</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/assets/css/admin-v2.css?v=3">
</head><body class="admin-v2-body">
<div class="admin-v2-shell">
<aside class="admin-v2-sidebar" data-admin-sidebar>
 <div class="admin-v2-brand"><a href="/" class="admin-v2-brand-mark" aria-label="العودة للموقع">س</a><div><strong>سراديب</strong><small>مركز الإدارة 2026</small></div><button type="button" class="admin-v2-close" data-admin-menu-close aria-label="إغلاق القائمة">×</button></div>
 <div class="admin-v2-profile"><span class="admin-v2-avatar"><?=e($initial?:'س')?></span><div><strong><?=e($name)?></strong><small>مدير النظام</small></div></div>
 <nav class="admin-v2-nav" aria-label="أقسام الإدارة"><span class="admin-v2-nav-label">الإدارة</span><?php foreach($nav as $item):?><a class="admin-v2-nav-link <?=$isActive($item['href'])?'active':''?>" href="<?=e($item['href'])?>"><span class="admin-v2-nav-icon" aria-hidden="true"><?=e($item['icon'])?></span><span><?=e($item['label'])?></span></a><?php endforeach;?></nav>
 <div class="admin-v2-sidebar-foot"><a href="/" target="_blank" rel="noopener">عرض الموقع ↗</a><form method="post" action="/logout"><?=csrf_field()?><button type="submit">تسجيل الخروج</button></form></div>
</aside>
<button class="admin-v2-backdrop" data-admin-menu-close type="button" aria-label="إغلاق القائمة"></button>
<main class="admin-v2-main">
 <header class="admin-v2-topbar"><div class="admin-v2-top-left"><button class="admin-v2-menu" type="button" data-admin-menu aria-label="فتح قائمة الإدارة">☰</button><div><span class="admin-v2-kicker">لوحة الإدارة المعزولة</span><h1><?=e($title??'مركز الإدارة')?></h1></div></div><div class="admin-v2-top-actions"><form class="admin-v2-search" method="get" action="/admin-v2/search"><label class="sr-only" for="adminV2Search">بحث الإدارة</label><input id="adminV2Search" name="q" placeholder="بحث سريع…" autocomplete="off"><kbd>Ctrl K</kbd></form><form id="adminV2ThemeForm" method="post" action="/admin-v2/theme"><input type="hidden" name="_token" value="<?=e(App\Core\Csrf::token())?>"><input type="hidden" name="theme" id="adminV2ThemeInput" value="<?=e(($adminTheme??'dark')==='dark'?'light':'dark')?>"><button class="admin-v2-theme" type="submit" aria-label="تبديل مظهر لوحة الإدارة"><?=($adminTheme??'dark')==='dark'?'☼':'☾'?></button></form></div></header>
 <?php if($x=flash('success')):?><div class="admin-v2-alert success"><?=e($x)?></div><?php endif;?><?php if($x=flash('error')):?><div class="admin-v2-alert error"><?=e($x)?></div><?php endif;?>
 <div class="admin-v2-content"><?=$content?></div>
</main></div><script src="/assets/js/admin-v2.js?v=1" defer></script></body></html>
