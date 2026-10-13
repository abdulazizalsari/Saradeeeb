<?php
$locale=$locale??default_locale();
$li=locale_info($locale);
$sourceSiteName=setting('site_name',config('name'));
$siteName=translation_text('site','name',$locale,$locale==='ar'?$sourceSiteName:'Saradeeeb');
$sourceTagline=setting('tagline',config('tagline'));
$tagline=translation_text('site','tagline',$locale,$locale==='ar'?$sourceTagline:'Stories, mysteries and knowledge beyond the familiar.');
if(isset($title) && $locale!=='ar' && (string)$title===(string)$sourceSiteName) $title=$siteName;
$user=auth_user();
$canonical=$canonical??url($_SERVER['REQUEST_URI']??'/');
$description=$description??$tagline;
$brandColor=setting('brand_color','#157176') ?: '#157176';
$brandLogo=setting('brand_logo','');
$brandLogoHeight=max(34,min(110,(int)(setting('brand_logo_height','64')?:64)));
$showMark=setting('brand_mark_enabled','1')==='1';
$markText=setting('brand_mark_text','س') ?: 'س';
if($locale!=='ar' && preg_match('/[\x{0600}-\x{06FF}]/u',$markText)) $markText='S';
$markBg=setting('brand_mark_bg',$brandColor) ?: $brandColor;
$markColor=setting('brand_mark_color','#ffffff') ?: '#ffffff';
$languages=enabled_locales();

$tickerEnabled=setting('news_ticker_enabled','1')==='1';
$tickerItems=$tickerEnabled?news_ticker_items($locale,10):[];
$tickerLabels=['ar'=>'أهم المقالات','en'=>'Top stories','es'=>'Historias destacadas','fr'=>'Articles à la une','ru'=>'Главные истории'];
$tickerLabel=translation_text('layout','ticker_label',$locale,$tickerLabels[$locale]??$tickerLabels['en']);
$tickerSpeed=max(12,min(120,(int)(setting('news_ticker_speed','32')?:32)));
$tickerBg=setting('header_ticker_background','#071118') ?: '#071118';
$tickerText='#ffffff';
$tickerAccent=setting('header_ticker_accent','#37c1c7') ?: '#37c1c7';

$sitePopup=active_site_popup($locale);
// Writer recognition remains available in profiles and the administration
// area, but its floating site-wide card is disabled by default.
$floatingAwards=setting('writer_awards_popup_enabled','0')==='1' ? active_writer_awards($locale) : [];
$adblockNotice=setting('adblock_notice_enabled','0')==='1';
$adblockMode=setting('adblock_mode','notice');

$headerMenu=configured_navigation($locale,'header');
$footerMenu=configured_navigation($locale,'footer');
$privacyFooterLabels=['ar'=>'سياسة الخصوصية','en'=>'Privacy policy','es'=>'Política de privacidad','fr'=>'Politique de confidentialité','ru'=>'Политика конфиденциальности'];
$privacyFooterUrl=locale_url($locale,'privacy-policy');
$footerMenu=array_values(array_filter($footerMenu,static fn($item)=>!str_contains((string)($item['url']??''),'privacy-policy')));
$mobileMenu=configured_navigation($locale,'mobile');
$headerSticky=setting('header_sticky','1')==='1';
$headerCompact=setting('header_scroll_compact','1')==='1';
$headerHide=setting('header_hide_on_scroll','0')==='1';
$headerShadow=setting('header_shadow','1')==='1';
$headerTagline=setting('header_show_tagline','0')==='1';
$headerSearch=setting('header_show_search','1')==='1';
$headerTheme=setting('header_show_theme','1')==='1';
$headerIcons=setting('header_show_icons','1')==='1';
$headerSocialsEnabled=setting('header_show_socials','1')==='1';
$headerMegaEnabled=setting('header_show_categories_mega','1')==='1';
$writerCtaEnabled=setting('header_account_cta_enabled',setting('header_writer_cta_enabled','1'))==='1';
$writerCtaMobile=setting('header_account_cta_mobile',setting('header_writer_cta_mobile','1'))==='1';
$accountCtaDefaults=['ar'=>'حسابي','en'=>'My account','es'=>'Mi cuenta','fr'=>'Mon compte','ru'=>'Мой аккаунт'];
$writerCtaLabel=translation_text('nav','my_account',$locale,$accountCtaDefaults[$locale]??$accountCtaDefaults['en']);
$role=(string)($user['role']??'');
if(!$user){
    $writerCtaUrl=locale_url($locale,'login');
}elseif(in_array($role,['admin','editor'],true)){
    $writerCtaUrl='/admin';
}elseif(in_array($role,['writer','trusted_writer'],true)){
    $writerCtaUrl='/writer/account';
}else{
    $writerCtaUrl=locale_url($locale,'community/account');
}
$headerHeight=max(64,min(110,(int)(setting('header_height','86')?:86)));
$headerBg=setting('header_background','#ffffff') ?: '#ffffff';
$headerScrolledBg=setting('header_scrolled_background','#ffffff') ?: '#ffffff';
$headerText=setting('header_text_color','#152d31') ?: '#152d31';
$headerBorder=setting('header_border_color','#e1e8e9') ?: '#e1e8e9';
$headerHover=setting('header_hover_color',$brandColor) ?: $brandColor;
$headerHoverBg=setting('header_hover_background','#eaf6f6') ?: '#eaf6f6';
$megaBg=setting('header_mega_background','#121a1d') ?: '#121a1d';
$megaText=setting('header_mega_text','#f6fbfb') ?: '#f6fbfb';
$megaCard=setting('header_mega_card','#1c2529') ?: '#1c2529';
$drawerBg=setting('header_mobile_background','#ffffff') ?: '#ffffff';
$drawerText=setting('header_mobile_text','#163438') ?: '#163438';
$typographyCss=typography_css_variables();

$footerEnabled=setting('footer_enabled','1')==='1';
$footerShowLogo=setting('footer_show_logo','1')==='1';
$footerShowLanguages=setting('footer_show_languages','1')==='1';
$footerShowSocials=setting('footer_show_socials','1')==='1';
$footerBackTop=setting('footer_back_to_top','1')==='1';
$footerBg=setting('footer_background','#071019') ?: '#071019';
$footerTextColor=setting('footer_text_color','#c7d7db') ?: '#c7d7db';
$footerHeadingColor=setting('footer_heading_color','#ffffff') ?: '#ffffff';
$footerAccent=setting('footer_accent_color','#31bec5') ?: '#31bec5';
$footerButtonBg=setting('footer_button_background',$footerAccent) ?: $footerAccent;
$footerButtonText=setting('footer_button_text','#071519') ?: '#071519';
$footerBorderColor=setting('footer_border_color','#244348') ?: '#244348';
$footerLayout=setting('footer_layout','balanced'); if(!in_array($footerLayout,['balanced','wide-brand','compact'],true))$footerLayout='balanced';
$footerPaddingTop=max(36,min(120,(int)(setting('footer_padding_top','72')?:72)));
$footerPaddingBottom=max(24,min(100,(int)(setting('footer_padding_bottom','56')?:56)));
$footerColumnGap=max(16,min(90,(int)(setting('footer_column_gap','48')?:48)));
$footerLogoHeight=max(34,min(120,(int)(setting('footer_logo_height','74')?:74)));
$footerSocialSize=max(30,min(58,(int)(setting('footer_social_size','38')?:38)));
$footerHeadingSize=max(12,min(26,(int)(setting('footer_heading_size','19')?:19)));
$footerTextSize=max(11,min(19,(int)(setting('footer_text_size','14')?:14)));
$footerElementRadius=max(0,min(28,(int)(setting('footer_element_radius','11')?:11)));
$footerLocaleDefaults=require BASE_PATH.'/app/Views/partials/footer-defaults.php';
$fd=$footerLocaleDefaults[$locale]??$footerLocaleDefaults['en'];
$uiCopy=[
 'ar'=>['skip'=>'تجاوز إلى المحتوى','social'=>'الشبكات الاجتماعية','search'=>'بحث','theme'=>'تبديل المظهر','menu'=>'القائمة','close'=>'إغلاق','email'=>'بريدك الإلكتروني','subscribe'=>'اشتراك','consent_news'=>'أوافق على استلام جديد القصص ويمكنني إلغاء الاشتراك لاحقًا.','social_links'=>'روابط التواصل','privacy'=>'إعدادات الخصوصية','top'=>'العودة للأعلى','notice'=>'إشعار من سراديب','more'=>'معرفة المزيد','adblock_title'=>'يبدو أن مانع الإعلانات مفعّل.','adblock_strict'=>'عطّل مانع الإعلانات لهذا الموقع ثم أعد تحميل الصفحة لمتابعة القراءة.','adblock_soft'=>'الإعلانات تساعد في دعم سراديب. نقدّر إضافة الموقع إلى الاستثناءات.','hide'=>'إخفاء','necessary'=>'ضروري','analytics'=>'تحليلات','marketing'=>'تسويق','ads'=>'إعلانات','accept'=>'قبول الكل','save'=>'حفظ اختياري','reject'=>'رفض غير الضروري','privacy_policy'=>'سياسة الخصوصية'],
 'en'=>['skip'=>'Skip to content','social'=>'Social media','search'=>'Search','theme'=>'Toggle theme','menu'=>'Menu','close'=>'Close','email'=>'Your email','subscribe'=>'Subscribe','consent_news'=>'I agree to receive new stories and can unsubscribe at any time.','social_links'=>'Social links','privacy'=>'Privacy settings','top'=>'Back to top','notice'=>'Saradeeeb notice','more'=>'Learn more','adblock_title'=>'It looks like an ad blocker is enabled.','adblock_strict'=>'Disable the ad blocker for this site and reload the page to continue reading.','adblock_soft'=>'Ads help support Saradeeeb. We appreciate adding the site to your allowlist.','hide'=>'Hide','necessary'=>'Necessary','analytics'=>'Analytics','marketing'=>'Marketing','ads'=>'Ads','accept'=>'Accept all','save'=>'Save choices','reject'=>'Reject non-essential','privacy_policy'=>'Privacy policy'],
 'es'=>['skip'=>'Saltar al contenido','social'=>'Redes sociales','search'=>'Buscar','theme'=>'Cambiar tema','menu'=>'Menú','close'=>'Cerrar','email'=>'Tu correo electrónico','subscribe'=>'Suscribirse','consent_news'=>'Acepto recibir nuevas historias y puedo darme de baja en cualquier momento.','social_links'=>'Redes sociales','privacy'=>'Configuración de privacidad','top'=>'Volver arriba','notice'=>'Aviso de Saradeeeb','more'=>'Saber más','adblock_title'=>'Parece que tienes un bloqueador de anuncios activo.','adblock_strict'=>'Desactiva el bloqueador para este sitio y recarga la página para continuar.','adblock_soft'=>'Los anuncios ayudan a mantener Saradeeeb. Agradecemos que añadas el sitio a tus excepciones.','hide'=>'Ocultar','necessary'=>'Necesario','analytics'=>'Analítica','marketing'=>'Marketing','ads'=>'Anuncios','accept'=>'Aceptar todo','save'=>'Guardar opciones','reject'=>'Rechazar lo no esencial','privacy_policy'=>'Política de privacidad'],
 'fr'=>['skip'=>'Aller au contenu','social'=>'Réseaux sociaux','search'=>'Rechercher','theme'=>'Changer le thème','menu'=>'Menu','close'=>'Fermer','email'=>'Votre e-mail','subscribe'=>'S’abonner','consent_news'=>'J’accepte de recevoir les nouvelles histoires et je peux me désabonner à tout moment.','social_links'=>'Réseaux sociaux','privacy'=>'Paramètres de confidentialité','top'=>'Retour en haut','notice'=>'Avis de Saradeeeb','more'=>'En savoir plus','adblock_title'=>'Un bloqueur de publicités semble être activé.','adblock_strict'=>'Désactivez le bloqueur pour ce site puis rechargez la page pour continuer.','adblock_soft'=>'Les publicités contribuent à soutenir Saradeeeb. Merci d’ajouter le site à vos exceptions.','hide'=>'Masquer','necessary'=>'Nécessaire','analytics'=>'Analytique','marketing'=>'Marketing','ads'=>'Publicités','accept'=>'Tout accepter','save'=>'Enregistrer mes choix','reject'=>'Refuser le non essentiel','privacy_policy'=>'Politique de confidentialité'],
 'ru'=>['skip'=>'Перейти к содержимому','social'=>'Социальные сети','search'=>'Поиск','theme'=>'Сменить тему','menu'=>'Меню','close'=>'Закрыть','email'=>'Ваш e-mail','subscribe'=>'Подписаться','consent_news'=>'Я согласен получать новые истории и могу отказаться в любое время.','social_links'=>'Социальные сети','privacy'=>'Настройки приватности','top'=>'Наверх','notice'=>'Уведомление Saradeeeb','more'=>'Подробнее','adblock_title'=>'Похоже, включён блокировщик рекламы.','adblock_strict'=>'Отключите блокировщик для этого сайта и перезагрузите страницу.','adblock_soft'=>'Реклама помогает поддерживать Saradeeeb. Добавьте сайт в исключения.','hide'=>'Скрыть','necessary'=>'Обязательные','analytics'=>'Аналитика','marketing'=>'Маркетинг','ads'=>'Реклама','accept'=>'Принять всё','save'=>'Сохранить выбор','reject'=>'Отклонить необязательные','privacy_policy'=>'Политика конфиденциальности'],
];
$ui=$uiCopy[$locale]??$uiCopy['en'];
$communityUi=['ar'=>['notifications'=>'إشعارات المنتدى','saved'=>'المحفوظات','account'=>'حسابي'],'en'=>['notifications'=>'Community notifications','saved'=>'Saved','account'=>'My account'],'es'=>['notifications'=>'Notificaciones de la comunidad','saved'=>'Guardados','account'=>'Mi cuenta'],'fr'=>['notifications'=>'Notifications de la communauté','saved'=>'Enregistrés','account'=>'Mon compte'],'ru'=>['notifications'=>'Уведомления сообщества','saved'=>'Сохранённое','account'=>'Мой аккаунт']][$locale]??['notifications'=>'Community notifications','saved'=>'Saved','account'=>'My account'];
foreach($ui as $k=>$fallback) $ui[$k]=translation_text('layout',$k,$locale,$fallback);
foreach($fd as $k=>$fallback) $fd[$k]=translation_text('footer',$k,$locale,(string)$fallback);
$footerTitle=translation_text('footer','title',$locale,setting('footer_title_'.$locale,setting('footer_title',$fd['title'])) ?: $fd['title']);
$footerDescription=translation_text('footer','description',$locale,setting('footer_description_'.$locale,setting('footer_description',setting('footer_text',$fd['description']))) ?: $fd['description']);
$footerMenuTitle=translation_text('footer','menu',$locale,setting('footer_menu_title_'.$locale,setting('footer_menu_title',$fd['menu'])) ?: $fd['menu']);
$footerLanguagesTitle=translation_text('footer','languages',$locale,setting('footer_languages_title_'.$locale,setting('footer_languages_title',$fd['languages'])) ?: $fd['languages']);
$footerCopyright=translation_text('footer','copyright',$locale,trim(setting('footer_copyright_'.$locale,setting('footer_copyright',$fd['copyright']))));
$footerDesignerEnabled=setting('footer_designer_enabled','1')==='1';
$footerDesignerPrefix=translation_text('footer','designer_prefix',$locale,setting('footer_designer_prefix_'.$locale,['ar'=>'تم تصميم هذا الموقع بواسطة','en'=>'Site designed by','es'=>'Sitio diseñado por','fr'=>'Site conçu par'][$locale]??'Site designed by'));
$footerDesignerName=trim(setting('footer_designer_name','عبدالعزيز'));
if($locale!=='ar' && preg_match('/[\x{0600}-\x{06FF}]/u',$footerDesignerName)) $footerDesignerName='AbdulAziz';
$footerDesignerUrl=trim(setting('footer_designer_url',''));
$footerDesignerNewTab=setting('footer_designer_new_tab','1')==='1';
$socialLinks=footer_social_links();
$headerSocialLinks=$headerSocialsEnabled?site_social_links('header'):[];
$waConfig=whatsapp_frontend_config();
$trackingConsentEnabled=tracking_consent_required();
$trackingConsentEnabled=false;
$trackingConsentState=tracking_consent_state();
$trackingConsentVersion=\App\Core\Integrations::consentVersion();
$consentDefaults=[
'ar'=>['خصوصيتك تهمنا','نستخدم أدوات قياس وإعلانات غير ضرورية فقط بعد موافقتك. يمكنك تغيير اختيارك في أي وقت.'],
'en'=>['Your privacy matters','We use non-essential analytics and advertising tools only with your consent. You can change your choice at any time.'],
'es'=>['Tu privacidad importa','Usamos herramientas analíticas y publicitarias no esenciales solo con tu consentimiento. Puedes cambiar tu elección en cualquier momento.'],
'fr'=>['Votre vie privée compte','Nous utilisons des outils de mesure et de publicité non essentiels uniquement avec votre consentement. Vous pouvez modifier votre choix à tout moment.']];
$trackingConsentTitle=setting('tracking_consent_title_'.$locale,$consentDefaults[$locale][0]??$consentDefaults['en'][0]);
$trackingConsentMessage=setting('tracking_consent_message_'.$locale,$consentDefaults[$locale][1]??$consentDefaults['en'][1]);
$trackingPrivacyUrl=setting('tracking_privacy_url','/page/privacy-policy');
$communityUnread=$user?community_unread_notifications((int)$user['id']):0;
$protectionMaster=setting('security_content_protection','1')==='1';
$protectConfig=['copy'=>$protectionMaster && setting('protect_copy','1')==='1','context'=>$protectionMaster && setting('protect_context','1')==='1','select'=>$protectionMaster && setting('protect_select','1')==='1','drag'=>$protectionMaster && setting('protect_drag','1')==='1','save'=>$protectionMaster && setting('protect_save_shortcut','1')==='1','print'=>$protectionMaster && setting('protect_print','1')==='1','watermark'=>$protectionMaster && setting('protect_image_overlay','0')==='1','watermarkText'=>setting('protect_watermark_text',$siteName)];
$footerShowCategories=setting('footer_show_categories','1')==='1';
$footerShowCta=setting('footer_show_cta','1')==='1';
$footerShowStoryButton=setting('footer_show_story_button','1')==='1';
$footerShowWriterButton=setting('footer_show_writer_button','1')==='1';
$footerCategoriesTitle=translation_text('footer','categories',$locale,setting('footer_categories_title_'.$locale,setting('footer_categories_title',$fd['categories'])) ?: $fd['categories']);
$footerCtaTitle=translation_text('footer','cta_title',$locale,setting('footer_cta_title_'.$locale,setting('footer_cta_title',$fd['cta_title'])) ?: $fd['cta_title']);
$footerCtaText=translation_text('footer','cta_text',$locale,setting('footer_cta_text_'.$locale,setting('footer_cta_text',$fd['cta_text'])) ?: $fd['cta_text']);
$footerCtaLabel=translation_text('footer','cta_label',$locale,setting('footer_cta_label_'.$locale,setting('footer_cta_label',$fd['cta_label'])) ?: $fd['cta_label']);
$footerCtaUrl=setting('footer_cta_url','/contact') ?: '/contact';
$footerStoryDefault=['ar'=>'أرسل قصتك','en'=>'Send your story','es'=>'Envía tu historia','fr'=>'Envoyez votre histoire','ru'=>'Отправить историю'][$locale]??'Send your story';
$footerWriterDefault=['ar'=>'قدّم طلب عضوية كاتب','en'=>'Apply for writer membership','es'=>'Solicita una membresía de autor','fr'=>'Demander une adhésion auteur','ru'=>'Заявка автора'][$locale]??'Apply for writer membership';
$footerStoryLabel=translation_text('footer','story_cta',$locale,setting('footer_story_label_'.$locale,setting('footer_story_label',$footerStoryDefault)) ?: $footerStoryDefault);
$footerWriterLabel=translation_text('footer','writer_cta',$locale,setting('footer_writer_label_'.$locale,setting('footer_writer_label',$footerWriterDefault)) ?: $footerWriterDefault);
$behindSaradeebLabel=translation_text('footer','behind_saradeeb',$locale,['ar'=>'عن سراديب','en'=>'About Saradeeeb','es'=>'Acerca de Saradeeeb','fr'=>'À propos de Saradeeeb','ru'=>'О Saradeeeb'][$locale]??'About Saradeeeb');
$footerActionUrl=static function(string $raw,string $fallback) use($locale): string { $raw=trim($raw); if($raw==='' || !preg_match('#^(?:https?://|mailto:|tel:|/)#i',$raw)) $raw=$fallback; return str_starts_with($raw,'/')?locale_url($locale,ltrim($raw,'/')):$raw; };
$footerStoryUrl=$footerActionUrl(setting('footer_story_url','/send-story'),'/send-story');
$footerWriterUrl=$footerActionUrl(setting('footer_writer_url','/writer-application'),'/writer-application');
$footerShowBehindButton=setting('footer_show_behind_button','1')==='1';
$footerBehindLabel=translation_text('footer','behind_cta',$locale,setting('footer_behind_label_'.$locale,setting('footer_behind_label',$behindSaradeebLabel)) ?: $behindSaradeebLabel);
$footerBehindUrl=$footerActionUrl(setting('footer_behind_url','/behind-saradeeb'),'/behind-saradeeb');
if(setting('footer_behind_use_page','1')==='1') {
    $footerBehindUrl=\App\Services\BehindPage::url($locale);
    if($footerBehindUrl==='') $footerShowBehindButton=false;
}
$contactEmail=trim(setting('footer_contact_email',setting('contact_email','')));
$footerShowEmail=setting('footer_show_email','1')==='1';
$footerShowNewsletter=setting('footer_show_newsletter','1')==='1';
$footerNewsletterTitle=translation_text('footer','newsletter_title',$locale,setting('footer_newsletter_title_'.$locale,setting('footer_newsletter_title',$fd['newsletter_title'])) ?: $fd['newsletter_title']);
$footerNewsletterText=translation_text('footer','newsletter_text',$locale,setting('footer_newsletter_text_'.$locale,setting('footer_newsletter_text',$fd['newsletter_text'])) ?: $fd['newsletter_text']);

$headerCategories=[];
$footerCategories=[];
try{
    $headerCategories=App\Core\Database::all("SELECT ct.name,ct.slug,COUNT(a.id) article_count FROM categories c JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=? AND ct.status='published' LEFT JOIN articles a ON a.category_id=c.id AND a.status='published' WHERE c.active=1 GROUP BY c.id,ct.name,ct.slug ORDER BY c.sort_order,c.id LIMIT 12",[$locale]);
    $footerCategories=array_slice($headerCategories,0,6);
}catch(Throwable){}

$footerGroups=\App\Services\ManagedPages::footerLinks($locale);
if(\App\Services\Books::hasPublicBooks()){
    $bookLabels=['ar'=>'إصدارات سراديب','en'=>'Saradeeeb books','es'=>'Libros de Saradeeeb','fr'=>'Livres de Saradeeeb','ru'=>'Книги Saradeeeb'];
    $bookNav=['key'=>'books','url'=>'/books','label'=>$bookLabels[$locale]??$bookLabels['en'],'icon'=>'article','target'=>'_self'];
    $headerMenu[]=$bookNav;
    $mobileMenu[]=$bookNav;
    $footerGroups['menu'][]=$bookNav;
}
$writerCtaEnabled=$writerCtaEnabled && \App\Services\ManagedPages::forKey('write',$locale)!==null;
$headerSearch=$headerSearch && \App\Services\ManagedPages::forKey('explore',$locale)!==null;
$footerMenu=$footerGroups['menu'];
$footerMenu=array_values(array_filter($footerMenu,static fn($item)=>!str_contains((string)($item['url']??''),'privacy-policy')));
$footerGroups['menu']=$footerMenu;
$footerGroups['connect'][]=['url'=>'/admin','label'=>'الإدارة','icon'=>'settings','target'=>'_self'];
$footerCopy=json_decode(setting('footer_copy_json','{}'),true)?:[];
$localCopy=$footerCopy[$locale]??[];
$copyVisible=$locale==='ar' || ($localCopy['status']??'draft')==='published';
foreach(['footerTitle'=>'title','footerDescription'=>'description','footerMenuTitle'=>'menu_title','footerCategoriesTitle'=>'categories_title','footerCtaTitle'=>'cta_title','footerCtaText'=>'cta_text','footerCopyright'=>'copyright'] as $variable=>$key){
    $$variable=$copyVisible?($locale==='ar'?setting('footer_'.$key,$localCopy[$key]??''):($localCopy[$key]??'')):'';
}
$footerBrandLogo=setting('footer_logo','')?:$brandLogo;
$newsletterPage=\App\Services\ManagedPages::forKey('newsletter',$locale);
$footerShowNewsletter=$footerShowNewsletter && $newsletterPage!==null;
$newsletterColumn=$newsletterPage?\App\Services\ManagedPages::placement((int)$newsletterPage['page_id'])['column']:'connect';
if($newsletterPage){
    $footerNewsletterTitle=$newsletterPage['title'];
    $fields=json_decode(setting('managed_page_fields_json','{}'),true)?:[];
    $newsFields=$fields[$newsletterPage['page_id']][$locale]??[];
    foreach(['email'=>'email_placeholder','consent_news'=>'consent','subscribe'=>'button_label'] as $uiKey=>$field) if(trim((string)($newsFields[$field]??''))!=='') $ui[$uiKey]=$newsFields[$field];
}
$currentPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$isNavActive=static function(array $item)use($currentPath):bool{
    if(str_contains((string)($item['url']??''),'#')) return false;
    $path=parse_url((string)($item['url']??''),PHP_URL_PATH);
    if(!$path || $path==='/') return $currentPath==='/';
    return $currentPath===$path || str_starts_with($currentPath,rtrim($path,'/').'/');
};
$hasMegaItem=false;
foreach($headerMenu as $navItem){ if(($navItem['key']??'')==='categories'){$hasMegaItem=true;break;} }
$navClass=static function(array $item,bool $mobile=false): string { $classes=[]; $key=(string)($item['key']??''); if($key!=='')$classes[]='nav-key-'.preg_replace('/[^a-z0-9_-]/i','-',$key); if($key==='community')$classes[]='nav-community-cta'; if(!empty($item['custom'])){ $classes[]='custom-nav-item'; if(($item['style_type']??'link')==='button')$classes[]='custom-nav-button'; $display=$item['display']??'both'; if($display==='desktop')$classes[]='nav-desktop-only'; if($display==='mobile')$classes[]='nav-mobile-only'; $classes[]='nav-size-'.($item['size']??'md'); } return implode(' ',$classes); };
$navStyle=static function(array $item): string { if(empty($item['custom']) || ($item['style_type']??'link')!=='button') return ''; return '--nav-btn-bg:'.($item['bg']??'#157176').';--nav-btn-text:'.($item['text_color']??'#fff').';--nav-btn-hover:'.($item['hover_bg']??'#0f5f63').';--nav-btn-border:'.($item['border_color']??'#157176').';--nav-btn-radius:'.((int)($item['radius']??10)).'px;'; };
?>
<!doctype html>
<html lang="<?=e($locale)?>" dir="<?=e($li['dir'])?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e(($title??$siteName).' | '.$siteName)?></title>
<meta name="description" content="<?=e($description)?>">
<?php if(!empty($robots)):?><meta name="robots" content="<?=e($robots)?>"><?php endif;?>
<link rel="canonical" href="<?=e($canonical)?>">
<link rel="alternate" type="application/rss+xml" title="<?=e($siteName)?> RSS" href="<?=e(url('/feed.xml'))?>">
<?php foreach(current_hreflang_urls() as $hrefLocale=>$href):?><link rel="alternate" hreflang="<?=e($hrefLocale)?>" href="<?=e($href)?>"><?php endforeach;?>
<meta property="og:type" content="<?=e($ogType??'website')?>"><meta property="og:title" content="<?=e($ogTitle??$title??$siteName)?>"><meta property="og:description" content="<?=e($ogDescription??$description)?>"><meta property="og:url" content="<?=e($canonical)?>"><?php if(!empty($ogImage)):?><meta property="og:image" content="<?=e(str_starts_with((string)$ogImage,'http')?$ogImage:url((string)$ogImage))?>"><?php endif;?>
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?=e($ogTitle??$title??$siteName)?>"><meta name="twitter:description" content="<?=e($ogDescription??$description)?>"><?php if(!empty($ogImage)):?><meta name="twitter:image" content="<?=e(str_starts_with((string)$ogImage,'http')?$ogImage:url((string)$ogImage))?>"><?php endif;?>
<?php $sameAs=array_values(array_filter(array_map(static fn($link)=>(string)($link['url']??''),$socialLinks??[]))); $siteSchema=['@context'=>'https://schema.org','@graph'=>[['@type'=>'Organization','@id'=>url('/').'#organization','name'=>$siteName,'url'=>url('/'),'logo'=>$brandLogo?url($brandLogo):null,'sameAs'=>$sameAs],['@type'=>'WebSite','@id'=>url('/').'#website','url'=>url('/'),'name'=>$siteName,'publisher'=>['@id'=>url('/').'#organization'],'potentialAction'=>['@type'=>'SearchAction','target'=>url('/search?q={search_term_string}'),'query-input'=>'required name=search_term_string']]]]; ?><script type="application/ld+json"><?=json_encode($siteSchema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@100..700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?=e(asset('css/app.css'))?>?v=738">
<?php if($waConfig):?><link rel="stylesheet" href="<?=e(asset('css/whatsapp.css'))?>?v=631"><?php endif;?>
<?php if($trackingConsentEnabled):?><link rel="stylesheet" href="<?=e(asset('css/consent.css'))?>?v=631"><?php endif;?>
<script defer src="<?=e(asset('js/app.js'))?>?v=676"></script>
<?php if(\App\Services\CardTemplateService::active('award-card')):?><script defer src="/assets/vendor/qrcode/qrcode.min.js"></script><script defer src="/assets/js/card-templates.js?v=1"></script><?php endif;?>
<?php if($trackingConsentEnabled):?><script defer src="<?=e(asset('js/consent.js'))?>?v=631"></script><?php endif;?>
<script>window.SARA_PROTECTION=<?=json_encode($protectConfig,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;<?php if($waConfig):?>window.UWCP_DATA=<?=json_encode($waConfig,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;<?php endif;?></script>
<?php if($waConfig):?><script defer src="<?=e(asset('js/whatsapp.js'))?>?v=631"></script><?php endif;?>
<style>:root{--brand:<?=e($brandColor)?>;--accent:<?=e($brandColor)?>;--site-header-height:<?=$headerHeight?>px;--site-header-bg:<?=e($headerBg)?>;--site-header-scrolled-bg:<?=e($headerScrolledBg)?>;--site-header-text:<?=e($headerText)?>;--site-header-border:<?=e($headerBorder)?>;--site-header-hover:<?=e($headerHover)?>;--site-header-hover-bg:<?=e($headerHoverBg)?>;--ticker-bg:<?=e($tickerBg)?>;--ticker-text:<?=e($tickerText)?>;--ticker-accent:<?=e($tickerAccent)?>;--mega-bg:<?=e($megaBg)?>;--mega-text:<?=e($megaText)?>;--mega-card:<?=e($megaCard)?>;--drawer-bg:<?=e($drawerBg)?>;--drawer-text:<?=e($drawerText)?>;--site-footer-bg:<?=e($footerBg)?>;--site-footer-text:<?=e($footerTextColor)?>;--site-footer-heading:<?=e($footerHeadingColor)?>;--site-footer-accent:<?=e($footerAccent)?>;--site-footer-button:<?=e($footerButtonBg)?>;--site-footer-button-text:<?=e($footerButtonText)?>;--site-footer-border:<?=e($footerBorderColor)?>;--site-footer-pad-top:<?=$footerPaddingTop?>px;--site-footer-pad-bottom:<?=$footerPaddingBottom?>px;--site-footer-gap:<?=$footerColumnGap?>px;--site-footer-logo-height:<?=$footerLogoHeight?>px;--site-footer-social-size:<?=$footerSocialSize?>px;--site-footer-heading-size:<?=$footerHeadingSize?>px;--site-footer-text-size:<?=$footerTextSize?>px;--site-footer-radius:<?=$footerElementRadius?>px}</style>
<style>:root{<?=$typographyCss?></style>
<?=$headExtra??''?>
<?=integration_head_markup()?>
</head>
<body class="site-light-default">
<?=integration_body_start_markup()?>
<a class="skip-link" href="#main"><?=e($ui['skip'])?></a>

<?php if($tickerEnabled && $tickerItems): ?>
<div class="news-ticker news-ticker-v45" style="--ticker-duration:<?=$tickerSpeed?>s">
  <div class="news-ticker-inner-v45">
    <strong class="news-ticker-label"><?=e($tickerLabel)?><i></i></strong>
    <div class="news-ticker-window"><div class="news-ticker-track">
      <?php foreach(array_merge($tickerItems,$tickerItems) as $item): ?>
        <a href="<?=e(locale_url($locale,'article/'.$item['slug']))?>"><span>✦</span><?=e($item['title'])?></a>
      <?php endforeach; ?>
    </div></div>
  </div>
</div>
<?php endif; ?>

<header class="site-header site-header-pro site-header-v45 <?=$headerSticky?'is-sticky':'not-sticky'?> <?=$headerCompact?'has-compact-scroll':''?> <?=$headerHide?'has-hide-scroll':''?> <?=$headerShadow?'has-scroll-shadow':''?>" data-site-header data-hide-on-scroll="<?=$headerHide?'1':'0'?>">
  <div class="container header-inner header-pro-inner header-v45-inner">
    <a class="brand brand-dynamic header-brand-pro header-brand-v45" href="<?=e(locale_url($locale))?>" aria-label="<?=e($siteName)?>">
      <?php if($brandLogo): ?>
        <img class="brand-logo" src="<?=e($brandLogo)?>" alt="<?=e($siteName)?>" style="--brand-logo-height:<?=$brandLogoHeight?>px">
      <?php else: ?>
        <?php if($showMark): ?><span class="brand-mark" style="background:<?=e($markBg)?>;color:<?=e($markColor)?>;border-color:<?=e($markBg)?>"><?=e($markText)?></span><?php endif; ?>
        <span class="brand-copy"><strong><?=e($siteName)?></strong><?php if($headerTagline):?><small><?=e($tagline)?></small><?php endif;?></span>
      <?php endif; ?>
    </a>

    <nav class="main-nav main-nav-pro main-nav-v45" aria-label="navigation">
      <?php foreach($headerMenu as $item): $active=$isNavActive($item); $isMega=(($item['key']??'')==='categories' && $headerMegaEnabled && $headerCategories); ?>
        <?php if($isMega): ?>
          <div class="header-mega-wrap" data-header-mega-wrap>
            <button class="nav-pro-link header-mega-trigger <?=$active?'active':''?>" type="button" data-header-mega-toggle aria-expanded="false">
              <?php if($headerIcons):?><span class="nav-pro-icon"><?=navigation_icon_svg($item['icon']??'grid')?></span><?php endif;?><span><?=e($item['label'])?></span><span class="mega-chevron"><?=navigation_icon_svg('chevron-down')?></span>
            </button>
            <div class="header-mega-panel" data-header-mega-panel>
              <div class="header-mega-grid">
                <?php foreach($headerCategories as $cat):?><a href="<?=e(locale_url($locale,'category/'.$cat['slug']))?>"><span class="header-mega-accent"></span><strong><?=e($cat['name'])?></strong><small><?=number_format((int)$cat['article_count'])?></small></a><?php endforeach;?>
              </div>
            </div>
          </div>
        <?php else: ?>
          <a class="nav-pro-link <?=$active?'active':''?> <?=e($navClass($item))?>" style="<?=e($navStyle($item))?>" href="<?=e($item['url'])?>" <?=($item['target']==='_blank'||($item['key']??'')==='write'||str_contains((string)($item['url']??''),'writer-application'))?'target="_blank" rel="noopener"':''?>>
            <?php if($headerIcons):?><span class="nav-pro-icon"><?=navigation_icon_svg($item['icon']??'link')?></span><?php endif;?><span><?=e($item['label'])?></span>
          </a>
        <?php endif; ?>
      <?php endforeach;?>
      <?php if(!$hasMegaItem && $headerMegaEnabled && $headerCategories):?>
        <div class="header-mega-wrap" data-header-mega-wrap>
          <button class="nav-pro-link header-mega-trigger" type="button" data-header-mega-toggle aria-expanded="false"><span class="nav-pro-icon"><?=navigation_icon_svg('grid')?></span><span><?=e(tr('sections',$locale))?></span><span class="mega-chevron"><?=navigation_icon_svg('chevron-down')?></span></button>
          <div class="header-mega-panel" data-header-mega-panel><div class="header-mega-grid"><?php foreach($headerCategories as $cat):?><a href="<?=e(locale_url($locale,'category/'.$cat['slug']))?>"><span class="header-mega-accent"></span><strong><?=e($cat['name'])?></strong><small><?=number_format((int)$cat['article_count'])?></small></a><?php endforeach;?></div></div>
        </div>
      <?php endif;?>
    </nav>

    <div class="header-actions header-actions-pro header-actions-v45">
      <?php if($headerSocialLinks):?><div class="header-socials-pro header-socials-v45" aria-label="<?=e($ui['social'])?>"><?php foreach($headerSocialLinks as $social):?><a href="<?=e($social['url'])?>" target="_blank" rel="noopener" aria-label="<?=e($social['label'])?>"><?=navigation_icon_svg($social['key'])?></a><?php endforeach;?></div><?php endif;?>
      <?php if($writerCtaEnabled):?><a class="header-writer-cta header-account-cta" href="<?=e($writerCtaUrl)?>" target="_blank" rel="noopener"><span class="header-account-cta-icon"><?=navigation_icon_svg('user')?></span><span><?=e($writerCtaLabel)?></span></a><?php endif;?>
      <?php if($headerSearch):?><form class="mini-search mini-search-pro mini-search-v45" action="<?=e(locale_url($locale,'search'))?>"><input name="q" aria-label="<?=e($ui['search'])?>" placeholder="<?=e(tr('search_ph',$locale))?>"><button aria-label="<?=e($ui['search'])?>"><?=navigation_icon_svg('search')?></button></form><?php endif;?>
      <?php if($user):?><a class="community-notification-bell" href="<?=e(locale_url($locale,'community/notifications'))?>" aria-label="<?=e($communityUi['notifications'])?>"><?=navigation_icon_svg('bell')?><?php if($communityUnread>0):?><span><?=min(99,$communityUnread)?></span><?php endif;?></a><?php endif;?>
      <?php if($headerTheme):?><button class="icon-btn theme-toggle-pro" type="button" data-theme-toggle aria-label="<?=e($ui['theme'])?>" aria-pressed="false"><span class="theme-icon theme-icon-moon"><?=navigation_icon_svg('moon')?></span><span class="theme-icon theme-icon-sun"><?=navigation_icon_svg('sun')?></span></button><?php endif;?>
      <button class="menu-btn menu-btn-pro menu-btn-v45" type="button" data-menu aria-label="<?=e($ui['menu'])?>" aria-expanded="false"><?=navigation_icon_svg('menu')?></button>
    </div>
  </div>
</header>

<div class="mobile-drawer-backdrop" data-mobile-menu-backdrop hidden></div>
<aside class="mobile-menu mobile-menu-pro mobile-drawer-v45 mobile-drawer-v2" data-mobile-menu aria-hidden="true">
  <div class="mobile-drawer-head mobile-drawer-head-v2"><div class="mobile-drawer-identity"><?php if($brandLogo):?><img class="mobile-drawer-brand-logo" src="<?=e($brandLogo)?>" alt="<?=e($siteName)?>"><?php else:?><strong><?=e($siteName)?></strong><small><?=e($tagline)?></small><?php endif;?></div><button type="button" data-mobile-menu-close aria-label="<?=e($ui['close'])?>"><?=navigation_icon_svg('close')?></button></div>
  <div class="mobile-drawer-content-v2">
  <?php if($headerSearch):?><form action="<?=e(locale_url($locale,'search'))?>" class="mobile-nav-search mobile-nav-search-v45"><input name="q" placeholder="<?=e(tr('search_ph',$locale))?>"><button><?=navigation_icon_svg('search')?></button></form><?php endif;?>
  <p class="mobile-nav-title-v2"><?=e($ui['menu'])?></p>
  <nav class="mobile-menu-pro-inner mobile-nav-list-v45">
    <?php foreach($mobileMenu as $item): $active=$isNavActive($item); $isMega=(($item['key']??'')==='categories' && $headerMegaEnabled && $headerCategories); ?>
      <?php if($isMega):?>
        <div class="mobile-accordion <?=$active?'active':''?>" data-mobile-accordion><button type="button" data-mobile-accordion-toggle><span><i class="nav-pro-icon"><?=navigation_icon_svg($item['icon']??'grid')?></i><strong><?=e($item['label'])?></strong></span><?=navigation_icon_svg('chevron-down')?></button><div data-mobile-accordion-panel><?php foreach($headerCategories as $cat):?><a href="<?=e(locale_url($locale,'category/'.$cat['slug']))?>"><?=e($cat['name'])?><small><?=number_format((int)$cat['article_count'])?></small></a><?php endforeach;?></div></div>
      <?php else:?><a class="<?=$active?'active ':''?><?=e($navClass($item,true))?>" style="<?=e($navStyle($item))?>" href="<?=e($item['url'])?>" <?=$item['target']==='_blank'?'target="_blank" rel="noopener"':''?>><span class="nav-pro-icon"><?=navigation_icon_svg($item['icon']??'link')?></span><strong><?=e($item['label'])?></strong></a><?php endif;?>
    <?php endforeach;?>
    <?php if(!$hasMegaItem && $headerMegaEnabled && $headerCategories):?><div class="mobile-accordion" data-mobile-accordion><button type="button" data-mobile-accordion-toggle><span><i class="nav-pro-icon"><?=navigation_icon_svg('grid')?></i><strong><?=e(tr('sections',$locale))?></strong></span><?=navigation_icon_svg('chevron-down')?></button><div data-mobile-accordion-panel><?php foreach($headerCategories as $cat):?><a href="<?=e(locale_url($locale,'category/'.$cat['slug']))?>"><?=e($cat['name'])?><small><?=number_format((int)$cat['article_count'])?></small></a><?php endforeach;?></div></div><?php endif;?>
  </nav>
  <?php if($user):?><div class="mobile-community-account"><a href="<?=e(locale_url($locale,'community/notifications'))?>"><?=navigation_icon_svg('bell')?><span><?=e($communityUi['notifications'])?></span><?php if($communityUnread>0):?><b><?=min(99,$communityUnread)?></b><?php endif;?></a><a href="<?=e(locale_url($locale,'community/saved'))?>">🔖 <span><?=e($communityUi['saved'])?></span></a><a href="<?=e(locale_url($locale,'community/account'))?>">👤 <span><?=e($communityUi['account'])?></span></a></div><?php endif;?>
  <?php if($writerCtaEnabled && $writerCtaMobile):?><a class="mobile-writer-cta mobile-account-cta" href="<?=e($writerCtaUrl)?>" target="_blank" rel="noopener"><span><?=navigation_icon_svg('user')?></span><strong><?=e($writerCtaLabel)?></strong></a><?php endif;?>
  <?php if($headerSocialLinks):?><div class="mobile-socials-v45"><?php foreach($headerSocialLinks as $social):?><a href="<?=e($social['url'])?>" target="_blank" rel="noopener" aria-label="<?=e($social['label'])?>"><?=navigation_icon_svg($social['key'])?></a><?php endforeach;?></div><?php endif;?>
  </div>
</aside>

<?=ad_slot('below_header',$locale)?>
<?php if($msg=flash('success')):?><div class="container public-notice-wrap"><div class="notice success public-notice"><?=e($msg)?></div></div><?php endif;?><?php if($msg=flash('error')):?><div class="container public-notice-wrap"><div class="notice error public-notice"><?=e($msg)?></div></div><?php endif;?>
<main id="main"><?=$content?></main>
<?=ad_slot('footer_top',$locale)?>
<?=render_site_blocks('footer_before',$locale)?>

<?php if($footerEnabled):?>
<footer class="site-footer site-footer-pro site-footer-v45 footer-layout-<?=e($footerLayout)?>">
  <div class="footer-v45-topline"></div>
  <div class="container footer-pro-grid footer-v45-grid">
    <section class="footer-pro-brand footer-v45-brand">
      <?php if($footerShowLogo):?><a class="brand footer-brand footer-pro-logo" href="<?=e(locale_url($locale))?>"><?php if($footerBrandLogo):?><img class="brand-logo footer-logo-image" src="<?=e($footerBrandLogo)?>" alt="<?=e($siteName)?>"><?php elseif($showMark):?><span class="brand-mark" style="background:<?=e($markBg)?>;color:<?=e($markColor)?>;border-color:<?=e($markBg)?>"><?=e($markText)?></span><strong><?=e($footerTitle)?></strong><?php else:?><strong><?=e($footerTitle)?></strong><?php endif;?></a><?php else:?><h2><?=e($footerTitle)?></h2><?php endif;?>
      <p><?=e($footerDescription)?></p>
      <?php if($footerShowCta && $footerGroups['brand']):?><div class="footer-action-stack">
      <?php foreach($footerGroups['brand'] as $item):?><a class="footer-cta-btn footer-cta-v45 <?=in_array($item['key']??'',['write','behind'],true)?'footer-writer-cta':''?>" href="<?=e($item['url'])?>" <?=in_array($item['key']??'',['write','behind'],true)?'target="_blank" rel="noopener"':'target="'.e($item['target']??'_self').'" rel="noopener"'?>><?=e($item['label'])?> <span>←</span></a><?php endforeach;?></div><?php endif;?>
    <?php if($newsletterColumn==='brand') require BASE_PATH.'/app/Views/partials/footer-newsletter.php'; ?>
    </section>

    <?php if($footerMenu || ($footerShowNewsletter && $newsletterColumn==='menu')):?><section class="footer-pro-column"><h3><?=e($footerMenuTitle)?></h3><nav class="footer-nav-pro"><?php foreach($footerMenu as $item):?><a class="<?=e($navClass($item))?>" style="<?=e($navStyle($item))?>" href="<?=e($item['url'])?>" <?=$item['target']==='_blank'?'target="_blank" rel="noopener"':''?>><span><?=navigation_icon_svg($item['icon']??'link')?></span><?=e($item['label'])?></a><?php endforeach;?></nav><?php if($newsletterColumn==='menu') require BASE_PATH.'/app/Views/partials/footer-newsletter.php'; ?></section><?php endif;?>
    <?php if($footerShowCategories && $footerCategories):?><section class="footer-pro-column"><h3><?=e($footerCategoriesTitle)?></h3><nav class="footer-nav-pro"><?php foreach($footerCategories as $cat):?><a href="<?=e(locale_url($locale,'category/'.$cat['slug']))?>"><span><?=navigation_icon_svg('grid')?></span><?=e($cat['name'])?></a><?php endforeach;?></nav></section><?php endif;?>
    <section class="footer-pro-column footer-v45-connect"><h3><?=e($footerCtaTitle)?></h3><p><?=e($footerCtaText)?></p>
        <nav class="footer-nav-pro"><?php foreach($footerGroups['connect'] as $item):?><a href="<?=e($item['url'])?>" target="<?=e($item['target']??'_self')?>" rel="noopener"><?=navigation_icon_svg($item['icon']??'link')?><?=e($item['label'])?></a><?php endforeach;?></nav>
      <?php if($footerShowEmail && filter_var($contactEmail,FILTER_VALIDATE_EMAIL)):?><a class="footer-contact-email" href="mailto:<?=e($contactEmail)?>"><?=navigation_icon_svg('mail')?><span><?=e($contactEmail)?></span></a><?php endif;?>
      <?php if($newsletterColumn==='connect') require BASE_PATH.'/app/Views/partials/footer-newsletter.php'; ?>
      <?php if($footerShowLanguages && count($languages)>1):?><div class="footer-language-selector" data-footer-language><button type="button" class="footer-language-trigger" data-footer-language-toggle aria-expanded="false" aria-haspopup="listbox"><span aria-hidden="true">◎</span><img class="language-flag-img" src="<?=e(language_flag_src($locale))?>" alt=""><strong><?=e(localized_language_name($locale,$locale))?></strong><span class="footer-language-chevron" aria-hidden="true">⌄</span></button><div class="footer-language-menu" data-footer-language-menu role="listbox" aria-label="<?=e($footerLanguagesTitle)?>"><?php foreach($languages as $code=>$info):?><a role="option" aria-selected="<?=$code===$locale?'true':'false'?>" class="<?=$code===$locale?'active':''?>" href="<?=e(localized_current_url($code,true))?>"><img class="language-flag-img" src="<?=e(language_flag_src($code))?>" alt=""><span><?=e(localized_language_name($code,$locale))?></span><?php if($code===$locale):?><b aria-hidden="true">✓</b><?php endif;?></a><?php endforeach;?></div></div><?php endif;?><?php if($footerShowSocials && $socialLinks):?><div class="footer-socials" aria-label="<?=e($ui['social_links'])?>"><?php foreach($socialLinks as $social):?><a href="<?=e($social['url'])?>" target="_blank" rel="noopener" aria-label="<?=e($social['label'])?>" title="<?=e($social['label'])?>"><?=navigation_icon_svg($social['key'])?></a><?php endforeach;?></div><?php endif;?></section>
  </div>
  <div class="container footer-pro-bottom footer-v45-bottom"><span><?=e($footerCopyright!==''?$footerCopyright:'© '.date('Y').' '.$siteName.' — '.tr('copyright',$locale))?><?php if($footerDesignerEnabled && $footerDesignerName!==''):?><small class="footer-designer-credit"> · <?=e($footerDesignerPrefix)?> <?php if($footerDesignerUrl!==''):?><a href="<?=e($footerDesignerUrl)?>" <?=$footerDesignerNewTab?'target="_blank" rel="noopener"':''?>><?=e($footerDesignerName)?></a><?php else:?><?=e($footerDesignerName)?><?php endif;?></small><?php endif;?></span><div><a class="footer-privacy-link" href="<?=e($privacyFooterUrl)?>"><?=e($privacyFooterLabels[$locale]??$privacyFooterLabels['en'])?></a><?php if($trackingConsentEnabled):?><button class="privacy-settings-btn" type="button" data-consent-manage><?=e($ui['privacy'])?></button><?php endif;?><?php if($footerBackTop):?><button class="footer-to-top" type="button" data-back-to-top><?=navigation_icon_svg('arrow-up')?><span><?=e($ui['top'])?></span></button><?php endif;?></div></div>
</footer>
<?php endif;?>
<?php if(count($languages)>1): ?><div class="floating-language floating-language-fixed-left premium-language-fixed" data-language-switcher><button type="button" class="floating-language-current" data-language-toggle aria-expanded="false" aria-haspopup="listbox"><span aria-hidden="true">◎</span><img class="language-flag-img" src="<?=e(language_flag_src($locale))?>" alt=""><strong><?=e(localized_language_name($locale,$locale))?></strong><span aria-hidden="true">⌄</span></button><div class="floating-language-menu" data-language-menu role="listbox" aria-label="<?=e($footerLanguagesTitle)?>"><?php foreach($languages as $code=>$info):?><a role="option" aria-selected="<?=$code===$locale?'true':'false'?>" class="<?=$code===$locale?'active':''?>" href="<?=e(localized_current_url($code,true))?>"><img class="language-flag-img" src="<?=e(language_flag_src($code))?>" alt=""><strong><?=e(localized_language_name($code,$locale))?></strong><?php if($code===$locale):?><b aria-hidden="true">✓</b><?php endif;?></a><?php endforeach;?></div></div><?php endif; ?>
<?php if($trackingConsentEnabled && !$footerEnabled):?><button class="privacy-settings-fallback" type="button" data-consent-manage><?=e($ui['privacy'])?></button><?php endif;?>

<?php if($sitePopup): ?>
<div class="site-popup" data-site-popup data-popup-id="<?=e((string)$sitePopup['id'])?>"><div class="site-popup-card" role="dialog" aria-modal="true" aria-label="<?=e($sitePopup['title'])?>"><button type="button" class="site-popup-close" data-site-popup-close aria-label="<?=e($ui['close'])?>">×</button><span class="site-popup-kicker"><?=e($ui['notice'])?></span><h2><?=e($sitePopup['title'])?></h2><p><?=nl2br(e($sitePopup['message']))?></p><?php if(!empty($sitePopup['button_url'])):?><a class="btn btn-primary" href="<?=e($sitePopup['button_url'])?>"><?=e($sitePopup['button_label']?:$ui['more'])?></a><?php endif;?></div></div>
<?php endif; ?>
<?php if($floatingAwards): ?><div class="floating-awards" data-floating-awards data-initial-delay="15000">
<?php foreach($floatingAwards as $awardRow): $snapshot=json_decode((string)($awardRow['snapshot_json']??''),true); $award=is_array($snapshot)?array_merge($awardRow,$snapshot):$awardRow; ?>
<div class="floating-award-item" hidden data-award-id="<?=$awardRow['id']?>" data-mode="<?=e($awardRow['display_mode']??'session')?>" data-interval="<?=(int)($awardRow['display_interval']??60)?>" data-duration="<?=(int)($awardRow['display_duration']??7)?>"><button class="floating-award-close" type="button" aria-label="<?=e($ui['close'])?>" data-award-close>×</button><a href="<?=e(writer_url($award['username'],$locale))?>" aria-label="<?=e($award['writer_name'])?>"><?php require BASE_PATH.'/app/Views/components/award-card.php'; ?></a></div>
<?php endforeach; ?></div><?php endif; ?>
<?php if($adblockNotice): ?><div class="adblock-note <?=$adblockMode==='strict'?'is-strict':''?>" data-adblock-note data-adblock-mode="<?=e($adblockMode)?>" hidden><div class="adblock-note-inner"><strong><?=e($ui['adblock_title'])?></strong><span><?=e($adblockMode==='strict'?$ui['adblock_strict']:$ui['adblock_soft'])?></span><?php if($adblockMode!=='strict'):?><button type="button" data-adblock-dismiss><?=e($ui['hide'])?></button><?php endif;?></div></div><?php endif; ?>
<?=integration_body_end_markup()?>
<?php if($trackingConsentEnabled): ?>
<div class="tracking-consent" data-tracking-consent data-consent-version="<?=e($trackingConsentVersion)?>" aria-hidden="true">
  <div class="tracking-consent-card" role="dialog" aria-modal="true" aria-labelledby="tracking-consent-title">
    <h2 id="tracking-consent-title"><?=e($trackingConsentTitle)?></h2>
    <p><?=e($trackingConsentMessage)?></p>
    <div class="tracking-consent-options">
      <label><span><?=e($ui['necessary'])?></span><input type="checkbox" checked disabled></label>
      <label><span><?=e($ui['analytics'])?></span><input type="checkbox" data-consent-choice="analytics" <?=!empty($trackingConsentState['analytics'])?'checked':''?>></label>
      <label><span><?=e($ui['marketing'])?></span><input type="checkbox" data-consent-choice="marketing" <?=!empty($trackingConsentState['marketing'])?'checked':''?>></label>
      <label><span><?=e($ui['ads'])?></span><input type="checkbox" data-consent-choice="ads" <?=!empty($trackingConsentState['ads'])?'checked':''?>></label>
    </div>
    <div class="tracking-consent-actions"><button class="btn btn-primary" type="button" data-consent-accept><?=e($ui['accept'])?></button><button class="btn btn-ghost" type="button" data-consent-save><?=e($ui['save'])?></button><button class="btn btn-ghost" type="button" data-consent-reject><?=e($ui['reject'])?></button></div>
    <a class="tracking-consent-policy" href="<?=e($trackingPrivacyUrl)?>"><?=e($ui['privacy_policy'])?></a>
  </div>
</div>
<?php endif; ?>
<script>addEventListener('message',event=>{const frame=Array.from(document.querySelectorAll('.managed-code-frame')).find(item=>item.contentWindow===event.source);if(frame&&Number.isFinite(event.data?.saradeeebPageHeight))frame.style.height=Math.min(50000,Math.max(180,event.data.saradeeebPageHeight))+'px'});</script>
</body></html>
