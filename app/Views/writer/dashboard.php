<?php $writerPublicUrl=writer_url((string)$u['username'],default_locale()); ?>
<section class="panel writer-dashboard-identity"><div><span class="panel-kicker">رابط الكاتب</span><h2><?=e($u['name'])?> <?=writer_badges_html($u)?></h2><p>رابطك المهني ثابت ويمكن مشاركته مباشرة.</p><div class="writer-public-link"><code><?=e($writerPublicUrl)?></code><button type="button" class="btn btn-ghost" data-copy-dashboard-link data-url="<?=e($writerPublicUrl)?>">نسخ الرابط</button><a class="btn btn-primary" href="/writer/account">تعديل اسم المستخدم</a></div></div><div class="writer-dashboard-qr"><img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?=rawurlencode($writerPublicUrl)?>" alt="QR"><small>QR لملفك الشخصي</small></div></section>
<script>document.querySelector('[data-copy-dashboard-link]')?.addEventListener('click',async e=>{try{await navigator.clipboard.writeText(e.currentTarget.dataset.url);const t=e.currentTarget.textContent;e.currentTarget.textContent='تم النسخ';setTimeout(()=>e.currentTarget.textContent=t,1200)}catch(_){}});</script>
<div class="dash-actions"><a class="btn btn-primary" href="/writer/articles/new">＋ مقال جديد</a><a class="btn btn-ghost" href="/writer/editorial-messages">✉ مراسلة فريق التحرير</a><a class="btn btn-ghost" href="/writer/account">ملفي المهني والحساب</a></div><section class="writer-stat-grid" aria-label="إحصاءات المشاركات"><article class="writer-stat-card"><span>إجمالي المشاركات</span><strong><?=number_format((int)($submissionStats['total']??0))?></strong><a href="/writer/story-submissions">عرض مشاركاتي</a></article><article class="writer-stat-card"><span>قيد المراجعة</span><strong><?=number_format((int)($submissionStats['pending']??0))?></strong></article><article class="writer-stat-card"><span>مقبولة أو منشورة</span><strong><?=number_format((int)($submissionStats['approved']??0))?></strong></article><article class="writer-stat-card"><span>مرفوضة</span><strong><?=number_format((int)($submissionStats['rejected']??0))?></strong></article></section><section class="writer-quick-links" aria-label="روابط الكاتب"><a href="/send-story"><strong>كتابة مشاركة</strong><span>أرسل قصة جديدة إلى فريق سراديب</span></a><a href="/writer/editorial-messages"><strong>مراسلات الإدارة</strong><span>تواصل مع فريق التحرير بأمان</span></a><a href="/writer/account"><strong>ملفي الشخصي</strong><span>الاسم والصورة والنبذة المهنية</span></a><a href="/writer/account#settings"><strong>الإعدادات</strong><span>إدارة بيانات الحساب وكلمة المرور</span></a><a href="#writer-notifications"><strong>الإشعارات</strong><span>تابع آخر تحديثات فريق التحرير</span></a></section>

<?php if(!empty($notifications)): ?>
<section id="writer-notifications" class="panel writer-notifications">
  <div class="panel-head"><div><span class="panel-kicker">التنبيهات</span><h2>إشعارات التحرير</h2></div><span><?=count($notifications)?> إشعار</span></div>
  <div class="notification-list">
    <?php foreach($notifications as $n): ?>
    <article class="notification-item <?=$n['is_read']?'is-read':'is-unread'?>">
      <div><strong><?=e($n['title'])?></strong><p><?=nl2br(e($n['message']))?></p><small><?=e($n['created_at'])?></small></div>
      <div class="notification-actions">
        <?php if(!empty($n['link'])):?><a class="btn btn-ghost" href="<?=e($n['link'])?>">ظپطھط­</a><?php endif;?>
        <?php if(!$n['is_read']):?><form method="post" action="/writer/notifications/read"><?=csrf_field()?><input type="hidden" name="id" value="<?=$n['id']?>"><button class="text-btn">تحديد كمقروء</button></form><?php endif;?>
      </div>
    </article>
    <?php endforeach;?>
  </div>
</section>
<?php endif; ?>

<div class="panel"><div class="panel-head"><h2>مقالاتي</h2><span><?=count($articles)?> مقال</span></div><div class="table-wrap"><table><thead><tr><th>العنوان</th><th>الحالة</th><th>المشاهدات</th><th>آخر تعديل</th><th></th></tr></thead><tbody><?php foreach($articles as $a):?><tr><td><strong><?=e($a['title']?:'بدون عنوان')?></strong></td><td><span class="status s-<?=e($a['status'])?>"><?=e($a['status'])?></span></td><td><?=number_format((int)$a['views'])?></td><td><?=e($a['updated_at'])?></td><td><a href="/writer/articles/<?=e($a['id'])?>">تحرير</a></td></tr><?php endforeach;?></tbody></table></div></div>

