<?php
$kindLabel=static function(string $mime): string {
    if(str_starts_with($mime,'image/')) return 'صورة';
    if(str_starts_with($mime,'audio/')) return 'صوت';
    if(str_starts_with($mime,'video/')) return 'فيديو';
    if($mime==='application/pdf') return 'PDF';
    if($mime==='application/zip') return 'ZIP';
    return 'ملف';
};
$extOf=static fn(array $m)=>strtoupper(pathinfo((string)($m['original_name']?:$m['path']),PATHINFO_EXTENSION)?:'FILE');
?>
<section class="panel media-pro-hero">
  <div class="panel-head">
    <div><span class="panel-kicker">مكتبة الوسائط</span><h2>الصور والملفات</h2><p>ارفع من جهازك بالسحب والإفلات مثل مكتبة WordPress، ثم استخدم الملفات في الصفحات والمقالات.</p></div>
    <div class="media-view-switch"><button type="button" class="active" data-media-view="grid">شبكة</button><button type="button" data-media-view="list">قائمة</button></div>
  </div>
  <form class="media-toolbar-pro" method="get">
    <input type="hidden" name="view" value="<?=e($view??'library')?>">
    <input name="q" value="<?=e($q)?>" placeholder="ابحث باسم الملف أو العنوان أو Alt أو الوصف">
    <select name="type">
      <option value="">كل الوسائط</option>
      <option value="image" <?=$type==='image'?'selected':''?>>الصور</option>
      <option value="document" <?=$type==='document'?'selected':''?>>المستندات وPDF</option>
      <option value="audio" <?=$type==='audio'?'selected':''?>>الصوت</option>
      <option value="video" <?=$type==='video'?'selected':''?>>الفيديو</option>
      <option value="archive" <?=$type==='archive'?'selected':''?>>ZIP</option>
    </select>
    <select name="sort"><option value="newest" <?=$sort==='newest'?'selected':''?>>الأحدث</option><option value="oldest" <?=$sort==='oldest'?'selected':''?>>الأقدم</option><option value="name" <?=$sort==='name'?'selected':''?>>الاسم</option><option value="size" <?=$sort==='size'?'selected':''?>>الحجم</option></select>
    <select name="folder"><option value="0">كل المجلدات</option><?php foreach(($folders??[]) as $folder):?><option value="<?=$folder['id']?>" <?=((int)($folderId??0)===(int)$folder['id'])?'selected':''?>><?=e(($folder['parent_id']?'↳ ':'').$folder['name'])?></option><?php endforeach;?></select>
    <button class="btn btn-ghost">تطبيق</button>
  </form>
  <div class="media-library-nav"><a class="<?=$view==='library'?'active':''?>" href="/admin/media">المكتبة</a><a class="<?=$view==='recent'?'active':''?>" href="/admin/media?view=recent">المستخدمة حديثًا</a><a class="<?=$view==='trash'?'active':''?>" href="/admin/media?view=trash">سلة المحذوفات</a></div>
  <form class="media-folder-create" method="post" action="/admin/media/folders"><?=csrf_field()?><input name="name" maxlength="160" placeholder="مجلد جديد"><select name="parent_id"><option value="0">في الجذر</option><?php foreach(($folders??[]) as $folder):?><option value="<?=$folder['id']?>"><?=e($folder['name'])?></option><?php endforeach;?></select><button class="btn btn-ghost">إنشاء مجلد</button></form>
</section>

<section class="panel media-upload-pro">
  <div class="media-upload-title"><div><span class="panel-kicker">رفع جديد</span><h3>رفع من الجهاز</h3><p>صور، PDF، Word، Excel، PowerPoint، نصوص، صوت، فيديو وZIP آمن.</p></div><span class="media-upload-limit">حتى 30 ملفًا دفعة واحدة</span></div>
  <form method="post" enctype="multipart/form-data" action="/admin/media/upload" data-media-drop-form><?=csrf_field()?>
    <label class="media-drop-zone" data-media-drop-zone>
      <input type="file" name="files[]" accept="image/jpeg,image/png,image/webp,image/avif,image/gif,application/pdf,text/plain,text/csv,application/rtf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,audio/mpeg,audio/wav,audio/ogg,audio/mp4,video/mp4,video/webm,video/ogg,application/zip" multiple required>
      <span class="media-drop-icon">⇧</span><strong>اسحب الملفات هنا</strong><span>أو اضغط لاختيار الصور والملفات من جهازك</span><em data-media-file-count>لم يتم اختيار ملفات</em>
    </label>
    <div class="media-single-meta"><label>عنوان اختياري للملف المفرد<input name="title"></label><label>Alt للصورة المفردة<input name="alt_text"></label><label>المجلد<select name="folder_id"><option value="0">الجذر</option><?php foreach(($folders??[]) as $folder):?><option value="<?=$folder['id']?>"><?=e(($folder['parent_id']?'↳ ':'').$folder['name'])?></option><?php endforeach;?></select></label><button class="btn btn-primary">رفع إلى المكتبة</button></div>
  </form>
</section>

<form id="bulk-media-delete" method="post" action="/admin/media/bulk-delete" data-media-library-form><?=csrf_field()?></form>
<div class="media-bulk-bar"><span><b data-media-selected-count>0</b> محدد</span><?php if((auth_user()['role']??'')==='admin'):?><button class="btn btn-danger" type="submit" form="bulk-media-delete" onclick="return confirm('حذف الملفات المحددة نهائيًا؟')">حذف المحدد</button><?php endif;?></div>

<section class="media-library-grid media-pro-grid" data-media-library>
<?php foreach($media as $m): $used=!empty($usedPaths[(string)$m['path']]); $isImage=str_starts_with((string)$m['mime'],'image/'); ?>
  <article class="media-library-card media-pro-card" data-media-card>
    <div class="media-card-preview <?=$isImage?'':'media-file-preview'?>">
      <label class="media-select"><input type="checkbox" name="ids[]" value="<?=$m['id']?>" form="bulk-media-delete" data-media-select><span></span></label>
      <?php if($isImage):?>
        <img src="<?=e($m['path'])?>" alt="<?=e($m['alt_text']??'')?>" loading="lazy">
      <?php else:?><a class="media-file-tile" href="<?=e($m['path'])?>" target="_blank" rel="noopener"><b><?=e($extOf($m))?></b><span><?=e($kindLabel((string)$m['mime']))?></span></a><?php endif;?>
      <span class="media-used-badge <?=$used?'used':'unused'?>"><?=$used?'مستخدم':'غير مستخدم'?></span>
      <?php if($isImage):?><button type="button" class="media-preview-btn" data-media-preview data-src="<?=e($m['path'])?>" data-title="<?=e($m['title']?:$m['original_name'])?>">معاينة</button><?php else:?><a class="media-preview-btn" href="<?=e($m['path'])?>" target="_blank" rel="noopener">فتح الملف</a><?php endif;?>
    </div>
    <div class="media-library-copy">
      <strong><?=e($m['title']?:$m['original_name']?:basename($m['path']))?></strong>
      <small><?=$isImage?e(($m['width']??'?').'×'.($m['height']??'?')).' · ':''?><?=number_format(((int)($m['size_bytes']??0))/1024,1)?> KB · <?=e($kindLabel((string)$m['mime']))?></small>
      <div class="media-path-row"><input readonly value="<?=e($m['path'])?>"><button type="button" data-copy-media="<?=e($m['path'])?>">نسخ الرابط</button></div><?php $locations=$mediaUsage[(int)$m['id']]??[]; ?><details class="media-usage-details"><summary>أماكن استخدام الملف (<?=count($locations)?>)</summary><?php if($locations): ?><ul><?php foreach($locations as $location): ?><li><a href="<?=e($location['url'])?>"><?=e($location['label'])?></a></li><?php endforeach; ?></ul><?php else: ?><p>لم يُعثر على استخدام مسجل لهذا الملف.</p><?php endif; ?></details>
      <form method="post" action="/admin/media/update" class="form-stack mini-form media-meta-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=$m['id']?>"><label>العنوان<input name="title" value="<?=e($m['title']??'')?>"></label><?php if($isImage):?><label>Alt Text<input name="alt_text" value="<?=e($m['alt_text']??'')?>"></label><?php endif;?><label>Caption<input name="caption" value="<?=e($m['caption']??'')?>"></label><label>الوصف<textarea name="description" rows="2"><?=e($m['description']??'')?></textarea></label><button class="btn btn-ghost">حفظ البيانات</button></form>
      <?php if(!empty($m['deleted_at'])):?><form method="post" action="/admin/media/restore" class="media-delete-form"><?=csrf_field()?><input type="hidden" name="id" value="<?=$m['id']?>"><button class="btn btn-primary" type="submit">استعادة الملف</button></form>
      <?php else:?><form method="post" action="/admin/media/delete" class="media-delete-form" onsubmit="return confirm('نقل هذا الملف إلى سلة المحذوفات؟<?= $used?' الملف مستخدم حاليًا في الموقع.':'' ?>')"><?=csrf_field()?><input type="hidden" name="id" value="<?=$m['id']?>"><button class="btn btn-danger" type="submit">نقل إلى السلة</button></form><?php endif;?>
    </div>
  </article>
<?php endforeach;?>
<?php if(!$media):?><div class="empty-state big"><strong>لا توجد ملفات مطابقة.</strong><span>ارفع أول ملف من جهازك أو غيّر عوامل التصفية.</span></div><?php endif;?>
</section>
<div class="media-preview-modal" data-media-preview-modal hidden><button type="button" data-media-preview-close aria-label="إغلاق">×</button><img src="" alt=""><strong></strong></div>
