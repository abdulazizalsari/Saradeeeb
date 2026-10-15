<?php
$headExtra='<meta name="robots" content="index,follow">';
if(empty($data['sections'])){
  $data=['title'=>'سياسة الخصوصية','intro'=>'نحترم خصوصيتك ونلتزم بالتعامل مع بياناتك بوضوح ومسؤولية.','updated'=>'آخر تحديث: 11 أكتوبر 2026','sections'=>[
    ['title'=>'البيانات التي نجمعها','body'=>'قد نجمع الاسم والبريد الإلكتروني ومحتوى الرسائل أو المشاركات التي تقدمها طوعًا.'],
    ['title'=>'كيف نستخدم البيانات','body'=>'نستخدم البيانات لتشغيل الموقع وإدارة الحسابات والمشاركات والرد على الرسائل وتحسين الأمان.'],
    ['title'=>'ملفات الارتباط','body'=>'نستخدم ملفات الارتباط الضرورية لتشغيل الموقع وحفظ تفضيلاتك، والاختيارية بعد موافقتك.'],
    ['title'=>'حقوقك','body'=>'يمكنك طلب الاطلاع على بياناتك أو تصحيحها أو حذفها، وإلغاء الاشتراك في النشرة البريدية في أي وقت.'],
    ['title'=>'تحديث السياسة','body'=>'قد نحدّث هذه السياسة عند تغير الخدمات أو المتطلبات القانونية، وسنعرض تاريخ آخر تحديث أسفل الصفحة.']
  ]];
}
?>
<section class="page-hero privacy-policy-hero">
  <div class="container">
    <span class="eyebrow">Saradeeeb</span>
    <h1><?=e($data['title'])?></h1>
    <p><?=e($data['intro'])?></p>
  </div>
</section>
<section class="section privacy-policy-page">
  <div class="container privacy-policy-card">
    <?php foreach($data['sections'] as $section): ?>
      <article class="privacy-policy-section">
        <h2><?=e($section['title'])?></h2>
        <p><?=e($section['body'])?></p>
      </article>
    <?php endforeach; ?>
    <section class="privacy-copyright-card" lang="ar" dir="rtl" aria-labelledby="privacy-copyright-title"><div class="privacy-copyright-icon" aria-hidden="true">♢</div><h2 id="privacy-copyright-title">حماية الإبداع والحقوق الأدبية</h2><p>تؤمن سراديب أن الكلمة أمانة، وأن الإبداع حقٌّ لصاحبه. ولا نتسامح مع انتحال الأعمال الأدبية أو نسخ القصص والمقالات ونسبتها إلى غير أصحابها.</p><p>إذا اكتشفت أن قصتك المنشورة في سراديب قد سُرقت أو نُشرت دون إذنك، أو وجدت محتوى في سراديب تعتقد أنه ينتهك حقوقك الأدبية، فيمكنك إبلاغ الإدارة مع تقديم الروابط والأدلة المتاحة.</p><p>سنراجع البلاغ بعناية ونتخذ الإجراء المناسب ضمن صلاحياتنا، مع احترام حقوق جميع الأطراف.</p><a class="btn btn-primary" href="https://saradeeeb.com/contact/">الإبلاغ عن سرقة قصة أو انتهاك حقوق النشر</a></section>
    <p class="privacy-policy-updated"><?=e($data['updated'])?></p>
  </div>
</section>
