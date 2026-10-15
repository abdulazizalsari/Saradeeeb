<?php $writerPublicUrl=writer_url((string)$u['username'],default_locale()); ?>
<section class="panel writer-community-privilege" aria-labelledby="writerCommunityPrivilegeTitle">
 <div><span class="panel-kicker">ميزة الكاتب المعتمد</span><h2 id="writerCommunityPrivilegeTitle">صوتك في منتدى سراديب</h2><p>بصفتك كاتبًا معتمدًا، يمكنك إنشاء مواضيع جديدة وقيادة نقاشاتها والرد عليها في منتدى سراديب. هذه الصلاحية حصرية للكتّاب المعتمدين وفريق التحرير؛ العضو العادي يقرأ ويتفاعل فقط.</p></div>
 <div class="writer-community-privilege-actions"><a class="btn btn-primary" href="/community/new">ابدأ نقاشًا جديدًا</a><a class="btn btn-ghost" target="_blank" rel="noopener" href="<?=e(locale_url(default_locale(),'community/member/'.$u['username']))?>">عرض نقاشاتي العامة</a></div>
</section>
<section class="panel writer-link-hub">
 <div class="writer-link-hub-main"><div><span class="panel-kicker">هويتك في سراديب</span><h2>رابط الكاتب والباركود</h2><p>اختر اسم المستخدم الإنجليزي الخاص بك، ثم شارك هذا الرابط مع القرّاء والعملاء.</p><div class="writer-public-link"><code id="writerPublicLinkText"><?=e($writerPublicUrl)?></code><button type="button" class="btn btn-ghost" data-copy-writer-link>نسخ الرابط</button><a class="btn btn-primary" target="_blank" href="<?=e($writerPublicUrl)?>">فتح ملفي</a></div></div><div class="writer-qr-card"><img id="writerAccountQr" src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=<?=rawurlencode($writerPublicUrl)?>" alt="QR"><small>امسح لفتح ملف الكاتب</small></div></div>
 <div class="writer-username-quick"><label>اسم المستخدم الخاص بك<div class="writer-username-inline"><span>saradeeeb.com/writer/</span><input id="writerUsernameQuick" value="<?=e($u['username'])?>" maxlength="30" pattern="[A-Za-z][A-Za-z0-9_-]{2,29}" autocomplete="off"></div><small id="writerUsernameQuickStatus">إنجليزي فقط، ويجب أن يكون غير محجوز.</small></label><a class="btn btn-ghost" href="#professionalProfileForm">تعديل وحفظ الاسم</a></div>
</section>
<section class="panel writer-avatar-panel"><div class="panel-head"><div><span class="panel-kicker">صورتك</span><h2>الصورة الشخصية</h2><p>تظهر في ملفك المهني وبطاقة الكاتب وتحت مقالاتك.</p></div></div><form method="post" action="/writer/account/avatar" enctype="multipart/form-data" class="avatar-upload-form"><?=csrf_field()?><?php if(!empty($u['avatar'])):?><img class="account-avatar-image" src="<?=e($u['avatar'])?>" alt=""><?php else:?><div class="account-avatar-image fallback"><?=e(mb_substr($u['name'],0,1,'UTF-8'))?></div><?php endif;?><label>اختر صورة<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required></label><button class="btn btn-primary">رفع الصورة</button></form></section>
<section class="panel writer-profile-editor">
 <div class="panel-head"><div><span class="panel-kicker">هويتك المهنية</span><h2>ملفي المهني والسيرة الذاتية</h2><p>أنشئ صفحة مهنية عامة يمكنك مشاركتها كرابط سيرة ذاتية.</p></div><a class="btn btn-ghost" target="_blank" href="<?=e(writer_url($u['username'],default_locale()))?>">معاينة صفحتي</a></div>
 <form method="post" action="/writer/account/profile" class="form-grid" id="professionalProfileForm"><?=csrf_field()?>
  <div class="span-2 writer-username-box">
   <div><strong>رابطك الخاص في سراديب</strong><p>اسم المستخدم إنجليزي وفريد، ويصبح رابطك: <b id="writerUsernameUrl">saradeeeb.com/writer/<?=e($u['username'])?></b></p></div>
   <label>اسم المستخدم<input id="writerUsernameInput" name="username" value="<?=e($u['username'])?>" maxlength="30" pattern="[A-Za-z][A-Za-z0-9_-]{2,29}" autocomplete="off" required><small id="writerUsernameStatus">يُسمح بالأحرف الإنجليزية والأرقام و - و _ فقط.</small></label>
  </div>
  <label>المسمى المهني<input name="professional_title" maxlength="190" value="<?=e($profile['professional_title']??'')?>" placeholder="مثال: كاتب وباحث في التقنية"></label>
  <label>الموقع / المدينة<input name="location" maxlength="190" value="<?=e($profile['location']??'')?>"></label>
  <label>لغة الكتابة الأساسية
    <select name="primary_writing_language" required>
      <?php $currentWritingLanguage=(string)($profile['primary_writing_language']??'ar'); foreach(($writingLanguages??article_locales()) as $code=>$language): ?>
        <option value="<?=e($code)?>" <?=$currentWritingLanguage===$code?'selected':''?>><?=e(language_flag($code).' '.language_name($code))?></option>
      <?php endforeach; ?>
    </select>
    <small>تُطبَّق على الأعمال الجديدة فقط. لا تغيّر لغة المقالات أو الخواطر السابقة، ولا تتأثر بلغة واجهة الموقع.</small>
  </label>
  <label class="span-2">نبذة السيرة الذاتية<textarea name="cv_summary" rows="6" maxlength="5000" placeholder="عرّف بخبرتك وتخصصك ومسيرتك المهنية..."><?=e($profile['cv_summary']??($u['bio']??''))?></textarea></label>
  <section class="span-2 panel" aria-labelledby="writer-profile-translations-title">
   <div class="panel-head"><div><span class="panel-kicker">الواجهة متعددة اللغات</span><h3 id="writer-profile-translations-title">ترجمات ملف الكاتب</h3><p>لن تظهر أي ترجمة للزوار قبل أن تكون مكتملة ومعتمدة.</p></div></div>
   <div class="form-grid">
   <?php foreach(all_locales() as $code=>$language): if($code==='ar') continue; $translated=$profileTranslations[$code]??[]; ?>
    <fieldset class="span-2 translation-editor"><legend><?=e(language_flag($code).' '.language_name($code))?></legend>
     <div class="form-grid">
      <label>الاسم المعروض<input name="profile_translation[<?=e($code)?>][display_name]" maxlength="190" value="<?=e($translated['display_name']??'')?>"></label>
      <label>المسمى المهني<input name="profile_translation[<?=e($code)?>][professional_title]" maxlength="190" value="<?=e($translated['professional_title']??'')?>"></label>
      <label class="span-2">نبذة قصيرة<textarea name="profile_translation[<?=e($code)?>][bio]" rows="3" maxlength="1200"><?=e($translated['bio']??'')?></textarea></label>
      <label class="span-2">ملخص السيرة<textarea name="profile_translation[<?=e($code)?>][cv_summary]" rows="4" maxlength="5000"><?=e($translated['cv_summary']??'')?></textarea></label>
      <?php if(is_editor()):?><label>حالة الترجمة<select name="profile_translation[<?=e($code)?>][status]"><?php foreach(['draft'=>'مسودة','review'=>'للمراجعة','published'=>'منشورة'] as $value=>$label):?><option value="<?=$value?>" <?=($translated['status']??'draft')===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><?php else:?><input type="hidden" name="profile_translation[<?=e($code)?>][status]" value="review"><?php endif;?>
     </div>
    </fieldset>
   <?php endforeach;?>
   </div>
  </section>
  <label>الموقع الشخصي<input type="url" name="website" value="<?=e($profile['website']??'')?>" placeholder="https://..."></label>
  <label>LinkedIn<input type="url" name="linkedin" value="<?=e($profile['linkedin']??'')?>" placeholder="https://linkedin.com/in/..."></label>
  <label>رابط معرض الأعمال<input type="url" name="portfolio_url" value="<?=e($profile['portfolio_url']??'')?>" placeholder="https://..."></label>
  <label>بريد مهني اختياري<input type="email" name="contact_email" value="<?=e($profile['contact_email']??'')?>"></label>
  <?php $areas=['experience'=>'الخبرات العملية','education'=>'التعليم والمؤهلات','skills'=>'المهارات','certificates'=>'الدورات والشهادات','languages'=>'اللغات','achievements'=>'الإنجازات والجوائز','portfolio'=>'أعمال ومشاريع مختارة']; foreach($areas as $key=>$label): $vals=json_decode($profile[$key.'_json']??'[]',true)?:[]; ?>
   <label class="span-2"><?=$label?><textarea name="<?=$key?>" rows="4" placeholder="اكتب كل عنصر في سطر مستقل"><?=e(implode("\n",$vals))?></textarea><small>كل سطر سيظهر كعنصر مستقل في السيرة.</small></label>
  <?php endforeach;?>
  <label class="check-line span-2"><input type="checkbox" name="is_public" value="1" <?=!empty($profile['is_public'])?'checked':''?>> نشر ملفي المهني للعامة</label>
  <label class="check-line span-2"><input type="checkbox" name="show_contact" value="1" <?=!empty($profile['show_contact'])?'checked':''?>> إظهار البريد المهني للزوار</label>
  <button class="btn btn-primary span-2">حفظ السيرة الذاتية</button>
 </form>
</section>
<section class="panel account-security-panel">
 <div class="panel-head"><div><span class="panel-kicker">الأمان</span><h2>كلمة المرور</h2></div></div>
 <div class="account-summary"><div class="account-avatar"><?=e(mb_substr((string)$u['name'],0,1,'UTF-8'))?></div><div><strong><?=e($u['name'])?></strong><span><?=e($u['email'])?></span></div></div>
 <form method="post" action="/writer/account/password" class="form-grid password-change-form"><?=csrf_field()?>
  <label class="span-2">كلمة المرور الحالية<input type="password" name="current_password" required autocomplete="current-password"></label><label>كلمة المرور الجديدة<input type="password" name="new_password" minlength="12" required autocomplete="new-password"></label><label>تأكيد كلمة المرور<input type="password" name="new_password_confirmation" minlength="12" required autocomplete="new-password"></label><button class="btn btn-primary span-2">تغيير كلمة المرور</button>
 </form>
</section>

<script>
(()=>{const input=document.getElementById('writerUsernameInput'),quick=document.getElementById('writerUsernameQuick'),status=document.getElementById('writerUsernameStatus'),quickStatus=document.getElementById('writerUsernameQuickStatus'),url=document.getElementById('writerUsernameUrl'),full=document.getElementById('writerPublicLinkText'),qr=document.getElementById('writerAccountQr');if(!input)return;let timer;const clean=v=>v.toLowerCase().replace(/[^a-z0-9_-]/g,'').replace(/^[-_]+|[-_]+$/g,'');const sync=async(raw,source)=>{const v=clean(raw);input.value=v;if(quick&&source!==quick)quick.value=v;url.textContent='saradeeeb.com/writer/'+v;const absolute=location.origin+'/writer/'+v;if(full)full.textContent=absolute;if(qr)qr.src='https://api.qrserver.com/v1/create-qr-code/?size=240x240&data='+encodeURIComponent(absolute);[status,quickStatus].forEach(x=>{if(x){x.textContent='جارٍ التحقق...';x.className='checking'}});clearTimeout(timer);timer=setTimeout(async()=>{if(v.length<3){[status,quickStatus].forEach(x=>{if(x){x.textContent='اسم المستخدم قصير جدًا.';x.className='unavailable'}});return}try{const r=await fetch('/writer/username/check?username='+encodeURIComponent(v),{headers:{'Accept':'application/json'}});const j=await r.json();[status,quickStatus].forEach(x=>{if(x){x.textContent=j.message;x.className=j.available?'available':'unavailable'}})}catch(e){[status,quickStatus].forEach(x=>{if(x){x.textContent='سيتم التحقق عند الحفظ.';x.className=''}})}},350)};input.addEventListener('input',()=>sync(input.value,input));quick?.addEventListener('input',()=>sync(quick.value,quick));document.querySelector('[data-copy-writer-link]')?.addEventListener('click',async e=>{try{await navigator.clipboard.writeText(full?.textContent||'');const old=e.currentTarget.textContent;e.currentTarget.textContent='تم النسخ';setTimeout(()=>e.currentTarget.textContent=old,1200)}catch(_){}})})();
</script>
