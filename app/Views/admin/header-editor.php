<?php
$v=fn(string $key,string $default='')=>(string)($map[$key]??$default);
$c=fn(string $key,string $default='1')=>($map[$key]??$default)==='1';
?>
<style>
.simple-editor{max-width:1050px}.simple-editor .panel{margin-bottom:18px}.simple-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.simple-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.simple-checks label{display:flex;gap:9px;align-items:center;padding:12px;border:1px solid var(--border,#dde5e6);border-radius:12px}.savebar{position:sticky;bottom:12px;background:#fff;padding:12px;border:1px solid #dde5e6;border-radius:14px;display:flex;justify-content:space-between;align-items:center;z-index:4}@media(max-width:720px){.simple-grid,.simple-checks{grid-template-columns:1fr}}
</style>
<div class="simple-editor">
  <div class="page-head"><div><span class="panel-kicker">واجهة بسيطة</span><h1>تعديل الهيدر</h1><p>تحكم بما يظهر أعلى الموقع بدون أكواد.</p></div><a class="btn btn-ghost" href="/" target="_blank" rel="noopener">معاينة الموقع</a></div>
  <form method="post" action="/admin/header/save">
    <?=csrf_field()?>
    <section class="panel">
      <h2>زر «حسابي»</h2>
      <p>زر ثابت وواضح مع أيقونة حساب. يفتح تلقائيًا الحساب المناسب: لوحة الإدارة للمدير، الملف المهني للكاتب، حساب المنتدى للعضو، أو صفحة الدخول للزائر.</p>
      <div class="simple-checks">
        <label><input type="checkbox" name="header_account_cta_enabled" <?=$c('header_account_cta_enabled',$v('header_writer_cta_enabled','1'))?'checked':''?>> إظهار زر «حسابي»</label>
        <label><input type="checkbox" name="header_account_cta_mobile" <?=$c('header_account_cta_mobile',$v('header_writer_cta_mobile','1'))?'checked':''?>> إظهاره على الجوال</label>
      </div>
      <small>الترجمة التلقائية: My account / Mi cuenta / Mon compte / Мой аккаунт.</small>
    </section>
    <section class="panel"><h2>عناصر القائمة</h2><div class="simple-checks"><?php foreach($catalog as $key=>$item):?><label><input type="checkbox" name="header_items[]" value="<?=e($key)?>" <?=in_array($key,$headerMenu,true)?'checked':''?>> <?=e($item['label']??$key)?></label><?php endforeach;?></div><p><a href="/admin/navigation">إدارة روابط مخصصة وترتيب متقدم</a></p></section>
    <section class="panel"><h2>خيارات العرض</h2><div class="simple-checks"><?php foreach(['header_sticky'=>'تثبيت الهيدر','header_scroll_compact'=>'تصغيره عند التمرير','header_show_search'=>'إظهار البحث','header_show_theme'=>'زر الوضع الليلي','header_show_icons'=>'أيقونات القائمة','header_show_socials'=>'السوشيال','header_show_categories_mega'=>'قائمة الأقسام الكبيرة','header_shadow'=>'ظل خفيف'] as $key=>$label):?><label><input type="checkbox" name="<?=$key?>" <?=$c($key)?'checked':''?>> <?=$label?></label><?php endforeach;?></div><div class="simple-grid"><label>ارتفاع الهيدر<input type="number" min="64" max="110" name="header_height" value="<?=e($v('header_height','86'))?>"></label><label>الخلفية<input type="color" name="header_background" value="<?=e($v('header_background','#ffffff'))?>"></label><label>لون النص<input type="color" name="header_text_color" value="<?=e($v('header_text_color','#152d31'))?>"></label><label>لون التفاعل<input type="color" name="header_hover_color" value="<?=e($v('header_hover_color','#157176'))?>"></label></div></section>
    <div class="savebar"><span>تظهر التغييرات بعد الحفظ مباشرة.</span><button class="btn btn-primary">حفظ الهيدر</button></div>
  </form>
</div>
