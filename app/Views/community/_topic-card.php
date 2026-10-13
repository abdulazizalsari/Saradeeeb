<?php
$t=$topic;
$C=$C??community_ui($locale);
$heat=$t['heat']??['label'=>$C['calm'],'icon'=>'🟦','class'=>'calm'];
?>
<article class="community-topic-card <?=$t['pinned']?'is-pinned':''?>">
  <div class="community-topic-avatar"><?php if(!empty($t['avatar'])):?><img src="<?=e($t['avatar'])?>" alt="<?=e($t['author_name'])?>" loading="lazy"><?php else:?><span><?=e(mb_substr((string)$t['author_name'],0,1,'UTF-8'))?></span><?php endif;?></div>
  <div class="community-topic-main">
    <div class="community-topic-topline">
      <a class="community-topic-author" href="<?=e(locale_url($locale,'community/member/'.$t['username']))?>"><?=e($t['author_name'])?></a>
      <span>·</span><time><?=e(time_ago((string)$t['created_at'],$locale))?></time>
      <?php if($t['pinned']):?><span class="community-pin"><?=e($C['pinned'])?></span><?php endif;?>
    </div>
    <h2><a href="<?=e(locale_url($locale,'community/topic/'.$t['slug']))?>"><?=e($t['title'])?></a></h2>
    <?php if(!empty($t['excerpt'])):?><p><?=e($t['excerpt'])?></p><?php endif;?>
    <div class="community-topic-meta">
      <?php if(!empty($t['category_name'])):?><a class="community-category-pill" href="<?=e(locale_url($locale,'community/'.$t['category_slug']))?>"><?=e($t['category_name'])?></a><?php endif;?>
      <span><?=number_format((int)$t['replies_count'])?> <?=e($C['reply'])?></span>
      <span><?=number_format((int)$t['views'])?> <?=e($C['views'])?></span>
      <span class="community-heat <?=e($heat['class'])?>"><?=e($heat['icon'])?> <?=e($heat['label'])?></span>
    </div>
  </div>
  <?php if(!empty($t['image'])):?><a class="community-topic-thumb" href="<?=e(locale_url($locale,'community/topic/'.$t['slug']))?>"><img src="<?=e($t['image'])?>" alt="" loading="lazy"></a><?php endif;?>
</article>
