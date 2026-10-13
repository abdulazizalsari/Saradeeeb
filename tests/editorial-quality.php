<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\EditorialQualityChecker;

function editorial_assert(bool $condition,string $message): void {
    if(!$condition) throw new RuntimeException($message);
}

$draft=[
    'article_id'=>0,
    'locale'=>'ar',
    'title'=>'',
    'slug'=>'editorial-check-'.bin2hex(random_bytes(4)),
    'excerpt'=>'',
    'content'=>'<p></p><p>هذا هذا نص هاذا!!!بدون مسافة.</p><p>أنت غبي ويمكن التواصل عبر test@example.com.</p><p><img src="/uploads/missing.webp" alt=""></p><p><a href="/article/not-found-editorial-test">رابط داخلي</a></p>',
    'seo_title'=>'',
    'seo_description'=>'',
    'image_alt'=>'',
    'featured_upload_name'=>'cover.jpg',
    'featured_upload_size'=>3*1024*1024,
    'featured_upload_type'=>'image/jpeg',
];
$before=hash('sha256',serialize($draft));
$report=(new EditorialQualityChecker())->inspect($draft);
$titles=array_column($report['findings'],'title');

editorial_assert($report['ok']===true,'The checker did not return success.');
editorial_assert(in_array('عنوان المقالة مفقود',$titles,true),'Missing title was not detected.');
editorial_assert(in_array('كلمة متكررة',$titles,true),'Repeated Arabic word was not detected.');
editorial_assert(in_array('علامات ترقيم متكررة',$titles,true),'Repeated punctuation was not detected.');
editorial_assert(in_array('مسافة مفقودة بعد علامة ترقيم',$titles,true),'Missing punctuation spacing was not detected.');
editorial_assert(in_array('تهجئة تحتاج مراجعة',$titles,true),'Supported Arabic spelling hint was not detected.');
editorial_assert(in_array('فقرة فارغة',$titles,true),'Empty paragraph was not detected.');
editorial_assert(in_array('عنوان SEO غير موجود',$titles,true),'Missing SEO title was not detected.');
editorial_assert(in_array('النص البديل للصورة مفقود',$titles,true),'Missing image alt text was not detected.');
editorial_assert(in_array('رابط داخلي غير موجود',$titles,true),'Broken internal link was not detected.');
editorial_assert(in_array('عبارة شخصية حادة',$titles,true),'Editorial policy indicator was not detected.');
editorial_assert($report['readiness']==='توجد متطلبات تقنية ناقصة','Technical readiness state is incorrect.');
editorial_assert(hash('sha256',serialize($draft))===$before,'The original draft was modified.');

echo "Editorial quality checker tests passed.\n";
