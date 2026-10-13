<?php
$defaultLocale='ar';
$systemPages=$systemPages??[
  ['name'=>'تسجيل الدخول','route'=>'/login'],['name'=>'إنشاء حساب','route'=>'/register'],['name'=>'استعادة كلمة المرور','route'=>'/forgot-password'],['name'=>'البحث','route'=>'/search'],['name'=>'ملف الكاتب','route'=>'/writers/{username}'],['name'=>'المقال','route'=>'/article/{slug}'],['name'=>'التصنيف','route'=>'/category/{slug}'],['name'=>'صفحة 404','route'=>'/أي-رابط-غير-موجود'],
];
?>
<section class="dash-actions">
  <?php $behindPageId=\App\Services\BehindPage::id(); if($behindPageId):?><a class="btn btn-primary" href="/admin/about">التعريف بالمنصة</a><?php endif;?>
  <a class="btn btn-primary" href="/admin/pages/new">＋ إضافة صفحة</a>
  <a class="btn btn-ghost" href="/admin/navigation">إدارة القوائم</a>
  <a class="btn btn-ghost" href="/admin/header">عناصر الهيدر</a>
  <a class="btn btn-ghost" href="/admin/footer">عناصر الفوتر</a>
  <a class="btn btn-ghost" href="/admin/blocks">أقسام الموقع</a>
</section>

<?php if(!empty($trashedPages)):?>
<section class="panel">
  <div class="panel-head"><div><span class="panel-kicker">سلة المهملات</span><h2>صفحات محذوفة قابلة للاستعادة</h2><p>لن تظهر هذه الصفحات في الموقع أو الفوتر. استعادتها لا تعيدها إلى الفوتر تلقائيًا.</p></div><span><?=count($trashedPages)?> صفحة</span></div>
  <div class="table-wrap"><table><thead><tr><th>الصفحة</th><th>تاريخ الحذف</th><th>الإجراء</th></tr></thead><tbody><?php foreach($trashedPages as $trashed):?><tr><td><strong><?=e($trashed['title']?:'بدون عنوان')?></strong><small>#<?=e($trashed['id'])?></small></td><td><?=e($trashed['deleted_at'])?></td><td><form method="post" action="/admin/pages/restore" class="inline-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($trashed['id'])?>"><button class="btn btn-ghost" type="submit">استعادة الصفحة</button></form></td></tr><?php endforeach;?></tbody></table></div>
</section>
<?php endif;?>

<section class="panel">
  <div class="panel-head"><div><span class="panel-kicker">المحتوى</span><h2>الصفحات القابلة للإدارة</h2><p>تحكّم بالعنوان والمحتوى والصور والألوان والروابط وحالة النشر لكل صفحة. استخدم محرر الفوتر لتعديل العناصر الظاهرة أسفل الموقع، والقوائم لترتيب روابط التنقل.</p></div><span><?=count($pages)?> صفحة</span></div>
  <div class="table-wrap"><table><thead><tr><th>الاسم والعنوان</th><th>الرابط</th><th>اللغات المنشورة</th><th>الحالة</th><th>الترتيب</th><th>آخر تعديل</th><th>الإجراءات</th></tr></thead><tbody>
  <?php foreach($pages as $p): $slug=trim((string)($p['slug']??'')); $preview=$slug!==''?\App\Services\ManagedPages::url((int)$p['id'],$defaultLocale,$slug):''; ?>
    <tr>
      <td><strong><?=e($p['title']?:'بدون عنوان')?></strong><small>#<?=e($p['id'])?> · <?=e($p['template']??'standard')?></small></td>
      <td><?php if($preview!==''):?><a href="<?=e($preview)?>" target="_blank" rel="noopener">/page/<?=e($slug)?></a><?php else:?><span>أضف عنوانًا وSlug للمعاينة</span><?php endif;?></td>
      <td><?=e($p['translations'])?> / <?=count(enabled_locales())?></td>
      <td><span class="status s-<?=e($p['status'])?>"><?=e($p['status'])?></span></td>
      <td><?=e($p['sort_order'])?></td>
      <td><?=e($p['updated_at'])?></td>
      <td><div class="actions"><a class="btn btn-ghost" href="<?=((int)$p['id']===$behindPageId)?'/admin/about':'/admin/pages/'.e($p['id'])?>">تحرير</a><?php if($preview!==''):?><a class="btn btn-ghost" href="<?=e($preview)?>" target="_blank" rel="noopener">معاينة</a><?php endif;?><form method="post" action="/admin/pages/toggle" class="inline-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($p['id'])?>"><button class="btn btn-ghost" type="submit"><?=($p['status']??'draft')==='published'?'تعطيل':'تفعيل'?></button></form><form method="post" action="/admin/pages/footer" class="inline-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($p['id'])?>"><button class="btn btn-ghost" type="submit"><?=!empty($p['in_footer'])?'إزالة من الفوتر':'إضافة للفوتر'?></button></form><form method="post" action="/admin/pages/trash" class="inline-form" onsubmit="return confirm('نقل الصفحة إلى سلة المهملات؟')"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($p['id'])?>"><button class="btn btn-ghost danger" type="submit" <?=\App\Services\ManagedPages::key((int)$p['id'])!==null?'disabled title="صفحة نظامية محمية"':''?>>حذف</button></form></div></td>
    </tr>
  <?php endforeach;?>
  <?php if(!$pages):?><tr><td colspan="7" class="empty-state">لا توجد صفحات محتوى بعد. أنشئ صفحة ثم أضفها إلى القائمة التي تريدها.</td></tr><?php endif;?>
  </tbody></table></div>
</section>

<section class="panel">
  <div class="panel-head"><div><span class="panel-kicker">صفحات النظام</span><h2>الصفحات والـRoutes المحمية</h2><p>هذه صفحات وظيفية موجودة في الموقع. تُعرض هنا لمنع وجود مسارات غير مرئية للإدارة، لكن هيكلها البرمجي محمي من التحرير كـHTML.</p></div></div>
  <div class="table-wrap"><table><thead><tr><th>الصفحة</th><th>المسار</th><th>الإدارة الآمنة</th></tr></thead><tbody><?php foreach($systemPages as $page):?><tr><td><strong><?=e($page['name'])?></strong></td><td><code><?=e($page['route'])?></code></td><td><span class="status s-review">إعدادات المحتوى والواجهة فقط</span></td></tr><?php endforeach;?></tbody></table></div>
</section>
