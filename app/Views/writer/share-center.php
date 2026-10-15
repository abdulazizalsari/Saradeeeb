<?php
$profileUrl=writer_url($u['username'],default_locale());
$type=(string)($prefs['card_size']??'post');
$type=['square'=>'post','horizontal'=>'business','compact'=>'business'][$type]??$type;
if(!in_array($type,['post','business','pdf'],true))$type='post';
$fixedTemplates=['post'=>'cinematic','business'=>'executive','pdf'=>'editorial'];
$template=$fixedTemplates[$type];
$cardLogo=trim((string)setting('brand_logo',''));
$title=(string)($profile['professional_title']??'');
$summary=trim((string)($profile['cv_summary']??$u['bio']??''));
$quote=trim((string)($prefs['quote_text']??''));
$fallbackQuote=$summary!==''?mb_substr($summary,0,180,'UTF-8'):'أكتب لأترك للمعنى أثرًا أبعد من الكلمات.';
$quote=$quote!==''?$quote:$fallbackQuote;
$avatar=(string)($u['avatar']??'');
$joined=substr((string)($u['created_at']??date('Y')),0,4);
$socials=array_filter(['website'=>$profile['website']??'','linkedin'=>$profile['linkedin']??'','portfolio'=>$profile['portfolio_url']??'']);
$contact=!empty($profile['show_contact'])?(string)($profile['contact_email']??''):'';
$jsonList=function(string $key) use ($profile): array {$value=json_decode((string)($profile[$key.'_json']??'[]'),true);return is_array($value)?array_values(array_filter(array_map('trim',$value))):[];};
$skills=$jsonList('skills');$experience=$jsonList('experience');$education=$jsonList('education');$achievements=$jsonList('achievements');
$cs=is_array($cardStyle??null)?$cardStyle:[];
$sv=fn(string $k,mixed $d)=>$cs[$k]??$d;
$fontStacks=[
 'plex'=>'"IBM Plex Sans Arabic","Segoe UI",Tahoma,Arial,sans-serif',
 'tajawal'=>'"Tajawal","Segoe UI",Tahoma,Arial,sans-serif',
 'kufi'=>'"Noto Kufi Arabic","Segoe UI",Tahoma,Arial,sans-serif',
 'tahoma'=>'Tahoma,"Segoe UI",Arial,sans-serif',
 'serif'=>'Georgia,"Times New Roman",serif',
];
$fontStack=$fontStacks[$sv('font_family','plex')]??$fontStacks['plex'];
$hexToRgba=static function(string $hex,float $opacity): string {
    $hex=ltrim($hex,'#'); if(strlen($hex)!==6)$hex='031c1f';
    $r=hexdec(substr($hex,0,2));$g=hexdec(substr($hex,2,2));$b=hexdec(substr($hex,4,2));
    return "rgba($r,$g,$b,".max(0,min(1,$opacity)).")";
};
$cardCss='--wc-bg:'.e((string)$sv('background','#031c1f')).';'.
 '--wc-overlay:'.$hexToRgba((string)$sv('overlay_color','#031c1f'),((int)$sv('overlay_opacity',64))/100).';'.
 '--wc-accent:'.e((string)$sv('accent','#69d5d8')).';'.
 '--wc-primary:'.e((string)$sv('primary_text','#ffffff')).';'.
 '--wc-secondary:'.e((string)$sv('secondary_text','#bfd3d5')).';'.
 '--wc-panel:'.$hexToRgba((string)$sv('panel_color','#031c1f'),((int)$sv('panel_opacity',70))/100).';'.
 '--wc-border:'.e((string)$sv('border_color','#69d5d8')).';'.
 '--wc-radius:'.(int)$sv('card_radius',34).'px;'.
 '--wc-padding:'.(int)$sv('card_padding',6).'%;'.
 '--wc-gap:'.(int)$sv('card_gap',12).'px;'.
 '--wc-panel-radius:'.(int)$sv('panel_radius',28).'px;'.
 '--wc-name-size:'.(int)$sv('name_size',42).'px;'.
 '--wc-name-weight:'.(int)$sv('name_weight',700).';'.
 '--wc-title-size:'.(int)$sv('title_size',15).'px;'.
 '--wc-title-weight:'.(int)$sv('title_weight',700).';'.
 '--wc-quote-size:'.(int)$sv('quote_size',13).'px;'.
 '--wc-quote-weight:'.(int)$sv('quote_weight',500).';'.
 '--wc-quote-line:'.(((int)$sv('quote_line_height',180))/100).';'.
 '--wc-handle-size:'.(int)$sv('handle_size',15).'px;'.
 '--wc-avatar-border:'.(int)$sv('avatar_border',4).'px;'.
 '--wc-avatar-radius:'.(int)$sv('avatar_radius',50).'%;'.
 '--wc-qr-size:'.(int)$sv('qr_size',118).'px;'.
 '--wc-logo-width:'.(int)$sv('logo_width',150).'px;'.
 '--wc-shadow-alpha:'.(((int)$sv('shadow_strength',42))/100).';'.
 '--wc-watermark-opacity:'.(((int)$sv('watermark_opacity',10))/100).';'.
 '--wc-scene-y:'.(int)$sv('scene_position_y',65).'%;'.
 '--wc-scene-brightness:'.(int)$sv('scene_brightness',92).'%;'.
 '--wc-scene-saturation:'.(int)$sv('scene_saturation',105).'%;'.
 '--wc-font:'.$fontStack.';';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@300..900&family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/writer-card-brand.css?v=18">
<style>
.writer-card-studio-v2{--studio-line:#d7e5e6;--studio-soft:#f4f9f9;--studio-ink:#0b3033;--studio-accent:#127d82}.writer-card-studio-v2 .writer-studio-head{margin-bottom:18px}.writer-card-studio-v2 .writer-design-workspace{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,.7fr);gap:20px;align-items:start}.writer-card-studio-v2 .writer-design-preview{position:sticky;top:18px;min-height:620px}.writer-card-designer{display:grid;gap:12px;min-width:0}.designer-intro{padding:14px 16px;border:1px solid var(--studio-line);border-radius:16px;background:linear-gradient(145deg,#f9fcfc,#eef7f7);color:#31565a;font-size:12px;line-height:1.8}.designer-presets{display:flex;gap:8px;flex-wrap:wrap}.designer-presets button{border:1px solid var(--studio-line);border-radius:999px;background:#fff;color:var(--studio-ink);padding:8px 12px;cursor:pointer;font-weight:800;font-size:11px}.designer-presets button:hover{border-color:var(--studio-accent);background:#edf8f8}.designer-group{border:1px solid var(--studio-line);border-radius:16px;background:#fff;overflow:hidden}.designer-group>summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;cursor:pointer;color:var(--studio-ink);font-weight:900;list-style:none}.designer-group>summary::-webkit-details-marker{display:none}.designer-group>summary:after{content:"＋";color:var(--studio-accent);font-size:18px}.designer-group[open]>summary:after{content:"−"}.designer-group-body{display:grid;gap:13px;padding:0 16px 16px;border-top:1px solid #edf2f2}.designer-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.designer-field{display:grid;gap:7px;min-width:0}.designer-field>span,.designer-field>label{font-size:11px;font-weight:850;color:#35575b}.designer-field small{color:#73898c;font-size:9px}.designer-field input[type="text"],.designer-field input[type="number"],.designer-field input[type="email"],.designer-field select,.designer-field textarea{width:100%;border:1px solid #d5e2e3;border-radius:11px;background:#fff;color:#17383c;padding:10px 11px;outline:0}.designer-field input:focus,.designer-field select:focus,.designer-field textarea:focus{border-color:#127d82;box-shadow:0 0 0 3px #127d8218}.designer-color{display:grid;grid-template-columns:44px 1fr;gap:8px;align-items:center}.designer-color input[type="color"]{width:44px;height:40px;padding:3px;border:1px solid #d5e2e3;border-radius:10px;background:#fff;cursor:pointer}.designer-range{display:grid;grid-template-columns:minmax(0,1fr) 48px;gap:9px;align-items:center}.designer-range input[type="range"]{width:100%;accent-color:#127d82}.designer-range output{display:grid;place-items:center;min-height:30px;border:1px solid #d9e5e6;border-radius:9px;background:#f7fbfb;color:#0d6267;font-size:10px;font-weight:900}.designer-toggles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.designer-toggle{display:flex;align-items:center;gap:9px;padding:10px 11px;border:1px solid #dce7e8;border-radius:11px;background:#fbfdfd;color:#284b4f;font-size:11px;font-weight:800}.designer-toggle input{accent-color:#127d82}.designer-actions{position:sticky;bottom:10px;display:flex;flex-wrap:wrap;gap:8px;padding:12px;border:1px solid #d9e4e5;border-radius:16px;background:#ffffffed;box-shadow:0 14px 38px rgba(7,63,66,.12);backdrop-filter:blur(12px);z-index:8}.designer-actions .btn{flex:1 1 auto;white-space:nowrap}.designer-reset{border-color:#d7e4e5!important;background:#fff!important;color:#476166!important}.writer-preview-label b{color:#127d82}.writer-qr-note{margin-top:8px;padding:9px 11px;border-radius:11px;background:#edf9f9;color:#236166;font-size:10px;line-height:1.7}.writer-qr-note strong{color:#0c6c72}.writer-card-studio-v2 [data-card-status]{display:block;min-height:20px;color:#0c6c72;font-size:11px;font-weight:800}.designer-field-full{grid-column:1/-1}@media(max-width:1100px){.writer-card-studio-v2 .writer-design-workspace{grid-template-columns:1fr}.writer-card-studio-v2 .writer-design-preview{position:static;min-height:0}.writer-card-designer{grid-row:2}}@media(max-width:620px){.designer-grid,.designer-toggles{grid-template-columns:1fr}.writer-card-studio-v2 .writer-design-typebar{padding:12px}.writer-card-studio-v2 .writer-design-types{grid-template-columns:1fr}.designer-actions{bottom:4px}}
</style>
<section class="panel writer-card-studio writer-design-studio writer-card-studio-v2" data-writer-card-studio>
  <div class="panel-head writer-studio-head"><div><span class="panel-kicker">استوديو بطاقة الكاتب</span><h2>صمّم بطاقتك</h2><p>تحكم في النصوص والألوان والخطوط والمقاسات والتباعد والتأثيرات مع معاينة مباشرة قبل الحفظ أو التصدير.</p></div><a class="btn btn-ghost" href="/writer/account">تعديل بياناتي</a></div>
  <form method="post" action="/writer/card/save" id="writerCardForm"><?=csrf_field()?>
    <div class="writer-design-typebar"><fieldset><legend>نوع التصميم</legend><div class="writer-design-types"><?php foreach(['post'=>'منشور 4:5','business'=>'بطاقة أعمال','pdf'=>'ملف PDF'] as $key=>$label):?><label><input type="radio" name="card_size" value="<?=$key?>" <?=$type===$key?'checked':''?>><span><b><?=$label?></b><small><?=$key==='post'?'1080 × 1350':($key==='business'?'1200 × 680':'A4 · متعدد الصفحات')?></small></span></label><?php endforeach;?></div></fieldset></div>
    <div class="writer-card-workspace writer-design-workspace">
      <div class="writer-card-preview writer-design-preview">
        <div class="writer-preview-label"><span>معاينة مباشرة</span><b>أي تعديل يظهر فورًا</b></div>
        <div class="writer-design-canvas" data-design-canvas data-type="<?=$type?>" data-template="<?=$template?>">
          <?php $managedWriterTemplate=\App\Services\CardTemplateService::renderActive('writer-card',['name'=>(string)$u['name'],'username'=>(string)$u['username'],'reason'=>(string)($quote?:'كاتب في سراديب'),'profile_url'=>(string)$profileUrl]); if($managedWriterTemplate!==''):?>
            <div class="writer-identity-card writer-design-card" id="writerIdentityCard"><?=$managedWriterTemplate?></div>
          <?php else:?>
          <div class="writer-identity-card writer-design-card writer-card-v4 design-<?=$type?> template-<?=e($template)?>" id="writerIdentityCard" data-width="<?= $type==='business'?'1200':($type==='pdf'?'794':'1080')?>" data-height="<?= $type==='business'?'680':($type==='pdf'?'1123':'1350')?>" style="<?=e($cardCss)?><?= $avatar!==''?'--writer-photo:url(\''.e($avatar).'\');':'' ?>">
            <img class="v4-scene" src="/assets/writer-ruins-background.png" alt="" aria-hidden="true">
            <div class="v4-overlay" aria-hidden="true"></div>
            <header class="v4-head"><div class="v4-brand"><?php if($cardLogo!==''):?><img src="<?=e($cardLogo)?>" alt="<?=e(setting('site_name','سراديب'))?>"><?php else:?><b><?=e(setting('site_name','سراديب'))?></b><?php endif;?></div><span class="v4-badge"><?=!empty($u['writer_honored'])?'كاتب مميز':(!empty($u['writer_verified'])?'موثق':'كاتب سراديب')?></span></header>
            <main class="v4-main"><div class="v4-copy"><small>ملف الكاتب</small><h3 data-card-name><?=e($u['name'])?></h3><strong data-card-title><?=e($title?:'كاتب وباحث في سراديب')?></strong><blockquote data-preview-quote><?=e($quote)?></blockquote><span class="v4-handle">@<?=e($u['username'])?></span></div><div class="v4-avatar" data-card-section="photo" data-card-photo <?=(int)($prefs['show_photo']??1)!==1?'hidden':''?>><?php if($avatar):?><img src="<?=e($avatar)?>" alt="<?=e($u['name'])?>" crossorigin="anonymous"><?php else:?><span><?=e(mb_substr($u['name'],0,1,'UTF-8'))?></span><?php endif;?></div></main>
            <div class="v4-stats" data-card-section="stats" <?=(int)($prefs['show_stats']??1)!==1?'hidden':''?>><div class="v4-stat"><b><?=number_format((int)($stats['articles']??0))?></b><span>مقالة</span></div><div class="v4-stat"><b><?=number_format((int)($stats['views']??0))?></b><span>قراءة</span></div><div class="v4-stat"><b><?=e($joined)?></b><span>عضو منذ</span></div></div>
            <div class="v4-bottom"><div class="v4-socials" data-card-section="socials" <?=(int)($prefs['show_socials']??1)!==1?'hidden':''?>><b>@<?=e($u['username'])?></b><span>saradeeeb.com<?= $socials?' · '.e(implode(' · ',array_keys($socials))):'' ?><?= $contact!==''?' · '.e($contact):'' ?></span></div><div class="v4-qr" data-card-section="qr" <?=(int)($prefs['show_qr']??1)!==1?'hidden':''?>><img src="https://api.qrserver.com/v1/create-qr-code/?size=420x420&ecc=H&margin=20&data=<?=rawurlencode($profileUrl)?>" alt="QR لملف الكاتب" crossorigin="anonymous" loading="eager" draggable="false"><span><b>امسح الرمز</b><small>saradeeeb.com</small></span></div></div>
            <footer class="v4-footer"><span>سراديب · <?=e($u['username'])?></span><b>اقرأ · اكتب · شارك</b></footer>
          </div>
          <?php endif;?>
          <?php include __DIR__.'/professional-pdf.php'; ?>
        </div>
      </div>

      <aside class="writer-card-controls writer-design-controls writer-card-designer">
        <div class="designer-intro">هذه اللوحة تتحكم في <strong>تصميم بطاقتك نفسها</strong>. لا تحتاج كتابة CSS أو أكواد. ويمكنك إعادة النمط الافتراضي في أي وقت.</div>
        <div class="designer-presets" aria-label="أنماط جاهزة"><button type="button" data-style-preset="saradeeeb">سراديب</button><button type="button" data-style-preset="midnight">ليلي</button><button type="button" data-style-preset="paper">فاتح</button><button type="button" data-style-preset="contrast">تباين قوي</button></div>

        <details class="designer-group" open><summary>المحتوى والعناصر</summary><div class="designer-group-body">
          <div class="designer-field designer-field-full"><span>العبارة القصيرة</span><textarea name="quote_text" maxlength="180" rows="4" data-card-quote><?=e($prefs['quote_text']??'')?></textarea><small>حتى 180 حرفًا. استخدم جملة واحدة قوية وواضحة.</small></div>
          <div class="designer-toggles">
            <label class="designer-toggle"><input type="checkbox" name="show_photo" value="1" data-card-toggle="photo" <?=(int)($prefs['show_photo']??1)===1?'checked':''?>><span>صورة الكاتب</span></label>
            <label class="designer-toggle"><input type="checkbox" name="show_socials" value="1" data-card-toggle="socials" <?=(int)($prefs['show_socials']??1)===1?'checked':''?>><span>المعرف والروابط</span></label>
            <?php if(setting('writer_cards_stats','1')==='1'):?><label class="designer-toggle"><input type="checkbox" name="show_stats" value="1" data-card-toggle="stats" <?=(int)($prefs['show_stats']??1)===1?'checked':''?>><span>الإحصاءات</span></label><?php endif;?>
            <?php if(setting('writer_cards_qr','1')==='1'):?><label class="designer-toggle"><input type="checkbox" name="show_qr" value="1" data-card-toggle="qr" <?=(int)($prefs['show_qr']??1)===1?'checked':''?>><span>QR ملف الكاتب</span></label><?php endif;?>
          </div>
          <div class="writer-qr-note"><strong>تم تكبير QR وتحسين المساحة البيضاء حوله.</strong> حجم الرمز الافتراضي الآن مناسب للمسح من الشاشة أو بعد تنزيل البطاقة.</div>
        </div></details>

        <details class="designer-group" open><summary>الألوان</summary><div class="designer-group-body"><div class="designer-grid">
          <?php foreach([
            'background'=>['خلفية البطاقة','#031c1f'],'overlay_color'=>['لون طبقة الصورة','#031c1f'],'accent'=>['لون اللمسات','#69d5d8'],'primary_text'=>['النص الرئيسي','#ffffff'],'secondary_text'=>['النص الثانوي','#bfd3d5'],'panel_color'=>['خلفية صندوق النص','#031c1f'],'border_color'=>['الإطارات','#69d5d8']
          ] as $key=>$meta):?><label class="designer-field"><span><?=$meta[0]?></span><span class="designer-color"><input type="color" name="style[<?=$key?>]" value="<?=e((string)$sv($key,$meta[1]))?>"><input type="text" value="<?=e((string)$sv($key,$meta[1]))?>" data-color-text readonly></span></label><?php endforeach;?>
          <label class="designer-field"><span>شفافية طبقة الصورة</span><span class="designer-range"><input type="range" min="0" max="92" name="style[overlay_opacity]" value="<?=(int)$sv('overlay_opacity',64)?>"><output><?=(int)$sv('overlay_opacity',64)?>%</output></span></label>
          <label class="designer-field"><span>شفافية صندوق النص</span><span class="designer-range"><input type="range" min="0" max="95" name="style[panel_opacity]" value="<?=(int)$sv('panel_opacity',70)?>"><output><?=(int)$sv('panel_opacity',70)?>%</output></span></label>
        </div></div></details>

        <details class="designer-group"><summary>الخطوط والكتابة</summary><div class="designer-group-body"><div class="designer-grid">
          <label class="designer-field designer-field-full"><span>نوع الخط</span><select name="style[font_family]"><option value="plex" <?=$sv('font_family','plex')==='plex'?'selected':''?>>IBM Plex Sans Arabic</option><option value="tajawal" <?=$sv('font_family','plex')==='tajawal'?'selected':''?>>Tajawal</option><option value="kufi" <?=$sv('font_family','plex')==='kufi'?'selected':''?>>Noto Kufi Arabic</option><option value="tahoma" <?=$sv('font_family','plex')==='tahoma'?'selected':''?>>Tahoma</option><option value="serif" <?=$sv('font_family','plex')==='serif'?'selected':''?>>Serif أدبي</option></select></label>
          <?php foreach([
            'name_size'=>['حجم اسم الكاتب',22,64,'px'],'title_size'=>['حجم الوصف المهني',10,28,'px'],'quote_size'=>['حجم العبارة',10,28,'px'],'handle_size'=>['حجم المعرّف',9,24,'px'],'quote_line_height'=>['تباعد سطور العبارة',120,240,'%']
          ] as $key=>$meta):?><label class="designer-field"><span><?=$meta[0]?></span><span class="designer-range"><input type="range" min="<?=$meta[1]?>" max="<?=$meta[2]?>" name="style[<?=$key?>]" value="<?=(int)$sv($key,['name_size'=>42,'title_size'=>15,'quote_size'=>13,'handle_size'=>15,'quote_line_height'=>180][$key])?>"><output><?=(int)$sv($key,['name_size'=>42,'title_size'=>15,'quote_size'=>13,'handle_size'=>15,'quote_line_height'=>180][$key])?><?=$meta[3]?></output></span></label><?php endforeach;?>
          <?php foreach(['name_weight'=>'سماكة اسم الكاتب','title_weight'=>'سماكة الوصف','quote_weight'=>'سماكة العبارة'] as $key=>$label):?><label class="designer-field"><span><?=$label?></span><select name="style[<?=$key?>]"><?php foreach([300=>'خفيف',400=>'عادي',500=>'متوسط',600=>'نصف عريض',700=>'عريض',800=>'عريض جدًا',900=>'أسود'] as $w=>$wl):?><option value="<?=$w?>" <?=(int)$sv($key,$key==='name_weight'?700:($key==='title_weight'?700:500))===$w?'selected':''?>><?=$wl?> · <?=$w?></option><?php endforeach;?></select></label><?php endforeach;?>
        </div></div></details>

        <details class="designer-group"><summary>الحجم والتناسق</summary><div class="designer-group-body"><div class="designer-grid">
          <?php foreach([
            'card_radius'=>['استدارة البطاقة',0,54,34,'px'],'card_padding'=>['الحواف الداخلية',3,10,6,'%'],'card_gap'=>['المسافة بين الأقسام',4,28,12,'px'],'panel_radius'=>['استدارة صندوق النص',0,42,28,'px'],'avatar_border'=>['سماكة إطار الصورة',0,10,4,'px'],'avatar_radius'=>['استدارة صورة الكاتب',0,50,50,'%'],'qr_size'=>['حجم QR',84,170,118,'px'],'logo_width'=>['عرض الشعار',80,240,150,'px']
          ] as $key=>$meta):?><label class="designer-field"><span><?=$meta[0]?></span><span class="designer-range"><input type="range" min="<?=$meta[1]?>" max="<?=$meta[2]?>" name="style[<?=$key?>]" value="<?=(int)$sv($key,$meta[3])?>"><output><?=(int)$sv($key,$meta[3])?><?=$meta[4]?></output></span></label><?php endforeach;?>
        </div></div></details>

        <details class="designer-group"><summary>الصورة والتأثيرات</summary><div class="designer-group-body"><div class="designer-grid">
          <?php foreach([
            'shadow_strength'=>['قوة الظل',0,100,42,'%'],'watermark_opacity'=>['وضوح العلامة المائية',0,30,10,'%'],'scene_position_y'=>['موضع الخلفية عموديًا',0,100,65,'%'],'scene_brightness'=>['سطوع الخلفية',50,130,92,'%'],'scene_saturation'=>['تشبع الخلفية',0,170,105,'%']
          ] as $key=>$meta):?><label class="designer-field"><span><?=$meta[0]?></span><span class="designer-range"><input type="range" min="<?=$meta[1]?>" max="<?=$meta[2]?>" name="style[<?=$key?>]" value="<?=(int)$sv($key,$meta[3])?>"><output><?=(int)$sv($key,$meta[3])?><?=$meta[4]?></output></span></label><?php endforeach;?>
        </div><small>هذه الإعدادات تؤثر على البطاقة البصرية. ملف PDF المهني يحافظ على تنسيقه الوثائقي المستقل.</small></div></details>

        <div class="designer-actions"><button class="btn btn-primary" type="submit">حفظ التصميم</button><button class="btn btn-ghost designer-reset" type="button" data-style-reset>إعادة الافتراضي</button><?php if(setting('writer_cards_download','1')==='1'):?><button class="btn btn-ghost" type="button" data-export-card>PNG</button><button class="btn btn-ghost" type="button" data-export-jpg>JPG</button><button class="btn btn-ghost" type="button" data-export-webp>WebP</button><?php endif;?><?php if(setting('writer_cards_pdf','1')==='1'):?><button class="btn btn-ghost" type="button" data-export-pdf>PDF</button><?php endif;?><?php if(setting('writer_cards_share','1')==='1'):?><button class="btn btn-ghost" type="button" data-share-card>مشاركة</button><?php endif;?></div>
        <small data-card-status aria-live="polite"></small>
      </aside>
    </div>
  </form>
</section>
<script defer src="/assets/js/writer-professional-pdf.js?v=1"></script>
<script>
(()=>{
 const form=document.querySelector('#writerCardForm'),card=document.querySelector('#writerIdentityCard'),canvas=document.querySelector('[data-design-canvas]'),pdfPages=document.querySelector('[data-pdf-pages]'),status=document.querySelector('[data-card-status]');
 if(!form||!card)return;
 const compactLayout=()=>card.classList.toggle('card-compact',window.matchMedia('(max-width:700px)').matches||card.getBoundingClientRect().width<=420);
 const defaults=<?=json_encode([
  'font_family'=>'plex','background'=>'#031c1f','overlay_color'=>'#031c1f','overlay_opacity'=>64,'accent'=>'#69d5d8','primary_text'=>'#ffffff','secondary_text'=>'#bfd3d5','panel_color'=>'#031c1f','panel_opacity'=>70,'border_color'=>'#69d5d8','card_radius'=>34,'card_padding'=>6,'card_gap'=>12,'panel_radius'=>28,'name_size'=>42,'name_weight'=>700,'title_size'=>15,'title_weight'=>700,'quote_size'=>13,'quote_weight'=>500,'quote_line_height'=>180,'handle_size'=>15,'avatar_border'=>4,'avatar_radius'=>50,'qr_size'=>118,'logo_width'=>150,'shadow_strength'=>42,'watermark_opacity'=>10,'scene_position_y'=>65,'scene_brightness'=>92,'scene_saturation'=>105
 ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
 const presets={
  saradeeeb:{background:'#031c1f',overlay_color:'#031c1f',overlay_opacity:64,accent:'#69d5d8',primary_text:'#ffffff',secondary_text:'#bfd3d5',panel_color:'#031c1f',panel_opacity:70,border_color:'#69d5d8'},
  midnight:{background:'#050912',overlay_color:'#03050a',overlay_opacity:74,accent:'#52d5e5',primary_text:'#ffffff',secondary_text:'#b8c9d6',panel_color:'#07101b',panel_opacity:82,border_color:'#52d5e5'},
  paper:{background:'#ece5d7',overlay_color:'#e4dccd',overlay_opacity:34,accent:'#0f6d70',primary_text:'#102f36',secondary_text:'#40595e',panel_color:'#fffdf8',panel_opacity:82,border_color:'#0f6d70'},
  contrast:{background:'#071316',overlay_color:'#000000',overlay_opacity:78,accent:'#35e2e8',primary_text:'#ffffff',secondary_text:'#eefafa',panel_color:'#000000',panel_opacity:78,border_color:'#35e2e8'}
 };
 const fontStacks={plex:'"IBM Plex Sans Arabic","Segoe UI",Tahoma,Arial,sans-serif',tajawal:'"Tajawal","Segoe UI",Tahoma,Arial,sans-serif',kufi:'"Noto Kufi Arabic","Segoe UI",Tahoma,Arial,sans-serif',tahoma:'Tahoma,"Segoe UI",Arial,sans-serif',serif:'Georgia,"Times New Roman",serif'};
 const field=k=>form.elements['style['+k+']'];
 const val=k=>field(k)?.value??defaults[k];
 const hexRgb=(hex,a)=>{hex=(hex||'#031c1f').replace('#','');if(hex.length!==6)hex='031c1f';const n=parseInt(hex,16);return `rgba(${(n>>16)&255},${(n>>8)&255},${n&255},${a})`};
 const updateOutputs=()=>{
  form.querySelectorAll('.designer-range').forEach(w=>{const i=w.querySelector('input[type="range"]'),o=w.querySelector('output');if(!i||!o)return;const name=i.name.match(/\[([^\]]+)\]/)?.[1]||'';const unit=['card_padding','avatar_radius','overlay_opacity','panel_opacity','shadow_strength','watermark_opacity','scene_position_y','scene_brightness','scene_saturation','quote_line_height'].includes(name)?'%':'px';o.textContent=i.value+unit});
  form.querySelectorAll('.designer-color').forEach(w=>{const c=w.querySelector('input[type="color"]'),t=w.querySelector('[data-color-text]');if(c&&t)t.value=c.value.toUpperCase()});
 };
 const applyStyle=()=>{
  const s=card.style;
  s.setProperty('--wc-bg',val('background'));s.setProperty('--wc-overlay',hexRgb(val('overlay_color'),Number(val('overlay_opacity'))/100));s.setProperty('--wc-accent',val('accent'));s.setProperty('--wc-primary',val('primary_text'));s.setProperty('--wc-secondary',val('secondary_text'));s.setProperty('--wc-panel',hexRgb(val('panel_color'),Number(val('panel_opacity'))/100));s.setProperty('--wc-border',val('border_color'));s.setProperty('--wc-radius',val('card_radius')+'px');s.setProperty('--wc-padding',val('card_padding')+'%');s.setProperty('--wc-gap',val('card_gap')+'px');s.setProperty('--wc-panel-radius',val('panel_radius')+'px');s.setProperty('--wc-name-size',val('name_size')+'px');s.setProperty('--wc-name-weight',val('name_weight'));s.setProperty('--wc-title-size',val('title_size')+'px');s.setProperty('--wc-title-weight',val('title_weight'));s.setProperty('--wc-quote-size',val('quote_size')+'px');s.setProperty('--wc-quote-weight',val('quote_weight'));s.setProperty('--wc-quote-line',Number(val('quote_line_height'))/100);s.setProperty('--wc-handle-size',val('handle_size')+'px');s.setProperty('--wc-avatar-border',val('avatar_border')+'px');s.setProperty('--wc-avatar-radius',val('avatar_radius')+'%');s.setProperty('--wc-qr-size',val('qr_size')+'px');s.setProperty('--wc-logo-width',val('logo_width')+'px');s.setProperty('--wc-shadow-alpha',Number(val('shadow_strength'))/100);s.setProperty('--wc-watermark-opacity',Number(val('watermark_opacity'))/100);s.setProperty('--wc-scene-y',val('scene_position_y')+'%');s.setProperty('--wc-scene-brightness',val('scene_brightness')+'%');s.setProperty('--wc-scene-saturation',val('scene_saturation')+'%');s.setProperty('--wc-font',fontStacks[val('font_family')]||fontStacks.plex);
  updateOutputs();
 };
 const sync=()=>{
  const type=form.card_size.value||'post',template=type==='business'?'executive':type==='pdf'?'editorial':'cinematic';
  card.className='writer-identity-card writer-design-card writer-card-v4 design-'+type+' template-'+template;card.dataset.width=type==='business'?'1200':type==='pdf'?'794':'1080';card.dataset.height=type==='business'?'680':type==='pdf'?'1123':'1350';canvas.dataset.type=type;canvas.dataset.template=template;
  const q=form.querySelector('[data-preview-quote]');if(q)q.textContent=form.quote_text.value.trim()||<?=json_encode($quote,JSON_UNESCAPED_UNICODE)?>;
  form.querySelectorAll('[data-card-toggle]').forEach(x=>card.querySelectorAll('[data-card-section="'+x.dataset.cardToggle+'"]').forEach(e=>e.hidden=!x.checked));
  if(pdfPages)pdfPages.hidden=type!=='pdf';card.hidden=type==='pdf';document.querySelectorAll('[data-export-card],[data-export-jpg],[data-export-webp]').forEach(x=>x.hidden=type==='pdf');const pdf=document.querySelector('[data-export-pdf]');if(pdf)pdf.hidden=type==='post';
  compactLayout();applyStyle();
 };
 form.addEventListener('input',sync);form.addEventListener('change',sync);
 document.querySelectorAll('[data-style-preset]').forEach(b=>b.addEventListener('click',()=>{Object.entries(presets[b.dataset.stylePreset]||{}).forEach(([k,v])=>{if(field(k))field(k).value=v});sync()}));
 document.querySelector('[data-style-reset]')?.addEventListener('click',()=>{Object.entries(defaults).forEach(([k,v])=>{if(field(k))field(k).value=v});sync();status.textContent='تمت استعادة الإعدادات الافتراضية في المعاينة. اضغط حفظ لتثبيتها.'});
 sync();window.addEventListener('resize',compactLayout,{passive:true});
 const typeTarget=()=>form.card_size.value==='pdf'?pdfPages:card;
 const download=async(kind)=>{const target=typeTarget();if(!target)return;status.textContent='جارٍ تجهيز الملف...';const rect=target.getBoundingClientRect(),clone=target.cloneNode(true),copy=(a,b)=>{const css=getComputedStyle(a);let inline='';for(const p of css)inline+=p+':'+css.getPropertyValue(p)+';';b.setAttribute('style',inline);[...a.children].forEach((c,i)=>b.children[i]&&copy(c,b.children[i]))};copy(target,clone);try{await Promise.all([...clone.querySelectorAll('img')].map(async image=>{const response=await fetch(image.src);if(!response.ok)throw new Error('Image unavailable');const blob=await response.blob();image.src=await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.onerror=reject;reader.readAsDataURL(blob)});image.removeAttribute('crossorigin')}));clone.style.width=rect.width+'px';clone.style.height=rect.height+'px';const wrap=document.createElement('div');wrap.setAttribute('xmlns','http://www.w3.org/1999/xhtml');wrap.append(clone);const w=+target.dataset.width||1080,h=+target.dataset.height||1350,svg='<svg xmlns="http://www.w3.org/2000/svg" width="'+w+'" height="'+h+'" viewBox="0 0 '+rect.width+' '+rect.height+'"><foreignObject width="100%" height="100%">'+new XMLSerializer().serializeToString(wrap)+'</foreignObject></svg>',url=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml'})),img=new Image();await new Promise((ok,no)=>{img.onload=ok;img.onerror=no;img.src=url});const c=document.createElement('canvas');c.width=w;c.height=h;c.getContext('2d').drawImage(img,0,0,w,h);const blob=await new Promise(resolve=>c.toBlob(resolve,kind==='jpeg'?'image/jpeg':kind==='webp'?'image/webp':'image/png',.94));const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='saradeeeb-'+(form.card_size.value==='pdf'?'writer-profile':'writer-card')+'.'+(kind==='jpeg'?'jpg':kind);a.click();URL.revokeObjectURL(url);status.textContent='تم تجهيز الملف.'}catch(_){status.textContent='تعذر التصدير في هذا المتصفح. جرّب PNG أو متصفحًا آخر.'}};
 document.querySelector('[data-export-card]')?.addEventListener('click',()=>download('png'));document.querySelector('[data-export-jpg]')?.addEventListener('click',()=>download('jpeg'));document.querySelector('[data-export-webp]')?.addEventListener('click',()=>download('webp'));document.querySelector('[data-export-pdf]')?.addEventListener('click',()=>window.print());document.querySelector('[data-share-card]')?.addEventListener('click',async()=>{try{if(navigator.share)await navigator.share({title:'بطاقتي في سراديب',url:<?=json_encode($profileUrl)?>});else{await navigator.clipboard.writeText(<?=json_encode($profileUrl)?>);status.textContent='تم نسخ رابط الكاتب.'}}catch(_){} });
})();
</script>
<script>
(()=>{const form=document.querySelector('#writerCardForm');if(!form)return;const pdfMain=document.querySelector('.pdf-cover-main'),source=document.querySelector('#writerIdentityCard [data-card-section="stats"]');if(pdfMain&&source&&!pdfMain.querySelector('.pdf-cover-stats')){const clone=source.cloneNode(true);clone.className='pdf-cover-stats';clone.hidden=false;pdfMain.append(clone)}})();
</script>
<script>
document.querySelector('[data-export-card]')?.replaceChildren(document.createTextNode('تنزيل PNG'));
document.querySelector('[data-export-jpg]')?.replaceChildren(document.createTextNode('تنزيل JPG'));
document.querySelector('[data-export-webp]')?.replaceChildren(document.createTextNode('تنزيل WebP'));
document.querySelector('[data-export-pdf]')?.replaceChildren(document.createTextNode('تنزيل PDF'));
</script>
