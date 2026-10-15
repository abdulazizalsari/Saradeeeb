<section class="panel book-panel">
<a href="/writer">← العودة إلى لوحة الكاتب</a>
<h2>اتفاق نشر وتسويق الكتاب · النسخة <?=e((string)$agreement['version'])?></h2>
<p>راجع جميع الشروط قبل اتخاذ قرارك. موافقتك تخص هذه النسخة فقط، وأي تعديل لاحق يحتاج موافقة جديدة.</p>
<div class="book-terms"><?=nl2br(e((string)$agreement['terms']))?></div>
<p>مواضع العرض المتفق عليها: دليل إصدارات سراديب وصفحة الكاتب<?php if(!empty($agreement['allow_home'])):?> والصفحة الرئيسية<?php endif;?>. <?=!empty($agreement['listing_after_expiry'])?'يمكن استمرار عرض الكتاب بعد نهاية مدة الترويج وفق هذه النسخة.':'ينتهي عرض الكتاب بانتهاء مدة الاتفاق.'?></p>
<dl class="book-facts"><div><dt>الرسوم</dt><dd><?=e((string)$agreement['fee'])?> <?=e((string)$agreement['currency'])?></dd></div><div><dt>شروط الدفع</dt><dd><?=nl2br(e((string)$agreement['payment_terms']))?></dd></div><div><dt>نطاق الترويج</dt><dd><?=nl2br(e((string)$agreement['promo_scope']))?></dd></div><div><dt>حقوق الكاتب</dt><dd><?=nl2br(e((string)$agreement['rights_terms']))?></dd></div><div><dt>الإلغاء</dt><dd><?=nl2br(e((string)$agreement['cancellation_terms']))?></dd></div><div><dt>الفترة</dt><dd><?=e((string)$agreement['service_start'])?> — <?=e((string)$agreement['service_end'])?></dd></div></dl>
<?php if($agreement['status']==='offered'): ?>
<form method="post" action="/writer/book-offer/decision" class="book-actions"><?=csrf_field()?><input type="hidden" name="agreement_id" value="<?=(int)$agreement['id']?>"><button class="btn btn-primary" name="decision" value="accepted" onclick="return confirm('هل توافق صراحةً على شروط هذه النسخة؟')">أوافق على الاتفاق</button><button class="btn btn-ghost" name="decision" value="declined" onclick="return confirm('هل تريد رفض هذا العرض؟')">أرفض العرض</button></form>
<?php else: ?><p class="notice">حالة العرض: <?=e((string)$agreement['status'])?><?php if($agreement['accepted_at']):?> · تاريخ الموافقة: <?=e((string)$agreement['accepted_at'])?><?php endif;?></p><?php endif; ?>
</section>
