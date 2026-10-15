<?php $filters=$filters??['q'=>'','category'=>0,'status'=>'','sort'=>'date_desc']; ?>
<div class="dash-actions"><a class="btn btn-primary" href="/admin/podcast/new">＋ حلقة جديدة</a><a class="btn btn-ghost" href="/podcast" target="_blank" rel="noopener">عرض صفحة البودكاست</a></div>
<section class="panel article-admin-panel">
  <div class="panel-head"><div><span class="panel-kicker">إدارة الحلقات</span><h2>البودكاست</h2></div><span><?=count($episodes)?> نتيجة</span></div>
  <form class="article-filter-bar" method="get" action="/admin/podcast">
    <label class="article-search"><span>⌕</span><input type="search" name="q" value="<?=e($filters['q'])?>" placeholder="ابحث بعنوان الحلقة أو الرابط..."></label>
    <select name="category"><option value="0">كل التصنيفات</option><?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=((int)$filters['category']===(int)$c['id'])?'selected':''?>><?=e($c['name']?:'بدون اسم')?></option><?php endforeach;?></select>
    <select name="status"><option value="">كل الحالات</option><option value="published" <?=$filters['status']==='published'?'selected':''?>>منشورة</option><option value="draft" <?=$filters['status']==='draft'?'selected':''?>>مسودة</option></select>
    <select name="sort"><option value="date_desc" <?=$filters['sort']==='date_desc'?'selected':''?>>التاريخ: الأحدث</option><option value="date_asc" <?=$filters['sort']==='date_asc'?'selected':''?>>التاريخ: الأقدم</option><option value="episode_desc" <?=$filters['sort']==='episode_desc'?'selected':''?>>رقم الحلقة: تنازلي</option><option value="episode_asc" <?=$filters['sort']==='episode_asc'?'selected':''?>>رقم الحلقة: تصاعدي</option><option value="name_asc" <?=$filters['sort']==='name_asc'?'selected':''?>>الاسم: أ → ي</option><option value="name_desc" <?=$filters['sort']==='name_desc'?'selected':''?>>الاسم: ي → أ</option></select>
    <button class="btn btn-primary">تطبيق</button><a class="btn btn-ghost" href="/admin/podcast">مسح</a>
  </form>
  <div class="table-wrap"><table><thead><tr><th>الحلقة</th><th>التصنيف</th><th>المقال</th><th>الصوت</th><th>الحالة</th><th>التاريخ</th><th>إجراء</th></tr></thead><tbody>
  <?php foreach($episodes as $ep):?>
    <tr>
      <td><a href="/admin/podcast/<?=$ep['id']?>"><strong><?=e($ep['title'])?></strong></a><small><?=e('/podcast/'.$ep['slug'])?><?=!empty($ep['episode_number'])?' · #'.(int)$ep['episode_number']:''?><?=!empty($ep['duration'])?' · '.e($ep['duration']):''?></small></td>
      <td><?=e($ep['category_name']?:'—')?></td>
      <td><?php if($ep['article_title']):?><a href="<?=e(locale_url(default_locale(),'article/'.$ep['article_slug']))?>" target="_blank"><?=e($ep['article_title'])?></a><?php else:?>—<?php endif;?></td>
      <td><span class="status"><?=e($ep['audio_source'])?></span></td>
      <td><span class="status s-<?=e($ep['status'])?>"><?=e($ep['status'])?></span></td>
      <td><small><?=e(substr((string)($ep['published_at']?:$ep['updated_at']?:$ep['created_at']),0,16))?></small></td>
      <td><a class="btn btn-ghost table-action" href="/admin/podcast/<?=$ep['id']?>">تحرير</a></td>
    </tr>
  <?php endforeach;?>
  <?php if(!$episodes):?><tr><td colspan="7"><div class="empty-state">لا توجد حلقات مطابقة للفلاتر الحالية.</div></td></tr><?php endif;?>
  </tbody></table></div>
</section>
