<?php
$iconNames=navigation_icon_names();
$hIndex=array_flip(array_values($headerMenu));
$fIndex=array_flip(array_values($footerMenu));
$mobileMenu=$mobileMenu??json_decode(setting('mobile_menu_json',''),true);
if(!is_array($mobileMenu)) $mobileMenu=$headerMenu;
$mIndex=array_flip(array_values($mobileMenu));
?>
<form method="post" action="/admin/navigation/save" class="navigation-admin">
<?=csrf_field()?>
<section class="panel appearance-hero">
  <div><span class="panel-kicker">التنقل</span><h2>القوائم والتنقل</h2><p>حدد ما يظهر في الهيدر والفوتر وترتيبه وأيقونته. أي صفحة تنشرها من قسم الصفحات تظهر هنا تلقائيًا لتختار مكانها.</p></div>
  <button class="btn btn-primary" type="submit">حفظ القوائم</button>
</section>
<section class="panel">
  <div class="panel-head"><div><span class="panel-kicker">العناصر الجاهزة والصفحات</span><h2>روابط الموقع</h2></div><a class="btn btn-ghost" href="/admin/pages">إدارة الصفحات</a></div>
  <div class="table-wrap"><table class="navigation-table"><thead><tr><th>العنصر</th><th>الأيقونة</th><th>اللغات</th><th>الهيدر</th><th>ترتيب الهيدر</th><th>الفوتر</th><th>ترتيب الفوتر</th></tr></thead><tbody>
  <?php foreach($catalog as $key=>$item): if(str_starts_with((string)$key,'custom:')) continue; ?>
    <tr>
      <td><strong><?=e($item['label'])?></strong><small class="nav-url-preview"><?=e($item['url'])?></small></td>
      <td><select name="nav_icon[<?=e($key)?>]"><?php foreach($iconNames as $icon=>$label):?><option value="<?=e($icon)?>" <?=($iconOverrides[$key]??$item['icon']??'link')===$icon?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></td>
      <td><div class="nav-locale-checks"><?php foreach(enabled_locales() as $lc=>$li): $selected=$localeVisibility[$key]??array_keys(enabled_locales()); if($key==='community')$selected=['ar']; ?><label class="check"><input type="checkbox" name="nav_locales[<?=e($key)?>][]" value="<?=e($lc)?>" <?=in_array($lc,$selected,true)?'checked':''?> <?=$key==='community'&&$lc!=='ar'?'disabled':''?>> <?=e(language_name($lc))?></label><?php endforeach;?></div></td>
      <td><label class="check"><input type="checkbox" name="nav_header[<?=e($key)?>]" <?=isset($hIndex[$key])?'checked':''?>> يظهر</label></td>
      <td><input type="number" name="nav_header_order[<?=e($key)?>]" value="<?=isset($hIndex[$key])?($hIndex[$key]+1)*10:80?>" min="0" max="999"></td>
      <td><label class="check"><input type="checkbox" name="nav_footer[<?=e($key)?>]" <?=isset($fIndex[$key])?'checked':''?>> يظهر</label></td>
      <td><input type="number" name="nav_footer_order[<?=e($key)?>]" value="<?=isset($fIndex[$key])?($fIndex[$key]+1)*10:80?>" min="0" max="999"></td>
      <td><label class="check"><input type="checkbox" name="nav_mobile[<?=e($key)?>]" <?=isset($mIndex[$key])?'checked':''?>> جوال</label></td>
      <td><input type="number" name="nav_mobile_order[<?=e($key)?>]" value="<?=isset($mIndex[$key])?($mIndex[$key]+1)*10:80?>" min="0" max="999" aria-label="ترتيب الجوال"></td>
    </tr>
  <?php endforeach;?>
  </tbody></table></div>
</section>
<section class="panel nav-button-builder">
  <div class="panel-head"><div><span class="panel-kicker">عناصر وأزرار مخصصة</span><h2>إضافة أزرار للهيدر والفوتر</h2><p>يمكن أن يكون العنصر رابطًا عاديًا أو زر CTA مخصصًا بالكامل، مع تحكم مستقل في الجوال والكمبيوتر.</p></div><span>حتى 16 عنصرًا</span></div>
  <div class="custom-nav-grid">
  <?php foreach($slots as $i=>$row): $key='custom:slot'.$i; ?>
    <article class="custom-nav-card nav-custom-card">
      <div class="nav-custom-card-head"><strong>العنصر <?=$i?></strong><span><?=!empty($row['label'])?e($row['label']):'فارغ'?></span></div>
      <div class="form-grid nav-custom-main"><label>الاسم الإداري<input type="text" name="custom[<?=$i?>][label]" value="<?=e($row['label']??'')?>" placeholder="اسم داخلي/احتياطي"></label><label>الرابط<input type="text" name="custom[<?=$i?>][url]" value="<?=e($row['url']??'')?>" placeholder="/send-story أو https://..."></label><label>الأيقونة<select name="custom[<?=$i?>][icon]"><?php foreach($iconNames as $icon=>$label):?><option value="<?=e($icon)?>" <?=($row['icon']??'link')===$icon?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label><label>نوع العنصر<select name="custom[<?=$i?>][style_type]"><option value="link" <?=($row['style_type']??'link')==='link'?'selected':''?>>رابط</option><option value="button" <?=($row['style_type']??'link')==='button'?'selected':''?>>زر CTA</option></select></label><label>الظهور على الأجهزة<select name="custom[<?=$i?>][display]"><option value="both" <?=($row['display']??'both')==='both'?'selected':''?>>الجوال والكمبيوتر</option><option value="desktop" <?=($row['display']??'')==='desktop'?'selected':''?>>الكمبيوتر فقط</option><option value="mobile" <?=($row['display']??'')==='mobile'?'selected':''?>>الجوال فقط</option></select></label><label>الحجم<select name="custom[<?=$i?>][size]"><option value="sm" <?=($row['size']??'md')==='sm'?'selected':''?>>صغير</option><option value="md" <?=($row['size']??'md')==='md'?'selected':''?>>متوسط</option><option value="lg" <?=($row['size']??'md')==='lg'?'selected':''?>>كبير</option></select></label></div>
      <div class="nav-label-translations"><strong>نص الزر/الرابط حسب اللغة</strong><div class="translation-inline-grid"><?php foreach(enabled_locales() as $lc=>$li): $localized=$row['labels'][$lc]??($lc===default_locale()?($row['label']??''):''); ?><label><span><?=e(language_name($lc))?> <small><?=e(strtoupper($lc))?></small></span><input type="text" name="custom[<?=$i?>][labels][<?=e($lc)?>]" value="<?=e($localized)?>" placeholder="نص العنصر بهذه اللغة"></label><?php endforeach;?></div></div>
      <div class="nav-style-grid"><label>الخلفية<input type="color" name="custom[<?=$i?>][bg]" value="<?=e($row['bg']??'#157176')?>"></label><label>النص<input type="color" name="custom[<?=$i?>][text_color]" value="<?=e($row['text_color']??'#ffffff')?>"></label><label>Hover<input type="color" name="custom[<?=$i?>][hover_bg]" value="<?=e($row['hover_bg']??'#0f5f63')?>"></label><label>الحد<input type="color" name="custom[<?=$i?>][border_color]" value="<?=e($row['border_color']??'#157176')?>"></label><label>تدوير الحواف<input type="number" min="0" max="40" name="custom[<?=$i?>][radius]" value="<?=e($row['radius']??10)?>"></label></div>
      <label>اللغات<div class="nav-locale-checks"><?php foreach(enabled_locales() as $lc=>$li): $selected=$row['locales']??array_keys(enabled_locales()); ?><label class="check"><input type="checkbox" name="custom[<?=$i?>][locales][]" value="<?=e($lc)?>" <?=in_array($lc,$selected,true)?'checked':''?>> <?=e(language_name($lc))?></label><?php endforeach;?></div></label>
      <div class="custom-nav-options"><label class="check"><input type="checkbox" name="custom[<?=$i?>][header]" <?=isset($hIndex[$key])?'checked':''?>> الهيدر</label><input type="number" name="custom[<?=$i?>][header_order]" value="<?=isset($hIndex[$key])?($hIndex[$key]+1)*10:100?>"><label class="check"><input type="checkbox" name="custom[<?=$i?>][footer]" <?=isset($fIndex[$key])?'checked':''?>> الفوتر</label><input type="number" name="custom[<?=$i?>][footer_order]" value="<?=isset($fIndex[$key])?($fIndex[$key]+1)*10:100?>"></div>
      <label class="check"><input type="checkbox" name="custom[<?=$i?>][new_tab]" <?=!empty($row['new_tab'])?'checked':''?>> فتح في نافذة جديدة</label>
    </article>
  <?php endforeach;?>
  </div>
</section>
<div class="dash-actions"><button class="btn btn-primary" type="submit">حفظ وتطبيق على الموقع</button></div>
</form>
