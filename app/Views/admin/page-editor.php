<?php $pageId=(int)($page['id']??0); $pageLocales=article_locales(); $managedKey=\App\Services\ManagedPages::key($pageId); $placement=\App\Services\ManagedPages::placement($pageId); $componentFields=json_decode(setting('managed_page_fields_json','{}'),true)?:[]; ?>
<form method="post" action="/admin/pages/save" class="editor-form page-builder-pro" id="pageBuilderForm">
<label class="check page-footer-placement"><input type="checkbox" name="show_in_footer" <?=!empty($page['show_in_footer'])?'checked':''?>> إظهار هذه الصفحة في الفوتر</label>
<?php if($managedKey):?><p class="notice">صفحة نظامية: تحرّر المحتوى المحيط بالمكوّن، ويظل النموذج أو البحث أو المنتدى يعمل بمساره الأصلي. لا يمكن حذفها.</p><?php endif;?>
<p class="field-help">العربية هي الأصل الإلزامي. اختر حالة نشر كل ترجمة؛ لا تعرض المسودات للزوار.</p>
<div class="form-grid two"><label>عمود الفوتر<select name="footer_column"><?php foreach(['brand'=>'الهوية والأزرار','menu'=>'روابط الاستكشاف','connect'=>'التواصل والاشتراك'] as $value=>$label):?><option value="<?=$value?>" <?=$placement['column']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><label>ترتيب الرابط داخل العمود<input type="number" name="footer_order" min="-999" max="999" value="<?=e($placement['order'])?>"></label></div>
<input type="hidden" name="page_id" value="<?=$pageId?>"><?=csrf_field()?>
<div class="editor-toolbar-top"><div><a href="/admin/pages">← الصفحات</a><h2><?=e($pageId?'تحرير الصفحة':'إنشاء صفحة')?></h2><p>محرر مرئي: نصوص، ألوان، أحجام، روابط وصور من الجهاز أو مكتبة الوسائط.</p></div><div class="actions"><select name="status"><option value="draft" <?=($page['status']??'')==='draft'?'selected':''?>>مسودة</option><option value="published" <?=($page['status']??'')==='published'?'selected':''?>>منشورة</option></select><button type="button" class="btn btn-ghost" onclick="window.open('/','_blank')">معاينة الموقع</button><button class="btn btn-primary">حفظ الصفحة</button></div></div>
<div class="editor-grid"><div class="editor-main"><div class="panel tabs-panel">
<div class="lang-tabs"><?php $first=true;foreach($pageLocales as $loc=>$info):?><button type="button" class="lang-tab <?=$first?'active':''?>" data-tab="<?=$loc?>"><?=e(language_flag($loc).' '.language_name($loc))?> · <?=e(article_translation_state($byLocale[$loc]??null))?></button><?php $first=false;endforeach;?></div>
<?php $first=true;foreach($pageLocales as $loc=>$info):$t=$byLocale[$loc]??[];$mode=$t['content_mode']??'visual';$bg=$t['page_bg_color']??'#ffffff';$text=$t['page_text_color']??'#202522';$cw=$t['content_width']??'standard';?>
<section class="lang-panel <?=$first?'active':''?>" data-panel="<?=$loc?>">
<label>عنوان رابط الفوتر لهذه اللغة (اختياري)<input name="footer_label[<?=$loc?>]" value="<?=e($placement['labels'][$loc]??'')?>" placeholder="يستخدم عنوان الصفحة عند تركه فارغًا"></label>
<?php if($pageId && $loc!=='ar'):?><button type="submit" class="btn btn-ghost" name="translation_locale" value="<?=$loc?>">حفظ هذه الترجمة فقط</button><?php endif;?>
<?php if($managedKey==='newsletter'):?><div class="form-grid two"><?php foreach(['email_placeholder'=>'نص حقل البريد','consent'=>'نص الموافقة الإلزامية','button_label'=>'عنوان زر الاشتراك'] as $key=>$label):?><label><?=e($label)?><input name="component_fields[<?=$loc?>][<?=$key?>]" value="<?=e($componentFields[$pageId][$loc][$key]??'')?>" placeholder="النص الافتراضي لهذه اللغة"></label><?php endforeach;?></div><?php endif;?>
<div class="builder-title-grid"><label>عنوان الصفحة<input name="title[<?=$loc?>]" value="<?=e($t['title']??'')?>" placeholder="عنوان واضح للصفحة"></label><label>الرابط المختصر<input name="slug[<?=$loc?>]" <?=isset(\App\Services\ManagedPages::ROUTES[$managedKey??''])?'readonly':''?> value="<?=e($t['slug']??'')?>" placeholder="about-us"></label></div>
<div class="code-mode-switch page-code-mode"><span>محتوى الصفحة</span><label><input type="radio" name="content_mode[<?=$loc?>]" value="visual" <?=$mode==='visual'?'checked':''?> data-page-mode="visual"> ✎ المحرر المرئي</label><label><input type="radio" name="content_mode[<?=$loc?>]" value="html" <?=$mode==='html'?'checked':''?> data-page-mode="html"> &lt;/&gt; كتابة HTML</label><button type="button" class="btn btn-ghost" data-page-preview>معاينة التعديلات</button></div>
<div class="visual-editor-block" data-mode-panel="visual">
<div class="page-builder-toolbar" data-toolbar-for="<?=$loc?>">
<select data-format title="نوع النص"><option value="p">فقرة</option><option value="h1">H1</option><option value="h2">H2</option><option value="h3">H3</option><option value="h4">H4</option><option value="blockquote">اقتباس</option></select>
<select data-font-size title="حجم النص"><option value="">الحجم</option><?php foreach([12,14,16,18,20,24,28,32,36,42,48,56,64,72] as $fs):?><option value="<?=$fs?>"><?=$fs?>px</option><?php endforeach;?></select>
<label class="toolbar-color" title="لون النص"><span>A</span><input type="color" data-text-color value="#202522"></label><label class="toolbar-color highlight" title="لون خلفية النص"><span>▰</span><input type="color" data-bg-color value="#fff2a8"></label>
<button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="underline"><u>U</u></button><button type="button" data-cmd="strikeThrough"><s>S</s></button>
<button type="button" data-cmd="justifyRight" title="يمين">↦</button><button type="button" data-cmd="justifyCenter" title="وسط">≡</button><button type="button" data-cmd="justifyLeft" title="يسار">↤</button><button type="button" data-cmd="justifyFull" title="ضبط">☰</button>
<button type="button" data-cmd="insertUnorderedList">• قائمة</button><button type="button" data-cmd="insertOrderedList">1. قائمة</button>
<button type="button" data-link>🔗 رابط</button><button type="button" data-unlink>إلغاء الرابط</button><button type="button" data-image>🖼 صورة</button><button type="button" data-button>＋ زر CTA</button><button type="button" data-rule>— فاصل</button><button type="button" data-cmd="undo">↶</button><button type="button" data-cmd="redo">↷</button><button type="button" data-cmd="removeFormat">مسح التنسيق</button>
</div>
<div class="rich-editor page-canvas" contenteditable="true" data-rich-editor="<?=$loc?>" style="background:<?=e($bg)?>;color:<?=e($text)?>"><?=$mode==='visual'?sanitize_page_html($t['content']??''):''?></div>
<textarea hidden name="content[<?=$loc?>]" data-rich-target="<?=$loc?>"><?=e($t['content']??'')?></textarea>
<div class="builder-media-inline"><button type="button" class="btn btn-ghost" data-open-media data-purpose="insert">🖼 اختيار صورة من الوسائط</button><button type="button" class="btn btn-ghost" data-open-upload data-purpose="insert">⇧ رفع صورة من الجهاز</button><span>تُدرج الصورة في موضع المؤشر داخل الصفحة.</span></div>
</div>
<div class="code-editor-block" data-mode-panel="html" <?=$mode==='visual'?'hidden':''?>>
  <div class="panel-head compact"><div><span class="panel-kicker">HTML</span><h3>كود محتوى الصفحة</h3><p>ضع كود الصفحة هنا. أضف التنسيق والتفاعل في القسم التالي.</p></div></div>
  <label>HTML<textarea class="code-textarea" data-code-content="<?=$loc?>" rows="20" spellcheck="false" autocomplete="off" aria-label="كود HTML — <?=$loc?>"><?=e($t['content']??'')?></textarea></label>
  <p class="field-help">استخدم هذا الوضع للحفاظ على كود HTML الكامل. المحرر المرئي مخصص للنصوص والصور والتنسيق البسيط.</p>
</div>
<section class="page-extra-code">
  <div class="panel-head compact"><div><span class="panel-kicker">التصميم والتفاعل</span><h3>أكواد CSS وJavaScript</h3><p>تعمل مع المحتوى المرئي وHTML، وتُحفظ مع الصفحة واللغة الحالية.</p></div></div>
  <div class="page-code-tabs" role="tablist" aria-label="أكواد الصفحة">
    <button type="button" role="tab" id="css-tab-<?=$loc?>" aria-controls="css-panel-<?=$loc?>" aria-selected="true" data-extra-code-tab="css">CSS — التنسيق</button>
    <button type="button" role="tab" id="js-tab-<?=$loc?>" aria-controls="js-panel-<?=$loc?>" aria-selected="false" tabindex="-1" data-extra-code-tab="js">JavaScript — التفاعل</button>
  </div>
  <div role="tabpanel" id="css-panel-<?=$loc?>" aria-labelledby="css-tab-<?=$loc?>" data-extra-code-panel="css"><label>CSS<textarea class="code-textarea code-css" name="custom_css[<?=$loc?>]" rows="12" spellcheck="false" placeholder=".standalone-page h2 { color: #31bec5; }"><?=e($t['custom_css']??'')?></textarea></label><p class="field-help">ضع CSS فقط دون وسم &lt;style&gt;. استخدم .standalone-page لتخصيص محتوى هذه الصفحة.</p></div>
  <div role="tabpanel" id="js-panel-<?=$loc?>" aria-labelledby="js-tab-<?=$loc?>" data-extra-code-panel="js" hidden><label>JavaScript<textarea class="code-textarea code-js" name="custom_js[<?=$loc?>]" rows="12" spellcheck="false" placeholder="document.querySelector('.standalone-page');"><?=e($t['custom_js']??'')?></textarea></label><p class="field-help">ضع JavaScript فقط دون وسم &lt;script&gt;.</p></div>
</section>

<div class="page-display-settings">
  <div class="panel-head compact"><div><span class="panel-kicker">طريقة العرض</span><h3>ألوان وشكل الصفحة</h3></div></div>
  <div class="page-display-grid">
    <label>لون خلفية الصفحة<div class="color-input-row"><input type="color" name="page_bg_color[<?=$loc?>]" value="<?=e($bg)?>" data-page-bg><code><?=e($bg)?></code></div></label>
    <label>لون النص الافتراضي<div class="color-input-row"><input type="color" name="page_text_color[<?=$loc?>]" value="<?=e($text)?>" data-page-text><code><?=e($text)?></code></div></label>
    <label>عرض المحتوى<select name="content_width[<?=$loc?>]"><option value="narrow" <?=$cw==='narrow'?'selected':''?>>ضيق للقراءة</option><option value="standard" <?=$cw==='standard'?'selected':''?>>قياسي</option><option value="wide" <?=$cw==='wide'?'selected':''?>>عريض</option><option value="full" <?=$cw==='full'?'selected':''?>>كامل الشاشة</option></select></label>
  </div>
  <p class="field-help">لون الصفحة هنا افتراضي. يمكنك تحديد كلمة أو جملة داخل المحرر وتغيير لونها أو حجمها بشكل مستقل من شريط الأدوات.</p>
</div>

<details class="seo-box" open><summary>الصورة البارزة والظهور وSEO</summary>
  <div class="featured-media-control" data-featured-control>
    <input type="hidden" name="featured_image[<?=$loc?>]" value="<?=e($t['featured_image']??'')?>" data-featured-value>
    <div class="featured-media-preview" data-featured-preview><?php if(!empty($t['featured_image'])):?><img src="<?=e($t['featured_image'])?>" alt=""><?php else:?><span>لا توجد صورة بارزة</span><?php endif;?></div>
    <div class="actions"><button type="button" class="btn btn-ghost" data-open-media data-purpose="featured">اختيار من الوسائط</button><button type="button" class="btn btn-ghost" data-open-upload data-purpose="featured">رفع من الجهاز</button><button type="button" class="btn btn-ghost" data-remove-featured>إزالة</button></div>
  </div>
  <label class="check"><input type="checkbox" name="show_title[<?=$loc?>]" <?=($t['show_title']??1)?'checked':''?>> إظهار عنوان الصفحة للزائر</label><label>عنوان Google<input name="seo_title[<?=$loc?>]" value="<?=e($t['seo_title']??'')?>"></label><label>وصف Google<textarea name="seo_description[<?=$loc?>]" rows="3"><?=e($t['seo_description']??'')?></textarea></label><label>Canonical اختياري<input name="canonical_url[<?=$loc?>]" value="<?=e($t['canonical_url']??'')?>" placeholder="https://..."></label><div class="form-grid two"><label>حالة النسخة<select name="translation_status[<?=$loc?>]"><option value="draft" <?=($t['status']??'draft')==='draft'?'selected':''?>>مسودة</option><option value="review" <?=($t['status']??'')==='review'?'selected':''?>>تحتاج مراجعة</option><option value="published" <?=($t['status']??'')==='published'?'selected':''?>>منشورة</option></select></label><label>حالة المراجعة<select name="review_state[<?=$loc?>]"><option value="draft" <?=($t['review_state']??'draft')==='draft'?'selected':''?>>مسودة</option><option value="review" <?=($t['review_state']??'')==='review'?'selected':''?>>تحتاج مراجعة</option><option value="approved" <?=($t['review_state']??'')==='approved'?'selected':''?>>معتمدة</option></select></label></div>
</details>
</section><?php $first=false;endforeach;?></div></div>
<aside class="editor-side"><div class="panel"><h3>إعدادات الصفحة</h3><label>القالب<select name="template"><option value="standard" <?=($page['template']??'standard')==='standard'?'selected':''?>>قياسي</option><option value="wide" <?=($page['template']??'')==='wide'?'selected':''?>>عرض كامل</option><option value="landing" <?=($page['template']??'')==='landing'?'selected':''?>>Landing</option></select></label><label>الترتيب<input type="number" name="sort_order" value="<?=e($page['sort_order']??0)?>"></label><div class="builder-tip"><b>تحرير سريع</b><p>حدد أي كلمة أو جملة لتغيير اللون والحجم، أو تحويلها إلى رابط. الصور تُرفع مباشرة من جهازك أو تختارها من المكتبة.</p></div></div></aside></div></form>

<dialog class="page-media-dialog" id="pageMediaDialog">
 <div class="page-media-dialog-head"><div><span class="panel-kicker">مكتبة الوسائط</span><h3>اختيار صورة</h3></div><button type="button" data-close-media>×</button></div>
 <div class="page-media-tabs"><button type="button" class="active" data-media-tab="library">المكتبة</button><button type="button" data-media-tab="upload">رفع من الجهاز</button></div>
 <section data-media-panel="library"><input class="media-dialog-search" type="search" placeholder="ابحث في الصور..." data-media-search><div class="page-media-grid" data-page-media-grid><?php foreach($mediaLibrary as $m):?><button type="button" data-pick-page-media data-path="<?=e($m['path'])?>" data-alt="<?=e($m['alt_text']?:$m['title']?:$m['original_name']?:'')?>" data-title="<?=e($m['title']?:$m['original_name']?:'صورة')?>"><img src="<?=e($m['path'])?>" alt=""><span><?=e($m['title']?:$m['original_name']?:'صورة')?></span></button><?php endforeach;?><?php if(!$mediaLibrary):?><p class="empty-state">المكتبة فارغة.</p><?php endif;?></div></section>
 <section data-media-panel="upload" hidden><div class="page-direct-upload"><label class="media-drop-zone compact"><input type="file" id="pageDirectUpload" accept="image/jpeg,image/png,image/webp,image/avif,image/gif"><strong>اختر صورة من جهازك</strong><span>JPG / PNG / WebP / AVIF / GIF</span></label><div class="actions"><button type="button" class="btn btn-primary" id="pageUploadButton">رفع واستخدام الصورة</button></div><p id="pageUploadStatus" class="field-help"></p></div></section>
</dialog>

<dialog class="page-code-preview" id="pageCodePreview">
  <div class="page-media-dialog-head"><div><span class="panel-kicker">معاينة قبل الحفظ</span><h3>محتوى الصفحة والتنسيق والتفاعل</h3><p>اضغط «حفظ الصفحة» لتظهر التعديلات في الموقع.</p></div><button type="button" data-close-page-preview aria-label="إغلاق المعاينة">×</button></div>
  <iframe title="معاينة الصفحة" sandbox="allow-scripts"></iframe>
</dialog>
<script>
(()=>{
 const form=document.getElementById('pageBuilderForm'),dialog=document.getElementById('pageMediaDialog');let activeEditor=null,activePanel=null,purpose='insert',savedRange=null;
 const previewDialog=document.getElementById('pageCodePreview');
 document.querySelector('[data-close-page-preview]').onclick=()=>previewDialog.close();
 document.querySelectorAll('.lang-panel').forEach(panel=>{
   const ed=panel.querySelector('.page-canvas'),code=panel.querySelector('[data-code-content]');
   let previousMode=panel.querySelector('[data-page-mode]:checked').value;
   panel.querySelectorAll('[data-mode-panel]').forEach(block=>block.hidden=block.dataset.modePanel!==previousMode);
   panel.querySelectorAll('[data-page-mode]').forEach(radio=>radio.addEventListener('change',()=>{
     if(radio.value==='html' && previousMode==='visual') code.value=ed.innerHTML;
     if(radio.value==='visual' && previousMode==='html'){
       const parsed=new DOMParser().parseFromString(code.value,'text/html');
       parsed.querySelectorAll('script,style,iframe,object,embed,link,meta,base,form').forEach(node=>node.remove());
       parsed.body.querySelectorAll('*').forEach(node=>Array.from(node.attributes).forEach(attr=>{
         if(/^on/i.test(attr.name) || attr.name==='srcdoc' || (['href','src','action','formaction'].includes(attr.name.toLowerCase()) && !/^(https?:\/\/|\/|#|mailto:|tel:)/i.test(attr.value.trim()))) node.removeAttribute(attr.name);
       }));
       ed.innerHTML=parsed.body.innerHTML;
     }
     previousMode=radio.value;
     panel.querySelectorAll('[data-mode-panel]').forEach(block=>block.hidden=block.dataset.modePanel!==previousMode);
   }));
   const codeTabs=Array.from(panel.querySelectorAll('[data-extra-code-tab]'));
   const activateTab=tab=>{
     codeTabs.forEach(button=>{const active=button===tab;button.setAttribute('aria-selected',String(active));button.tabIndex=active?0:-1});
     panel.querySelectorAll('[data-extra-code-panel]').forEach(block=>block.hidden=block.dataset.extraCodePanel!==tab.dataset.extraCodeTab);
   };
   codeTabs.forEach((tab,index)=>{
     tab.onclick=()=>activateTab(tab);
     tab.onkeydown=event=>{let next=index;if(event.key==='ArrowLeft'||event.key==='ArrowRight')next=(index+1)%codeTabs.length;else if(event.key==='Home')next=0;else if(event.key==='End')next=codeTabs.length-1;else return;event.preventDefault();activateTab(codeTabs[next]);codeTabs[next].focus()};
   });
   panel.querySelector('[data-page-preview]').onclick=()=>{
     const visual=panel.querySelector('[data-page-mode="visual"]').checked;
     const html=visual?ed.innerHTML:code.value;
     const css=panel.querySelector('.code-css').value,js=panel.querySelector('.code-js').value;
     const bg=panel.querySelector('[data-page-bg]').value,text=panel.querySelector('[data-page-text]').value;
     const frame=previewDialog.querySelector('iframe');
     const cssUrl=new URL('/assets/css/app.css?v=736',location.origin).href;
     frame.srcdoc='<!doctype html><html lang="'+panel.dataset.panel+'" dir="'+(panel.dataset.panel==='ar'?'rtl':'ltr')+'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="'+location.origin+'/"><link rel="stylesheet" href="'+cssUrl+'"><style>body{margin:0;padding:24px;background:'+bg+';color:'+text+'}'+css+'</style></head><body><div class="article-content standalone-page">'+html+'</div><script>'+js+'<'+'/script></body></html>';
     previewDialog.showModal();
   };
 });
 const setActive=ed=>{activeEditor=ed;activePanel=ed?.closest('.lang-panel')||null};
 document.querySelectorAll('.page-canvas').forEach(ed=>{['focus','click','keyup'].forEach(ev=>ed.addEventListener(ev,()=>setActive(ed)));ed.addEventListener('mouseup',()=>{setActive(ed);const s=getSelection();if(s&&s.rangeCount)savedRange=s.getRangeAt(0).cloneRange()})});
 const restore=()=>{if(!activeEditor)return;activeEditor.focus();if(savedRange){const s=getSelection();s.removeAllRanges();s.addRange(savedRange)}};
 const cmd=(ed,c,v=null)=>{setActive(ed);restore();document.execCommand(c,false,v)};
 const inlineStyle=(ed,prop,value)=>{setActive(ed);restore();const sel=getSelection();if(!sel||!sel.rangeCount||sel.isCollapsed)return;const range=sel.getRangeAt(0);const span=document.createElement('span');span.style[prop]=value;try{range.surroundContents(span)}catch(_){document.execCommand(prop==='color'?'foreColor':'hiliteColor',false,value)}savedRange=null;};
 document.querySelectorAll('.page-builder-toolbar').forEach(tb=>{const ed=tb.closest('.lang-panel').querySelector('.page-canvas');tb.querySelectorAll('[data-cmd]').forEach(b=>b.onclick=()=>cmd(ed,b.dataset.cmd));tb.querySelector('[data-format]').onchange=e=>cmd(ed,'formatBlock',e.target.value);tb.querySelector('[data-font-size]').onchange=e=>{if(e.target.value)inlineStyle(ed,'fontSize',e.target.value+'px')};tb.querySelector('[data-text-color]').oninput=e=>inlineStyle(ed,'color',e.target.value);tb.querySelector('[data-bg-color]').oninput=e=>inlineStyle(ed,'backgroundColor',e.target.value);tb.querySelector('[data-link]').onclick=()=>{restore();const u=prompt('ضع الرابط:','https://');if(u&&/^(https?:\/\/|\/|#|mailto:)/i.test(u))cmd(ed,'createLink',u)};tb.querySelector('[data-unlink]').onclick=()=>cmd(ed,'unlink');tb.querySelector('[data-image]').onclick=()=>openMedia(ed,'insert','library');tb.querySelector('[data-button]').onclick=()=>{const label=prompt('نص الزر:','اعرف المزيد');if(!label)return;const u=prompt('رابط الزر:','/');if(u)cmd(ed,'insertHTML','<p><a class="btn btn-primary" href="'+u.replace(/"/g,'')+'">'+label.replace(/</g,'&lt;')+'</a></p>')};tb.querySelector('[data-rule]').onclick=()=>cmd(ed,'insertHorizontalRule')});
 document.querySelectorAll('[data-page-bg]').forEach(i=>i.addEventListener('input',()=>{const p=i.closest('.lang-panel');p.querySelector('.page-canvas').style.background=i.value;i.nextElementSibling.textContent=i.value}));document.querySelectorAll('[data-page-text]').forEach(i=>i.addEventListener('input',()=>{const p=i.closest('.lang-panel');p.querySelector('.page-canvas').style.color=i.value;i.nextElementSibling.textContent=i.value}));
 function openMedia(ed,purp,tab){setActive(ed||activeEditor||document.querySelector('.lang-panel.active .page-canvas'));purpose=purp;activePanel=(ed||activeEditor)?.closest('.lang-panel')||document.querySelector('.lang-panel.active');switchTab(tab||'library');dialog.showModal();}
 function switchTab(name){document.querySelectorAll('[data-media-tab]').forEach(b=>b.classList.toggle('active',b.dataset.mediaTab===name));document.querySelectorAll('[data-media-panel]').forEach(p=>p.hidden=p.dataset.mediaPanel!==name)}
 document.querySelectorAll('[data-media-tab]').forEach(b=>b.onclick=()=>switchTab(b.dataset.mediaTab));document.querySelector('[data-close-media]').onclick=()=>dialog.close();
 document.querySelectorAll('[data-open-media]').forEach(b=>b.onclick=()=>openMedia(b.closest('.lang-panel')?.querySelector('.page-canvas'),b.dataset.purpose,'library'));document.querySelectorAll('[data-open-upload]').forEach(b=>b.onclick=()=>openMedia(b.closest('.lang-panel')?.querySelector('.page-canvas'),b.dataset.purpose,'upload'));
 function useImage(path,alt=''){if(!activePanel)return;if(purpose==='featured'){const input=activePanel.querySelector('[data-featured-value]'),prev=activePanel.querySelector('[data-featured-preview]');input.value=path;prev.innerHTML='<img src="'+path+'" alt="">'}else{const ed=activePanel.querySelector('.page-canvas');cmd(ed,'insertHTML','<figure><img src="'+path+'" alt="'+String(alt).replace(/"/g,'&quot;')+'"><figcaption></figcaption></figure>')}dialog.close()}
 document.querySelectorAll('[data-pick-page-media]').forEach(b=>b.onclick=()=>useImage(b.dataset.path,b.dataset.alt||''));document.querySelectorAll('[data-remove-featured]').forEach(b=>b.onclick=()=>{const p=b.closest('.lang-panel');p.querySelector('[data-featured-value]').value='';p.querySelector('[data-featured-preview]').innerHTML='<span>لا توجد صورة بارزة</span>'});
 const search=document.querySelector('[data-media-search]');search?.addEventListener('input',()=>{const q=search.value.trim().toLowerCase();document.querySelectorAll('[data-pick-page-media]').forEach(b=>b.hidden=q&&!b.textContent.toLowerCase().includes(q))});
 document.getElementById('pageUploadButton').onclick=async()=>{const f=document.getElementById('pageDirectUpload').files?.[0],st=document.getElementById('pageUploadStatus');if(!f){st.textContent='اختر صورة أولًا.';return}const fd=new FormData();fd.append('_token',form.querySelector('input[name="_token"]').value);fd.append('file',f);st.textContent='جارٍ الرفع...';try{const r=await fetch('/admin/media/upload-ajax',{method:'POST',body:fd,headers:{'Accept':'application/json'}}),j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||'تعذر الرفع');const grid=document.querySelector('[data-page-media-grid]'),b=document.createElement('button');b.type='button';b.dataset.pickPageMedia='';b.dataset.path=j.path;b.dataset.alt=j.alt||j.title||'';b.innerHTML='<img src="'+j.path+'" alt=""><span>'+String(j.title||'صورة')+'</span>';b.onclick=()=>useImage(j.path,j.alt||j.title||'');grid.prepend(b);st.textContent='تم الرفع.';useImage(j.path,j.alt||j.title||'')}catch(e){st.textContent=e.message||'تعذر رفع الصورة.'}};
 form.addEventListener('submit',()=>{document.querySelectorAll('.lang-panel').forEach(panel=>{const ed=panel.querySelector('.page-canvas'),target=panel.querySelector('[data-rich-target]'),code=panel.querySelector('[data-code-content]'),visual=panel.querySelector('[data-page-mode="visual"]')?.checked;target.value=visual?ed.innerHTML:code.value})});
})();
</script>
