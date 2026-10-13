<?php
$canonical=locale_url($locale,'podcast/'.$episode['slug']);
$image=$episode['og_image']?:$episode['cover_image'];
$episodeCopy=['ar'=>['episode'=>'حلقة','related'=>'حلقات مشابهة','linked'=>'المقال المرتبط','linked_text'=>'هذه الحلقة مرتبطة بمقال منشور في سراديب.','open_article'=>'فتح المقال'],'en'=>['episode'=>'Episode','related'=>'Related episodes','linked'=>'Related article','linked_text'=>'This episode is linked to a published Saradeeeb article.','open_article'=>'Open article'],'es'=>['episode'=>'Episodio','related'=>'Episodios relacionados','linked'=>'Artículo relacionado','linked_text'=>'Este episodio está vinculado a un artículo publicado en Saradeeeb.','open_article'=>'Abrir artículo'],'fr'=>['episode'=>'Épisode','related'=>'Épisodes similaires','linked'=>'Article associé','linked_text'=>'Cet épisode est lié à un article publié sur Saradeeeb.','open_article'=>'Ouvrir l’article'],'ru'=>['episode'=>'Выпуск','related'=>'Похожие выпуски','linked'=>'Связанная статья','linked_text'=>'Этот выпуск связан с опубликованной статьёй Saradeeeb.','open_article'=>'Открыть статью']][$locale]??['episode'=>'Episode','related'=>'Related episodes','linked'=>'Related article','linked_text'=>'This episode is linked to a published Saradeeeb article.','open_article'=>'Open article'];
ob_start(); ?>
<meta property="og:type" content="article">
<?php if($image):?><meta property="og:image" content="<?=e(url($image))?>"><?php endif;?>
<script type="application/ld+json"><?=json_encode(['@context'=>'https://schema.org','@type'=>'PodcastEpisode','name'=>$episode['title'],'description'=>$episode['excerpt'],'url'=>$canonical,'datePublished'=>$episode['published_at'],'associatedMedia'=>$episode['audio_url']?['@type'=>'MediaObject','contentUrl'=>$episode['audio_url']]:null,'partOfSeries'=>['@type'=>'PodcastSeries','name'=>'بودكاست سراديب','url'=>locale_url($locale,'podcast')]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
<?php $headExtra=ob_get_clean(); ?>
<article class="podcast-episode-page">
  <header class="podcast-episode-hero"><div class="container podcast-episode-head">
    <div class="podcast-episode-cover"><?=render_podcast_cover($episode,$episode['category_name']??'',$locale)?></div>
    <div><a class="podcast-back-link" href="<?=e(locale_url($locale,'podcast'))?>">Saradeeeb podcast</a><span class="podcast-episode-number"><?=!empty($episode['episode_number'])?'#'.(int)$episode['episode_number']:$episodeCopy['episode']?></span><h1><?=e($episode['title'])?></h1><?php if($episode['excerpt']):?><p><?=e($episode['excerpt'])?></p><?php endif;?><div class="podcast-card-meta"><span><?=e($episode['duration']?:'')?></span><time><?=e(!empty($episode['published_at'])?date('Y-m-d',strtotime($episode['published_at'])):'')?></time><?php if($episode['category_name']):?><a href="<?=e(locale_url($locale,'category/'.$episode['category_slug']))?>"><?=e($episode['category_name'])?></a><?php endif;?></div></div>
  </div></header>
  <div class="container podcast-episode-layout">
    <main>
      <?=render_podcast_media($episode,$locale)?>
      <?php if($episode['content']):?><div class="article-content article-content-v45 podcast-episode-content"><?=sanitize_html($episode['content'])?></div><?php endif;?>
      <?php if($episode['article_title']):?><section class="article-community-cta"><div><span><?=e($episodeCopy['linked'])?></span><h3><?=e($episode['article_title'])?></h3><p><?=e($episodeCopy['linked_text'])?></p></div><a class="btn btn-primary" href="<?=e(locale_url($locale,'article/'.$episode['article_slug']))?>"><?=e($episodeCopy['open_article'])?></a></section><?php endif;?>
    </main>
    <?php if($related):?><aside class="podcast-related"><h2><?=e($episodeCopy['related'])?></h2><?php foreach($related as $ep):?><a href="<?=e(locale_url($locale,'podcast/'.$ep['slug']))?>"><strong><?=e($ep['title'])?></strong><small><?=!empty($ep['episode_number'])?'#'.(int)$ep['episode_number']:''?> <?=e($ep['duration']??'')?></small></a><?php endforeach;?></aside><?php endif;?>
  </div>
</article>
