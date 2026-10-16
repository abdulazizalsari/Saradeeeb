<?php $c=static fn(string $k,string $d='0')=>(($map[$k]??$d)==='1'); $v=static fn(string $k,string $d='')=>(string)($map[$k]??$d); ?>
<form method="post" action="/admin/protection/save" class="settings-v4"><?=csrf_field()?>
<section class="settings-hero panel"><div><span class="panel-kicker">Content Protection</span><h2>حماية المحتوى والصور</h2><p>هذه الخيارات تقلل النسخ والتحميل المباشر للمستخدم العادي. لا يمكن لأي موقع ويب منع لقطة الشاشة أو أدوات المطور بنسبة 100%.</p></div><button class="btn btn-primary">حفظ</button></section>
<section class="panel appearance-card"><div class="toggle-grid">
<label class="switch-row"><input type="checkbox" name="protect_copy" <?=$c('protect_copy','1')?'checked':''?>><span><strong>منع النسخ</strong><small>تعطيل Ctrl+C وحدث copy.</small></span></label>
<label class="switch-row"><input type="checkbox" name="protect_context" <?=$c('protect_context','1')?'checked':''?>><span><strong>منع الزر الأيمن</strong><small>تقليل حفظ الصور بالطريقة المعتادة.</small></span></label>
<label class="switch-row"><input type="checkbox" name="protect_select" <?=$c('protect_select','1')?'checked':''?>><span><strong>منع تحديد النص</strong></span></label>
<label class="switch-row"><input type="checkbox" name="protect_drag" <?=$c('protect_drag','1')?'checked':''?>><span><strong>منع سحب الصور</strong></span></label>
<label class="switch-row"><input type="checkbox" name="protect_save_shortcut" <?=$c('protect_save_shortcut','1')?'checked':''?>><span><strong>تعطيل Ctrl+S</strong></span></label>
<label class="switch-row"><input type="checkbox" name="protect_print" <?=$c('protect_print','1')?'checked':''?>><span><strong>منع الطباعة</strong><small>إخفاء محتوى الصفحة عند الطباعة.</small></span></label>
<label class="switch-row"><input type="checkbox" name="protect_image_overlay" <?=$c('protect_image_overlay','0')?'checked':''?>><span><strong>علامة مائية مرئية</strong><small>تظهر طبقة خفيفة فوق صور المقالات لردع إعادة الاستخدام.</small></span></label>
</div><label>نص العلامة المائية<input name="protect_watermark_text" value="<?=e($v('protect_watermark_text',setting('site_name','سراديب')))?>"></label></section>
<div class="settings-save-bar"><button class="btn btn-primary">حفظ الحماية</button></div></form>
