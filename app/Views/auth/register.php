<?php
$A=auth_ui($locale);
if(!empty($managedPage)) $A['writer_title']=$managedPage['title'];
$existingAccountCopy=['ar'=>'لديك حساب بالفعل؟ تسجيل الدخول','en'=>'Already have an account? Sign in','es'=>'¿Ya tienes una cuenta? Inicia sesión','fr'=>'Vous avez déjà un compte ? Connectez-vous','ru'=>'Уже есть аккаунт? Войти'];
$existingAccountText=$existingAccountCopy[$locale]??$existingAccountCopy['en'];
?>
<section class="auth-section writer-application-page"><div class="auth-card wide writer-application-card">
<span class="eyebrow"><?=e($A['writer_eyebrow'])?></span><h1><?=e($A['writer_title'])?></h1><p><?=e($A['writer_intro'])?></p>
<?php if($x=flash('success')):?><div class="notice success"><?=e($x)?></div><?php endif;?><?php if($x=flash('error')):?><div class="notice error"><?=e($x)?></div><?php endif;?>
<form method="post" action="<?=e(locale_url($locale,'writer-application'))?>" class="form-grid writer-application-form"><?=csrf_field()?>
<label><?=e($A['full_name'])?><input name="name" value="<?=old('name')?>" required></label>
<label>اسم المستخدم<input name="username" value="<?=old('username')?>" pattern="[A-Za-z][A-Za-z0-9_.-]{2,29}" minlength="3" maxlength="30" required autocomplete="username"></label>
<label><?=e($A['email'])?><input type="email" name="email" value="<?=old('email')?>" required autocomplete="email"></label>
<label><?=e($A['password']??'Password')?><input type="password" name="password" minlength="12" required autocomplete="new-password"></label>
<label><?=e($A['password_confirmation']??'Confirm password')?><input type="password" name="password_confirmation" minlength="12" required autocomplete="new-password"></label>
<label><?=e($A['phone'])?> <small>(اختياري)</small><input name="phone" value="<?=old('phone')?>"></label>
<label><?=e($A['country'])?><input name="country" value="<?=old('country')?>"></label>
<label><?=e($A['city'])?><input name="city" value="<?=old('city')?>"></label>
<label><?=e($A['topics'])?><input name="topics" value="<?=old('topics')?>" placeholder="<?=e($A['topics_ph'])?>"></label>
<label class="span-2"><?=e($A['experience'])?><textarea name="experience" rows="4"><?=old('experience')?></textarea></label>
<label class="span-2"><?=e($A['sample'])?><input type="url" name="sample_url" value="<?=old('sample_url')?>" placeholder="https://..."></label>
<label class="span-2"><?=e($A['bio'])?> <small>(اختياري)</small><textarea name="bio" rows="7"><?=old('bio')?></textarea></label>
<label class="check span-2"><input type="checkbox" name="consent" value="1" required> <?=e($A['consent'])?> — أوافق على الشروط وسياسة الخصوصية</label>
<button class="btn btn-primary span-2"><?=e($A['submit_application'])?></button>
</form>
<p class="writer-application-login"><a href="<?=e(locale_url($locale,'login'))?>"><?=e($existingAccountText)?></a></p>
</div></section>
