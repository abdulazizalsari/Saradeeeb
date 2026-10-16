<section class="panel">
  <div class="panel-head"><div><span class="panel-kicker">إدارة القوالب</span><h2>قوالب البطاقات</h2><p>ارفع حزمة ZIP معتمدة. تخضع الملفات للفحص قبل حفظها وتبقى غير فعّالة حتى مرحلة المراجعة والتفعيل.</p></div></div>
  <form method="post" action="/admin/card-templates/upload" enctype="multipart/form-data" class="form-grid compact">
    <?=csrf_field()?>
    <label>نوع البطاقة<select name="type" required><?php foreach($types as $key=>$label):?><option value="<?=e($key)?>"><?=e($label)?></option><?php endforeach;?></select></label>
    <label>ملف القالب HTML<input type="file" name="template_html" accept=".html,text/html" required></label>
    <div class="span-2"><small>الحد الأقصى 512 كيلوبايت. يبدأ الملف مثل: <code>&lt;!-- card-template: {"type":"award-card","name":"اسمي","version":"1.0.0"} --&gt;</code>. المتغيرات المسموحة: name، username، reason، profile_url، logo، medal، qr.</small></div>
    <div class="span-2"><button class="btn btn-primary" type="submit">رفع وفحص القالب</button></div>
  </form>
</section>

<section class="panel">
  <div class="panel-head"><div><span class="panel-kicker">النسخ المرفوعة</span><h2>مكتبة القوالب</h2></div></div>
  <?php if(!$templates):?><p class="empty-state">لم يُرفع أي قالب بعد.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>النوع</th><th>القالب</th><th>الإصدار</th><th>الحالة</th><th>رفعه</th><th>الإجراء</th></tr></thead><tbody><?php foreach($templates as $template):?><tr><td><?=e($types[$template['type']]??$template['type'])?></td><td><?=e($template['name'])?></td><td><?=e($template['version'])?></td><td><span class="status <?=$template['is_active']?'s-active':'s-pending'?>"><?=$template['is_active']?'فعّال':'غير فعّال'?></span></td><td><?=e($template['uploaded_by_name']??'—')?><small><?=e($template['created_at'])?></small></td><td><div class="actions"><a class="btn btn-ghost" href="/admin/card-templates/<?=(int)$template['id']?>/preview">معاينة</a><?php if(!$template['is_active']):?><form method="post" action="/admin/card-templates/activate"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$template['id']?>"><button class="btn btn-primary">تفعيل</button></form><?php endif;?><form method="post" action="/admin/card-templates/delete" onsubmit="return confirm('هل تريد إزالة هذا القالب؟')"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$template['id']?>"><button class="btn btn-ghost">إزالة</button></form></div></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</section>
