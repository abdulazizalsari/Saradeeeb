<?php $A=auth_ui($locale); ?>
<section class="auth-section"><div class="auth-card">
<span class="eyebrow"><?=e($A['recover'])?></span><h1><?=e($A['forgot_title'])?></h1><p><?=e($A['forgot_intro'])?></p>
<?php if($x=flash('success')):?><div class="notice success"><?=e($x)?></div><?php endif;?><?php if($x=flash('error')):?><div class="notice error"><?=e($x)?></div><?php endif;?>
<form method="post" action="<?=e(locale_url($locale,'forgot-password'))?>" class="form-stack"><?=csrf_field()?>
<label><?=e($A['email'])?><input type="email" name="email" required autocomplete="email"></label>
<button class="btn btn-primary btn-full"><?=e($A['send_reset'])?></button>
</form><div class="auth-foot"><a href="<?=e(locale_url($locale,'login'))?>"><?=e($A['back_login'])?></a></div></div></section>
