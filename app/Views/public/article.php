<?php
$articleUrl=locale_url($locale,'article/'.$article['slug']);
$canonical=$articleUrl;
$heat=reader_heat((int)$article['views'],$locale);
$hashtags=preg_split('/\s+/u',trim((string)($article['hashtags']??'')),-1,PREG_SPLIT_NO_EMPTY)?:[];
$summaryPoints=article_summary_points((string)($article['excerpt']??''),(string)($article['content']??''),4);
$articleCopy=[
 'ar'=>['community_label'=>'منتدى سراديب','community_title'=>'لديك رأي أو سؤال حول هذه المقالة؟','community_text'=>'انقل الحوار إلى المنتدى وشارك القرّاء والكتّاب أفكارك بدون نظام تعليقات متداخل.','community_cta'=>'ناقش هذا المقال في منتدى سراديب','share_label'=>'شارك المقال','share_kicker'=>'مشاركة','copy_link'=>'نسخ الرابط','hashtags_label'=>'هشتاغات المقال','writer_bio'=>'كاتب في سراديب.','thanks_badge'=>'بطاقة شكر','thanks_prefix'=>'شكرًا','summary_kicker'=>'الخلاصة','summary_title'=>'ملخص سريع','toc_kicker'=>'التنقل','reading_kicker'=>'القراءة'],
 'en'=>['community_label'=>'Saradeeeb Community','community_title'=>'Have a thought or question about this article?','community_text'=>'Continue the conversation in the community and share your ideas with readers and writers.','community_cta'=>'Discuss this article','share_label'=>'Share article','share_kicker'=>'Share','copy_link'=>'Copy link','hashtags_label'=>'Article hashtags','writer_bio'=>'Writer at Saradeeeb.','thanks_badge'=>'Thank-you card','thanks_prefix'=>'Thank you','summary_kicker'=>'Summary','summary_title'=>'Quick summary','toc_kicker'=>'Navigation','reading_kicker'=>'Reading'],
 'es'=>['community_label'=>'Comunidad Saradeeeb','community_title'=>'¿Tienes una opinión o pregunta sobre este artículo?','community_text'=>'Continúa la conversación en la comunidad y comparte tus ideas con lectores y autores.','community_cta'=>'Debatir este artículo','share_label'=>'Compartir artículo','share_kicker'=>'Compartir','copy_link'=>'Copiar enlace','hashtags_label'=>'Etiquetas del artículo','writer_bio'=>'Autor en Saradeeeb.','thanks_badge'=>'Tarjeta de agradecimiento','thanks_prefix'=>'Gracias','summary_kicker'=>'Resumen','summary_title'=>'Resumen rápido','toc_kicker'=>'Navegación','reading_kicker'=>'Lectura'],
 'fr'=>['community_label'=>'Communauté Saradeeeb','community_title'=>'Une remarque ou une question sur cet article ?','community_text'=>'Poursuivez la discussion dans la communauté et partagez vos idées avec les lecteurs et les auteurs.','community_cta'=>'Discuter de cet article','share_label'=>'Partager l’article','share_kicker'=>'Partager','copy_link'=>'Copier le lien','hashtags_label'=>'Mots-clés de l’article','writer_bio'=>'Auteur sur Saradeeeb.','thanks_badge'=>'Carte de remerciement','thanks_prefix'=>'Merci','summary_kicker'=>'Résumé','summary_title'=>'Résumé rapide','toc_kicker'=>'Navigation','reading_kicker'=>'Lecture'],
 'ru'=>['community_label'=>'Сообщество Saradeeeb','community_title'=>'Есть мнение или вопрос об этой статье?','community_text'=>'Продолжите разговор в сообществе и поделитесь мыслями с читателями и авторами.','community_cta'=>'Обсудить статью','share_label'=>'Поделиться статьёй','share_kicker'=>'Поделиться','copy_link'=>'Скопировать ссылку','hashtags_label'=>'Хэштеги статьи','writer_bio'=>'Автор Saradeeeb.','thanks_badge'=>'Благодарственная карточка','thanks_prefix'=>'Спасибо','summary_kicker'=>'Кратко','summary_title'=>'Быстрый обзор','toc_kicker'=>'Навигация','reading_kicker'=>'Чтение'],
];
$A=$articleCopy[$locale]??$articleCopy['en']; foreach($A as $k=>$fallback)$A[$k]=translation_text('article',$k,$locale,(string)$fallback);
$articleExtra=[
 'ar'=>['discussion'=>'المناقشة','comments'=>'التعليقات','comment_note'=>'شارك رأيك باحترام. التعليقات المزعجة أو المسيئة قد تُحجب.','reply'=>'رد','first_comment'=>'كن أول من يشارك رأيه حول هذا المقال.','name'=>'الاسم','email'=>'البريد الإلكتروني','private'=>'لن يظهر للزوار','comment'=>'تعليقك','comment_ph'=>'اكتب رأيك أو أضف شيئًا مفيدًا للنقاش...','send_comment'=>'إرسال التعليق','author_title'=>'كاتب في سراديب','published_articles'=>'مقالات منشورة','author_info'=>'معلومات الكاتب','share_article'=>'شارك المقال','copy_article'=>'نسخ رابط المقال','back_top'=>'العودة إلى الأعلى'],
 'en'=>['discussion'=>'Discussion','comments'=>'Comments','comment_note'=>'Share your thoughts respectfully. Abusive or disruptive comments may be hidden.','reply'=>'Reply','first_comment'=>'Be the first to share your thoughts about this article.','name'=>'Name','email'=>'Email','private'=>'Not shown to visitors','comment'=>'Comment','comment_ph'=>'Share a thought or add something useful to the discussion...','send_comment'=>'Post comment','author_title'=>'Writer at Saradeeeb','published_articles'=>'published articles','author_info'=>'Writer information','share_article'=>'Share article','copy_article'=>'Copy article link','back_top'=>'Back to top'],
 'es'=>['discussion'=>'Debate','comments'=>'Comentarios','comment_note'=>'Comparte tu opinión con respeto. Los comentarios abusivos pueden ocultarse.','reply'=>'Responder','first_comment'=>'Sé el primero en compartir tu opinión sobre este artículo.','name'=>'Nombre','email'=>'Correo electrónico','private'=>'No visible para los visitantes','comment'=>'Comentario','comment_ph'=>'Comparte una idea o añade algo útil al debate...','send_comment'=>'Publicar comentario','author_title'=>'Autor de Saradeeeb','published_articles'=>'artículos publicados','author_info'=>'Información del autor','share_article'=>'Compartir artículo','copy_article'=>'Copiar enlace del artículo','back_top'=>'Volver arriba'],
 'fr'=>['discussion'=>'Discussion','comments'=>'Commentaires','comment_note'=>'Partagez votre avis avec respect. Les commentaires abusifs peuvent être masqués.','reply'=>'Répondre','first_comment'=>'Soyez le premier à donner votre avis sur cet article.','name'=>'Nom','email'=>'E-mail','private'=>'Non visible par les visiteurs','comment'=>'Commentaire','comment_ph'=>'Partagez une idée ou ajoutez quelque chose d’utile à la discussion…','send_comment'=>'Publier le commentaire','author_title'=>'Auteur sur Saradeeeb','published_articles'=>'articles publiés','author_info'=>'Informations sur l’auteur','share_article'=>'Partager l’article','copy_article'=>'Copier le lien de l’article','back_top'=>'Retour en haut'],
 'ru'=>['discussion'=>'Обсуждение','comments'=>'Комментарии','comment_note'=>'Делитесь мнением уважительно. Оскорбительные комментарии могут быть скрыты.','reply'=>'Ответить','first_comment'=>'Будьте первым, кто поделится мнением об этой статье.','name'=>'Имя','email'=>'Эл. почта','private'=>'Не видно посетителям','comment'=>'Комментарий','comment_ph'=>'Поделитесь мыслью или добавьте что-то полезное к обсуждению…','send_comment'=>'Опубликовать комментарий','author_title'=>'Автор Saradeeeb','published_articles'=>'опубликованных статей','author_info'=>'Информация об авторе','share_article'=>'Поделиться статьёй','copy_article'=>'Скопировать ссылку на статью','back_top'=>'Наверх'],
][$locale]??[]; foreach($articleExtra as $k=>$fallback)$A[$k]=$fallback;
ob_start();
foreach($translations as $t): ?><link rel="alternate" hreflang="<?=e($t['locale'])?>" href="<?=e(locale_url($t['locale'],'article/'.$t['slug']))?>"><?php endforeach; ?>
<?php foreach($translations as $sourceTranslation) if($sourceTranslation['locale']==='ar'):?><link rel="alternate" hreflang="x-default" href="<?=e(locale_url('ar','article/'.$sourceTranslation['slug']))?>"><?php break; endif;?>
<?php if(!empty($article['seo_keywords'])):?><meta name="keywords" content="<?=e($article['seo_keywords'])?>"><?php endif;?>
<meta property="og:type" content="article">
<?php if(!empty($article['featured_image'])):?><meta property="og:image" content="<?=e(url($article['featured_image']))?>"><?php endif;?>
<script type="application/ld+json"><?=json_encode(['@context'=>'https://schema.org','@type'=>'Article','headline'=>$article['title'],'description'=>$article['excerpt'],'datePublished'=>$article['published_at'],'dateModified'=>$article['updated_at'],'author'=>['@type'=>'Person','name'=>$article['author_name'],'url'=>writer_url($article['username'],$locale)],'mainEntityOfPage'=>$articleUrl,'image'=>$article['featured_image']?url($article['featured_image']):null],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<?php $headExtra=ob_get_clean(); $articleBody=inject_article_ads($article['content'],$locale); ?>
<div class="reading-progress reading-progress-v45" data-reading-progress></div>
<article class="article-page article-page-v45">
  <header class="article-hero article-hero-v45">
    <div class="container article-head-v45">
      <div class="breadcrumbs article-breadcrumbs-v45"><a href="<?=e(locale_url($locale))?>"><?=e($locale==='ar'?'سراديب':'Saradeeeb')?></a><span>•</span><?php if($article['category_name']):?><a href="<?=e(locale_url($locale,'category/'.$article['category_slug']))?>"><?=e($article['category_name'])?></a><?php endif;?></div>
      <?php if($article['category_name']):?><a class="article-category-chip" href="<?=e(locale_url($locale,'category/'.$article['category_slug']))?>"><?=e($article['category_name'])?></a><?php endif;?>
      <h1><?=e($article['title'])?></h1>
      <?php if($article['excerpt']):?><p class="lead article-lead-v45"><?=e($article['excerpt'])?></p><?php endif;?>
      <div class="article-meta-row-v45">
        <a class="article-author-identity article-author-v45" href="<?=e(writer_url($article['username'],$locale))?>">
          <?php if(!empty($article['author_avatar'])):?><img src="<?=e($article['author_avatar'])?>" alt="<?=e($article['author_name'])?>"><?php else:?><span class="author-avatar-fallback"><?=e(mb_substr((string)$article['author_name'],0,1,'UTF-8'))?></span><?php endif;?>
          <span><small><?=e(tr('author',$locale))?></small><strong><?=e($article['author_name'])?> <?=writer_badges_html($article)?></strong></span>
        </a>
        <div class="article-meta-v45"><span><time><?=e(date('Y-m-d',strtotime($article['published_at']??'now')))?></time></span><span><?=navigation_icon_svg('book')?> <?=reading_time($article['content'])?> <?=e(tr('minutes',$locale))?></span><span><?=navigation_icon_svg('star')?> <?=number_format((int)$article['views'])?> <?=e(tr('views',$locale))?></span><span class="reader-heat <?=e($heat['class'])?>"><?=e($heat['icon'].' '.$heat['label'])?></span></div>
      </div>
      <div class="article-hero-actions article-toolbar" data-article-toolbar aria-label="<?=e($A['share_article'])?>">
        <button type="button" class="article-tool-button" data-article-save aria-pressed="false"><?=navigation_icon_svg('star')?> <span><?=e($locale==='ar'?'حفظ المقال':'Save article')?></span></button>
        <button type="button" class="article-tool-button" data-hero-copy data-copy-value="<?=e($articleUrl)?>"><?=navigation_icon_svg('copy')?> <span><?=e($locale==='ar'?'نسخ الرابط':'Copy link')?></span></button>
        <button type="button" class="article-tool-button article-font-button" data-font-size aria-expanded="false"><?=navigation_icon_svg('text')?> <span><?=e($locale==='ar'?'حجم الخط':'Font size')?></span></button>
        <span class="article-tool-status" data-hero-share-status aria-live="polite"></span>
        <div class="article-font-menu" data-font-menu hidden role="group" aria-label="<?=e($locale==='ar'?'حجم الخط':'Font size')?>">
          <button type="button" data-font-step="down" aria-label="<?=e($locale==='ar'?'تصغير الخط':'Decrease font size')?>">A−</button>
          <button type="button" data-font-step="reset">A</button>
          <button type="button" data-font-step="up" aria-label="<?=e($locale==='ar'?'تكبير الخط':'Increase font size')?>">A+</button>
        </div>
      </div>
      <?php if(count($translations)>1):?><div class="language-pills article-language-pills article-language-v45"><span><?=e(tr('read_in',$locale))?></span><?php foreach($translations as $t):?><a class="<?=$t['locale']===$locale?'active':''?>" href="<?=e(locale_url($t['locale'],'article/'.$t['slug']))?>"><img class="language-flag-img" src="<?=e(language_flag_src($t['locale']))?>" alt=""><span><?=e(localized_language_name($t['locale'],$locale))?></span></a><?php endforeach;?></div><?php endif;?>
    </div>
  </header>

  <?=ad_slot('article_top',$locale)?>
  <?php if($article['featured_image']):?><div class="container article-cover-wrap-v45"><figure class="article-cover article-cover-v45"><img src="<?=e($article['featured_image'])?>" alt="<?=e($article['image_alt']?:$article['title'])?>"><?php if(!empty($article['image_caption'])):?><figcaption><?=e($article['image_caption'])?></figcaption><?php endif;?></figure></div><?php endif;?>
  <?=render_site_blocks('article_after_cover',$locale)?>

  <details class="container article-toc-mobile" data-mobile-toc>
    <summary><span><?=e($A['toc_kicker'])?></span><strong><?=e(tr('toc',$locale))?></strong></summary>
    <nav data-mobile-toc-list></nav>
  </details>

  <div class="container article-layout article-layout-v45">
    <main class="article-content article-content-v45" data-article-content>
      <?php if(!empty($podcastEpisode)):?><?=render_podcast_media($podcastEpisode,$locale)?><?php endif;?>
      <?=$articleBody?>

      <?php if(setting('community_enabled','1')==='1'):?><section class="article-community-cta"><div><span><?=e($A['community_label'])?></span><h3><?=e($A['community_title'])?></h3><p><?=e($A['community_text'])?></p></div><a class="btn btn-primary" href="<?=e(locale_url($locale,'community/article/'.$article['id']))?>"><?=e($A['community_cta'])?></a></section><?php endif;?>

      <?php if(setting('article_share_enabled','1')==='1'):
        $shareTitle=$article['title']; $shareUrl=$articleUrl;
        $shareLabel=$A['share_label']; ?>
      <section class="article-share-bar article-share-v45" data-share-bar data-share-url="<?=e($shareUrl)?>" data-share-title="<?=e($shareTitle)?>">
        <div><span class="article-share-kicker"><?=e($A['share_kicker'])?></span><strong><?=e($shareLabel)?></strong></div>
        <div class="article-share-actions">
          <?php if(setting('article_native_share','1')==='1'):?><button type="button" data-native-share aria-label="<?=e($shareLabel)?>"><?=navigation_icon_svg('share')?></button><?php endif;?>
          <a target="_blank" rel="noopener" href="https://wa.me/?text=<?=rawurlencode($shareTitle.' '.$shareUrl)?>" aria-label="WhatsApp"><?=navigation_icon_svg('whatsapp')?></a>
          <a target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?=rawurlencode($shareUrl)?>" aria-label="Facebook"><?=navigation_icon_svg('facebook')?></a>
          <a target="_blank" rel="noopener" href="https://x.com/intent/post?text=<?=rawurlencode($shareTitle)?>&url=<?=rawurlencode($shareUrl)?>" aria-label="X"><?=navigation_icon_svg('x')?></a>
          <a target="_blank" rel="noopener" href="https://t.me/share/url?url=<?=rawurlencode($shareUrl)?>&text=<?=rawurlencode($shareTitle)?>" aria-label="Telegram"><?=navigation_icon_svg('telegram')?></a>
          <a target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=<?=rawurlencode($shareUrl)?>" aria-label="LinkedIn"><?=navigation_icon_svg('linkedin')?></a>
          <button type="button" data-copy-share aria-label="<?=e($A['copy_link'])?>"><?=navigation_icon_svg('copy')?></button>
        </div><span class="share-copy-status" data-share-status aria-live="polite"></span>
      </section>
      <?php endif;?>

      <?php if($hashtags):?><div class="article-hashtags article-hashtags-v45" aria-label="<?=e($A['hashtags_label'])?>"><?php foreach($hashtags as $tag):?><span><?=e($tag)?></span><?php endforeach;?></div><?php endif;?>
      <?=ad_slot('inside_article_end',$locale)?>

      <section class="article-author-card-v42 article-author-card-v45">
        <div class="article-author-card-info">
          <span class="article-author-label"><?=e(tr('author',$locale))?></span>
          <h3><a href="<?=e(writer_url($article['username'],$locale))?>"><?=e($article['author_name'])?></a> <?=writer_badges_html($article)?></h3>
          <strong class="article-author-title"><?=e($article['professional_title']?:$A['author_title'])?></strong>
          <div class="article-author-facts"><span>@<?=e($article['username'])?></span><span><?=number_format((int)($article['author_article_count']??0))?> <?=e($A['published_articles'])?></span></div>
          <p><?=e(mb_substr((string)($article['author_bio']?:$A['writer_bio']),0,260,'UTF-8'))?></p>
          <a class="btn btn-primary article-author-button" href="<?=e(writer_url($article['username'],$locale))?>"><?=e($A['author_info'])?></a>
        </div>
        <a class="article-author-card-media" href="<?=e(writer_url($article['username'],$locale))?>" aria-label="<?=e($article['author_name'])?>">
          <?php if(!empty($article['author_avatar'])):?><img src="<?=e($article['author_avatar'])?>" alt="<?=e($article['author_name'])?>" loading="lazy"><?php else:?><span class="author-avatar-fallback large"><?=e(mb_substr((string)$article['author_name'],0,1,'UTF-8'))?></span><?php endif;?>
        </a>
      </section>
      <?php if(!empty($thank)): ?><section class="writer-thank-card writer-award-card-3d award-<?=e($thank['award_type']??'featured')?>"><div class="writer-award-shine"></div><div class="writer-award-brand"><b><?=e($locale==='ar'?'سراديب':'Saradeeeb')?></b><span><?=e($locale==='ar'?'تكريم كتّاب سراديب':($locale==='ru'?'Награда авторам Saradeeeb':($locale==='es'?'Reconocimiento a autores Saradeeeb':($locale==='fr'?'Hommage aux auteurs Saradeeeb':'Saradeeeb writer honor'))))?></span></div><div class="writer-award-body"><span class="writer-thank-kicker">● <?=e($A['thanks_badge'])?></span><h3><?=e($thank['title'] ?: ($A['thanks_prefix'].' '.($article['author_name']??'')))?></h3><p><?=nl2br(e($thank['message']??''))?></p><a href="<?=e(writer_url($article['username'],$locale))?>"><?=e($locale==='ar'?'عرض ملف الكاتب':($locale==='ru'?'Открыть профиль автора':($locale==='es'?'Ver perfil del autor':($locale==='fr'?'Voir le profil de l’auteur':'View writer profile'))))?> ←</a></div></section><?php endif; ?>
    </main>

    <aside class="article-insight-sidebar-v45">
      <div class="article-insight-sticky-v45">
        <?php if($summaryPoints):?><section class="article-insight-card article-summary-card-v45"><span class="article-insight-kicker"><?=e($A['summary_kicker'])?></span><h2><?=e($A['summary_title'])?></h2><ol><?php foreach($summaryPoints as $point):?><li><?=e($point)?></li><?php endforeach;?></ol></section><?php endif;?>
        <section class="article-insight-card article-toc-card-v45"><span class="article-insight-kicker"><?=e($A['toc_kicker'])?></span><h2><?=e(tr('toc',$locale))?></h2><nav data-toc></nav></section>
        <section class="article-insight-card article-reading-card-v45"><span class="article-insight-kicker"><?=e($A['reading_kicker'])?></span><div class="article-side-metric"><strong><?=number_format((int)$article['views'])?></strong><small><?=e(tr('views',$locale))?></small></div><div class="reader-heat big <?=e($heat['class'])?>"><?=e($heat['icon'])?> <b><?=e($heat['label'])?></b></div><div class="article-side-mini"><span><?=reading_time($article['content'])?> <?=e(tr('minutes',$locale))?></span><?php if($article['category_name']):?><a href="<?=e(locale_url($locale,'category/'.$article['category_slug']))?>"><?=e($article['category_name'])?></a><?php endif;?></div></section>
        <?=ad_slot('article_sidebar',$locale)?>
      </div>
    </aside>
  </div>


  <?php if(setting('comments_enabled','1')==='1'):
    $commentRows=$comments??[]; $top=[]; $children=[];
    foreach($commentRows as $c){ if(empty($c['parent_id']))$top[]=$c; else $children[(int)$c['parent_id']][]=$c; }
    $renderComment=function($c,$depth=0) use (&$renderComment,&$children,$locale,$article){ $name=$c['user_name']?:$c['guest_name']?:(['ar'=>'قارئ','es'=>'Lector','fr'=>'Lecteur','ru'=>'Читатель'][$locale]??'Reader'); ?>
      <article class="article-comment <?= $depth?'article-comment-reply':'' ?>" id="comment-<?= (int)$c['id'] ?>">
        <div class="comment-avatar"><?=e(mb_substr((string)$name,0,1,'UTF-8'))?></div><div class="comment-main"><header><strong><?=e($name)?></strong><time><?=e(date('Y-m-d H:i',strtotime($c['created_at'])))?></time></header><p><?=nl2br(e($c['content']))?></p>
        <?php if(setting('comments_allow_replies','1')==='1'):?><button type="button" class="comment-reply-btn" onclick="document.getElementById('comment-parent').value='<?= (int)$c['id']?>';document.getElementById('comment-content').focus();"><?=e($A['reply'])?></button><?php endif;?>
        <?php if($depth<1 && !empty($children[(int)$c['id']])):?><div class="comment-children"><?php foreach($children[(int)$c['id']] as $ch)$renderComment($ch,$depth+1);?></div><?php endif;?></div>
      </article><?php };
  ?>
  <section class="article-comments-section" id="comments"><div class="container comments-container"><div class="comments-heading"><span><?=e($A['discussion'])?></span><h2><?=e($A['comments'])?> <small><?=count($commentRows)?></small></h2><p><?=e($A['comment_note'])?></p></div>
    <?php if($top):?><div class="comments-list"><?php foreach($top as $c)$renderComment($c);?></div><?php else:?><div class="comments-empty"><?=e($A['first_comment'])?></div><?php endif;?>
    <form class="comment-form" method="post" action="<?=e(locale_url($locale,'article/'.$article['slug'].'/comment'))?>"><?=csrf_field()?><input type="hidden" name="parent_id" id="comment-parent" value="0"><div class="comment-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <?php if(!auth_user()):?><div class="comment-grid"><label><?=e($A['name'])?><input required maxlength="120" name="name"></label><label><?=e($A['email'])?> <small><?=e($A['private'])?></small><input required type="email" maxlength="190" name="email"></label></div><?php endif;?>
      <label><?=e($A['comment'])?><textarea id="comment-content" required name="content" maxlength="5000" rows="5" placeholder="<?=e($A['comment_ph'])?>"></textarea></label><button class="btn btn-primary" type="submit"><?=e($A['send_comment'])?></button>
    </form>
  </div></section>
  <?php endif;?>

  <?=render_site_blocks('article_before_related',$locale)?>
  <?php if($related):?><section class="section related-v42 related-v45"><div class="container"><div class="section-head"><h2><?=e(tr('similar',$locale))?></h2></div><div class="story-grid compact"><?php foreach($related as $a):?><article class="story-card"><a class="story-image" href="<?=e(locale_url($locale,'article/'.$a['slug']))?>" <?php if($a['featured_image']):?>style="background-image:url('<?=e($a['featured_image'])?>')"<?php endif;?>></a><div class="story-body"><h3><a href="<?=e(locale_url($locale,'article/'.$a['slug']))?>"><?=e($a['title'])?></a></h3><p><?=e($a['excerpt'])?></p><small class="story-author-line">✍ <a href="<?=e(writer_url($a['username'],$locale))?>"><?=e($a['author_name'])?> <?=writer_badges_html($a)?></a></small></div></article><?php endforeach;?></div></div></section><?php endif;?>
</article>
<button type="button" class="article-back-top" data-back-top aria-label="<?=e($A['back_top'])?>">↑</button>
