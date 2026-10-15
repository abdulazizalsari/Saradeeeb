<?php $C=community_detail_ui($locale); ?>
<section class="community-page"><div class="container community-account-page">
  <div class="community-page-head"><div><span class="community-eyebrow"><?=e($C['your_account'])?></span><h1><?=e($C['account_title'])?></h1><p><?=e($C['account_intro'])?></p></div><a class="btn btn-ghost" href="<?=e(locale_url($locale,'community/member/'.$profile['username']))?>"><?=e($C['public_profile'])?></a></div>
  <?php if($x=flash('success')):?><div class="notice success"><?=e($x)?></div><?php endif;?>
  <?php if($x=flash('error')):?><div class="notice error"><?=e($x)?></div><?php endif;?>
  <form method="post" enctype="multipart/form-data" action="<?=e(locale_url($locale,'community/account'))?>" class="community-profile-form">
    <?=csrf_field()?>
    <div class="community-profile-editor-avatar"><?php if(!empty($profile['avatar'])):?><img src="<?=e($profile['avatar'])?>" alt=""><?php else:?><span><?=e(mb_substr((string)$profile['name'],0,1,'UTF-8'))?></span><?php endif;?><label><?=e($C['change_photo'])?><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/avif"></label></div>
    <div class="form-stack"><label><?=e($C['name'])?><input name="name" value="<?=e($profile['name'])?>" maxlength="120" required></label><label><?=e($C['username'])?><input value="<?=e($profile['username'])?>" readonly dir="ltr"></label><label><?=e($C['email'])?><input value="<?=e($profile['email'])?>" readonly dir="ltr"></label><label><?=e($C['bio'])?><textarea name="bio" rows="6" maxlength="1000" placeholder="<?=e($C['bio_ph'])?>"><?=e($profile['bio']??'')?></textarea></label><button class="btn btn-primary" type="submit"><?=e($C['save_profile'])?></button></div>
  </form>
  <div class="community-account-links-panel"><a href="<?=e(locale_url($locale,'community/preferences'))?>"><?=e($C['alert_settings'])?></a><a href="<?=e(locale_url($locale,'forgot-password'))?>"><?=e($C['password'])?></a><a href="<?=e(locale_url($locale,'community/saved'))?>"><?=e($C['saved_topics'])?></a></div>
</div></section>

