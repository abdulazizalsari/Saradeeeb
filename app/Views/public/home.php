<?php
$topFive = array_slice($featured, 0, 5);
$randomStories = [];

foreach (array_slice($featured, 0, 18) as $story) {
    if (empty($story['slug']) || empty($story['title'])) continue;
    $randomStories[] = [
        'title' => $story['title'],
        'slug' => $story['slug'],
        'image' => $story['featured_image'] ?? '',
        'category' => $story['category_name'] ?? tr('uncategorized',$locale),
        'minutes' => reading_time($story['content'] ?? ''),
    ];
}

$labels = [
    'ar' => [
        'top' => 'أهم المقالات',
        'top_sub' => 'مختارات من سراديب',
        'latest' => 'أحدث المقالات',
        'latest_sub' => 'جديد سراديب',
        'categories' => 'تصفح حسب الفئة',
        'categories_sub' => 'اختر سِردابك',
        'discover' => 'واصل الاستكشاف',
        'discover_sub' => 'من عمق سراديب',
        'random' => 'قصة مقترحة لك',
        'read' => 'اقرأ القصة',
        'write_title' => 'لديك قصة لا تُنسى؟',
        'write_text' => 'اكتب قصتك وأرسلها إلى سراديب. بعد المراجعة يمكن أن تصبح جزءًا من أرشيف الحكايات.',
        'write_cta' => 'أرسل قصتك',
        'popular' => 'الأكثر قراءة',
    ],
    'en' => [
        'top' => 'Top stories','top_sub' => 'Selected from Saradeeeb','latest' => 'Latest stories','latest_sub' => 'New in Saradeeeb','categories' => 'Browse categories','categories_sub' => 'Choose your passage','discover' => 'Keep exploring','discover_sub' => 'From the depths','random' => 'Suggested for you','read' => 'Read story','write_title' => 'Have a story to tell?','write_text' => 'Send your story to Saradeeeb for editorial review.','write_cta' => 'Send your story','popular' => 'Most read',
    ],
    'es' => [
        'top' => 'Artículos destacados','top_sub' => 'Selecciones de Saradeeeb','latest' => 'Últimos artículos','latest_sub' => 'Nuevo en Saradeeeb','categories' => 'Explorar categorías','categories_sub' => 'Elige tu ruta','discover' => 'Sigue explorando','discover_sub' => 'Desde las profundidades','random' => 'Sugerido para ti','read' => 'Leer historia','write_title' => '¿Tienes una historia?','write_text' => 'Envía tu historia a Saradeeeb para revisión editorial.','write_cta' => 'Enviar historia','popular' => 'Más leídos',
    ],
    'fr' => [
        'top' => 'Articles à la une','top_sub' => 'Sélection de Saradeeeb','latest' => 'Derniers articles','latest_sub' => 'Nouveau sur Saradeeeb','categories' => 'Parcourir les catégories','categories_sub' => 'Choisissez votre passage','discover' => 'Poursuivre l’exploration','discover_sub' => 'Depuis les profondeurs','random' => 'Suggestion pour vous','read' => 'Lire l’histoire','write_title' => 'Vous avez une histoire ?','write_text' => 'Envoyez votre histoire à Saradeeeb pour une révision éditoriale.','write_cta' => 'Envoyer l’histoire','popular' => 'Les plus lus',
    ],
    'ru' => [
        'top' => 'Главные истории','top_sub' => 'Выбор Saradeeeb','latest' => 'Последние публикации','latest_sub' => 'Новое в Saradeeeb','categories' => 'Обзор категорий','categories_sub' => 'Выберите свой путь','discover' => 'Продолжить исследование','discover_sub' => 'Из глубин','random' => 'Предложение для вас','read' => 'Читать историю','write_title' => 'Есть история?','write_text' => 'Отправьте историю в Saradeeeb на редакционную проверку.','write_cta' => 'Отправить историю','popular' => 'Самые читаемые',
    ],
];
$L = $labels[$locale] ?? $labels['en'];
foreach($L as $k=>$fallback) $L[$k]=translation_text('home',$k,$locale,(string)$fallback);
$L['close']=translation_text('home','close',$locale,($locale==='ar'?'إغلاق':'Close'));
$sectionLabels=[
 'ar'=>['title'=>'أقسام سراديب','podcast'=>'بودكاست سراديب','podcast_desc'=>'حلقات صوتية مختارة من روح الموقع.','khawatir'=>'خواطر','khawatir_desc'=>'نصوص قصيرة وتأملات وحِكم.','community'=>'منتدى سراديب','community_desc'=>'نقاشات واهتمامات ومساحات للمشاركة.','articles'=>'المقالات','articles_desc'=>'قصص ومعرفة وتحليلات وملفات مختارة.'],
 'en'=>['title'=>'Saradeeeb sections','podcast'=>'Saradeeeb podcast','podcast_desc'=>'Selected audio episodes from the spirit of the site.','khawatir'=>'Reflections','khawatir_desc'=>'Short texts, thoughts and wisdom.','community'=>'Saradeeeb community','community_desc'=>'Discussions, interests and spaces to participate.','articles'=>'Articles','articles_desc'=>'Stories, knowledge, analysis and selected files.'],
 'es'=>['title'=>'Secciones de Saradeeeb','podcast'=>'Podcast Saradeeeb','podcast_desc'=>'Episodios de audio seleccionados del espíritu del sitio.','khawatir'=>'Reflexiones','khawatir_desc'=>'Textos breves, pensamientos y sabiduría.','community'=>'Comunidad Saradeeeb','community_desc'=>'Debates, intereses y espacios para participar.','articles'=>'Artículos','articles_desc'=>'Historias, conocimiento, análisis y expedientes seleccionados.'],
 'fr'=>['title'=>'Rubriques Saradeeeb','podcast'=>'Podcast Saradeeeb','podcast_desc'=>'Des épisodes audio choisis dans l’esprit du site.','khawatir'=>'Réflexions','khawatir_desc'=>'Textes courts, pensées et sagesses.','community'=>'Communauté Saradeeeb','community_desc'=>'Discussions, centres d’intérêt et espaces de participation.','articles'=>'Articles','articles_desc'=>'Histoires, connaissances, analyses et dossiers sélectionnés.'],
 'ru'=>['title'=>'Разделы Saradeeeb','podcast'=>'Подкаст Saradeeeb','podcast_desc'=>'Избранные аудиоэпизоды в духе сайта.','khawatir'=>'Размышления','khawatir_desc'=>'Короткие тексты, мысли и мудрость.','community'=>'Сообщество Saradeeeb','community_desc'=>'Обсуждения, интересы и пространство для участия.','articles'=>'Статьи','articles_desc'=>'Истории, знания, анализ и избранные материалы.'],
];
$S=$sectionLabels[$locale]??$sectionLabels['en'];
foreach($S as $key=>$fallback) $S[$key]=translation_text('home','sections_'.$key,$locale,$fallback);
$uncategorizedLabel=tr('uncategorized',$locale);
$categoryAllPrefix=[
 'ar'=>'عرض كل مقالات ','en'=>'View all ','es'=>'Ver todos los artículos de ','fr'=>'Voir tous les articles de ','ru'=>'Все статьи: ',
][$locale]??'View all ';
$categoryAllLabel=$locale==='ar'?'عرض كل المقالات':tr('view_all',$locale);
?>

<?=render_site_blocks('home_before_featured',$locale)?>
<!-- القسم الأول: أهم المقالات - 5 بطاقات -->
<?php if ($topFive): ?>
<section class="home-feature-section home-section-one">
  <div class="container home-wide-container">
    <div class="home-feature-head">
      <div>
        <span class="eyebrow"><?= e($L['top_sub']) ?></span>
        <h1><?= e($L['top']) ?></h1>
      </div>
      <a class="home-feature-more" href="<?= e(locale_url($locale,'search')) ?>"><?= e(tr('view_all', $locale)) ?> ←</a>
    </div>

    <div class="feature-five-grid home-five-grid">
      <?php if ($topFive): ?>
        <?php foreach ($topFive as $i => $a): ?>
          <?php
          $positionClass = match ($i) {
              0 => 'is-main',
              1 => 'is-small small-one',
              2 => 'is-small small-two',
              3 => 'is-small small-three',
              default => 'is-small small-four',
          };
          ?>
          <a class="feature-card-five <?= e($positionClass) ?>"
             href="<?= e(locale_url($locale,'article/'.$a['slug'])) ?>"
             <?php if (!empty($a['featured_image'])): ?>style="--card-image:url('<?= e($a['featured_image']) ?>')"<?php endif; ?>>
            <div class="feature-card-shade"></div>
            <div class="feature-card-content">
              <span class="feature-category"><?= e($a['category_name'] ?? tr('uncategorized',$locale)) ?></span>
              <h2><?= e($a['title']) ?></h2>
              <?php if ($i === 0 && !empty($a['excerpt'])): ?>
                <p><?= e($a['excerpt']) ?></p>
              <?php endif; ?>
              <div class="feature-meta">
                <span><?= e($a['author_name'] ?? '') ?></span><span>•</span>
                <span><?= e((string) reading_time($a['content'] ?? '')) ?> <?= e(tr('minutes', $locale)) ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($storyOfNight)):
$nightCopy=[
 'ar'=>['kicker'=>'اختيار سراديب اليومي','title'=>'قصة الليلة','read'=>'اقرأ قصة الليلة','minutes'=>'دقائق قراءة'],
 'en'=>['kicker'=>'Saradeeeb daily selection','title'=>'Story of the Night','read'=>'Read tonight’s story','minutes'=>'min read'],
 'es'=>['kicker'=>'Selección diaria de Saradeeeb','title'=>'Historia de la noche','read'=>'Leer la historia de esta noche','minutes'=>'min de lectura'],
 'fr'=>['kicker'=>'Sélection quotidienne de Saradeeeb','title'=>'Histoire de la nuit','read'=>'Lire l’histoire de ce soir','minutes'=>'min de lecture'],
 'ru'=>['kicker'=>'Ежедневный выбор Saradeeeb','title'=>'История ночи','read'=>'Читать историю этой ночи','minutes'=>'мин чтения'],
][$locale]??['kicker'=>'Saradeeeb daily selection','title'=>'Story of the Night','read'=>'Read tonight’s story','minutes'=>'min read'];
?>
<section class="story-night-section" aria-labelledby="story-night-title">
 <div class="container home-wide-container">
  <article class="story-night-card">
   <a class="story-night-media <?=empty($storyOfNight['featured_image'])?'is-placeholder':''?>" href="<?=e(locale_url($locale,'article/'.$storyOfNight['slug']))?>">
    <?php if(!empty($storyOfNight['featured_image'])):?><img src="<?=e($storyOfNight['featured_image'])?>" alt="<?=e($storyOfNight['image_alt']?:$storyOfNight['title'])?>" loading="lazy" decoding="async"><?php else:?><span aria-hidden="true">✦</span><?php endif;?>
   </a>
   <div class="story-night-copy">
    <span class="story-night-kicker"><?=e($nightCopy['kicker'])?></span>
    <h2 id="story-night-title"><?=e($nightCopy['title'])?></h2>
    <h3><a href="<?=e(locale_url($locale,'article/'.$storyOfNight['slug']))?>"><?=e($storyOfNight['title'])?></a></h3>
    <?php if(trim((string)($storyOfNight['excerpt']??''))!==''):?><p><?=e($storyOfNight['excerpt'])?></p><?php endif;?>
    <div class="story-night-meta"><span><?=e($storyOfNight['author_name']??'')?></span><?php if(!empty($storyOfNight['category_name'])):?><span><?=e($storyOfNight['category_name'])?></span><?php endif;?><span><?=e((string)reading_time($storyOfNight['content']??''))?> <?=e($nightCopy['minutes'])?></span></div>
    <a class="story-night-cta" href="<?=e(locale_url($locale,'article/'.$storyOfNight['slug']))?>"><?=e($nightCopy['read'])?> <span aria-hidden="true">←</span></a>
   </div>
  </article>
 </div>
</section>
<?php endif; ?>

<?= ad_slot('home_after_featured', $locale) ?>
<?=render_site_blocks('home_after_featured',$locale)?>
<?= ad_slot('home_top', $locale) ?>

<?=render_site_blocks('home_before_latest',$locale)?>
<!-- القسم الثاني: أحدث المقالات -->
<section class="home-latest-v3 section-v3 latest-browser" data-category-rotation data-interval="10000" data-category-all-prefix="<?=e($categoryAllPrefix)?>" data-category-all-label="<?=e($categoryAllLabel)?>">
  <div class="container home-wide-container">
    <div class="latest-browser-layout">
      <aside class="latest-category-panel" aria-label="<?=e($L['categories'])?>">
        <h3><?=e($L['categories'])?></h3>
        <div class="latest-category-list" role="group" aria-label="<?=e($L['categories'])?>">
          <button type="button" class="latest-category-button" data-category-reset data-all-url="<?=e(locale_url($locale,'search'))?>" aria-pressed="true"><span><?=e($locale==='ar'?'كل المقالات':tr('view_all',$locale))?></span><small><?=$totalPublished?></small></button>
          <?php foreach($categories as $category):?>
            <button type="button" class="latest-category-button" data-category-filter="<?=e((string)$category['id'])?>" data-home-category-name="<?=e($category['name'])?>" data-category-url="<?=e(locale_url($locale,'category/'.$category['slug']))?>" aria-pressed="false"><span><?=e($category['name'])?></span><small><?=e((string)$category['article_count'])?></small></button>
          <?php endforeach;?>
          <?php if($uncategorizedCount>0):?><button type="button" class="latest-category-button" data-category-filter="__uncategorized__" data-home-category-name="<?=e($uncategorizedLabel)?>" data-category-url="<?=e(locale_url($locale,'search'))?>" aria-pressed="false"><span><?=e($uncategorizedLabel)?></span><small><?=$uncategorizedCount?></small></button><?php endif;?>
        </div>
      </aside>
      <div class="latest-articles-panel" data-category-articles>
        <div class="latest-browser-head">
          <div><span class="eyebrow"><?=e($L['latest_sub'])?></span><h2><?=e($L['latest'])?></h2></div>
          <a href="<?=e(locale_url($locale,'search'))?>" data-category-all><?=e(tr('view_all',$locale))?> ←</a>
        </div>
        <div class="latest-four-grid" aria-live="polite">
          <?php foreach($browserArticles as $i=>$a):?>
            <article class="latest-article-card <?=$i<4?'is-visible':''?>" data-category-card="<?=e(!empty($a['category_id'])?(string)$a['category_id']:'__uncategorized__')?>" <?=$i<4?'':'hidden'?>">
              <a class="latest-article-media <?=empty($a['featured_image'])?'is-placeholder':''?>" href="<?=e(locale_url($locale,'article/'.$a['slug']))?>"><?php if(!empty($a['featured_image'])):?><img src="<?=e($a['featured_image'])?>" alt="" loading="lazy" decoding="async"><?php else:?><span><?=e(setting('brand_mark_text','س')?:'س')?></span><?php endif;?></a>
              <div class="latest-article-copy">
                <span class="v3-chip"><?=e($a['category_name']??tr('uncategorized',$locale))?></span>
                <h3><a href="<?=e(locale_url($locale,'article/'.$a['slug']))?>"><?=e($a['title'])?></a></h3>
                <div class="latest-article-meta"><a href="<?=e(writer_url($a['username'],$locale))?>"><?=e($a['author_name']??'')?> <?=writer_badges_html($a)?></a><span>•</span><span><?=e((string)reading_time($a['content']??''))?> <?=e(tr('minutes',$locale))?></span></div>
              </div>
            </article>
          <?php endforeach;?>
        </div>
      </div>
    </div>
  </div>
</section>

<?=render_site_blocks('home_after_latest',$locale)?>
<?= ad_slot('home_between_sections', $locale) ?>

<?=render_site_blocks('home_before_categories',$locale)?>
<!-- القسم الثالث: التصنيفات + شبكة مقالات متبدلة -->
<section class="home-categories-v3 section-v3 section-v3-soft" data-category-rotation data-interval="10000" data-category-all-prefix="<?=e($categoryAllPrefix)?>" data-category-all-label="<?=e($categoryAllLabel)?>">
  <div class="container home-wide-container">
    <div class="category-showcase-grid">
      <aside class="category-showcase-sidebar">
        <div class="category-showcase-title">
          <span class="category-showcase-star"><?=navigation_icon_svg('grid')?></span>
          <h2><?=e($S['title'])?></h2>
        </div>
        <div class="category-showcase-line"></div>
        <div class="category-showcase-list saradeeeb-sections-list">
          <?php
          $sectionItems=[
            ['podcast',$S['podcast'],$S['podcast_desc'],'podcast'],
            ['khawatir',$S['khawatir'],$S['khawatir_desc'],'quote'],
            ['community',$S['community'],$S['community_desc'],'community'],
            ['search',$S['articles'],$S['articles_desc'],'book'],
          ];
          // These two editorial products are intentionally Arabic-only.
          if($locale!=='ar') $sectionItems=array_values(array_filter($sectionItems,static fn(array $item):bool=>!in_array($item[0],['podcast','khawatir'],true)));
          foreach($sectionItems as [$path,$name,$summary,$icon]):?>
          <a href="<?=e(locale_url($locale,$path))?>" class="saradeeeb-section-card"><span class="saradeeeb-section-icon"><?=navigation_icon_svg($icon)?></span><span class="saradeeeb-section-copy"><strong><?=e($name)?></strong><small><?=e($summary)?></small></span><span class="saradeeeb-section-arrow" aria-hidden="true">←</span></a>
          <?php endforeach;?>
        </div>
      </aside>

      <div class="category-articles-area" data-category-articles>
       <div class="category-showcase-featured" aria-live="polite">
        <?php foreach ($featured as $i=>$a): ?>
          <article class="category-feature-card category-rotation-card <?=$i<4?'is-visible':''?>" data-category-card="<?=e(!empty($a['category_id'])?(string)$a['category_id']:'__uncategorized__')?>" <?=$i<4?'':'hidden'?>">
            <a class="category-feature-media <?=empty($a['featured_image'])?'is-placeholder':''?>" href="<?= e(locale_url($locale,'article/'.$a['slug'])) ?>">
              <?php if(!empty($a['featured_image'])):?><img src="<?=e($a['featured_image'])?>" alt="" loading="lazy" decoding="async"><?php endif;?><span class="category-feature-chip"><?= e($a['category_name'] ?? tr('uncategorized',$locale)) ?></span><?php if(empty($a['featured_image'])):?><span class="category-media-mark"><?=e(setting('brand_mark_text','س')?:'س')?></span><?php endif;?>
            </a>
            <div class="category-feature-copy"><h3><a href="<?= e(locale_url($locale,'article/'.$a['slug'])) ?>"><?= e($a['title']) ?></a></h3><div class="category-feature-bottom"><div class="category-feature-meta"><a href="<?=e(writer_url($a['username'],$locale))?>"><?=e($a['author_name'])?> <?=writer_badges_html($a)?></a><span>•</span><span><?=e((string)reading_time($a['content']??''))?> <?=e(tr('minutes',$locale))?></span></div><a class="category-card-open" href="<?=e(locale_url($locale,'article/'.$a['slug']))?>" aria-label="<?=e($a['title'])?>">←</a></div></div>
          </article>
        <?php endforeach; ?>
       </div>
       <div class="category-rotation-footer"><span><?=e($L['latest'])?></span><a href="<?=e(locale_url($locale,'search'))?>" data-category-all><?=e(tr('view_all',$locale))?> ←</a></div>
      </div>
    </div>
  </div>
</section>

<?=render_site_blocks('home_after_categories',$locale)?>
<!-- القسم الرابع: أهم المقالات — 12 مقالة بنفس المرجع -->
<section class="important-articles-exact section-v3">
  <div class="container home-wide-container">
    <div class="important-exact-shell">
      <div class="important-exact-heading">
        <h2><?= e($L['top']) ?></h2>
        <span class="important-exact-rule" aria-hidden="true"></span>
      </div>

      <div class="important-exact-grid">
        <?php foreach (array_slice($featured, 0, 12) as $i => $a): ?>
          <a class="important-exact-card" href="<?= e(locale_url($locale,'article/'.$a['slug'])) ?>">
            <span class="important-exact-rank"><?= e((string)($i + 1)) ?></span>

            <div class="important-exact-copy">
              <h3><?= e($a['title']) ?></h3>
              <div class="important-exact-meta">
                <span><?= e($a['author_name'] ?? '') ?></span>
                <span class="important-exact-dot">•</span>
                <span><?= e((string) reading_time($a['content'] ?? '')) ?> <?= e(tr('minutes', $locale)) ?></span>
                <?php if (!empty($a['category_name'])): ?>
                  <span class="important-exact-dot">•</span>
                  <span><?= e($a['category_name']) ?></span>
                <?php endif; ?>
              </div>
            </div>

            <span class="important-exact-media" <?php if (!empty($a['featured_image'])): ?>style="background-image:url('<?= e($a['featured_image']) ?>')"<?php endif; ?> aria-hidden="true"></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php if ($randomStories): ?>
<script type="application/json" id="random-story-data"><?= json_encode($randomStories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<aside class="random-story-card" data-random-story data-locale="<?= e($locale) ?>" data-default-locale="<?=e(default_locale())?>" aria-live="polite">
  <button class="random-story-close" type="button" data-random-story-close aria-label="<?=e($L['close'])?>">×</button>
  <a class="random-story-link" data-random-story-link href="#">
    <div class="random-story-image" data-random-story-image></div>
    <div class="random-story-copy">
      <span class="random-story-kicker"><?= e($L['random']) ?></span>
      <strong data-random-story-title></strong>
      <small><span data-random-story-category></span> · <span data-random-story-minutes></span> <?= e(tr('minutes', $locale)) ?></small>
      <em><?= e($L['read']) ?> ←</em>
    </div>
  </a>
</aside>
<?php endif; ?>
