<?php
$canonical=locale_url($locale,'community');
$C=community_ui($locale);
?>
<section class="community-page community-home community-home-pro">
  <div class="container">
    <header class="community-pro-hero">
      <div class="community-pro-hero-copy"><span class="community-eyebrow"><?=e($C['brand'])?></span><h1><?=e($C['hero'])?></h1><p><?=e($description)?></p><div class="community-pro-pills"><span>نقاشات محترمة</span><span>كتّاب ومهتمون</span><span>محتوى بمراجعة واضحة</span></div></div>
      <div class="community-pro-hero-mark" aria-hidden="true"><span>✦</span></div>
    </header>
    <div class="community-pro-status"><span>منتدى سراديب</span><small>مساحة للأفكار والأسئلة والنقاش الهادئ.</small></div>
    <div class="community-pro-layout">
      <aside class="community-pro-sidebar community-pro-categories">
        <section class="community-pro-panel"><header><h2>الأقسام</h2><a href="#discussions">كل النقاشات</a></header><nav><?php foreach($categories as $cat):?><a href="<?=e(locale_url($locale,'community/'.$cat['slug']))?>"><span><?=e($cat['name'])?></span><b><?=number_format((int)$cat['topic_count'])?></b></a><?php endforeach;?></nav></section>
        <section class="community-pro-panel community-pro-callout"><span>✎</span><h2>لديك فكرة للنقاش؟</h2><p>إنشاء المواضيع والردود ميزة حصرية للكتّاب المعتمدين وفريق التحرير؛ يستطيع الأعضاء القراءة والتفاعل وحفظ النقاشات والإبلاغ عنها.</p><?php if($user && in_array(($user['role']??''),['writer','trusted_writer','editor','admin'],true)):?><a class="btn btn-primary" href="<?=e(locale_url($locale,'community/new'))?>">ابدأ نقاشًا</a><?php elseif(!$user):?><a class="btn btn-primary" href="<?=e(locale_url($locale,'login'))?>">سجّل للتفاعل</a><?php endif;?></section>
      </aside>
      <main class="community-pro-feed" id="discussions">
        <section class="community-pro-toolbar"><div class="community-pro-toolbar-head"><div><span class="community-eyebrow">النقاشات</span><h2><?=e($q!==''?$C['results']:$C['now'])?></h2></div><?php if($user && in_array(($user['role']??''),['writer','trusted_writer','editor','admin'],true)):?><a class="btn btn-primary" href="<?=e(locale_url($locale,'community/new'))?>">+ <?=e($C['start'])?></a><?php endif;?></div>
          <form class="community-search" action="<?=e($canonical)?>"><input name="q" value="<?=e($q)?>" placeholder="<?=e($C['search'])?>"><button aria-label="<?=e(tr('search',$locale))?>"><?=navigation_icon_svg('search')?></button></form>
          <nav class="community-filters" aria-label="<?=e($C['filter_label'])?>"><?php $filters=['latest'=>$C['latest'],'discussed'=>$C['discussed'],'views'=>$C['views_filter'],'unanswered'=>$C['unanswered'],'active'=>$C['active'],'following'=>$C['following']]; foreach($filters as $key=>$label): if($key==='following'&&!$user)continue;?><a class="<?=$filter===$key?'active':''?>" href="<?=e($canonical.'?filter='.$key)?>"><?=e($label)?></a><?php endforeach;?></nav>
        </section>
        <?php if($week):?><section class="community-week-card"><div><span>✦ <?=e($C['week'])?></span><h2><a href="<?=e(locale_url($locale,'community/topic/'.$week['slug']))?>"><?=e($week['title'])?></a></h2><p><?=e($week['author_name'])?> · <?=e($week['category_name']??$C['brand'])?> · <?=number_format((int)$week['replies_count'])?> <?=e($C['reply'])?></p></div><a class="btn btn-ghost" href="<?=e(locale_url($locale,'community/topic/'.$week['slug']))?>"><?=e($C['enter'])?></a></section><?php endif;?>
        <div class="community-topic-list"><?php if(!$rows):?><div class="community-empty"><strong><?=e($C['empty'])?></strong><p><?=e($C['empty_text'])?></p></div><?php else:foreach($rows as $topic) require __DIR__.'/_topic-card.php';endif;?></div>
        <?=community_pagination($pagination,$canonical,['filter'=>$filter,'q'=>$q])?>
      </main>
      <aside class="community-pro-sidebar community-pro-info">
        <section class="community-pro-panel"><header><h2>عن المنتدى</h2></header><p>منتدى سراديب مساحة للنقاش الواعي حول المعرفة والقصص والأسئلة، مع احترام الأشخاص واختلاف الآراء.</p><a href="<?=e(locale_url($locale,'community/preferences'))?>">إعدادات الإشعارات</a></section>
        <section class="community-pro-panel"><header><h2>نشاط المنتدى</h2></header><div class="community-pro-stats"><span><b><?=number_format((int)$communityStats['topics'])?></b>نقاش</span><span><b><?=number_format((int)$communityStats['replies'])?></b>رد</span><span><b><?=number_format((int)$communityStats['contributors'])?></b>مشارك</span></div></section>
        <?php if($user):?><section class="community-pro-panel community-pro-links"><a href="<?=e(locale_url($locale,'community/saved'))?>"><?=e($C['saved'])?></a><a href="<?=e(locale_url($locale,'community/notifications'))?>"><?=e($C['notifications'])?></a><a href="<?=e(locale_url($locale,'community/account'))?>">حسابي في المنتدى</a></section><?php endif;?>
      </aside>
    </div>
  </div>
</section>
