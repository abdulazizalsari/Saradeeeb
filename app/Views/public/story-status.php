<?php
$labels=$labels??[]; $result=$result??null; $error=$error??''; $token=$token??'';
$statusLabels=['new'=>'قيد المراجعة','reviewed'=>'قيد المراجعة التحريرية','accepted'=>'تم القبول','rejected'=>'لم تُقبل المشاركة','changes_requested'=>'تحتاج إلى تعديل'];
?>
<section class="page-hero"><div class="container"><span class="eyebrow">Saradeeeb</span><h1><?=e($labels['title']??'متابعة قصتك')?></h1><p><?=e($labels['desc']??'')?></p></div></section>
<section class="section"><div class="container"><div class="panel public-form-card story-status-card">
<?php if($error!==''): ?><div class="form-error" role="alert"><?=e($error)?></div><?php endif; ?>
<form method="post" action="<?=e(locale_url($locale,'story-status'))?>" class="form-grid">
<label class="span-2">رمز المتابعة<input name="tracking_code" value="<?=e($token)?>" placeholder="<?=e($labels['placeholder']??'SRD-...')?>" required pattern="SRD-[A-Fa-f0-9]{16}-[A-Fa-f0-9]{16}" autocomplete="off" spellcheck="false"></label>
<div class="span-2"><button class="btn btn-primary" type="submit"><?=e($labels['submit']??'عرض الحالة')?></button></div>
</form>
<?php if($result): ?><div class="story-status-result" aria-live="polite"><div class="story-status-badge"><?=e($statusLabels[$result['status']]??$result['status'])?></div><h2><?=e($result['title'])?></h2><p>باسم: <?=e($result['name'])?></p><p class="settings-help">تاريخ الإرسال: <?=e((string)$result['created_at'])?></p><?php if(trim((string)$result['admin_note'])!==''): ?><div class="story-status-note"><strong>ملاحظة فريق سراديب</strong><p><?=nl2br(e($result['admin_note']))?></p></div><?php endif; ?></div><?php endif; ?>
<p class="settings-help">احتفظ بالرمز في مكان آمن؛ فهو المفتاح الخاص للوصول إلى حالة مشاركتك.</p>
</div></div></section>
