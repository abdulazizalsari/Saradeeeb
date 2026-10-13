<?php
$statusLabels=['pending'=>'بانتظار المراجعة','under_review'=>'قيد المراجعة','changes_requested'=>'مطلوب استكمال','approved'=>'معتمد ككاتب','rejected'=>'مرفوض','suspended'=>'موقوف','new'=>'جديد','reviewed'=>'تمت المراجعة'];
?>
<section class="panel applications-panel">
  <div class="panel-head"><div><span class="panel-kicker">طلبات العضوية</span><h2>طلبات الانضمام ككاتب</h2><p>يُنشأ حساب المتقدم عند إرسال الطلب. اعتماد الطلب يفعّل الحساب الحالي ككاتب؛ لا يُنشأ حساب ثانٍ ولا تُطلب كلمة مرور جديدة.</p></div></div>
  <?php if (!$applications): ?>
    <div class="empty-state"><strong>لا توجد طلبات حتى الآن</strong><p>ستظهر طلبات الكتّاب هنا فور إرسال النموذج.</p></div>
  <?php else: ?><div class="application-list">
    <?php foreach ($applications as $a): $status=(string)$a['status']; ?>
      <article class="application-card status-<?= e($status) ?>">
        <div class="application-head"><div><strong><?= e($a['name']) ?></strong><span><?= e($a['email'] ?: 'لا يوجد بريد') ?> · <?= e($a['phone'] ?: 'لا يوجد هاتف') ?></span></div><span class="status s-<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></span></div>
        <div class="application-meta"><span>الدولة: <?= e($a['country'] ?: '—') ?></span><span>المدينة: <?= e($a['city'] ?: '—') ?></span><span>المواضيع: <?= e($a['topics'] ?: '—') ?></span><span>تاريخ الطلب: <?= e($a['created_at']) ?></span></div>
        <div class="application-account-state <?= !empty($a['user_id']) ? 'is-linked' : 'is-legacy' ?>">
          <?php if (!empty($a['user_id'])): ?>
            <strong>✓ الحساب مرتبط بالطلب</strong><span>@<?= e($a['account_username'] ?: $a['username'] ?: '—') ?> · <?= e($a['account_role']) ?> · <?= e($a['account_status']) ?></span>
          <?php else: ?>
            <strong>طلب قديم غير مرتبط بحساب</strong><span>يُراجع يدوياً؛ لا يُنشأ حساب جديد تلقائياً من هذه الشاشة.</span>
          <?php endif; ?>
        </div>
        <?php if ($a['experience']): ?><div class="application-copy"><b>الخبرة</b><p><?= nl2br(e($a['experience'])) ?></p></div><?php endif; ?>
        <div class="application-copy"><b>نبذة المتقدم</b><p><?= nl2br(e($a['bio'])) ?></p></div>
        <?php if ($a['sample_url']): ?><a class="text-link" href="<?= e($a['sample_url']) ?>" target="_blank" rel="noopener">فتح نموذج الكتابة ↗</a><?php endif; ?>
        <form method="post" action="/admin/writer-applications/update" class="application-action-box application-review-form">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <label>حالة طلب العضوية<select name="status">
            <?php foreach (['pending','under_review','changes_requested','approved','rejected','suspended'] as $option): ?><option value="<?= $option ?>" <?= $status===$option?'selected':'' ?>><?= e($statusLabels[$option]) ?></option><?php endforeach; ?>
          </select></label>
          <label>ملاحظة الإدارة للطلب<textarea name="admin_note" rows="3"><?= e($a['admin_note'] ?? '') ?></textarea></label>
          <div class="application-review-help"><strong>عند اختيار «معتمد ككاتب»</strong><span>تُرقّى صلاحية الحساب المرتبط إلى كاتب وتُفعّل لوحة الكاتب فوراً.</span></div>
          <button class="btn btn-primary">حفظ قرار المراجعة</button>
        </form>
      </article>
    <?php endforeach; ?>
  </div><?php endif; ?>
</section>
