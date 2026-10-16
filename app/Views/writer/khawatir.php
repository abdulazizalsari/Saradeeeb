<?php $labels=['draft'=>'مسودة','review'=>'قيد المراجعة','changes'=>'تحتاج تعديلاً','rejected'=>'مرفوضة','published'=>'منشورة']; ?>
<div class="dash-actions"><a class="btn btn-primary" href="/writer/khawatir/new">أضف خاطرة</a><a class="btn btn-ghost" target="_blank" href="/khawatir">عرض خواطر</a></div>
<section class="panel kh-writer-panel">
  <div class="panel-head"><div><span class="panel-kicker">مساحة الكاتب</span><h2>خواطري</h2><p>تابع المسودات والمراجعات والمنشورات من مكان واحد.</p></div><span><?=count($thoughts)?> خاطرة</span></div>
  <nav class="kh-status-tabs"><a class="<?=$status===''?'active':''?>" href="/writer/khawatir">الكل</a><?php foreach($labels as $key=>$label):?><a class="<?=$status===$key?'active':''?>" href="/writer/khawatir?status=<?=$key?>"><?=$label?></a><?php endforeach;?></nav>
  <?php if($thoughts):?><div class="kh-writer-list"><?php foreach($thoughts as $item):?>
    <article><div><span class="status s-<?=e($item['status'])?>"><?=e($labels[$item['status']]??$item['status'])?></span><?php if($item['category_name']):?><small><?=e($item['category_name'])?></small><?php endif;?><p><?=e($item['body'])?></p><?php if($item['review_note']):?><em>ملاحظة التحرير: <?=e($item['review_note'])?></em><?php endif;?></div><div class="kh-row-actions"><a class="btn btn-ghost" href="/writer/khawatir/<?=$item['id']?>">تحرير / معاينة</a><?php if($item['status']==='published'):?><a class="btn btn-primary" target="_blank" href="/khawatir/<?=e($item['slug'])?>">عرض المنشور</a><?php endif;?></div></article>
  <?php endforeach;?></div><?php else:?><div class="empty-state"><h3>لا توجد خواطر هنا</h3><p>ابدأ بنص قصير، وسترى بطاقته قبل إرساله للمراجعة.</p><a class="btn btn-primary" href="/writer/khawatir/new">أضف خاطرة</a></div><?php endif;?>
</section>
