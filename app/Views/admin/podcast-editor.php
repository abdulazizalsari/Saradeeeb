<?php
$ep=$episode??[];
$isEdit=!empty($ep['id']);
$locales=enabled_locales();
$byLocale=$byLocale??[];
$publishedValue=!empty($ep['published_at'])?str_replace(' ','T',substr((string)$ep['published_at'],0,16)):'';
$coverCategory=''; foreach($categories as $categoryOption){if((int)$categoryOption['id']===(int)($ep['category_id']??0)){$coverCategory=(string)$categoryOption['name'];break;}}
?>
<div class="dash-actions"><a class="btn btn-ghost" href="/admin/podcast">العودة إلى البودكاست</a><?php if($isEdit && ($ep['status']??'')==='published'):?><a class="btn btn-primary" href="/podcast/<?=e($ep['slug'])?>" target="_blank" rel="noopener">عرض الحلقة</a><?php endif;?></div>
<form class="panel article-editor-panel podcast-editor" method="post" action="/admin/podcast/save" enctype="multipart/form-data">
  <?=csrf_field()?><input type="hidden" name="id" value="<?=e($ep['id']??0)?>">
  <div class="panel-head"><div><span class="panel-kicker">بودكاست سراديب</span><h2><?=$isEdit?'تعديل الحلقة':'حلقة جديدة'?></h2></div><button class="btn btn-primary" type="submit">حفظ الحلقة</button></div>
  <div class="editor-grid">
    <section class="editor-main-fields">
      <div class="lang-tabs" role="tablist"><?php $first=true;foreach($locales as $locale=>$language):?><button type="button" class="lang-tab <?=$first?'active':''?>" data-tab="podcast-<?=$locale?>"><?=e(language_flag($locale).' '.language_name($locale))?> <span class="translation-state <?=isset($byLocale[$locale])?'has-translation':''?>"><?=isset($byLocale[$locale])?'●':'○'?></span></button><?php $first=false;endforeach;?></div>
      <?php $first=true;foreach($locales as $locale=>$language):$translation=$byLocale[$locale]??[];$source=$locale===default_locale()?$ep:[];$titleValue=$translation['title']??($source['title']??'');$slugValue=$translation['slug']??($source['slug']??'');$excerptValue=$translation['excerpt']??($source['excerpt']??'');$contentValue=$translation['content']??($source['content']??'');$seoTitleValue=$translation['seo_title']??($source['seo_title']??'');$seoDescriptionValue=$translation['seo_description']??($source['seo_description']??''); ?>
      <section class="lang-panel <?=$first?'active':''?>" data-panel="podcast-<?=$locale?>">
        <div class="two-cols"><label>عنوان الحلقة<input name="title[<?=$locale?>]" maxlength="220" value="<?=e($titleValue)?>"></label><label>Slug<input name="slug[<?=$locale?>]" dir="ltr" value="<?=e($slugValue)?>" placeholder="episode-slug"></label></div>
        <label>وصف مختصر<textarea name="excerpt[<?=$locale?>]" rows="4"><?=e($excerptValue)?></textarea></label>
        <label>وصف كامل للحلقة<textarea name="content[<?=$locale?>]" rows="10"><?=e($contentValue)?></textarea></label>
        <div class="form-grid two"><label>Meta Title<input name="seo_title[<?=$locale?>]" maxlength="255" value="<?=e($seoTitleValue)?>"></label><label>Meta Description<input name="seo_description[<?=$locale?>]" maxlength="320" value="<?=e($seoDescriptionValue)?>"></label></div>
        <label>حالة ترجمة <?=e(language_name($locale))?><select name="translation_status[<?=$locale?>]"><option value="draft" <?=($translation['status']??'draft')==='draft'?'selected':''?>>مسودة</option><option value="review" <?=($translation['status']??'')==='review'?'selected':''?>>تحتاج مراجعة</option><option value="published" <?=($translation['status']??'')==='published'?'selected':''?>>منشورة</option></select></label>
      </section>
      <?php $first=false;endforeach;?>
      <fieldset class="podcast-source-box" data-podcast-source-box><legend>مصدر الحلقة</legend><div class="podcast-source-types"><?php foreach(['auto'=>'كشف تلقائي','direct'=>'رابط ملف صوت مباشر','youtube'=>'YouTube','rss'=>'خدمة بودكاست / RSS','cdn'=>'استضافة خارجية / CDN'] as $key=>$label):?><label><input type="radio" name="source_type" value="<?=$key?>" <?=($ep['source_type']??'auto')===$key?'checked':''?>> <span><?=$label?></span></label><?php endforeach;?></div><label>رابط المصدر<input name="audio_url" data-podcast-source-url dir="ltr" value="<?=e($ep['audio_url']??'')?>" placeholder="https://..."></label><div class="podcast-source-actions"><button class="btn btn-ghost" type="button" data-podcast-test-source>اختبار الرابط</button><strong data-podcast-source-status aria-live="polite"></strong></div><div class="podcast-source-preview" data-podcast-source-preview hidden></div></fieldset>
      <details class="podcast-upload-secondary"><summary>رفع ملف صوت صغير عند الحاجة</summary><label>الملف المحلي<input type="file" name="audio_file" accept="audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/mp4"></label><small class="field-hint">الروابط الخارجية هي الخيار الأساسي. الرفع المحلي ثانوي ومحدود بأنواع الصوت الآمنة.</small></details>
    </section>
    <aside class="editor-side-fields">
      <label>الحالة<select name="status"><option value="draft" <?=($ep['status']??'draft')==='draft'?'selected':''?>>مسودة</option><option value="published" <?=($ep['status']??'')==='published'?'selected':''?>>منشورة</option></select></label>
      <label>تاريخ النشر<input type="datetime-local" name="published_at" value="<?=e($publishedValue)?>"></label>
      <label>رقم الحلقة<input type="number" min="0" name="episode_number" value="<?=e($ep['episode_number']??'')?>" readonly><small class="field-hint">يُولّد تلقائيًا عند الحفظ.</small></label>
      <label>مدة الحلقة<input name="duration" maxlength="40" value="<?=e($ep['duration']??'')?>" placeholder="42:15"></label>
      <input type="hidden" name="audio_source" value="<?=e($ep['audio_source']??'external')?>">
      <label>التصنيف<select name="category_id"><option value="0">بدون تصنيف</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=((int)($ep['category_id']??0)===(int)$c['id'])?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></label>
      <label>ربط بمقال<select name="article_id"><option value="0">بدون مقال مرتبط</option><?php foreach($articles as $a):?><option value="<?=$a['id']?>" <?=((int)($ep['article_id']??0)===(int)$a['id'])?'selected':''?>>#<?=$a['id']?> - <?=e($a['title']?:'بدون عنوان')?></option><?php endforeach;?></select></label>
      <input type="hidden" name="cover_image" value="<?=e($ep['cover_image']??'')?>"><input type="hidden" name="og_image" value="<?=e($ep['og_image']??'')?>">
      <?php if($mediaLibrary):?><div class="media-pick-strip"><?php foreach(array_slice($mediaLibrary,0,12) as $m):?><button type="button" data-media-path="<?=e($m['path'])?>" onclick="navigator.clipboard&&navigator.clipboard.writeText(this.dataset.mediaPath)" title="نسخ مسار الصورة"><img src="<?=e($m['path'])?>" alt=""></button><?php endforeach;?></div><small class="field-hint">اضغط على صورة لنسخ مسارها ثم ضعه في حقل الغلاف أو OG.</small><?php endif;?>
    </aside>
  </div>
  <section class="podcast-design-library" data-podcast-cover-builder><div class="panel-head"><div><span class="panel-kicker">هوية غلاف سراديب</span><h2>تصميم غلاف الحلقة</h2><p>غلاف موحّد بمقاس 1600×1600، يتكيف تلقائيًا مع العنوان والصورة والتصنيف.</p></div></div><input type="hidden" name="cover_template" value="cinematic"><div class="podcast-cover-builder"><div class="podcast-cover-preview"><?=render_podcast_cover($ep+['status'=>$ep['status']??'draft'],$coverCategory,default_locale())?></div></div></section>
</form>
<div class="podcast-cover-export-actions" data-podcast-cover-export><button type="button" class="btn btn-ghost" data-podcast-export="png">تنزيل PNG 1600</button><button type="button" class="btn btn-ghost" data-podcast-export="jpeg">تنزيل JPG 1600</button><small data-podcast-export-status aria-live="polite"></small></div>
<?php if($isEdit):?><form method="post" action="/admin/podcast/delete" onsubmit="return confirm('حذف الحلقة؟')" class="danger-zone-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($ep['id'])?>"><button class="btn btn-ghost" type="submit">حذف الحلقة</button></form><?php endif;?>
