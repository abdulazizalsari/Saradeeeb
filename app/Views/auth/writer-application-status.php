<?php
$labels=['pending'=>'قيد المراجعة','under_review'=>'تحت المراجعة','changes_requested'=>'مطلوب تعديل','approved'=>'تمت الموافقة','rejected'=>'مرفوض'];
?><section class="auth-section"><div class="auth-card wide"><span class="eyebrow">طلبات الكتّاب</span><h1>حالة طلبك</h1>
<div class="notice <?=($application['status']==='approved'?'success':'')?>"><strong><?=e($labels[$application['status']]??$application['status'])?></strong><br>آخر تحديث: <?=e((string)$application['updated_at'])?></div>
<?php if(in_array($application['status'],['changes_requested','rejected'],true)): ?><p><?=e((string)($application['admin_note']??''))?></p><form method="post" action="<?=e(locale_url($locale,'writer-application/resubmit'))?>"><?=csrf_field()?><label>المواضيع<input name="topics" value="<?=e((string)$application['topics'])?>"></label><label>رابط نماذج الكتابة<input name="sample_url" value="<?=e((string)$application['sample_url'])?>"></label><label>النبذة<textarea name="bio" rows="7" required><?=e((string)$application['bio'])?></textarea></label><button class="btn btn-primary">إعادة الإرسال</button></form><?php endif; ?>
<h2>سجل الحالة</h2><ul><?php foreach($history as $h): ?><li><?=e($labels[$h['to_status']]??$h['to_status'])?> — <?=e((string)$h['created_at'])?><?php if(!empty($h['note'])): ?>: <?=e($h['note'])?><?php endif; ?></li><?php endforeach; ?></ul>
</div></section>
