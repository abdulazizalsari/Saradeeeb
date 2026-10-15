<?php
$articleId=(int)($article['id']??0);
$selectedCategoryId=(int)($article['category_id']??0);
$locales=article_locales();
$writingLocale=(string)($article['original_locale']??($writingLocale??'ar'));
if(!isset($locales[$writingLocale])) $writingLocale='ar';
// The writer's account controls the source language. Put it first so the
// author never has to choose an original language per article.
if(!is_editor() && isset($locales[$writingLocale])) $locales=[$writingLocale=>$locales[$writingLocale]];
$defaultLocale=$writingLocale;
$categoryName = static function(array $cat,string $locale,string $defaultLocale): string {
    $translations=$cat['translations']??[];
    foreach([$locale,'ar',$defaultLocale] as $candidate){
        $name=trim((string)($translations[$candidate]['name']??''));
        if($name!=='') return $name;
    }
    foreach($translations as $translation){
        $name=trim((string)($translation['name']??''));
        if($name!=='') return $name;
    }
    return '';
};
$categoryMapForJs=[];
foreach($categoryMatrix as $cid=>$cat){
    $categoryMapForJs[(string)$cid]=[];
    foreach($locales as $loc=>$info){
        $categoryMapForJs[(string)$cid][$loc]=$categoryName($cat,$loc,$defaultLocale);
    }
}
$articleStatus=(string)($article['status']??'draft');
?>
<form method="post" enctype="multipart/form-data" class="editor-form article-studio-form" data-editor-form data-article-autosave="<?=$articleId?:'new'?>" data-current-featured="<?=e($article['featured_image']??'')?>">
<input type="hidden" name="article_id" value="<?=$articleId?>"><?=csrf_field()?>

<header class="article-studio-head">
  <div class="article-studio-title">
    <a class="studio-back" href="<?=is_editor()?'/admin/articles':'/writer'?>">← رجوع</a>
    <div><span class="studio-kicker">SARADEEEB ARTICLE STUDIO</span><h2><?=e($articleId?'تحرير المقال':'مقال جديد')?></h2></div>
  </div>
  <div class="actions studio-actions">
    <?php if(is_editor() && $articleId):?><a class="btn btn-ghost" href="/admin/articles/<?=$articleId?>/revisions">نسخ سابقة</a><?php endif;?>
    <?php if(is_editor()):?><button type="button" class="btn btn-ghost editorial-check-trigger" data-editorial-check-open><span aria-hidden="true">✓</span> فحص جودة المقالة</button><?php endif;?>
    <button formaction="/writer/articles/save" class="btn btn-ghost">حفظ مسودة</button>
    <button formaction="/writer/articles/submit" class="btn btn-primary"><?=is_editor()?'حفظ ونشر':'إرسال للمراجعة'?></button>
  </div>
</header>

<div class="article-studio-grid">
  <main class="article-studio-main">
    <section class="studio-card studio-language-card">
      <div class="studio-card-head">
        <div><span class="studio-card-kicker">المحتوى والترجمات</span><h3>نسخ المقال حسب اللغة</h3></div>
        <span class="studio-help">لغة الكتابة الأساسية هي النسخة الأصلية لهذا المقال. لا تختارها لكل مقال؛ يحتفظ بها النظام من حساب الكاتب، وتبقى العربية مطلوبة قبل النشر العام عندما تكون اللغة الأصلية غير عربية.</span>
      </div>

      <div class="lang-tabs studio-lang-tabs" role="tablist">
      <?php $first=true; foreach($locales as $loc=>$info): ?>
        <button type="button" class="lang-tab <?=$first?'active':''?>" data-tab="<?=$loc?>">
          <span class="lang-flag"><?=e(language_flag($loc))?></span>
          <span><?=e(language_name($loc))?></span>
          <span class="translation-state <?=isset($byLocale[$loc])?'has-translation':''?>"><?=e($loc===$writingLocale?'النص الأصلي · '.article_translation_state($byLocale[$loc]??null):article_translation_state($byLocale[$loc]??null))?></span>
        </button>
      <?php $first=false; endforeach; ?>
      </div>

      <?php $first=true; foreach($locales as $loc=>$info):
        $t=$byLocale[$loc]??[];
        $catName=$selectedCategoryId?($categoryName($categoryMatrix[$selectedCategoryId],$loc,$defaultLocale)):'';
        $previewSlug=(string)($t['slug']??'');
      ?>
      <section class="lang-panel studio-lang-panel <?=$first?'active':''?>" data-panel="<?=$loc?>" data-locale="<?=$loc?>">
        <?php if($loc!==$writingLocale && $articleId):?><div class="actions"><button type="submit" class="btn btn-primary" name="translation_locale" value="<?=e($loc)?>" formaction="/writer/articles/save">حفظ هذه الترجمة فقط</button><small>يحفظ محتوى وحالة هذه اللغة دون تعديل النص الأصلي أو إعدادات المقالة.</small></div><?php endif;?>
        <?php
          $sourceOriginal=$byLocale[$writingLocale]??null;
          $translationOutdated=$loc!==$writingLocale && !empty($t) && !empty($sourceOriginal['updated_at']) && !empty($t['updated_at']) && strtotime((string)$sourceOriginal['updated_at'])>strtotime((string)$t['updated_at']);
          $translationComplete=trim((string)($t['title']??''))!=='' && trim((string)($t['slug']??''))!=='' && trim(strip_tags((string)($t['content']??'')))!=='';
          $translationPublic=$translationComplete && (string)($t['status']??'draft')==='published' && (string)($t['review_state']??'draft')==='approved';
        ?>
        <?php if($translationOutdated): ?><div class="translation-update-warning" role="status">تم تعديل النص الأصلي بعد آخر تحديث لهذه الترجمة. الترجمة الحالية لم تتغير ويمكنك تحديثها عندما تريد.</div><?php endif; ?>
        <div class="translation-context-bar">
          <div>
            <span>التصنيف الموروث من المقال</span>
            <strong data-category-name="<?=$loc?>"><?=$selectedCategoryId?e($catName?:'اسم التصنيف غير مترجم لهذه اللغة'):'بدون تصنيف'?></strong>
          </div>
          <small>التصنيف واحد للمقال بالكامل، ويظهر هنا باسمه المترجم لنفس التصنيف.</small>
          <?php if($articleId && $previewSlug): ?><a target="_blank" rel="noopener" href="/writer/articles/<?=$articleId?>/preview/<?=$loc?>">معاينة هذه اللغة ↗</a><?php endif; ?>
        </div>
        <?php if($loc!==$defaultLocale): ?>
          <div class="translation-publication-status <?=$translationPublic?'is-public':'is-pending'?>" role="status">
            <?php if($translationPublic): ?>هذه الترجمة مكتملة ومنشورة للزوار في <?=e(language_name($loc))?>.
            <?php elseif(!$translationComplete): ?>هذه نسخة عمل غير مكتملة ولن تظهر للزوار: أدخل العنوان والرابط والمحتوى المترجم أولًا.
            <?php else: ?>هذه الترجمة مكتملة لكنها ليست منشورة ومعتمدة بعد؛ اختر «منشورة» و«معتمدة» ثم احفظ وانشر.
            <?php endif; ?>
          </div>
          <?php
            $completenessLabels=['complete_ready'=>'مكتملة وجاهزة للمراجعة','incomplete'=>'ترجمة غير مكتملة','needs_review'=>'تحتاج إلى تدقيق لغوي','passed'=>'اجتازت فحص الاكتمال'];
            $validationReport=json_decode((string)($t['completeness_report']??''),true)?:[];
            $validationState=(string)($t['completeness_state']??'complete_ready');
          ?>
          <section id="translation-report-<?=e($loc)?>" class="translation-completeness-report state-<?=e($validationState)?>" aria-label="تقرير اكتمال ترجمة <?=e(language_name($loc))?>">
            <div class="translation-completeness-head"><div><span>فحص إلزامي قبل النشر</span><h4><?=e($completenessLabels[$validationState]??'مكتملة وجاهزة للمراجعة')?></h4></div><?php if(is_editor()&&$articleId):?><button class="btn btn-ghost" type="submit" formnovalidate formaction="/writer/articles/<?=$articleId?>/translation/<?=e($loc)?>/validate">إعادة الفحص</button><?php endif;?></div>
            <?php if($validationReport): $totals=$validationReport['totals']??[]; ?>
              <div class="translation-completeness-metrics"><span><b><?=number_format((int)($totals['required']??0))?></b>حقول مطلوبة</span><span><b><?=number_format((int)($totals['translated']??0))?></b>حقول مترجمة</span><span><b><?=number_format((int)($totals['missing']??0))?></b>أخطاء مؤكدة</span><span><b><?=number_format((int)($totals['suspicious']??0))?></b>نتائج تحتاج تدقيقًا</span></div>
              <?php foreach($validationReport['issues']??[] as $issue):?><article class="translation-completeness-issue severity-<?=e($issue['severity']??'ambiguous')?> <?=!empty($issue['resolved'])?'is-resolved':''?>">
                <header><strong><?=e($issue['field_label']??$issue['field']??'حقل')?><?=!empty($issue['location'])?' — الفقرة '.(int)$issue['location']:''?></strong><span><?=($issue['severity']??'')==='confirmed'?'مانع مؤكد':(!empty($issue['resolved'])?'موثّق كمقصود':'يحتاج قرارًا')?></span></header>
                <p><b>السبب:</b> <?=e($issue['reason']??'')?></p>
                <?php if(!empty($issue['source'])):?><div><b>الأصل:</b><blockquote><?=e($issue['source'])?></blockquote></div><?php endif;?>
                <div><b>الترجمة الحالية:</b><blockquote><?=e($issue['translation']?:'— فارغة —')?></blockquote></div>
                <p><b>الإجراء المقترح:</b> <?=e($issue['action']??'راجع النص.')?></p>
                <?php if(($issue['severity']??'')==='ambiguous'&&empty($issue['resolved'])&&is_editor()&&$articleId):?><label>سبب الإبقاء على النص كما هو<textarea name="resolution_reason[<?=e($issue['key'])?>]" rows="2" minlength="10" placeholder="مثال: اسم كتاب أو اقتباس مقصود بلغته الأصلية"></textarea></label><button class="btn btn-ghost" type="submit" formnovalidate name="issue_key" value="<?=e($issue['key'])?>" formaction="/writer/articles/<?=$articleId?>/translation/<?=e($loc)?>/resolve">توثيق القرار وإعادة الفحص</button><?php elseif(!empty($issue['resolved'])):?><small>سبب القرار: <?=e($issue['resolution_reason']??'')?></small><?php endif;?>
              </article><?php endforeach;?>
              <p class="translation-completeness-limit"><?=e($validationReport['limitations']??'')?></p>
            <?php else:?><p>غيّر المحتوى ثم احفظه، أو اضغط «إعادة الفحص» لإنشاء تقرير محدث. لا يُعاد استخدام اعتماد قديم بعد تعديل النص.</p><?php endif;?>
          </section>
        <?php endif; ?>

        <label class="studio-field studio-field-title">العنوان
          <input name="title[<?=$loc?>]" value="<?=e($t['title']??'')?>" placeholder="اكتب عنوانًا واضحًا وجذابًا" data-article-title="<?=$loc?>">
        </label>

        <div class="two-cols">
          <label class="studio-field">الرابط المختصر
            <input name="slug[<?=$loc?>]" value="<?=e($t['slug']??'')?>" placeholder="يُنشأ تلقائيًا من العنوان">
          </label>
          <label class="studio-field">المقدمة المختصرة
            <input name="excerpt[<?=$loc?>]" value="<?=e($t['excerpt']??'')?>" placeholder="سطر أو سطران يعرّفان بالمقال">
          </label>
        </div>

        <label class="studio-field">المحتوى
          <div class="rich-toolbar studio-rich-toolbar">
            <button type="button" data-cmd="bold"><b>B</b></button>
            <button type="button" data-cmd="italic"><i>I</i></button>
            <button type="button" data-block="h2">H2</button>
            <button type="button" data-block="h3">H3</button>
            <button type="button" data-cmd="insertUnorderedList">• قائمة</button>
            <button type="button" data-block="blockquote">❞ اقتباس</button>
          </div>
          <div class="rich-editor studio-rich-editor" contenteditable="true" data-rich-editor="<?=$loc?>"><?=$t['content']??''?></div>
          <textarea hidden name="content[<?=$loc?>]" data-rich-target="<?=$loc?>"><?=e($t['content']??'')?></textarea>
        </label>

        <?php if(is_editor()):?><div class="two-cols translation-publication-controls"><label>ظهور الترجمة<select name="translation_status[<?=$loc?>]"><option value="draft" <?=($t['status']??'draft')==='draft'?'selected':''?>>مسودة</option><option value="published" <?=($t['status']??'')==='published'?'selected':''?>>منشورة</option></select></label><label>حالة المراجعة<select name="review_state[<?=$loc?>]"><option value="draft" <?=($t['review_state']??'draft')==='draft'?'selected':''?>>مسودة</option><option value="review" <?=($t['review_state']??'')==='review'?'selected':''?>>تحتاج مراجعة</option><option value="approved" <?=($t['review_state']??'')==='approved'?'selected':''?>>معتمدة</option></select></label></div><?php endif;?>

        <div class="hashtag-assistant" data-hashtag-assistant="<?=$loc?>">
          <div class="hashtag-assistant-head">
            <div><span>مساعد الهشتاغات</span><strong>هشتاغات هذه اللغة فقط</strong></div>
            <small>استخدم 3–8 هشتاغات مرتبطة فعلًا بالمحتوى.</small>
          </div>
          <label class="studio-field">الهشتاغات
            <input name="hashtags[<?=$loc?>]" value="<?=e($t['hashtags']??'')?>" placeholder="#أساطير #غموض #تاريخ" data-hashtags-input="<?=$loc?>">
          </label>
          <div class="hashtag-tools">
            <button type="button" class="assist-chip" data-hashtag-from-title="<?=$loc?>">✨ اقتراح من العنوان</button>
            <button type="button" class="assist-chip" data-hashtag-category="<?=$loc?>">＋ إضافة التصنيف</button>
            <button type="button" class="assist-chip" data-hashtag-clean="<?=$loc?>">تنظيف وترتيب</button>
          </div>
          <div class="hashtag-preview" data-hashtag-preview="<?=$loc?>"></div>
        </div>

        <details class="seo-box studio-seo-box">
          <summary>SEO لهذه اللغة</summary>
          <label>عنوان Google<input name="seo_title[<?=$loc?>]" value="<?=e($t['seo_title']??'')?>"></label>
          <label>وصف Google<textarea name="seo_description[<?=$loc?>]" rows="3"><?=e($t['seo_description']??'')?></textarea></label>
          <label>الكلمات المفتاحية SEO<input name="seo_keywords[<?=$loc?>]" value="<?=e($t['seo_keywords']??'')?>" placeholder="غموض، قصص، تاريخ"></label>
          <label>النص البديل للصورة<input name="image_alt[<?=$loc?>]" value="<?=e($t['image_alt']??'')?>"></label>
          <label>وصف الصورة<input name="image_caption[<?=$loc?>]" value="<?=e($t['image_caption']??'')?>"></label>
          <label>عنوان Open Graph<input name="og_title[<?=$loc?>]" value="<?=e($t['og_title']??'')?>"></label>
          <label>وصف Open Graph<textarea name="og_description[<?=$loc?>]" rows="2"><?=e($t['og_description']??'')?></textarea></label>
        </details>
      </section>
      <?php $first=false; endforeach; ?>
    </section>
  </main>

  <aside class="article-studio-side">
    <?php if(is_editor()): ?>
    <section class="studio-card editorial-checker" data-editorial-checker hidden aria-labelledby="editorial-checker-title">
      <div class="studio-card-head compact editorial-checker-head"><div><span class="studio-card-kicker">الفحص التحريري</span><h3 id="editorial-checker-title">نتائج الفحص التحريري</h3></div><button type="button" class="editorial-check-close" data-editorial-check-close aria-label="إغلاق النتائج">×</button></div>
      <p class="editorial-check-scope" data-editorial-check-scope>يفحص النظام المسودة الحالية دون نشرها أو تعديلها.</p>
      <div class="editorial-check-loading" data-editorial-check-loading hidden role="status">جارٍ فحص المسودة الحالية…</div>
      <div class="editorial-check-metrics" data-editorial-check-metrics hidden></div>
      <div class="editorial-check-results" data-editorial-check-results aria-live="polite"></div>
      <div class="editorial-readiness" data-editorial-readiness hidden></div>
      <div class="editorial-check-actions">
        <button type="button" class="btn btn-primary" data-editorial-recheck>إعادة الفحص</button>
        <?php if($articleId):?><button type="button" class="btn btn-ghost" data-editorial-to-note disabled>إضافة المحدد إلى ملاحظة الكاتب</button><?php endif;?>
      </div>
    </section>
    <?php endif; ?>
    <section class="studio-card studio-settings-card">
      <div class="studio-card-head compact"><div><span class="studio-card-kicker">إعدادات مشتركة</span><h3>المقال</h3></div></div>
      <label class="studio-field">التصنيف
        <select name="category_id" data-article-category>
          <option value="">بدون تصنيف</option>
          <?php foreach($categoryMatrix as $cid=>$cat):
            $defaultName=$cat['translations'][$defaultLocale]['name']??(reset($cat['translations'])['name']??('تصنيف '.$cid)); ?>
            <option value="<?=$cid?>" <?=$selectedCategoryId===(int)$cid?'selected':''?>><?=e($defaultName)?></option>
          <?php endforeach; ?>
        </select>
        <small>اختيار واحد فقط للمقال؛ جميع الترجمات تستخدم نفس التصنيف مترجمًا.</small>
      </label>

      <label class="studio-field">الصورة الرئيسية
        <input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp,image/avif">
        <small>ارفع صورة جديدة، أو أعد استخدام صورة من مكتبة الوسائط.</small>
      </label>
      <?php if(is_editor()): ?>
      <div class="studio-field media-picker-field"><span>إعادة استخدام صورة من المكتبة</span><input type="hidden" name="featured_image_existing" value="" data-media-picker-value><button type="button" class="btn btn-ghost" data-open-media-picker>اختيار من مكتبة الوسائط</button><div class="media-picker-selection" data-media-picker-selection>لم يتم اختيار صورة من المكتبة.</div></div>
      <?php if(!empty($mediaLibrary)):?><div class="media-picker-modal" data-media-picker-modal hidden><div class="media-picker-dialog"><div class="panel-head"><div><h3>اختر صورة</h3><p>انقر على الصورة لاستخدامها كصورة رئيسية.</p></div><button type="button" class="btn btn-ghost" data-close-media-picker>إغلاق</button></div><div class="media-picker-grid"><?php foreach($mediaLibrary as $media):?><button type="button" data-pick-media="<?=e($media['path'])?>" data-media-title="<?=e($media['title']?:$media['original_name'])?>"><img src="<?=e($media['path'])?>" alt="<?=e($media['alt_text']??'')?>"><span><?=e($media['title']?:$media['original_name'])?></span></button><?php endforeach;?></div></div></div><?php endif;?>
      <?php endif; ?>
      <?php if(!empty($article['featured_image'])):?><img class="thumb-preview studio-thumb" src="<?=e($article['featured_image'])?>" alt=""><?php endif;?>

      <?php if($articleId): ?>
      <div class="article-status-box"><span>حالة المقال</span><strong class="status s-<?=e($articleStatus)?>"><?=e($articleStatus)?></strong></div>
      <?php endif; ?>
    </section>

    <section class="studio-card translation-guide studio-translation-guide">
      <div class="studio-card-head compact"><div><span class="studio-card-kicker">الترجمة</span><h3>حالة اللغات</h3></div></div>
      <?php foreach($locales as $loc=>$info): ?>
      <?php $outdated=$loc!=='ar' && isset($byLocale[$loc],$byLocale['ar']) && !empty($byLocale[$loc]['updated_at']) && !empty($byLocale['ar']['updated_at']) && strtotime((string)$byLocale['ar']['updated_at'])>strtotime((string)$byLocale[$loc]['updated_at']); ?>
      <div><span><?=e(language_flag($loc).' '.language_name($loc))?></span><strong><?=$outdated?'تحتاج مراجعة':(isset($byLocale[$loc])?'موجودة':'غير مترجمة')?></strong></div>
      <?php endforeach; ?>
      <p class="studio-note">عدم وجود ترجمة يعني أن المقال لن يظهر إطلاقًا في ذلك القسم اللغوي.</p>
    </section>

    <?php if(is_editor() && $articleId): ?>
    <section class="studio-card editorial-review-card">
      <div class="studio-card-head compact"><div><span class="studio-card-kicker">المراجعة التحريرية</span><h3>قرار الإدارة</h3></div></div>
      <label class="studio-field">ملاحظة للكاتب
        <textarea name="review_note" rows="5" placeholder="اكتب ما تريد من الكاتب تعديله. ستظهر داخل حسابه وتصل إلى بريده عند تفعيل البريد."></textarea>
      </label>
      <div class="review-actions">
        <button class="btn btn-primary" formaction="/admin/articles/review" name="action" value="publish">نشر الترجمات الجاهزة</button>
        <button class="btn btn-ghost" formaction="/admin/articles/review" name="action" value="changes">طلب تعديلات</button>
      </div>
    </section>
    <?php endif; ?>
  </aside>
</div>

<script type="application/json" id="article-category-map"><?=json_encode($categoryMapForJs,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?></script>
</form>
<?php if(is_editor()):?><script defer src="/assets/js/editorial-checker.js?v=2"></script><?php endif;?>
<script>
(()=>{const slugify=s=>s.toString().trim().toLowerCase().replace(/[^\p{L}\p{N}\s_-]+/gu,'').replace(/[\s_]+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');document.querySelectorAll('[data-article-title]').forEach(title=>{const locale=title.dataset.articleTitle,slug=document.querySelector(`[name="slug[${locale}]"]`);if(!slug)return;let edited=slug.value.trim()!=='';slug.addEventListener('input',()=>{edited=true});title.addEventListener('input',()=>{if(!edited||slug.value.trim()==='')slug.value=slugify(title.value)})})})();
</script>
