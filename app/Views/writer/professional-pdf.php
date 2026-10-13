<?php
$professionalLinks=[];
foreach($socials as $label=>$link){
    if(filter_var($link,FILTER_VALIDATE_URL)&&in_array(strtolower((string)parse_url($link,PHP_URL_SCHEME)),['http','https'],true)){
        $professionalLinks[$label]=$link;
    }
}
?>
<link rel="stylesheet" href="/assets/css/writer-professional-pdf.css?v=1" data-professional-pdf-style>
<div class="writer-pdf-pages professional-pdf" data-pdf-pages hidden>
  <article class="professional-document" dir="rtl">
    <header class="professional-heading">
      <div class="professional-name"><p class="professional-kicker">الملف المهني</p><h1><?=e($u['name'])?></h1><p class="professional-title"><?=e($title?:'كاتب وباحث')?></p></div>
      <?php if($avatar):?><img class="professional-photo" data-card-section="photo" src="<?=e($avatar)?>" alt="<?=e($u['name'])?>"><?php endif;?>
    </header>
    <?php if($contact!==''||$professionalLinks):?><address class="professional-contact" data-card-section="socials">
      <?php if($contact!==''&&filter_var($contact,FILTER_VALIDATE_EMAIL)):?><a href="mailto:<?=e($contact)?>"><?=e($contact)?></a><?php endif;?>
      <?php foreach($professionalLinks as $link):?><a href="<?=e($link)?>" rel="noopener" target="_blank"><?=e($link)?></a><?php endforeach;?>
    </address><?php endif;?>
    <?php if($summary!==''):?><section class="professional-section"><h2>نبذة مهنية</h2><p><?=nl2br(e($summary))?></p></section><?php endif;?>
    <?php foreach(['الخبرات'=>$experience,'التعليم'=>$education,'المهارات ومجالات الكتابة'=>$skills,'الإنجازات'=>$achievements] as $heading=>$items):?>
      <?php if($items):?><section class="professional-section"><h2><?=e($heading)?></h2><ul><?php foreach($items as $item):?><li><?=e($item)?></li><?php endforeach;?></ul></section><?php endif;?>
    <?php endforeach;?>
    <?php if($articles):?><section class="professional-section"><h2>أعمال مختارة</h2><ol class="professional-works"><?php foreach($articles as $article):?><li><strong><?=e($article['title'])?></strong><?php if(!empty($article['category_name'])):?><span><?=e($article['category_name'])?></span><?php endif;?></li><?php endforeach;?></ol></section><?php endif;?>
    <?php if($awards):?><section class="professional-section"><h2>التكريمات</h2><ul><?php foreach($awards as $award):?><li><strong><?=e($award['title']?:'تكريم')?></strong><?php if(!empty($award['message'])):?><p><?=e($award['message'])?></p><?php endif;?></li><?php endforeach;?></ul></section><?php endif;?>
  </article>
</div>
