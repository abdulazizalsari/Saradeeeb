<?php
use App\Core\Integrations;
$serviceIcons=[
    'search_console'=>'⌕','google_analytics'=>'GA','google_tag_manager'=>'GTM','meta_pixel'=>'M','tiktok_pixel'=>'TT','adsense'=>'Ad'
];
$consentLabels=['analytics'=>'التحليلات','marketing'=>'التسويق','ads'=>'الإعلانات'];
$slotLabels=[
    'below_header'=>'أسفل الهيدر',
    'home_top'=>'الرئيسية — أعلى المحتوى',
    'home_after_featured'=>'الرئيسية — بعد البطاقات المميزة',
    'home_between_sections'=>'الرئيسية — بين الأقسام',
    'article_top'=>'المقال — أعلى المقال',
    'article_sidebar'=>'المقال — الشريط الجانبي',
    'inside_article_3'=>'داخل المقال — بعد الفقرة 3',
    'inside_article_7'=>'داخل المقال — بعد الفقرة 7',
    'inside_article_end'=>'نهاية المقال',
    'footer_top'=>'قبل الفوتر',
];
?>
<section class="integration-hero panel">
  <div>
    <span class="panel-kicker">إدارة مركزية وآمنة</span>
    <h2>التكاملات والتتبع</h2>
    <p>اربط خدمات القياس والفهرسة والإعلانات كل خدمة بشكل مستقل. إيقاف أو تعديل خدمة لا يغيّر أي تكامل آخر.</p>
  </div>
  <div class="integration-hero-status"><strong><?=count(array_filter($records,fn($r)=>!empty($r['enabled'])))?></strong><span>تكاملات مفعّلة</span></div>
</section>

<section class="integration-grid">
<?php foreach($registry as $key=>$spec): $record=$records[$key]??['enabled'=>0,'config'=>[]]; $configured=Integrations::configured($key,$record); $cfg=$record['config']??[]; ?>
<article class="panel integration-card <?=$record['enabled']?'is-enabled':''?>">
  <div class="integration-card-head">
    <div class="integration-service-icon"><?=e($serviceIcons[$key]??'↗')?></div>
    <div class="integration-title-wrap"><h3><?=e($spec['name'])?></h3><p><?=e($spec['description'])?></p></div>
    <span class="integration-state <?=$configured?'ready':'needs-setup'?>"><?=$configured?'مضبوط':'يحتاج إعداد'?></span>
  </div>
  <?php if(!empty($spec['consent'])):?><div class="integration-consent-tag">يعمل بعد موافقة: <?=e($consentLabels[$spec['consent']]??$spec['consent'])?></div><?php endif;?>
  <form method="post" action="/admin/integrations/save" class="integration-form">
    <?=csrf_field()?><input type="hidden" name="service" value="<?=e($key)?>">
    <?php foreach($spec['fields'] as $fieldKey=>$field): $value=(string)($cfg[$fieldKey]??''); ?>
      <?php if(($field['type']??'text')==='checkbox'): ?>
        <label class="switch-row"><span><strong><?=e($field['label'])?></strong><small>يمكنك تغييره دون التأثير على بقية إعدادات الخدمة.</small></span><input type="checkbox" name="<?=e($fieldKey)?>" value="1" <?=!empty($cfg[$fieldKey])?'checked':''?>></label>
      <?php else: ?>
        <label><?=e($field['label'])?><?php if(!empty($field['required'])):?><em>*</em><?php endif;?>
          <input name="<?=e($fieldKey)?>" autocomplete="off" value="<?=empty($field['masked_after_save'])?e($value):''?>" placeholder="<?=e(!empty($field['masked_after_save'])&&$value!==''?'محفوظ: '.Integrations::maskValue($value):($field['placeholder']??''))?>">
        </label>
      <?php endif; ?>
    <?php endforeach;?>
    <div class="integration-help"><strong>من أين أحصل عليه؟</strong><span><?=e($spec['docs'])?></span></div>
    <?php if($key==='search_console'):?><div class="integration-inline-note"><span>خريطة الموقع:</span><code><?=e(url('/sitemap.xml'))?></code><small>بعد التحقق من الملكية أضف هذا الرابط داخل قسم Sitemaps في Search Console.</small></div><?php endif;?>
    <?php if($key==='adsense'):?><div class="integration-inline-note"><span>أماكن الإعلانات:</span><small>إدارة الأكواد والحملات والمواضع التفصيلية تبقى داخل <a href="/admin/ads">مركز الإعلانات</a>. إعداد AdSense هنا مستقل عنها.</small></div><?php endif;?>
    <div class="integration-card-actions"><label class="integration-enable"><input type="checkbox" name="enabled" value="1" <?=$record['enabled']?'checked':''?>><span>تشغيل التكامل</span></label><button class="btn btn-primary" type="submit">حفظ</button></div>
  </form>
</article>
<?php endforeach;?>
</section>

<section class="integrations-lower-grid">
  <article class="panel integration-consent-panel">
    <div class="panel-head"><div><span class="panel-kicker">الخصوصية</span><h2>موافقة ملفات الارتباط والتتبع</h2></div></div>
    <p class="settings-help">عند تشغيلها لن تُحمّل Analytics أو Pixels أو أكواد الإعلانات غير الضرورية قبل موافقة الزائر. يستطيع الزائر تغيير اختياره لاحقًا من الفوتر.</p>
    <form method="post" action="/admin/integrations/consent" class="form-grid"><?=csrf_field()?>
      <label class="span-2 check switch-row"><span><strong>تفعيل نافذة موافقة الخصوصية</strong><small>معطلة افتراضيًا. عند تعطيلها لا تُحمّل ملفات النافذة ولا يظهر أي Overlay للزوار.</small></span><input type="checkbox" name="enabled" value="1" <?=($consent['enabled']??'0')==='1'?'checked':''?>></label>
      <label>إصدار الموافقة<input name="version" value="<?=e($consent['version']??'1')?>" placeholder="1"></label>
      <label>رابط سياسة الخصوصية<input name="policy_url" value="<?=e($consent['policy_url']??'/page/privacy-policy')?>"></label>
      <label class="span-2">عنوان النافذة<input name="title" value="<?=e($consent['title']??'خصوصيتك تهمنا')?>"></label>
      <label class="span-2">النص<textarea name="message" rows="4"><?=e($consent['message']??'')?></textarea></label>
      <div class="span-2 privacy-note"><strong>عند تغيير السياسة:</strong> غيّر رقم «إصدار الموافقة» لإظهار الاختيارات للزائر من جديد.</div>
      <button class="btn btn-primary" type="submit">حفظ إعدادات الخصوصية</button>
    </form>
  </article>

  <article class="panel integration-ad-slots-panel">
    <div class="panel-head"><div><span class="panel-kicker">الإعلانات</span><h2>تشغيل مواضع الإعلانات</h2></div><a class="btn btn-ghost" href="/admin/ads">مركز الإعلانات</a></div>
    <p class="settings-help">هذا مفتاح عام لكل موضع. إيقاف موضع يمنع عرضه حتى لو كانت هناك حملة نشطة داخله.</p>
    <form method="post" action="/admin/integrations/ad-slots"><?=csrf_field()?>
      <div class="ad-slot-toggle-list"><?php foreach($slotLabels as $slot=>$label):?><label><span><strong><?=e($label)?></strong><code><?=e($slot)?></code></span><input type="checkbox" name="slots[<?=e($slot)?>]" value="1" <?=!empty($slotMap[$slot])?'checked':''?>></label><?php endforeach;?></div>
      <button class="btn btn-primary" type="submit">حفظ مواضع الإعلانات</button>
    </form>
  </article>
</section>

<section class="panel custom-code-panel">
  <div class="panel-head"><div><span class="panel-kicker">للمشرف فقط</span><h2>كود مخصص</h2></div><span class="danger-chip">صلاحية عالية الخطورة</span></div>
  <div class="custom-code-warning"><strong>تنبيه أمني</strong><p>الكود هنا ينفّذ داخل صفحات الموقع. استخدمه فقط لخدمة موثوقة. يتم رفض PHP و eval و document.write وبعض الأنماط الخطرة، ولا يستطيع المدير المساعد أو الكاتب الوصول إلى هذا القسم.</p></div>
  <form method="post" action="/admin/integrations/custom/save" class="form-grid custom-code-form"><?=csrf_field()?>
    <label>اسم داخلي<input name="name" required placeholder="مثال: أداة قياس خارجية"></label>
    <label>مكان التحميل<select name="location"><option value="head">داخل &lt;head&gt;</option><option value="body_start">بداية body</option><option value="body_end" selected>نهاية body</option></select></label>
    <label>فئة الموافقة<select name="consent_category"><option value="analytics">تحليلات</option><option value="marketing" selected>تسويق</option><option value="ads">إعلانات</option><option value="essential">ضروري — لا يحتاج موافقة</option></select></label>
    <label class="check"><input type="checkbox" name="active" value="1" checked> مفعّل</label>
    <label class="span-2">الكود<textarea name="code" rows="9" dir="ltr" required placeholder="<script>...</script>"></textarea></label>
    <label class="span-2 risk-confirm"><input type="checkbox" name="confirm_risk" value="1" required> أؤكد أن الكود من مصدر موثوق وأنني أفهم أنه قد يؤثر على الخصوصية والأمان والأداء.</label>
    <button class="btn btn-primary" type="submit">حفظ الكود المخصص</button>
  </form>
  <?php if($customCodes):?><div class="custom-code-list"><?php foreach($customCodes as $row):?><article><div><span class="status <?=$row['active']?'s-active':'s-blocked'?>"><?=$row['active']?'مفعّل':'متوقف'?></span><strong><?=e($row['name'])?></strong><small><?=e($row['location'])?> · <?=e($row['consent_category'])?></small></div><div class="custom-code-actions"><form method="post" action="/admin/integrations/custom/toggle"><?=csrf_field()?><input type="hidden" name="id" value="<?=$row['id']?>"><button class="btn btn-ghost" type="submit"><?=$row['active']?'إيقاف':'تشغيل'?></button></form><form method="post" action="/admin/integrations/custom/delete" onsubmit="return confirm('حذف هذا الكود نهائيًا؟')"><?=csrf_field()?><input type="hidden" name="id" value="<?=$row['id']?>"><button class="btn btn-danger" type="submit">حذف</button></form></div></article><?php endforeach;?></div><?php endif;?>
</section>
