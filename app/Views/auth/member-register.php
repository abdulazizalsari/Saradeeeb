<?php $A=auth_ui($locale); $authLogo=setting('brand_logo',''); ?>
<section class="auth-section auth-section-premium">
  <div class="auth-shell">
    <div class="auth-visual" aria-hidden="true">
      <div class="auth-visual-brand"><?php if($authLogo):?><img src="<?=e($authLogo)?>" alt="<?=e($A['brand'])?>"><?php else:?><span><?=$locale==='ar'?'ط³':'S'?></span><?php endif;?><strong><?=e($A['brand'])?></strong></div>
      <div class="auth-visual-copy"><span class="eyebrow"><?=e($A['member_brand'])?></span><h2><?=e($A['member_title'])?></h2><p><?=e($A['member_visual_text'])?></p></div>
      <div class="auth-visual-lines"><i></i><i></i><i></i></div>
    </div>
    <div class="auth-card auth-card-premium">
      <a class="auth-back-link" href="<?=e(locale_url($locale,'community'))?>">← <?=e($A['back_community'])?></a>
      <span class="eyebrow"><?=e($A['member'])?></span><h1><?=e($A['register_title'])?></h1><p><?=e($A['register_intro'])?></p>
      <?php if($x=flash('success')):?><div class="notice success"><?=e($x)?></div><?php endif;?>
      <?php if($x=flash('error')):?><div class="notice error"><?=e($x)?></div><?php endif;?>
      <form method="post" action="<?=e(locale_url($locale,'register'))?>" class="form-stack auth-form-premium">
        <?=csrf_field()?>
        <label><span><?=e($A['name'])?></span><input name="name" value="<?=old('name')?>" minlength="2" maxlength="120" required autocomplete="name"></label>
        <label><span><?=e($A['username'])?></span><input name="username" value="<?=old('username')?>" minlength="3" maxlength="120" required autocomplete="username" dir="ltr" placeholder="username"></label>
        <label><span><?=e($A['email'])?></span><input type="email" name="email" value="<?=old('email')?>" required autocomplete="email" placeholder="name@example.com"></label>
        <button class="btn btn-primary btn-full auth-submit" type="submit"><?=e($A['create_verify'])?> <span>←</span></button>
      </form>
      <div class="auth-foot"><?=e($A['want_writer'])?> <a href="<?=e(locale_url($locale,'writer-application'))?>"><?=e($A['apply_writer'])?></a></div>
      <div class="auth-security-note"><span>●</span> <?=e($A['account_note'])?></div>
    </div>
  </div>
</section>
