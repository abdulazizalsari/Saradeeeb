<?php
$copy=[
    'ar'=>[
        'eyebrow'=>'حكاية المكان',
        'hero'=>'من خلف سراديب',
        'intro'=>'مساحة تحريرية عربية تبحث عمّا وراء الحكايات المألوفة: تاريخٌ غامض، وأسئلة مفتوحة، ونصوص تترك أثرًا بعد قراءتها.',
        'purpose_title'=>'لماذا وُجد سراديب؟',
        'purpose'=>'لنمنح القارئ مكانًا هادئًا للمعرفة والسرد؛ بعيدًا عن الضجيج، وقريبًا من القصة الجيدة والفكرة الدقيقة.',
        'values_title'=>'ما الذي يقودنا؟',
        'values'=>[
            ['العُمق قبل السرعة','نمنح كل موضوع حقه من البحث والتحرير والسياق.'],
            ['السرد المسؤول','نفرّق بين الوقائع والروايات والتأملات بوضوح.'],
            ['مساحة للكتّاب','نفتح الباب للأصوات التي تملك قصة تستحق أن تُروى.'],
        ],
        'cta_title'=>'لديك قصة أو فكرة؟',
        'cta_text'=>'أرسلها إلى فريق سراديب، أو تقدّم للانضمام إلى منتدى الكتّاب.',
        'story'=>'أرسل قصتك',
        'writer'=>'انضم ككاتب',
    ],
    'en'=>[
        'eyebrow'=>'The story behind the site','hero'=>'Behind Saradeeeb','intro'=>'An Arabic editorial space for what lies beyond familiar stories: mysterious history, open questions and writing that stays with you.',
        'purpose_title'=>'Why Saradeeeb exists','purpose'=>'To give readers a calm place for knowledge and storytelling—away from noise and close to a good story and a precise idea.',
        'values_title'=>'What guides us',
        'values'=>[['Depth before speed','Every subject deserves research, editing and context.'],['Responsible storytelling','We clearly distinguish facts, accounts and reflections.'],['A space for writers','We welcome voices with a story worth telling.']],
        'cta_title'=>'Have a story or an idea?','cta_text'=>'Send it to the Saradeeeb team, or apply to join our writers’ community.','story'=>'Send your story','writer'=>'Join as a writer',
    ],
    'es'=>[
        'eyebrow'=>'La historia detrás del sitio','hero'=>'Detrás de Saradeeeb','intro'=>'Un espacio editorial árabe para explorar lo que hay detrás de las historias conocidas: historia misteriosa, preguntas abiertas y textos que perduran.',
        'purpose_title'=>'Por qué existe Saradeeeb','purpose'=>'Para ofrecer a los lectores un lugar sereno para el conocimiento y la narración, lejos del ruido y cerca de una buena historia.',
        'values_title'=>'Lo que nos guía','values'=>[['Profundidad antes que velocidad','Cada tema merece investigación, edición y contexto.'],['Narración responsable','Distinguimos con claridad hechos, relatos y reflexiones.'],['Un espacio para autores','Damos la bienvenida a voces con una historia que merece ser contada.']],
        'cta_title'=>'¿Tienes una historia o una idea?','cta_text'=>'Envíala al equipo de Saradeeeb o solicita unirte a nuestra comunidad de autores.','story'=>'Envía tu historia','writer'=>'Únete como autor',
    ],
    'fr'=>[
        'eyebrow'=>'L’histoire derrière le site','hero'=>'Dans les coulisses de Saradeeeb','intro'=>'Un espace éditorial arabe consacré à ce qui se cache derrière les récits familiers : histoire mystérieuse, questions ouvertes et textes durables.',
        'purpose_title'=>'Pourquoi Saradeeeb existe','purpose'=>'Offrir aux lecteurs un lieu serein pour le savoir et le récit, loin du bruit et proche d’une bonne histoire.',
        'values_title'=>'Ce qui nous guide','values'=>[['La profondeur avant la vitesse','Chaque sujet mérite recherche, édition et contexte.'],['Un récit responsable','Nous distinguons clairement les faits, les récits et les réflexions.'],['Un espace pour les auteurs','Nous accueillons les voix qui ont une histoire à raconter.']],
        'cta_title'=>'Vous avez une histoire ou une idée ?','cta_text'=>'Envoyez-la à l’équipe Saradeeeb ou rejoignez notre communauté d’auteurs.','story'=>'Envoyez votre histoire','writer'=>'Devenir auteur',
    ],
    'ru'=>[
        'eyebrow'=>'История проекта','hero'=>'За кулисами Saradeeeb','intro'=>'Арабское редакционное пространство для историй за пределами привычного: загадочной истории, открытых вопросов и текстов, которые остаются с читателем.',
        'purpose_title'=>'Зачем существует Saradeeeb','purpose'=>'Чтобы дать читателям спокойное место для знаний и повествования — вдали от шума и рядом с хорошей историей.',
        'values_title'=>'Что нами движет','values'=>[['Глубина важнее скорости','Каждая тема заслуживает исследования, редактуры и контекста.'],['Ответственное повествование','Мы ясно отделяем факты, рассказы и размышления.'],['Пространство для авторов','Мы рады голосам с историей, которую стоит рассказать.']],
        'cta_title'=>'Есть история или идея?','cta_text'=>'Отправьте её команде Saradeeeb или подайте заявку в сообщество авторов.','story'=>'Отправить историю','writer'=>'Стать автором',
    ],
];
$page=$copy[$locale]??$copy['en'];
?>
<main class="behind-page">
  <section class="behind-hero">
    <div class="container behind-hero-grid">
      <div class="behind-hero-copy">
        <span class="eyebrow"><?=e($page['eyebrow'])?></span>
        <h1><?=e($page['hero'])?></h1>
        <p><?=e($page['intro'])?></p>
      </div>
      <div class="behind-hero-mark" aria-hidden="true"><span>س</span><i></i><i></i><i></i></div>
    </div>
  </section>
  <section class="behind-purpose section">
    <div class="container behind-purpose-grid">
      <div><span class="eyebrow"><?=e($page['purpose_title'])?></span><h2><?=e($page['purpose'])?></h2></div>
      <div class="behind-purpose-line" aria-hidden="true"></div>
      <p><?=e($page['intro'])?></p>
    </div>
  </section>
  <section class="behind-values section section-muted">
    <div class="container"><div class="section-head"><div><span class="eyebrow">Saradeeeb</span><h2><?=e($page['values_title'])?></h2></div></div>
      <div class="behind-values-grid"><?php foreach($page['values'] as [$heading,$text]):?><article><b><?=e($heading)?></b><p><?=e($text)?></p></article><?php endforeach;?></div>
    </div>
  </section>
  <section class="behind-cta section"><div class="container behind-cta-inner"><div><span class="eyebrow">Saradeeeb</span><h2><?=e($page['cta_title'])?></h2><p><?=e($page['cta_text'])?></p></div><div class="behind-cta-actions"><a class="btn btn-primary" href="<?=e(locale_url($locale,'send-story'))?>"><?=e($page['story'])?></a><a class="btn btn-ghost" href="<?=e(locale_url($locale,'writer-application'))?>"><?=e($page['writer'])?></a></div></div></section>
</main>
