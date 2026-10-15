<section class="admin-mobile-app">
  <header class="mobile-app-hero"><div><span>تطبيق الإدارة</span><h2>مرحبًا، <?=e($u['name']??'مدير سراديب')?> 👋</h2><p>كل ما تحتاجه لإدارة العمل اليومي من هاتفك.</p></div><button type="button" class="btn btn-primary" id="installAdminApp" hidden>تثبيت التطبيق</button></header>
  <div class="mobile-stat-grid">
    <a href="/admin/editorial-messages"><strong><?=number_format((int)$stats['editorial'])?></strong><span>رسائل تحريرية</span></a>
    <a href="/admin/articles"><strong><?=number_format((int)$stats['review'])?></strong><span>بانتظار المراجعة</span></a>
    <a href="/admin/comments"><strong><?=number_format((int)$stats['comments'])?></strong><span>تعليقات معلّقة</span></a>
    <a href="/admin/writer-applications"><strong><?=number_format((int)$stats['applications'])?></strong><span>طلبات كتّاب</span></a>
  </div>
  <nav class="mobile-quick-actions">
    <a href="/admin/editorial-messages">✉️ <b>مراسلات التحرير</b><small>تواصل مع الكتّاب</small></a>
    <a href="/admin/articles">📝 <b>مراجعة المقالات</b><small>نشر أو طلب تعديلات</small></a>
    <a href="/admin/comments">💬 <b>التعليقات</b><small>موافقة وإدارة السبام</small></a>
    <a href="/admin/writer-applications">👤 <b>طلبات الكتّاب</b><small>مراجعة طلبات الانضمام</small></a>
  </nav>
  <section class="mobile-app-section"><div class="panel-head"><div><span class="panel-kicker">قائمة العمل</span><h3>المقالات التي تحتاج انتباهك</h3></div><a href="/admin/articles">عرض الكل</a></div>
    <div class="mobile-review-list"><?php if(!$reviews):?><div class="empty-state">لا توجد مقالات معلّقة الآن.</div><?php else: foreach($reviews as $r):?><a href="/admin/articles/<?=$r['id']?>"><div><strong><?=e($r['title']?:'مقال بدون عنوان')?></strong><small><?=e($r['author_name'])?> · <?=e($r['updated_at'])?></small></div><span class="status s-<?=e($r['status'])?>"><?=$r['status']==='review'?'مراجعة':'تعديلات'?></span></a><?php endforeach; endif;?></div>
  </section>
  <section class="mobile-app-section"><div class="panel-head"><div><span class="panel-kicker">آخر النشاط</span><h3>الإشعارات</h3></div></div><div class="mobile-notification-list"><?php if(!$notifications):?><div class="empty-state">لا توجد إشعارات جديدة.</div><?php else:foreach($notifications as $n):?><a href="<?=e($n['link']?:'/admin-app')?>"><strong><?=e($n['title'])?></strong><p><?=e($n['message'])?></p><small><?=e($n['created_at'])?></small></a><?php endforeach;endif;?></div></section>
</section>
<nav class="admin-app-bottom"><a class="active" href="/admin-app">⌂<span>الرئيسية</span></a><a href="/admin/editorial-messages">✉<span>الرسائل</span></a><a href="/admin/articles">▤<span>المراجعات</span></a><a href="/admin/comments">◌<span>التعليقات</span></a><a href="/admin">☰<span>المزيد</span></a></nav>
<script>let adminInstallPrompt=null;window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();adminInstallPrompt=e;const b=document.getElementById('installAdminApp');if(b)b.hidden=false});document.getElementById('installAdminApp')?.addEventListener('click',async()=>{if(!adminInstallPrompt)return;adminInstallPrompt.prompt();await adminInstallPrompt.userChoice;adminInstallPrompt=null;document.getElementById('installAdminApp').hidden=true});</script>
