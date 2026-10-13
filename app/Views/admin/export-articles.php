<?php $preset=$preset??['status'=>'','author'=>0,'category'=>0,'locale'=>'*']; ?>
<section class="panel">
  <div class="panel-head"><div><span class="panel-kicker">نسخة محمولة من المحتوى</span><h2>تصدير المقالات</h2></div><a class="btn btn-ghost" href="/admin/articles">العودة للمقالات</a></div>
  <div class="info-box"><strong>ماذا يتضمن التصدير؟</strong><p>العنوان والمحتوى والمقتطف والرابط والكاتب والتصنيف والحالة والصورة البارزة وبيانات SEO والوسوم وكل الترجمات المطابقة للفلاتر. لا يتم تعديل أي مقال أثناء التصدير.</p></div>
  <form method="post" action="/admin/export-articles" class="form-grid"><?=csrf_field()?>
    <label>الحالة<select name="status"><option value="">كل الحالات</option><?php foreach(['published'=>'منشور','review'=>'قيد المراجعة','changes'=>'يحتاج تعديلات','draft'=>'مسودة','archived'=>'مؤرشف'] as $k=>$v):?><option value="<?=$k?>" <?=$preset['status']===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
    <label>الكاتب<select name="author"><option value="0">كل الكتّاب</option><?php foreach($authors as $a):?><option value="<?=$a['id']?>" <?=((int)$preset['author']===(int)$a['id'])?'selected':''?>><?=e($a['name'])?> — <?=e($a['username'])?></option><?php endforeach;?></select></label>
    <label>التصنيف<select name="category"><option value="0">كل التصنيفات</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=((int)$preset['category']===(int)$c['id'])?'selected':''?>><?=e($c['name']?:'بدون اسم')?></option><?php endforeach;?></select></label>
    <label>اللغة<select name="locale"><option value="*">كل اللغات والترجمات</option><?php foreach($locales as $code=>$info):?><option value="<?=e($code)?>" <?=$preset['locale']===$code?'selected':''?>><?=e($info['name']??strtoupper($code))?> (<?=e($code)?>)</option><?php endforeach;?></select></label>
    <label class="span-2">صيغة الملف<select name="format"><option value="csv">CSV — مناسب لـ Excel والجداول</option><option value="json">JSON — نسخة كاملة للأنظمة والبرمجة</option><option value="xml">XML — ملف منظم قابل للنقل</option></select></label>
    <div class="span-2 info-box"><strong>ملاحظة الصور</strong><p>يتم تضمين مسار الصورة البارزة وOG Image داخل الملف. ملفات الصور نفسها تبقى في مكتبة الوسائط ولا تُحذف أو تتغير.</p></div>
    <div class="span-2"><button class="btn btn-primary" type="submit">⇩ إنشاء ملف التصدير</button></div>
  </form>
</section>
