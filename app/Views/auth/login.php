<?php $A=auth_ui($locale); $authLogo=setting('brand_logo',''); ?>
<?php $passwordUi=['ar'=>['show'=>'إظهار','hide'=>'إخفاء','remember'=>'تذكرني لمدة 30 يومًا على هذا الجهاز'], 'en'=>['show'=>'Show','hide'=>'Hide','remember'=>'Remember me for 30 days on this device'], 'es'=>['show'=>'Mostrar','hide'=>'Ocultar','remember'=>'Recordarme durante 30 días en este dispositivo'], 'fr'=>['show'=>'Afficher','hide'=>'Masquer','remember'=>'Se souvenir de moi pendant 30 jours sur cet appareil'], 'ru'=>['show'=>'Показать','hide'=>'Скрыть','remember'=>'Запомнить меня на 30 дней на этом устройстве']][$locale]??['show'=>'Show','hide'=>'Hide','remember'=>'Remember me']; ?>
<section class="auth-section auth-section-premium">
    <div class="auth-shell">
        <div class="auth-visual" aria-hidden="true">
            <div class="auth-visual-brand"><?php if($authLogo):?><img src="<?=e($authLogo)?>" alt="<?=e($A['brand'])?>"><?php else:?><span><?=$locale==='ar'?'ط³':'S'?></span><?php endif;?><strong><?=e($A['brand'])?></strong></div>
            <div class="auth-visual-copy">
                <span class="eyebrow">SARADEEБ CMS</span>
                <h2><?=e($A['login_visual_title'])?></h2>
                <p><?=e($A['login_visual_text'])?></p>
            </div>
            <div class="auth-visual-lines"><i></i><i></i><i></i></div>
        </div>

        <div class="auth-card auth-card-premium">
            <a class="auth-back-link" href="<?=e(locale_url($locale))?>">← <?=e($A['back_site'])?></a>
            <span class="eyebrow"><?= e(tr('welcome_back', $locale)) ?></span>
            <h1><?= e(tr('login', $locale)) ?></h1>
            <p><?= e(tr('login_desc', $locale)) ?></p>

            <?php if ($x = flash('success')): ?><div class="notice success"><?= e($x) ?></div><?php endif; ?>
            <?php if ($x = flash('error')): ?><div class="notice error"><?= e($x) ?></div><?php endif; ?>

            <form method="post" action="<?=e(locale_url($locale,'login'))?>" class="form-stack auth-form-premium">
                <?= csrf_field() ?>
                <label>
                    <span>البريد الإلكتروني أو اسم المستخدم</span>
                    <div class="input-with-icon"><i aria-hidden="true">@</i><input type="text" name="email" value="<?= old('email') ?>" required autocomplete="username" placeholder="name@example.com أو username"></div>
                </label>
                <label>
                    <span><?= e(tr('password', $locale)) ?></span>
                    <div class="input-with-icon password-field"><i aria-hidden="true">••</i><input type="password" name="password" required autocomplete="current-password" placeholder="••••••••••••"><button type="button" class="password-toggle" data-password-toggle data-show="<?=e($passwordUi['show'])?>" data-hide="<?=e($passwordUi['hide'])?>" aria-label="<?=e($passwordUi['show'])?>" aria-pressed="false"><?=e($passwordUi['show'])?></button></div>
                </label>
                <label class="remember-me"><input type="checkbox" name="remember_me" value="1"><span><?=e($passwordUi['remember'])?></span></label>
                <button class="btn btn-primary btn-full auth-submit" type="submit"><?= e(tr('enter', $locale)) ?> <span>←</span></button>
            </form>
            <?php if(\App\Core\GoogleOAuth::enabled()): ?>
            <?php $googleLabel=['ar'=>'المتابعة باستخدام Google','en'=>'Continue with Google','es'=>'Continuar con Google','fr'=>'Continuer avec Google','ru'=>'Продолжить с Google'][$locale]??'Continue with Google'; ?>
            <div class="auth-divider" aria-hidden="true"><span><?=e($locale==='ar'?'أو':'or')?></span></div>
            <a class="google-signin-button" href="<?=e(url('/auth/google/start').'?locale='.rawurlencode($locale))?>"><span class="google-signin-mark" aria-hidden="true">G</span><span><?=e($googleLabel)?></span></a>
            <?php endif; ?>
            <div class="auth-forgot"><a href="<?=e(locale_url($locale,'forgot-password'))?>"><?=e($A['forgot'])?></a></div>

            <div class="auth-foot"><?= e(tr('no_account', $locale)) ?> <a href="<?=e(locale_url($locale,'register'))?>"><?=e($A['create_account'])?></a> · <a href="<?=e(locale_url($locale,'writer-application'))?>"><?= e(tr('join_writer', $locale)) ?></a></div>
            <div class="auth-security-note"><span>◉</span> <?=e($A['secure'])?> · <?=e($A['session'])?></div>
        </div>
    </div>
</section>

