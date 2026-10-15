<?php
$slots=[
'below_header'=>['أسفل الهيدر','يظهر مباشرة بعد الهيدر في جميع الصفحات'],
'home_top'=>['الرئيسية — أعلى المحتوى','بعد القسم الرئيسي في الصفحة الرئيسية'],
'home_after_featured'=>['الرئيسية — بعد البطاقات المميزة','بعد شبكة المقالات الرئيسية'],
'home_between_sections'=>['الرئيسية — بين الأقسام','فاصل إعلاني داخل الصفحة الرئيسية'],
'article_top'=>['المقال — أعلى المقال','قبل محتوى المقال'],
'article_sidebar'=>['المقال — الشريط الجانبي','داخل العمود الجانبي للمقال'],
'inside_article_3'=>['داخل المقال — بعد الفقرة 3','إعلان مدمج بعد الفقرة الثالثة'],
'inside_article_7'=>['داخل المقال — بعد الفقرة 7','إعلان مدمج بعد الفقرة السابعة'],
'inside_article_end'=>['نهاية المقال','بعد نهاية محتوى المقال'],
'footer_top'=>['قبل الفوتر','يظهر قبل الفوتر في صفحات الموقع'],
];
$totalViews=array_sum(array_map(fn($a)=>(int)($a['views']??0),$ads)); $totalClicks=array_sum(array_map(fn($a)=>(int)($a['clicks']??0),$ads));
?>
<section class="ads-stats-grid"><article><span>المشاهدات</span><strong><?=number_format($totalViews)?></strong></article><article><span>النقرات</span><strong><?=number_format($totalClicks)?></strong></article><article><span>CTR</span><strong><?=$totalViews?number_format($totalClicks/$totalViews*100,2):'0.00'?>%</strong></article><article><span>الحملات</span><strong><?=count($ads)?></strong></article></section>
<section class="ads-admin-grid">
<div class="panel ad-builder-card"><div class="panel-head"><div><span class="panel-kicker">إعلان جديد</span><h2>مركز الإعلانات</h2></div></div><p class="settings-help">أنشئ الإعلان وحدد مكان ظهوره واللغة والجهاز والفترة الزمنية من نفس الشاشة.</p>
<form method="post" action="/admin/ads/save" class="form-grid ad-builder-form"><?=csrf_field()?><input type="hidden" name="id" value="0">
<label class="span-2">مكان الظهور<select name="slot"><?php foreach($slots as $key=>$info):?><option value="<?=$key?>"><?=e($info[0])?> — <?=e($info[1])?></option><?php endforeach;?></select></label>
<label>اللغة<select name="locale"><option value="*">كل اللغات</option><?php foreach(enabled_locales() as $l=>$i):?><option value="<?=$l?>"><?=e(language_flag($l).' '.language_name($l))?></option><?php endforeach;?></select></label>
<label>نوع الإعلان<select name="type"><option value="image">صورة + رابط</option><option value="code">كود شبكة إعلانية</option></select></label>
<label class="span-2">اسم داخلي للإعلان<input name="label" placeholder="مثال: حملة سبتمبر الرئيسية"></label>
<label>اسم المعلن<input name="advertiser_name" placeholder="دار نشر / شركة / كاتب"></label>
<label>السعر أو الاتفاق<input name="price_note" placeholder="مثال: 150 USD شهريًا"></label>
<label class="span-2">ملاحظة داخلية<textarea name="admin_note" rows="2" placeholder="تفاصيل الاتفاق لا تظهر للزائر"></textarea></label>
<label>الجهاز<select name="device"><option value="all">كل الأجهزة</option><option value="desktop">كمبيوتر فقط</option><option value="mobile">جوال فقط</option></select></label>
<label>بداية الحملة<input type="datetime-local" name="starts_at"></label><label>نهاية الحملة<input type="datetime-local" name="ends_at"></label>
<label>رابط الصورة<input name="image_url" placeholder="/uploads/... أو https://..."></label><label>رابط الهدف<input name="target_url" placeholder="https://..."></label>
<label class="span-2">كود الإعلان<textarea name="code" rows="7" placeholder="ضع كود AdSense أو الشبكة الإعلانية هنا"></textarea></label>
<label class="check"><input type="checkbox" name="active" checked> الإعلان مفعّل</label><button class="btn btn-primary">حفظ الإعلان</button></form></div>
<div class="panel ad-map-card"><div class="panel-head"><div><span class="panel-kicker">خريطة المواضع</span><h2>أماكن الإعلان المتاحة</h2></div></div><div class="ad-slot-map"><?php foreach($slots as $key=>$info):?><div><span><?=e($key)?></span><strong><?=e($info[0])?></strong><small><?=e($info[1])?></small></div><?php endforeach;?></div></div>
</section>
<section class="panel"><div class="panel-head"><div><span class="panel-kicker">الإعلانات الحالية</span><h2>إدارة الحملات والإحصاءات</h2></div></div>
<?php if(!$ads):?><div class="empty-state"><strong>لا توجد إعلانات بعد</strong></div><?php else:?><div class="ad-list-v4"><?php foreach($ads as $a):$info=$slots[$a['slot']]??[$a['slot'],''];$v=(int)($a['views']??0);$c=(int)($a['clicks']??0);?>
<article class="ad-admin-card"><div><span class="status <?=$a['active']?'s-active':'s-blocked'?>"><?=$a['active']?'مفعّل':'متوقف'?></span><strong><?=e($a['label']?:$info[0])?></strong><small><?=e($info[0])?> · <?=e($a['locale']==='*'?'كل اللغات':$a['locale'])?> · <?=e($a['device']??'all')?><?php if(!empty($a['advertiser_name'])):?> · <?=e($a['advertiser_name'])?><?php endif;?><?php if(!empty($a['price_note'])):?> · <?=e($a['price_note'])?><?php endif;?></small><div class="ad-card-stats"><span>👁 <?=number_format($v)?></span><span>↗ <?=number_format($c)?></span><span>CTR <?=$v?number_format($c/$v*100,2):'0.00'?>%</span></div></div><div class="ad-admin-actions"><form method="post" action="/admin/ads/toggle"><?=csrf_field()?><input type="hidden" name="id" value="<?=$a['id']?>"><button class="btn btn-ghost"><?=$a['active']?'إيقاف':'تشغيل'?></button></form><form method="post" action="/admin/ads/delete" onsubmit="return confirm('حذف الإعلان؟')"><?=csrf_field()?><input type="hidden" name="id" value="<?=$a['id']?>"><button class="btn btn-danger">حذف</button></form></div></article>
<?php endforeach;?></div><?php endif;?></section>
