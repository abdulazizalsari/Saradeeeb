<?php
$statusLabels=['draft'=>'مسودة','review'=>'بانتظار المراجعة','changes'=>'يحتاج تعديلات','published'=>'منشور','archived'=>'مؤرشف'];
$publicUrl=writer_url((string)$writer['username'],default_locale());
?>
<section class="writer-admin-profile panel">
  <div class="writer-admin-hero">
    <div class="writer-admin-identity">
      <?php if(!empty($writer['avatar'])):?><img src="<?=e($writer['avatar'])?>" alt=""><?php else:?><span class="writer-admin-avatar-fallback"><?=e(mb_substr((string)$writer['name'],0,1,'UTF-8'))?></span><?php endif;?>
      <div><span class="panel-kicker">ملف الكاتب</span><h2><?=e($writer['name'])?> <?=writer_badges_html($writer)?></h2><p><?=e($writer['professional_title']?:'كاتب في سراديب')?></p><code><?=e($publicUrl)?></code></div>
    </div>
    <div class="actions writer-admin-actions">
      <a class="btn btn-ghost" target="_blank" href="<?=e($publicUrl)?>">عرض الملف العام</a>
      <a class="btn btn-ghost" href="/admin/editorial-messages?writer=<?=$writer['id']?>">مراسلة الكاتب</a>
      <?php if(($actor['role']??'')==='admin'):?><form method="post" action="/admin/users/impersonate" onsubmit="return confirm('الدخول إلى مساحة الكاتب لأغراض الإدارة والدعم؟')"><?=csrf_field()?><input type="hidden" name="user_id" value="<?=$writer['id']?>"><button class="btn btn-primary">الدخول إلى لوحة الكاتب</button></form><?php endif;?>
    </div>
  </div>
  <div class="writer-admin-stats">
    <div><b><?=number_format((int)($stats['published']??0))?></b><span>مقال منشور</span></div>
    <div><b><?=number_format((int)($stats['total']??0))?></b><span>إجمالي المقالات</span></div>
    <div><b><?=number_format((int)($stats['views']??0))?></b><span>المشاهدات</span></div>
    <div><b><?=e($lastActivity?:'لا يوجد')?></b><span>آخر نشاط معروف</span></div>
  </div>
</section>

<section class="panel writer-account-control">
 <div class="panel-head"><div><span class="panel-kicker">تحكم كامل</span><h2>تعديل حساب الكاتب وملفه المهني</h2><p>تُحفظ تغييرات الحساب والملف العام مباشرة. تغيير كلمة المرور اختياري.</p></div></div>
 <form method="post" action="/admin/writers/save" enctype="multipart/form-data" class="form-grid">
  <?=csrf_field()?><input type="hidden" name="id" value="<?=$writer['id']?>">
  <label>الاسم<input name="name" maxlength="190" value="<?=e($writer['name'])?>" required></label>
  <label>اسم المستخدم<input name="username" maxlength="30" pattern="[A-Za-z][A-Za-z0-9_-]{2,29}" value="<?=e($writer['username'])?>" required><small>يحدد رابط الكاتب العام.</small></label>
  <label>البريد الإلكتروني<input type="email" name="email" value="<?=e($writer['email'])?>" required></label>
  <label>الهاتف<input name="phone" maxlength="80" value="<?=e($writer['phone']??'')?>"></label>
  <label>الدور<select name="role"><option value="writer" <?=$writer['role']==='writer'?'selected':''?>>كاتب</option><option value="trusted_writer" <?=$writer['role']==='trusted_writer'?'selected':''?>>كاتب موثوق</option></select></label>
  <label>حالة الحساب<select name="status"><option value="active" <?=$writer['status']==='active'?'selected':''?>>مفعّل</option><option value="pending" <?=$writer['status']==='pending'?'selected':''?>>معلّق</option><option value="blocked" <?=$writer['status']==='blocked'?'selected':''?>>محظور</option></select></label>
  <label>صورة الكاتب<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"><small>اتركها فارغة للإبقاء على الصورة الحالية.</small></label>
  <label>كلمة مرور جديدة<input type="password" name="new_password" minlength="12" placeholder="اتركها فارغة دون تغيير"><small>12 محرفًا على الأقل.</small></label>
  <label class="span-2">نبذة قصيرة<textarea name="bio" rows="3" maxlength="1200"><?=e($writer['bio']??'')?></textarea></label>
  <label>المسمى المهني<input name="professional_title" maxlength="190" value="<?=e($writer['professional_title']??'')?>"></label>
  <label>المدينة / الموقع<input name="location" maxlength="190" value="<?=e($writer['location']??'')?>"></label>
  <label class="span-2">السيرة المهنية<textarea name="cv_summary" rows="5" maxlength="5000"><?=e($writer['cv_summary']??'')?></textarea></label>
  <label>الموقع الشخصي<input type="url" name="website" value="<?=e($writer['website']??'')?>" placeholder="https://..."></label>
  <label>LinkedIn<input type="url" name="linkedin" value="<?=e($writer['linkedin']??'')?>" placeholder="https://..."></label>
  <label>رابط معرض الأعمال<input type="url" name="portfolio_url" value="<?=e($writer['portfolio_url']??'')?>" placeholder="https://..."></label>
  <label>بريد مهني للعرض<input type="email" name="contact_email" value="<?=e($writer['contact_email']??'')?>"></label>
  <div class="span-2 writer-admin-badges"><label class="check-line"><input type="checkbox" name="is_public" value="1" <?=!empty($writer['is_public'])?'checked':''?>> نشر الملف المهني للعامة</label><label class="check-line"><input type="checkbox" name="show_contact" value="1" <?=!empty($writer['show_contact'])?'checked':''?>> إظهار البريد المهني للزوار</label><?php if(($actor['role']??'')==='admin'):?><label class="check-line"><input type="checkbox" name="writer_verified" value="1" <?=!empty($writer['writer_verified'])?'checked':''?>> كاتب موثّق</label><label class="check-line"><input type="checkbox" name="writer_honored" value="1" <?=!empty($writer['writer_honored'])?'checked':''?>> كاتب مكرّم</label><label class="check-line vip-access-toggle"><input type="checkbox" name="writer_vip" value="1" <?=!empty($writer['writer_vip'])?'checked':''?>> تفعيل استديو الكاتب VIP</label><?php endif;?></div>
  <button class="btn btn-primary span-2">حفظ كل التعديلات</button>
 </form>
</section>

<div class="writer-admin-grid">
<section class="panel">
 <div class="panel-head"><div><span class="panel-kicker">النشاط</span><h2>ملخص الحساب</h2></div></div>
 <div class="writer-admin-facts">
  <div><span>اسم المستخدم</span><b>@<?=e($writer['username'])?></b></div>
  <div><span>البريد</span><b><?=e($writer['email'])?></b></div>
  <div><span>الحالة</span><b><?=e($writer['status'])?></b></div>
  <div><span>تاريخ الانضمام</span><b><?=e($writer['created_at']??'—')?></b></div>
  <div><span>آخر تسجيل دخول</span><b><?=e($writer['last_login_at']??'—')?></b></div>
  <div><span>آخر رسالة تحريرية</span><b><?=e($lastMessage['last_at']??'—')?></b></div>
 </div>
</section>
<section class="panel">
 <div class="panel-head"><div><span class="panel-kicker">الملف المهني</span><h2>بيانات الكاتب</h2></div></div>
 <p><?=nl2br(e($writer['cv_summary']?:$writer['bio']?:'لا توجد نبذة بعد.'))?></p>
 <?php if(!empty($writer['location'])):?><p><b>الموقع:</b> <?=e($writer['location'])?></p><?php endif;?>
</section>
</div>

<section class="panel">
 <div class="panel-head"><div><span class="panel-kicker">المحتوى</span><h2>مقالات الكاتب</h2></div><span><?=count($articles)?> نتيجة</span></div>
 <div class="table-wrap"><table><thead><tr><th>المقال</th><th>الحالة</th><th>المشاهدات</th><th>آخر تعديل</th><th></th></tr></thead><tbody>
 <?php foreach($articles as $a):?><tr><td><strong><?=e($a['title']?:'بدون عنوان')?></strong></td><td><span class="status s-<?=e($a['status'])?>"><?=e($statusLabels[$a['status']]??$a['status'])?></span></td><td><?=number_format((int)$a['views'])?></td><td><?=e($a['updated_at'])?></td><td><a href="/admin/articles/<?=$a['id']?>">فتح</a></td></tr><?php endforeach;?>
 <?php if(!$articles):?><tr><td colspan="5">لا توجد مقالات.</td></tr><?php endif;?>
 </tbody></table></div>
</section>

<?php if($activity):?><section class="panel"><div class="panel-head"><div><span class="panel-kicker">Audit</span><h2>آخر النشاطات المسجلة</h2></div></div><div class="writer-activity-list"><?php foreach($activity as $a):?><div><time><?=e($a['created_at'])?></time><b><?=e($a['action'])?></b><span><?=e($a['details']??'')?></span></div><?php endforeach;?></div></section><?php endif;?>
