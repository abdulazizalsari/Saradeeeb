<?php
$iconNames=navigation_icon_names();
$hIndex=array_flip(array_values($headerMenu));
$fIndex=array_flip(array_values($footerMenu));
$val=static fn(string $k,string $d='')=>(string)($map[$k]??$d);
$checked=static fn(string $k,string $d='0')=>(($map[$k]??$d)==='1');
?>
<form method="post" action="/admin/appearance/save" class="appearance-admin" data-appearance-form>
<?=csrf_field()?>
<section class="appearance-hero panel">
  <div>
    <span class="panel-kicker">واجهة الموقع</span>
    <h2>تصميم الهيدر</h2>
    <p>تحكم بألوان وسلوك وتصميم الهيدر. الفوتر أصبح له استوديو مستقل، وإدارة الروابط في قسم «القوائم والتنقل».</p>
  </div>
  <div class="appearance-hero-actions"><a class="btn btn-ghost" href="/" target="_blank" rel="noopener">معاينة الموقع</a><button class="btn btn-primary" type="submit">حفظ وتطبيق</button></div>
</section>

<div class="appearance-layout">
  <div class="appearance-main">
    <section class="panel appearance-card">
      <div class="panel-head"><div><span class="panel-kicker">Header</span><h2>تصميم الهيدر</h2></div><span class="settings-badge">احترافي ومتجاوب</span></div>
      <div class="form-grid appearance-colors">
        <label>خلفية الهيدر<input type="color" name="header_background" value="<?=e($val('header_background','#ffffff'))?>"></label>
        <label>الخلفية بعد التمرير<input type="color" name="header_scrolled_background" value="<?=e($val('header_scrolled_background','#ffffff'))?>"></label>
        <label>لون النص والأيقونات<input type="color" name="header_text_color" value="<?=e($val('header_text_color','#152d31'))?>"></label>
        <label>لون الحد<input type="color" name="header_border_color" value="<?=e($val('header_border_color','#e1e8e9'))?>"></label>
        <label>لون Hover<input type="color" name="header_hover_color" value="<?=e($val('header_hover_color','#157176'))?>"></label>
        <label>خلفية Hover<input type="color" name="header_hover_background" value="<?=e($val('header_hover_background','#eaf6f6'))?>"></label>
        <label>ارتفاع الهيدر<input type="number" min="64" max="110" name="header_height" value="<?=e($val('header_height','86'))?>"><small>من 64 إلى 110 بكسل</small></label>
      </div>
      <h3 class="settings-subtitle">شريط أهم المقالات</h3>
      <div class="form-grid appearance-colors">
        <label>خلفية الشريط<input type="color" name="header_ticker_background" value="<?=e($val('header_ticker_background','#071118'))?>"></label>
        <label>لون النص<input type="color" name="header_ticker_text" value="<?=e($val('header_ticker_text','#f4fbfb'))?>"></label>
        <label>لون اللمسة<input type="color" name="header_ticker_accent" value="<?=e($val('header_ticker_accent','#37c1c7'))?>"></label>
        <label>عنوان الشريط<input type="text" name="news_ticker_label" value="<?=e($val('news_ticker_label','أهم المقالات'))?>"></label>
        <label>سرعة الحركة<input type="number" min="12" max="120" name="news_ticker_speed" value="<?=e($val('news_ticker_speed','32'))?>"><small>الثواني للدورة الكاملة</small></label>
      </div>
      <h3 class="settings-subtitle">Mega Menu وقائمة الجوال</h3>
      <div class="form-grid appearance-colors">
        <label>خلفية Mega Menu<input type="color" name="header_mega_background" value="<?=e($val('header_mega_background','#121a1d'))?>"></label>
        <label>نص Mega Menu<input type="color" name="header_mega_text" value="<?=e($val('header_mega_text','#f6fbfb'))?>"></label>
        <label>بطاقات Mega Menu<input type="color" name="header_mega_card" value="<?=e($val('header_mega_card','#1c2529'))?>"></label>
        <label>خلفية قائمة الجوال<input type="color" name="header_mobile_background" value="<?=e($val('header_mobile_background','#ffffff'))?>"></label>
        <label>نص قائمة الجوال<input type="color" name="header_mobile_text" value="<?=e($val('header_mobile_text','#163438'))?>"></label>
      </div>
      <div class="toggle-grid">
        <label class="switch-row"><input type="checkbox" name="header_sticky" <?=$checked('header_sticky','1')?'checked':''?>><span><strong>هيدر ثابت</strong><small>يبقى أعلى الشاشة أثناء القراءة.</small></span></label>
        <label class="switch-row"><input type="checkbox" name="header_scroll_compact" <?=$checked('header_scroll_compact','1')?'checked':''?>><span><strong>تصغير عند التمرير</strong><small>حركة هادئة تقلل ارتفاع الهيدر.</small></span></label>
        <label class="switch-row"><input type="checkbox" name="header_hide_on_scroll" <?=$checked('header_hide_on_scroll','0')?'checked':''?>><span><strong>إخفاء عند النزول</strong><small>يظهر مجددًا عند التمرير للأعلى.</small></span></label>
        <label class="switch-row"><input type="checkbox" name="header_shadow" <?=$checked('header_shadow','1')?'checked':''?>><span><strong>ظل بعد التمرير</strong><small>يفصل الهيدر بصريًا عن المحتوى.</small></span></label>
        <label class="switch-row"><input type="checkbox" name="header_show_tagline" <?=$checked('header_show_tagline','1')?'checked':''?>><span><strong>الوصف تحت اسم الموقع</strong><small>إظهار الوصف القصير بجانب الشعار.</small></span></label>
        <label class="switch-row"><input type="checkbox" name="header_show_search" <?=$checked('header_show_search','1')?'checked':''?>><span><strong>البحث</strong><small>إظهار مربع البحث في الهيدر.</small></span></label>
        <label class="switch-row"><input type="checkbox" name="header_show_theme" <?=$checked('header_show_theme','1')?'checked':''?>><span><strong>الوضع المظلم</strong><small>إظهار زر الوضع الفاتح/المظلم.</small></span></label>
        <label class="switch-row"><input type="checkbox" name="header_show_icons" <?=$checked('header_show_icons','1')?'checked':''?>><span><strong>أيقونات القائمة</strong><small>أيقونة صغيرة احترافية مع كل رابط.</small></span></label>
        <label class="switch-row"><input type="checkbox" name="header_show_socials" <?=$checked('header_show_socials','1')?'checked':''?>><span><strong>سوشيال ميديا في الهيدر</strong><small>يعرض الشبكات المفعلة للهيدر من قسم السوشيال.</small></span></label>
        <label class="switch-row"><input type="checkbox" name="header_show_categories_mega" <?=$checked('header_show_categories_mega','1')?'checked':''?>><span><strong>Mega Menu للأقسام</strong><small>قائمة كبيرة للتصنيفات على الكمبيوتر وAccordion على الجوال.</small></span></label>
        <label class="switch-row"><input type="checkbox" name="news_ticker_enabled" <?=$checked('news_ticker_enabled','1')?'checked':''?>><span><strong>شريط أهم المقالات</strong><small>إظهار الشريط المتحرك أعلى الهيدر.</small></span></label>
      </div>
    </section>

    <section class="panel appearance-card"><div class="panel-head"><div><span class="panel-kicker">Navigation</span><h2>القوائم والتنقل</h2></div></div><p class="settings-help">إدارة ما يظهر في الهيدر والفوتر وترتيب الصفحات والروابط والأيقونات أصبحت في صفحة مستقلة.</p><a class="btn btn-primary" href="/admin/navigation">فتح القوائم والتنقل</a></section>
    <section class="panel appearance-card">
      <div class="panel-head"><div><span class="panel-kicker">Footer Studio</span><h2>الفوتر</h2></div><span class="settings-badge">لوحة مستقلة</span></div>
      <p class="settings-help">تم نقل جميع إعدادات الفوتر إلى استوديو مستقل حتى لا تتداخل مع إعدادات الهيدر. هناك معاينة مباشرة وتحكم بالألوان والمقاسات والروابط وحقوق النشر.</p>
      <a class="btn btn-primary" href="/admin/footer">فتح استوديو الفوتر</a>
    </section>

    <section class="panel appearance-card"><div class="panel-head"><div><span class="panel-kicker">Social</span><h2>السوشيال ميديا</h2></div></div><p class="settings-help">إدارة الشبكات والأيقونات وظهورها في الهيدر والفوتر أصبحت في قسم مستقل.</p><a class="btn btn-primary" href="/admin/social">فتح قسم السوشيال ميديا</a></section>
  </div>

  <aside class="appearance-side">
    <section class="panel appearance-preview-card">
      <span class="panel-kicker">معاينة تقريبية</span><h3>شكل الهيدر</h3>
      <div class="mini-header-preview" data-header-preview>
        <span class="mini-brand-preview">س</span><div class="mini-nav-preview"><i></i><i></i><i></i></div><span class="mini-action-preview"></span>
      </div>
      <p>المعاينة الفعلية الأفضل من زر «معاينة الموقع» بعد الحفظ.</p>
    </section>
    <section class="panel appearance-tip-card"><strong>ثابت مهما تغيّرت اللغة</strong><p>زر اللغة والبطاقة العشوائية يبقيان في <b>أسفل اليسار</b>، والواتساب في <b>أسفل اليمين</b>. اتجاه RTL/LTR لن يقلب موقعيهما.</p></section>
    <section class="panel appearance-save-card"><strong>احفظ بعد التعديل</strong><p>تغييرات هذه الصفحة تطبق على الهيدر. إعدادات الفوتر تحفظ من «استوديو الفوتر».</p><button class="btn btn-primary btn-full" type="submit">حفظ وتطبيق الآن</button></section>
  </aside>
</div>
</form>
