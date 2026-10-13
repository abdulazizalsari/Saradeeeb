<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\Database;

if(PHP_SAPI!=='cli') exit(1);

$categoryTranslations=[
  1=>['en'=>'Theories and Secrets','es'=>'Teorías y secretos','fr'=>'Théories et secrets','ru'=>'Теории и тайны'],
  2=>['en'=>'Ancient Civilizations','es'=>'Civilizaciones antiguas','fr'=>'Civilisations anciennes','ru'=>'Древние цивилизации'],
  3=>['en'=>'Myths and Beliefs','es'=>'Mitos y creencias','fr'=>'Mythes et croyances','ru'=>'Мифы и верования'],
  4=>['en'=>'Visitor Stories','es'=>'Historias de visitantes','fr'=>'Récits de visiteurs','ru'=>'Истории читателей'],
  5=>['en'=>'Crimes','es'=>'Crímenes','fr'=>'Crimes','ru'=>'Преступления'],
  6=>['en'=>'Cults and Beliefs','es'=>'Sectas y creencias','fr'=>'Sectes et croyances','ru'=>'Культы и верования'],
  7=>['en'=>'Events and Hidden Truths','es'=>'Sucesos y secretos','fr'=>'Événements et secrets','ru'=>'События и скрытые факты'],
  8=>['en'=>'Jinn and Demons','es'=>'Genios y demonios','fr'=>'Djinns et démons','ru'=>'Джинны и демоны'],
  9=>['en'=>'Philosophy','es'=>'Filosofía','fr'=>'Philosophie','ru'=>'Философия'],
 10=>['en'=>'The Illusion of Energy','es'=>'El engaño de la energía','fr'=>'L’illusion de l’énergie','ru'=>'Иллюзия энергии'],
];

$thoughtCategoryTranslations=[
  1=>['en'=>'Reflection','es'=>'Reflexión','fr'=>'Réflexion','ru'=>'Размышление'],
  2=>['en'=>'Wisdom','es'=>'Sabiduría','fr'=>'Sagesse','ru'=>'Мудрость'],
  3=>['en'=>'Poetry','es'=>'Poesía','fr'=>'Poésie','ru'=>'Поэзия'],
  4=>['en'=>'Philosophy','es'=>'Filosofía','fr'=>'Philosophie','ru'=>'Философия'],
  5=>['en'=>'Contemplation','es'=>'Contemplación','fr'=>'Contemplation','ru'=>'Созерцание'],
  6=>['en'=>'Saying','es'=>'Dicho','fr'=>'Pensée','ru'=>'Изречение'],
  7=>['en'=>'Quotation','es'=>'Cita','fr'=>'Citation','ru'=>'Цитата'],
  8=>['en'=>'Horror','es'=>'Terror','fr'=>'Horreur','ru'=>'Ужасы'],
  9=>['en'=>'Mystery','es'=>'Misterio','fr'=>'Mystère','ru'=>'Мистика'],
 10=>['en'=>'Civilization','es'=>'Civilización','fr'=>'Civilisation','ru'=>'Цивилизация'],
 11=>['en'=>'History','es'=>'Historia','fr'=>'Histoire','ru'=>'История'],
 12=>['en'=>'Short Text','es'=>'Texto breve','fr'=>'Texte court','ru'=>'Короткий текст'],
];

$articleTitles=[
  1=>['en'=>'The Mystery of the Hessdalen Lights','es'=>'El misterio de las luces de Hessdalen','fr'=>'Le mystère des lumières de Hessdalen','ru'=>'Тайна огней Хессдалена'],
  2=>['en'=>'The Civilization Hidden Beneath Cappadocia','es'=>'La civilización oculta bajo Capadocia','fr'=>'La civilisation cachée sous la Cappadoce','ru'=>'Цивилизация, скрытая под Каппадокией'],
  3=>['en'=>'666','es'=>'666','fr'=>'666','ru'=>'666'],
  4=>['en'=>'Eyes Above the Fence: The Strangest Night with the Violinist','es'=>'Ojos sobre la valla: la noche más extraña con el violinista','fr'=>'Des yeux au-dessus de la clôture : la nuit la plus étrange avec le violoniste','ru'=>'Глаза над забором: самая странная ночь со скрипачом'],
  5=>['en'=>'Robert Hansen: The Quiet Baker Who Turned the Alaskan Wilderness into a Hunting Ground','es'=>'Robert Hansen: el panadero tranquilo que convirtió Alaska en un coto de caza','fr'=>'Robert Hansen : le boulanger discret qui transforma l’Alaska en terrain de chasse','ru'=>'Роберт Хансен: тихий пекарь, превративший Аляску в охотничьи угодья'],
  6=>['en'=>'The Book of the Dead and the Afterlife in Ancient Egypt','es'=>'El Libro de los Muertos y el más allá en el antiguo Egipto','fr'=>'Le Livre des morts et l’au-delà dans l’Égypte antique','ru'=>'Книга мёртвых и загробный мир Древнего Египта'],
  7=>['en'=>'How Satanism Began','es'=>'Cómo comenzó el satanismo','fr'=>'Comment le satanisme a commencé','ru'=>'Как возник сатанизм'],
  8=>['en'=>'The Anunnaki: Sumerian Gods or Visitors from Another World?','es'=>'Los Anunnaki: ¿dioses sumerios o visitantes de otro mundo?','fr'=>'Les Anunnaki : dieux sumériens ou visiteurs d’un autre monde ?','ru'=>'Ануннаки: шумерские боги или гости из иного мира?'],
  9=>['en'=>'The Book That Frightened Readers Through the Ages','es'=>'El libro que aterrorizó a los lectores durante siglos','fr'=>'Le livre qui terrifia les lecteurs à travers les siècles','ru'=>'Книга, пугавшая читателей на протяжении веков'],
 10=>['en'=>'The Cemetery of the Accursed','es'=>'El cementerio de los malditos','fr'=>'Le cimetière des maudits','ru'=>'Кладбище проклятых'],
 11=>['en'=>'Jamila, Who Never Existed','es'=>'Jamila, la mujer que nunca existió','fr'=>'Jamila, celle qui n’a jamais existé','ru'=>'Джамиля, которой никогда не было'],
 12=>['en'=>'The Millennium Night Murder','es'=>'El crimen de la noche del milenio','fr'=>'Le meurtre de la nuit du millénaire','ru'=>'Убийство в ночь тысячелетия'],
 13=>['en'=>'Aum Shinrikyo: The Cult That Tried to Ignite the End of the World','es'=>'Aum Shinrikyo: la secta que quiso desatar el fin del mundo','fr'=>'Aum Shinrikyo : la secte qui voulait déclencher la fin du monde','ru'=>'Аум Синрикё: культ, пытавшийся приблизить конец света'],
 14=>['en'=>'The Terrifying Philadelphia Experiment','es'=>'El aterrador Experimento Filadelfia','fr'=>'La terrifiante expérience de Philadelphie','ru'=>'Ужасающий Филадельфийский эксперимент'],
 15=>['en'=>'The Spirits of Homes: Between the Unseen and Folklore','es'=>'Los espíritus de las casas: entre lo invisible y el folclore','fr'=>'Les esprits des maisons : entre l’invisible et le folklore','ru'=>'Духи домов: между невидимым миром и фольклором'],
 16=>['en'=>'Unknown Reflections: Questions Without Answers','es'=>'Reflejos desconocidos: preguntas sin respuesta','fr'=>'Reflets inconnus : des questions sans réponse','ru'=>'Неизвестные отражения: вопросы без ответов'],
 17=>['en'=>'The Well That Reflects Other Souls','es'=>'El pozo que refleja otras almas','fr'=>'Le puits qui reflète d’autres âmes','ru'=>'Колодец, отражающий чужие души'],
 18=>['en'=>'A Life Put on Hold','es'=>'Una vida en espera','fr'=>'Une vie en suspens','ru'=>'Жизнь, отложенная на потом'],
 19=>['en'=>'Idol Worship in Pre-Islamic Arabia: A Historical and Cultural Reading','es'=>'El culto a los ídolos en la Arabia preislámica: una lectura histórica y cultural','fr'=>'Le culte des idoles dans l’Arabie préislamique : lecture historique et culturelle','ru'=>'Поклонение идолам в доисламской Аравии: историко-культурный взгляд'],
 20=>['en'=>'The Window That Does Not Face the Street','es'=>'La ventana que no da a la calle','fr'=>'La fenêtre qui ne donne pas sur la rue','ru'=>'Окно, которое не выходит на улицу'],
];

$thoughtTranslations=[
  1=>[
    'en'=>'They departed and left my heart no solace, only pain and ever-renewing sorrow. My soul was torn as I bade them farewell, like a bird whose wing was clipped and left to falter. I am slain by every longing I concealed; whenever its fires yearn, they burn brighter.',
    'es'=>'Partieron y no dejaron consuelo en mi corazón, solo dolor y una pena que renace. Mi alma se desgarró al despedirlos, como un ave con el ala cortada que vacila. Me mata cada anhelo que oculté; cuando su fuego suspira, arde aún más.',
    'fr'=>'Ils sont partis sans laisser à mon cœur le moindre réconfort, seulement la douleur et un chagrin sans cesse renouvelé. Mon âme s’est déchirée en leur disant adieu, tel un oiseau à l’aile brisée. Chaque désir tu me tue ; dès que son feu soupire, il redouble.',
    'ru'=>'Они ушли, не оставив сердцу утешения, лишь боль и вновь оживающую печаль. Душа моя разорвалась при прощании, словно птица с подрезанным крылом. Меня убивает каждая скрытая тоска: стоит её огню вздохнуть, и он разгорается сильнее.',
  ],
  2=>[
    'en'=>'O you who knock at the door, be gentle when you knock; no one lives in this house anymore.',
    'es'=>'Tú que llamas a la puerta, hazlo con suavidad; ya no queda nadie en esta casa.',
    'fr'=>'Toi qui frappes à la porte, frappe doucement : il n’y a plus personne dans cette demeure.',
    'ru'=>'Ты, кто стучит в дверь, стучи тише: в этом доме больше никого нет.',
  ],
  3=>[
    'en'=>'Love is life for the one whose heart is alive.',
    'es'=>'El amor es vida para quien conserva vivo el corazón.',
    'fr'=>'L’amour est une vie pour celui dont le cœur demeure vivant.',
    'ru'=>'Любовь — это жизнь для того, чьё сердце живо.',
  ],
];

$podcastTitles=[
  1=>['en'=>'A Jinn Story','es'=>'Una historia de genios','fr'=>'Une histoire de djinn','ru'=>'История о джинне'],
  2=>['en'=>'A Horror Story','es'=>'Una historia de terror','fr'=>'Une histoire d’horreur','ru'=>'Страшная история'],
  3=>['en'=>'A Desert Horror Story','es'=>'Una historia de terror en el desierto','fr'=>'Une histoire d’horreur dans le désert','ru'=>'Страшная история из пустыни'],
];

Database::transaction(function() use($categoryTranslations,$thoughtCategoryTranslations,$articleTitles,$thoughtTranslations,$podcastTitles): void {
    foreach($categoryTranslations as $id=>$translations) foreach($translations as $locale=>$name){
        Database::exec('INSERT INTO category_translations(category_id,locale,name,slug,description,seo_title,seo_description,status) VALUES(?,?,?,?,?,?,?,\'published\') ON DUPLICATE KEY UPDATE name=VALUES(name),slug=VALUES(slug),seo_title=VALUES(seo_title),status=\'published\'',[$id,$locale,$name,slugify($name),'',$name,'']);
    }
    foreach($thoughtCategoryTranslations as $id=>$translations) foreach($translations as $locale=>$name){
        Database::exec('INSERT INTO khawatir_category_translations(category_id,locale,name,slug,description,status) VALUES(?,?,?,?,?,\'published\') ON DUPLICATE KEY UPDATE name=VALUES(name),slug=VALUES(slug),status=\'published\'',[$id,$locale,$name,slugify($name),'']);
    }
    foreach($articleTitles as $id=>$translations) foreach($translations as $locale=>$title){
        Database::exec('INSERT IGNORE INTO article_translations(article_id,locale,title,slug,excerpt,content,seo_title,seo_description,seo_keywords,hashtags,status,review_state) VALUES(?,?,?,?,?,?,?,?,?,?,\'draft\',\'review\')',[$id,$locale,$title,slugify($title),'','',$title,'','','']);
    }
    foreach($thoughtTranslations as $id=>$translations) foreach($translations as $locale=>$body){
        $source=Database::one('SELECT status FROM khawatir WHERE id=?',[$id]); $status=($source['status']??'draft')==='published'?'published':'review';
        Database::exec('INSERT INTO khawatir_translations(khawatir_id,locale,title,slug,body,description,seo_title,seo_description,status) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body),status=VALUES(status)',[$id,$locale,null,slugify(mb_substr($body,0,80)),$body,mb_substr($body,0,180),null,mb_substr($body,0,180),$status]);
    }
    foreach($podcastTitles as $id=>$translations) foreach($translations as $locale=>$title){
        Database::exec('INSERT IGNORE INTO podcast_episode_translations(episode_id,locale,title,slug,excerpt,content,seo_title,seo_description,og_title,og_description,status) VALUES(?,?,?,?,?,?,?,?,?,?,\'review\')',[$id,$locale,$title,slugify($title),'','',$title,'',$title,'']);
    }
});

// Persist the already reviewed core dictionaries in the CMS instead of relying
// on PHP fallbacks. Other interface namespaces remain editable in the same panel.
foreach(['en','es','fr','ru'] as $locale){
    foreach(translation_field_definitions() as $field){
        if($field['namespace']!=='core') continue;
        $value=tr((string)$field['key'],$locale);
        if($value==='' || $value===(string)$field['key']) continue;
        Database::exec('INSERT INTO translation_strings(namespace,text_key,locale,value) VALUES(\'core\',?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=CURRENT_TIMESTAMP',[$field['key'],$locale,$value]);
    }
}

echo "Seeded multilingual editorial drafts and published taxonomies.\n";
