<?php
$award=$award??[]; $awardPreview=$awardPreview??false;
$name=$award['writer_name']??'اسم الكاتب'; $username=$award['username']??'username';
$role=$award['professional_title']??'كاتب في سراديب'; $avatar=$award['avatar']??'';
$type=$award['award_type']??'featured'; $locale=$award['locale']??'ar';
$avatar=$avatar ?: (string)setting('brand_logo','');
$labels=['featured'=>['ar'=>'كاتب مميّز','en'=>'Featured Writer','es'=>'Autor destacado','fr'=>'Auteur à l’honneur'],'editor_choice'=>['ar'=>'اختيار التحرير','en'=>"Editor's Choice",'es'=>'Selección editorial','fr'=>'Choix de la rédaction'],'best_story'=>['ar'=>'أفضل قصة','en'=>'Best Story','es'=>'Mejor historia','fr'=>'Meilleure histoire'],'contributor'=>['ar'=>'مساهم مميّز','en'=>'Outstanding Contributor','es'=>'Colaborador destacado','fr'=>'Contribution remarquable']];
$kickers=['ar'=>'تكريم كتّاب سراديب','en'=>'Saradeeeb Writer Recognition','es'=>'Reconocimiento de autores','fr'=>'Distinction des auteurs'];
$profile=writer_url($username,valid_locale($locale)?$locale:default_locale());
$profileReadable=rawurldecode($profile);
$awardDate=!empty($award['created_at'])?date('Y-m-d',strtotime((string)$award['created_at'])):'';
$managedTemplate=\App\Services\CardTemplateService::renderActive('award-card',['name'=>(string)$name,'username'=>(string)$username,'reason'=>(string)($award['message']??''),'profile_url'=>(string)$profile]);
if($managedTemplate!==''){ echo $managedTemplate; return; }
?>
<link rel="stylesheet" href="/assets/css/award-certificate.css?v=4">
<article class="award-card-pro post award-official-card" <?=$awardPreview?'id="awardCard"':''?> dir="<?=$locale==='ar'?'rtl':'ltr'?>" data-award-link="<?=e($profile)?>">
 <div class="award-certificate-frame" aria-hidden="true"></div>
 <?php if($watermark=(string)setting('brand_logo','')):?><img class="award-certificate-watermark" src="<?=e($watermark)?>" alt="" aria-hidden="true"><?php endif;?>
 <img class="award-certificate-medal" src="/assets/award-gold-medal.png" alt="" aria-hidden="true">
 <header><div class="award-saradeeeb-mark"><span class="award-brand-logo"> <?php if($logo=(string)setting('brand_logo','')):?><img src="<?=e($logo)?>" alt="سراديب" draggable="false"><?php else:?><b>س</b><?php endif;?></span><div><b>سراديب</b><small>SARADEEEB</small></div></div><div class="award-ribbon"><i></i><span <?=$awardPreview?'id="awardTypeText"':''?>><?=e($labels[$type][$locale]??$labels[$type]['ar']??'تكريم')?></span></div></header>
 <section class="award-card-award" aria-label="عنوان التكريم"><small class="award-card-kicker" <?=$awardPreview?'id="awardKicker"':''?>><?=e($kickers[$locale]??$kickers['en'])?></small><h3 <?=$awardPreview?'id="awardTitlePreview"':''?>><?=e($award['title']??'كاتب مميّز في سراديب')?></h3></section>
 <section class="award-card-writer" aria-label="بيانات الكاتب"><div class="award-avatar" <?=$awardPreview?'id="awardAvatar"':''?>><?php if($avatar):?><img src="<?=e($avatar)?>" alt="<?=e($name)?>" crossorigin="anonymous" loading="lazy" draggable="false"><?php else:?><span><?=e(mb_substr($name,0,1,'UTF-8'))?></span><?php endif;?></div><div class="award-writer-copy"><h3 <?=$awardPreview?'id="awardName"':''?>><?=e($name)?> <?=writer_badges_html($award)?></h3><p class="award-role" <?=$awardPreview?'id="awardRole"':''?>><?=e($role?:'كاتب في سراديب')?></p><small>@<?=e($username)?></small></div></section>
 <section class="award-card-reason" aria-label="سبب التكريم"><small>سبب التكريم</small><p class="award-reason" <?=$awardPreview?'id="awardMessagePreview"':''?>><?=e($award['message']??'')?></p></section>
 <footer><div class="award-card-footer-copy"><a class="award-profile-url" <?=$awardPreview?'id="awardUrl"':''?> href="<?=e($profile)?>"><?=e('@'.$username)?></a><small>saradeeeb.com · اكتشف كتاباته<?= $awardDate?' · '.e($awardDate):'' ?></small></div><div class="award-qr-real"><img <?=$awardPreview?'id="awardQr"':''?> src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&amp;data=<?=rawurlencode($profileReadable)?>" alt="رمز رابط الكاتب" crossorigin="anonymous" loading="lazy" draggable="false"></div></footer>
</article>
