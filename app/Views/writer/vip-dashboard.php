<?php
$writerPublicUrl=writer_url((string)$u['username'],default_locale());
$statusLabels=['draft'=>'مسودة','review'=>'قيد المراجعة','changes'=>'مطلوب تعديل','published'=>'منشورة','archived'=>'مؤرشفة'];
$displayTitle=trim((string)($profile['professional_title']??''))?:'كاتب في سراديب';
$bio=trim((string)($profile['cv_summary']??$u['bio']??''));
$maxMonth=1; foreach($monthlyActivity as $monthRow)$maxMonth=max($maxMonth,(int)$monthRow['total']);
$canRequestBook=in_array((string)($u['role']??''),['writer','trusted_writer'],true)&&!empty($u['writer_verified'])&&!\App\Core\Auth::isImpersonating();
?>
<div class="vip-studio" dir="rtl">
 <section class="vip-studio-hero" aria-labelledby="vipStudioTitle">
  <div class="vip-studio-person">
   <?php if(!empty($u['avatar'])):?><img src="<?=e($u['avatar'])?>" alt="صورة <?=e($u['name'])?>"><?php else:?><span class="vip-studio-avatar" aria-hidden="true"><?=e(mb_substr((string)$u['name'],0,1,'UTF-8'))?></span><?php endif;?>
   <div><span class="vip-studio-kicker">مساحتك الأدبية الخاصة</span><h2 id="vipStudioTitle">مرحبًا، <?=e($u['name'])?></h2><div class="vip-studio-byline"><span class="vip-studio-badge">VIP</span><span><?=e($displayTitle)?></span></div></div>
  </div>
  <a class="vip-studio-profile-link" href="<?=e($writerPublicUrl)?>" target="_blank" rel="noopener">عرض صفحة الكاتب العامة <span aria-hidden="true">↗</span></a>
 </section>

 <section class="vip-studio-stats" aria-label="إحصاءات الكاتب">
  <article><span>القصص المنشورة</span><strong><?=number_format((int)$articleStats['published'])?></strong><small>من قاعدة المحتوى</small></article>
  <article><span>إجمالي المشاهدات</span><strong><?=number_format((int)$articleStats['views'])?></strong><small>لجميع مقالاتك</small></article>
  <article><span>قيد المراجعة</span><strong><?=number_format((int)$articleStats['review'])?></strong><small>بانتظار فريق التحرير</small></article>
  <article><span>المسودات</span><strong><?=number_format((int)$articleStats['drafts'])?></strong><small>محفوظة في حسابك</small></article>
  <article><span>تفاعل القرّاء</span><strong><?=number_format($commentCount)?></strong><small>تعليقات معتمدة</small></article>
 </section>

 <section class="vip-studio-section" aria-labelledby="literaryDeskTitle">
  <header><div><span class="vip-studio-kicker">ابدأ من هنا</span><h2 id="literaryDeskTitle">مكتبي الأدبي</h2></div><p>كل الأدوات المرتبطة بحسابك ومسار النشر التحريري.</p></header>
  <div class="vip-studio-actions">
   <a class="is-primary" href="/writer/articles/new"><span>＋</span><strong>كتابة قصة جديدة</strong><small>افتح محرر سراديب واحفظ مسودتك</small></a>
   <a href="#writer-stories"><span>⌁</span><strong>قصصي ومراجعاتي</strong><small>تابع حالة كل نص وملاحظات التحرير</small></a>
   <a href="/writer/account"><span>✎</span><strong>إدارة قسمي</strong><small>حدّث صورتك ونبذتك وهويتك العامة</small></a>
   <a href="<?=e($writerPublicUrl)?>" target="_blank" rel="noopener"><span>◉</span><strong>معاينة قسمي</strong><small>شاهد صفحتك كما يراها القرّاء</small></a>
   <?php if($canRequestBook):?>
    <?php if(empty($bookRequest)):?><form method="post" action="/writer/book-service/request" onsubmit="return confirm('هل تريد إرسال طلب نشر وتسويق كتابك إلى إدارة سراديب؟')"><?=csrf_field()?><button type="submit"><span>▤</span><strong>بيع كتابي عبر المنصة</strong><small>أرسل طلب الخدمة إلى الإدارة</small></button></form>
    <?php else:?><a href="<?=$bookOfferId?'/writer/book-offer/'.$bookOfferId:'#writer-book-request'?>"><span>▤</span><strong>طلب نشر كتابي</strong><small>الحالة الحالية: <?=e((string)$bookRequest['status'])?></small></a><?php endif;?>
   <?php endif;?>
  </div>
 </section>

 <section id="writer-stories" class="vip-studio-section" aria-labelledby="storyJourneyTitle">
  <header><div><span class="vip-studio-kicker">من المسودة إلى النشر</span><h2 id="storyJourneyTitle">رحلة قصصي</h2></div><a href="/writer/story-submissions">عرض القصص المرسلة</a></header>
  <div class="vip-studio-workflow" aria-label="مراحل المقالات">
   <div><b><?=number_format((int)$articleStats['drafts'])?></b><span>مسودة</span></div><i aria-hidden="true">←</i>
   <div><b><?=number_format((int)$articleStats['review'])?></b><span>مراجعة</span></div><i aria-hidden="true">←</i>
   <div><b><?=number_format((int)$articleStats['changes'])?></b><span>تعديلات</span></div><i aria-hidden="true">←</i>
   <div><b><?=number_format((int)$articleStats['published'])?></b><span>منشورة</span></div>
  </div>
  <div class="vip-studio-table-wrap"><table><thead><tr><th>القصة أو المقال</th><th>الحالة</th><th>المشاهدات</th><th>آخر تحديث</th><th><span class="sr-only">الإجراء</span></th></tr></thead><tbody>
  <?php foreach(array_slice($articles,0,8) as $article):?><tr><td><strong><?=e($article['title']?:'بدون عنوان')?></strong></td><td><span class="status s-<?=e($article['status'])?>"><?=e($statusLabels[$article['status']]??$article['status'])?></span></td><td><?=number_format((int)$article['views'])?></td><td><?=e((string)$article['updated_at'])?></td><td><a href="/writer/articles/<?=(int)$article['id']?>">فتح</a></td></tr><?php endforeach;?>
  <?php if(!$articles):?><tr><td colspan="5" class="vip-studio-empty">لم تبدأ قصة بعد. افتح استديو الكتابة لتبدأ مسودتك الأولى.</td></tr><?php endif;?>
  </tbody></table></div>
 </section>

 <div class="vip-studio-columns">
  <section class="vip-studio-section vip-studio-public" aria-labelledby="publicSectionTitle">
   <header><div><span class="vip-studio-kicker">هويتك أمام القرّاء</span><h2 id="publicSectionTitle">قسم الكاتب</h2></div><a href="/writer/account">تعديل</a></header>
   <div class="vip-studio-public-card">
    <?php if(!empty($u['avatar'])):?><img src="<?=e($u['avatar'])?>" alt=""><?php else:?><span class="vip-studio-avatar" aria-hidden="true"><?=e(mb_substr((string)$u['name'],0,1,'UTF-8'))?></span><?php endif;?>
    <div><h3><?=e($u['name'])?> <span class="vip-studio-badge">VIP</span></h3><p class="vip-studio-title"><?=e($displayTitle)?></p><p><?=e($bio?:'أضف نبذة أدبية قصيرة من إعدادات ملفك لتظهر للقرّاء هنا.')?></p><a href="<?=e($writerPublicUrl)?>" target="_blank" rel="noopener"><?=e($writerPublicUrl)?></a></div>
   </div>
   <?php if($topArticles):?><div class="vip-studio-featured"><span>الأكثر قراءة</span><strong><?=e($topArticles[0]['title']?:'مقال منشور')?></strong><small><?=number_format((int)$topArticles[0]['views'])?> مشاهدة</small></div><?php else:?><p class="vip-studio-empty">سيظهر المقال المميّز هنا بعد نشر أول عمل لك.</p><?php endif;?>
  </section>

  <section id="writer-insights" class="vip-studio-section" aria-labelledby="impactTitle">
   <header><div><span class="vip-studio-kicker">بيانات فعلية</span><h2 id="impactTitle">أثر كلماتك</h2></div></header>
   <?php if($monthlyActivity):?><div class="vip-studio-chart" aria-label="نشاط النشر خلال الأشهر الأخيرة"><?php foreach($monthlyActivity as $monthRow):?><div><span style="--bar:<?=max(12,round(((int)$monthRow['total']/$maxMonth)*100))?>%" title="<?=e($monthRow['month_key'])?>: <?=(int)$monthRow['total']?>"></span><small><?=e(substr((string)$monthRow['month_key'],5))?></small></div><?php endforeach;?></div><?php else:?><p class="vip-studio-empty">لا توجد بيانات نشر كافية لعرض الرسم بعد.</p><?php endif;?>
   <?php if($topArticles):?><ol class="vip-studio-ranking"><?php foreach($topArticles as $top):?><li><a href="/writer/articles/<?=(int)$top['id']?>"><?=e($top['title']?:'بدون عنوان')?></a><span><?=number_format((int)$top['views'])?> مشاهدة</span></li><?php endforeach;?></ol><?php endif;?>
   <p class="vip-studio-data-note">لا يعرض الاستديو عدد متابعين لأن الموقع لا يملك نظام متابعة كتّاب مفعّلًا حاليًا.</p>
  </section>
 </div>

 <section id="writer-notifications" class="vip-studio-section" aria-labelledby="notificationsTitle">
  <header><div><span class="vip-studio-kicker">التحديثات التحريرية</span><h2 id="notificationsTitle">الإشعارات</h2></div><span><?=$unreadNotifications?> غير مقروء</span></header>
  <div class="vip-studio-notifications">
  <?php foreach($notifications as $notification):?><article class="<?=empty($notification['is_read'])?'is-unread':''?>"><div><strong><?=e($notification['title'])?></strong><p><?=nl2br(e($notification['message']))?></p><time><?=e($notification['created_at'])?></time></div><div><?php if(!empty($notification['link'])):?><a href="<?=e($notification['link'])?>">فتح</a><?php endif;?><?php if(empty($notification['is_read'])):?><form method="post" action="/writer/notifications/read"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$notification['id']?>"><button type="submit">تحديد كمقروء</button></form><?php endif;?></div></article><?php endforeach;?>
  <?php if(!$notifications):?><p class="vip-studio-empty">لا توجد إشعارات جديدة.</p><?php endif;?>
  </div>
 </section>
</div>
