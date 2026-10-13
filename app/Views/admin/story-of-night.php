<?php
$now=new DateTimeImmutable('now',new DateTimeZone($timezone));
$defaultStart=$now->format('Y-m-d\TH:i');
$defaultEnd=$now->modify('+1 day')->format('Y-m-d\TH:i');
?>
<div class="story-night-admin-grid">
 <section class="panel story-night-admin-current">
  <div class="panel-head"><div><span class="panel-kicker">الواجهة الرئيسية</span><h2>قصة الليلة الحالية</h2><p>يظل الاختيار ثابتًا طوال اليوم وفق توقيت <?=e($timezone)?>، ثم يُحدّث تلقائيًا في اليوم التالي.</p></div><span class="status <?=$enabled?'s-active':'s-pending'?>"><?=$enabled?'النظام مفعّل':'النظام متوقف'?></span></div>
  <?php if($current):?><div class="story-night-admin-preview"><?php if(!empty($current['featured_image'])):?><img src="<?=e($current['featured_image'])?>" alt=""><?php endif;?><div><span><?=e($current['selection_mode']==='manual'?'اختيار يدوي':'اختيار تلقائي')?> · اختيرت في <?=e($current['selected_at']??'—')?></span><h3><?=e($current['title'])?></h3><p><?=e($current['author_name']??'')?><?php if(!empty($current['category_name'])):?> · <?=e($current['category_name'])?><?php endif;?></p><a class="btn btn-ghost" href="<?=e(locale_url('ar','article/'.$current['slug']))?>" target="_blank" rel="noopener">فتح القصة</a></div></div><?php else:?><p class="empty-state">لا توجد قصة مؤهلة للعرض حاليًا.</p><?php endif;?>
 </section>

 <section class="panel">
  <div class="panel-head"><div><span class="panel-kicker">التحكم</span><h2>إعدادات الاختيار التلقائي</h2></div></div>
  <form method="post" action="/admin/story-of-night/settings" class="form-grid compact"><?=csrf_field()?>
   <label class="check-row span-2"><input type="checkbox" name="enabled" value="1" <?=$enabled?'checked':''?>><span><strong>تفعيل قصة الليلة</strong><small>عند إيقافها تختفي البطاقة من الصفحة الرئيسية من دون ترك مساحة فارغة.</small></span></label>
   <label class="span-2">المنطقة الزمنية<input name="timezone" value="<?=e($timezone)?>" required><small>مثال: Europe/Istanbul</small></label>
   <fieldset class="span-2 story-night-category-field"><legend>التصنيفات المؤهلة</legend><p>عدم تحديد أي تصنيف يعني السماح بجميع التصنيفات النشطة.</p><div class="story-night-category-options"><?php foreach($categories as $category):?><label><input type="checkbox" name="categories[]" value="<?=(int)$category['id']?>" <?=in_array((int)$category['id'],$selectedCategories,true)?'checked':''?>> <?=e($category['name'])?></label><?php endforeach;?></div></fieldset>
   <div class="span-2"><button class="btn btn-primary" type="submit">حفظ الإعدادات</button></div>
  </form>
 </section>
</div>

<div class="story-night-admin-grid">
 <section class="panel">
  <div class="panel-head"><div><span class="panel-kicker">جدولة مؤقتة</span><h2>اختيار يدوي</h2><p>يتوقف مفعوله تلقائيًا عند انتهاء المدة ويعود النظام إلى الاختيار اليومي.</p></div></div>
  <?php if($override):?><div class="notice success"><strong><?=e($override['title']??'مقال محدد')?></strong><br>من <?=e($override['starts_at'])?> حتى <?=e($override['ends_at'])?></div><form method="post" action="/admin/story-of-night/automatic"><?=csrf_field()?><button class="btn btn-ghost">إلغاء الاختيار اليدوي والعودة للتلقائي</button></form><?php endif;?>
  <form method="post" action="/admin/story-of-night/override" class="form-grid compact"><?=csrf_field()?>
   <label class="span-2">المقال<select name="article_id" required><option value="">اختر قصة منشورة</option><?php foreach($articles as $article):?><option value="<?=(int)$article['id']?>"><?=e($article['title'])?> — <?=e($article['author_name'])?></option><?php endforeach;?></select></label>
   <label>البداية<input type="datetime-local" name="starts_at" value="<?=e($defaultStart)?>" required></label>
   <label>النهاية<input type="datetime-local" name="ends_at" value="<?=e($defaultEnd)?>" required></label>
   <div class="span-2"><button class="btn btn-primary">تفعيل الاختيار اليدوي</button></div>
  </form>
 </section>

 <section class="panel">
  <div class="panel-head"><div><span class="panel-kicker">قائمة المنع</span><h2>استبعاد قصة</h2><p>الاستبعاد يمنع المقال من الاختيار التلقائي فقط.</p></div></div>
  <form method="post" action="/admin/story-of-night/exclude" class="form-grid compact"><?=csrf_field()?>
   <label class="span-2">المقال<select name="article_id" required><option value="">اختر قصة</option><?php foreach($articles as $article):?><option value="<?=(int)$article['id']?>"><?=e($article['title'])?></option><?php endforeach;?></select></label>
   <label class="span-2">سبب داخلي اختياري<input name="reason" maxlength="255"></label><div class="span-2"><button class="btn btn-ghost">استبعاد</button></div>
  </form>
  <?php if($exclusions):?><div class="story-night-exclusions"><?php foreach($exclusions as $row):?><div><span><strong><?=e($row['title']??('مقال #'.$row['article_id']))?></strong><small><?=e($row['reason']?:'من دون ملاحظة')?></small></span><form method="post" action="/admin/story-of-night/include"><?=csrf_field()?><input type="hidden" name="article_id" value="<?=(int)$row['article_id']?>"><button class="btn btn-ghost">إعادة للقائمة</button></form></div><?php endforeach;?></div><?php endif;?>
 </section>
</div>

<section class="panel">
 <div class="panel-head"><div><span class="panel-kicker">سجل موثوق</span><h2>سجل الاختيارات اليومية</h2></div></div>
 <?php if(!$history):?><p class="empty-state">سيظهر السجل بعد أول اختيار.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>اليوم</th><th>اللغة</th><th>القصة</th><th>الكاتب</th><th>الطريقة</th></tr></thead><tbody><?php foreach($history as $row):?><tr><td><?=e($row['feature_date'])?></td><td><?=e(strtoupper($row['locale']))?></td><td><?=e($row['title']??'قصة محذوفة')?></td><td><?=e($row['author_name']??'—')?></td><td><span class="status <?=$row['selection_mode']==='manual'?'s-pending':'s-active'?>"><?=e($row['selection_mode']==='manual'?'يدوي':'تلقائي')?></span></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</section>
