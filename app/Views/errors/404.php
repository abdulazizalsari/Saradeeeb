<?php
$locale=$locale??default_locale();
$copy=[
 'ar'=>[
   'eyebrow'=>'هذا الممر لا يقود إلى مكان',
   'title'=>'يبدو أنك وصلت إلى صفحة اختفت في الظلام',
   'text'=>'ربما تغيّر الرابط أو نُقلت القصة. استخدم البحث أو عُد إلى سراديب واستكشف حكاية أخرى.',
   'search'=>'بحث',
   'placeholder'=>'ابحث عن قصة أو موضوع...',
   'home'=>'العودة إلى الرئيسية',
   'send'=>'أرسل قصتك',
   'discover'=>'استكشف:',
 ],
 'en'=>[
   'eyebrow'=>'This passage leads nowhere',
   'title'=>'It looks like you reached a page that vanished into the dark',
   'text'=>'The link may have changed or the story may have moved. Search Saradeeeb or return home and discover another tale.',
   'search'=>'Search',
   'placeholder'=>'Search for a story or topic...',
   'home'=>'Back to home',
   'send'=>'Send your story',
   'discover'=>'Explore:',
 ],
 'es'=>[
   'eyebrow'=>'Este pasadizo no lleva a ningún lugar',
   'title'=>'Parece que llegaste a una página que desapareció en la oscuridad',
   'text'=>'Quizá el enlace cambió o la historia fue trasladada. Busca en Saradeeeb o vuelve al inicio y descubre otra historia.',
   'search'=>'Buscar',
   'placeholder'=>'Busca una historia o un tema...',
   'home'=>'Volver al inicio',
   'send'=>'Envía tu historia',
   'discover'=>'Explora:',
 ],
 'fr'=>[
   'eyebrow'=>'Ce passage ne mène nulle part',
   'title'=>'Il semble que vous ayez atteint une page disparue dans l’obscurité',
   'text'=>'Le lien a peut-être changé ou l’histoire a été déplacée. Recherchez sur Saradeeeb ou revenez à l’accueil pour découvrir une autre histoire.',
   'search'=>'Rechercher',
   'placeholder'=>'Rechercher une histoire ou un sujet...',
   'home'=>'Retour à l’accueil',
   'send'=>'Envoyer votre histoire',
   'discover'=>'Explorer :',
 ],
];
$c=$copy[$locale]??$copy['en'];
foreach($c as $k=>$fallback) $c[$k]=translation_text('404',$k,$locale,$fallback);
$categories=[];
try{
    $categories=\App\Core\Database::all(
        "SELECT ct.name,ct.slug,COUNT(a.id) article_count
         FROM categories c
         JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=?
         LEFT JOIN articles a ON a.category_id=c.id AND a.status='published'
         GROUP BY c.id,ct.name,ct.slug,c.sort_order
         ORDER BY COUNT(a.id) DESC,c.sort_order,c.id
         LIMIT 5",
        [$locale]
    );
}catch(Throwable){}
?>
<section class="error-page error-page-saradeeeb" aria-labelledby="error-title">
  <div class="container">
    <div class="error-identity-card">
      <div class="error-code" aria-hidden="true">
        <span class="error-code-four">4</span>
        <span class="error-code-zero"><i></i></span>
        <span class="error-code-four">4</span>
      </div>

      <p class="error-eyebrow"><?=e($c['eyebrow'])?></p>
      <h1 id="error-title"><?=e($c['title'])?></h1>
      <p class="error-description"><?=e($c['text'])?></p>

      <form class="error-search error-search-identity" method="get" action="<?=e(locale_url($locale,'search'))?>">
        <label class="sr-only" for="error-q"><?=e($c['search'])?></label>
        <input id="error-q" name="q" required minlength="2" placeholder="<?=e($c['placeholder'])?>" autocomplete="off">
        <button type="submit"><?=e($c['search'])?></button>
      </form>

      <div class="error-actions-identity">
        <a class="error-btn error-btn-primary" href="<?=e(locale_url($locale))?>"><?=e($c['home'])?></a>
        <a class="error-btn error-btn-secondary" href="<?=e(locale_url($locale,'send-story'))?>"><?=e($c['send'])?></a>
      </div>

      <?php if($categories): ?>
      <nav class="error-discover" aria-label="<?=e($c['discover'])?>">
        <span><?=e($c['discover'])?></span>
        <?php foreach($categories as $category): ?>
          <a href="<?=e(locale_url($locale,'category/'.rawurlencode((string)$category['slug'])))?>"><?=e($category['name'])?></a>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>
    </div>
  </div>
</section>
