<?php $filters=$filters??['q'=>'','category'=>0,'author'=>0,'status'=>'','sort'=>'date_desc']; ?>
<div class="dash-actions"><a class="btn btn-primary" href="/writer/articles/new">＋ مقال جديد</a><a class="btn btn-ghost" href="/admin/export-articles?<?=e(http_build_query(['status'=>$filters['status']??'','author'=>$filters['author']??0,'category'=>$filters['category']??0]))?>">⇩ تصدير المقالات</a></div>
<section class="panel article-admin-panel">
  <div class="panel-head"><div><span class="panel-kicker">المراجعة والتحرير</span><h2>كل المقالات</h2></div><span><?=count($articles)?> نتيجة</span></div>

  <form class="article-filter-bar" method="get" action="/admin/articles">
    <label class="article-search"><span>⌕</span><input type="search" name="q" value="<?=e($filters['q'])?>" placeholder="ابحث باسم المقال أو الكاتب..."></label>
    <select name="category"><option value="0">كل الأنواع / التصنيفات</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=((int)$filters['category']===(int)$c['id'])?'selected':''?>><?=e($c['name']?:'بدون اسم')?></option><?php endforeach;?></select>
    <select name="status"><option value="">كل الحالات</option><?php foreach(['published'=>'منشور','review'=>'قيد المراجعة','changes'=>'يحتاج تعديلات','draft'=>'مسودة','archived'=>'مؤرشف'] as $k=>$v):?><option value="<?=$k?>" <?=$filters['status']===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select>
    <select name="author"><option value="0">كل الكتّاب</option><?php foreach($authors as $a):?><option value="<?=$a['id']?>" <?=((int)$filters['author']===(int)$a['id'])?'selected':''?>><?=e($a['name'])?> — <?=e($a['username'])?></option><?php endforeach;?></select>
    <select name="sort"><option value="date_desc" <?=$filters['sort']==='date_desc'?'selected':''?>>التاريخ: الأحدث</option><option value="date_asc" <?=$filters['sort']==='date_asc'?'selected':''?>>التاريخ: الأقدم</option><option value="name_asc" <?=$filters['sort']==='name_asc'?'selected':''?>>الاسم: أ ← ي</option><option value="name_desc" <?=$filters['sort']==='name_desc'?'selected':''?>>الاسم: ي ← أ</option><option value="views_desc" <?=$filters['sort']==='views_desc'?'selected':''?>>الأكثر قراءة</option></select>
    <button class="btn btn-primary">تطبيق</button><a class="btn btn-ghost" href="/admin/articles">مسح</a>
  </form>

  <div class="table-wrap"><table><thead><tr><th>المقال</th><th>الكاتب</th><th>اللغات</th><th>الحالة</th><th>التاريخ</th><th>القراء</th><th>إجراء</th></tr></thead><tbody>
  <form class="article-bulk-bar" id="bulk-form" method="post" action="/admin/articles/bulk" data-bulk-form><?=csrf_field()?><button type="button" class="btn btn-ghost" data-select-all>تحديد الكل</button><label>إجراء جماعي <select name="bulk_action" required><option value="">اختر الإجراء</option><option value="published">نشر المحدد</option><option value="draft">تحويل لمسودة</option><option value="archived">أرشفة المحدد</option></select></label><button class="btn btn-primary" type="submit">تطبيق</button><span data-selected-count>0 محدد</span></form>
  <?php foreach($articles as $a):?>
  <tr>
    <td><input type="checkbox" name="article_ids[]" value="<?=$a['id']?>" form="bulk-form" data-article-select aria-label="تحديد المقال"></td>
    <td><a href="/writer/articles/<?=$a['id']?>"><strong><?=e($a['title']?:'بدون عنوان')?></strong></a><small>يمكن مراجعة المحتوى والصورة والتصنيف والهشتاغات والترجمات.</small></td>
    <td><a href="<?=e(writer_url($a['author_username']))?>" target="_blank"><?=e($a['author_name'])?></a></td>
    <td><?php foreach(article_locales() as $code=>$language):?><small><strong><?=e(strtoupper($code))?><?=$code==='ar'?' · الأصل':''?></strong>: <?=e(article_translation_state($translationMatrix[(int)$a['id']][$code]??null))?></small><?php endforeach;?></td>
    <td><span class="status s-<?=e($a['status'])?>"><?=e($a['status'])?></span></td>
    <td><small><?=e(substr((string)($a['published_at']?:$a['updated_at']?:$a['created_at']),0,16))?></small></td>
    <td><span class="reader-heat mini <?=e(reader_heat((int)$a['views'])['class'])?>"><?=e(reader_heat((int)$a['views'])['icon'])?> <?=number_format((int)$a['views'])?></span></td>
    <td><div class="article-row-actions"><a class="btn btn-ghost table-action" href="/writer/articles/<?=$a['id']?>"><?=$a['status']==='review'?'مراجعة وتحرير':'تحرير المقال'?></a><?php if(in_array($a['status'],['published','archived'],true)):?><form method="post" action="/admin/articles/visibility" onsubmit="return <?=$a['status']==='published'?'confirm(\'إخفاء هذا المقال من الموقع العام؟\')':'true'?>"><?=csrf_field()?><input type="hidden" name="id" value="<?=$a['id']?>"><button class="btn btn-ghost table-action article-visibility-action" type="submit"><?=$a['status']==='published'?'إخفاء من الموقع':'إظهار على الموقع'?></button></form><?php endif;?></div></td>
  </tr>
  <?php endforeach;?>
  <?php if(!$articles):?><tr><td colspan="7"><div class="empty-state">لا توجد نتائج مطابقة للفلاتر الحالية.</div></td></tr><?php endif;?>
  </tbody></table></div><script>(()=>{const f=document.querySelector('[data-bulk-form]'),boxes=[...document.querySelectorAll('[data-article-select]')],all=document.querySelector('[data-select-all-box]'),toggle=document.querySelector('[data-select-all]'),count=document.querySelector('[data-selected-count]');if(!f)return;const sync=()=>{const n=boxes.filter(x=>x.checked).length;if(count)count.textContent=n+' محدد';if(all){all.checked=n===boxes.length&&n>0;all.indeterminate=n>0&&n<boxes.length}};all?.addEventListener('change',()=>{boxes.forEach(x=>x.checked=all.checked);sync()});toggle?.addEventListener('click',()=>{const next=boxes.some(x=>!x.checked);boxes.forEach(x=>x.checked=next);sync()});boxes.forEach(x=>x.addEventListener('change',sync));f.addEventListener('submit',e=>{if(!boxes.some(x=>x.checked)){e.preventDefault();alert('حدد مقالًا واحدًا على الأقل.')}});sync()})();</script>
</section>
