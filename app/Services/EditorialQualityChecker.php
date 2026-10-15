<?php
namespace App\Services;

use App\Core\Database;

final class EditorialQualityChecker {
    private const CATEGORIES = [
        'language'=>'اللغة والإملاء',
        'readability'=>'التنسيق والقراءة',
        'content'=>'العنوان والمحتوى',
        'media'=>'الصور والوسائط',
        'links'=>'الروابط',
        'seo'=>'محركات البحث',
        'policy'=>'ملاحظات سياسة النشر',
    ];

    public function inspect(array $draft): array {
        $articleId=max(0,(int)($draft['article_id']??0));
        $locale=preg_match('/^[a-z]{2,5}$/',(string)($draft['locale']??''))?(string)$draft['locale']:'ar';
        $title=$this->limit($draft['title']??'',255);
        $slug=$this->limit($draft['slug']??'',220);
        $excerpt=$this->limit($draft['excerpt']??'',2000);
        $content=mb_substr((string)($draft['content']??''),0,1500000);
        $seoTitle=$this->limit($draft['seo_title']??'',255);
        $seoDescription=$this->limit($draft['seo_description']??'',500);
        $imageAlt=$this->limit($draft['image_alt']??'',255);
        $featured=$this->limit($draft['featured_image']??'',700);
        $plain=$this->plain($content);
        $paragraphs=$this->paragraphs($content);
        $wordList=preg_split('/\s+/u',trim($plain),-1,PREG_SPLIT_NO_EMPTY)?:[];
        $findings=[];
        $add=function(string $category,string $severity,string $issue,string $explanation,string $location,string $suggestion,string $target='')use(&$findings):void{
            $findings[]=['category'=>$category,'severity'=>$severity,'title'=>$issue,'explanation'=>$explanation,'location'=>$location,'suggestion'=>$suggestion,'target'=>$target];
        };

        if($title==='') $add('content','technical','عنوان المقالة مفقود','لا يمكن تجهيز مقالة للنشر دون عنوان.','حقل العنوان','اكتب عنوانًا واضحًا يصف المحتوى.','title');
        elseif(mb_strlen($title)<8) $add('content','suggestion','العنوان قصير جدًا','قد لا يوضح العنوان موضوع المقالة للقارئ.','حقل العنوان','راجع العنوان إن كان الاختصار غير مقصود.','title');
        if($plain==='') $add('content','technical','محتوى المقالة مفقود','المحرر لا يحتوي نصًا قابلًا للقراءة.','حقل المحتوى','أضف محتوى المقالة قبل النشر.','content');
        elseif(count($wordList)<120) $add('content','suggestion','المحتوى قصير','النص أقصر من التوصية التحريرية العامة، وقد يكون ذلك طبيعيًا للنصوص الأدبية القصيرة.','المقالة كاملة','تأكد فقط من اكتمال الفكرة؛ لا يلزم إطالة النص إذا كان أسلوبه مقصودًا.','content');
        if($excerpt==='') $add('content','review','المقدمة المختصرة غير موجودة','تساعد المقدمة القارئ وواجهات العرض على فهم المقالة.','حقل المقدمة المختصرة','اكتب ملخصًا موجزًا من سطر أو سطرين.','excerpt');
        if((int)($draft['category_id']??0)<=0) $add('content','suggestion','التصنيف غير محدد','المقالة غير مرتبطة بأحد أقسام سراديب.','حقل التصنيف','اختر التصنيف الأنسب إذا كان المحتوى ينتمي إلى قسم واضح.','category_id');

        if(preg_match('/([!؟?،,.;؛:])\1{2,}/u',$plain)) $add('language','review','علامات ترقيم متكررة','توجد علامة ترقيم مكررة ثلاث مرات أو أكثر.','داخل المحتوى','راجع الموضع واستخدم عددًا مناسبًا من العلامات.','content');
        if(preg_match('/[،؛؟.!?](?=[\p{L}])/u',$plain)) $add('language','review','مسافة مفقودة بعد علامة ترقيم','عُثر على علامة ترقيم تليها كلمة مباشرة.','داخل المحتوى','أضف مسافة بعد علامة الترقيم حيث يلزم.','content');
        if(preg_match('/\h{3,}|\h+[،؛؟.!?,]/u',$plain)) $add('language','suggestion','مسافات غير معتادة','توجد مسافات متكررة أو مسافة قبل علامة ترقيم.','داخل المحتوى','وحّد المسافات يدويًا مع الحفاظ على تنسيق النص.','content');
        if(preg_match('/\b([\p{L}\p{N}]{2,})\s+\1\b/ui',$plain,$m)) $add('language','review','كلمة متكررة','تكررت كلمة متجاورة: «'.$m[1].'».','داخل المحتوى','تأكد إن كان التكرار مقصودًا.','content');
        $common=['هاذا'=>'هذا','هاذه'=>'هذه','لاكن'=>'لكن','مسوول'=>'مسؤول','شيئ'=>'شيء'];
        foreach($common as $wrong=>$right) if(preg_match('/(?<!\p{L})'.preg_quote($wrong,'/').'(?!\p{L})/u',$plain)) $add('language','suggestion','تهجئة تحتاج مراجعة','ظهرت كلمة «'.$wrong.'» التي تُكتب غالبًا «'.$right.'».','داخل المحتوى','تحقق من السياق وصححها يدويًا عند الحاجة.','content');

        if(preg_match('/<p\b[^>]*>(?:\s|&nbsp;|<br\s*\/?\s*>)*<\/p>/iu',$content)) $add('readability','review','فقرة فارغة','يتضمن التنسيق فقرة لا تحتوي نصًا.','داخل المحتوى','احذف الفقرة الفارغة إن لم تكن مقصودة.','content');
        foreach($paragraphs as $index=>$paragraph){
            $count=count(preg_split('/\s+/u',trim($paragraph),-1,PREG_SPLIT_NO_EMPTY)?:[]);
            if($count>180) $add('readability','review','فقرة طويلة','تحتوي الفقرة على '.$count.' كلمة، وقد تكون مرهقة بصريًا.','الفقرة '.($index+1),'فكّر في تقسيمها عند انتقال الفكرة، مع الحفاظ على صوت الكاتب.','content');
        }
        if(count($wordList)>500 && !preg_match('/<h[23]\b/i',$content)) $add('readability','suggestion','لا توجد عناوين فرعية','النص طويل نسبيًا ولا يحتوي عناوين فرعية.','هيكل المقالة','أضف عناوين فرعية فقط إذا كانت تخدم بنية النص.','content');
        if(substr_count(strtolower($content),'<h1')>0) $add('readability','review','عنوان H1 داخل المحتوى','عنوان الصفحة الرئيسي يُنشأ من حقل العنوان.','داخل المحتوى','استخدم H2 أو H3 للعناوين الداخلية.','content');
        if(preg_match_all('/\bstyle\s*=/i',$content,$styles) && count($styles[0])>12) $add('readability','suggestion','تنسيق يدوي متكرر','توجد خصائص تنسيق داخلية كثيرة قد تسبب عدم اتساق العرض.','داخل المحتوى','استخدم أدوات المحرر وأنماط الموقع قدر الإمكان.','content');
        $sentences=array_values(array_filter(array_map(fn($v)=>trim(preg_replace('/\s+/u',' ',$v)??''),preg_split('/[.!؟?]+/u',$plain)?:[]),fn($v)=>mb_strlen($v)>30));
        $seen=[]; foreach($sentences as $sentence){$key=mb_strtolower($sentence);if(isset($seen[$key])){$add('readability','review','جملة مكررة','ظهرت الجملة نفسها أكثر من مرة.','داخل المحتوى','راجع التكرار وتأكد إن كان مقصودًا.','content');break;}$seen[$key]=true;}

        if($slug==='') $add('seo','technical','الرابط المختصر مفقود','يلزم رابط مختصر صالح لحفظ عنوان المقالة العام.','حقل الرابط المختصر','أنشئ الرابط من العنوان أو اكتبه يدويًا.','slug');
        elseif(!preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',$slug)) $add('seo','review','صيغة الرابط المختصر تحتاج مراجعة','الرابط يحتوي فراغات أو رموزًا غير مناسبة.','حقل الرابط المختصر','استخدم كلمات وحروفًا وأرقامًا تفصل بينها شرطات.','slug');
        else { try { $duplicate=Database::one('SELECT article_id FROM article_translations WHERE locale=? AND slug=? AND article_id<>? LIMIT 1',[$locale,$slug,$articleId]); if($duplicate)$add('seo','technical','الرابط المختصر مستخدم','يوجد مقال آخر يستخدم الرابط نفسه في هذه اللغة.','حقل الرابط المختصر','اختر رابطًا فريدًا قبل النشر.','slug'); } catch(\Throwable){} }
        if($seoTitle==='') $add('seo','review','عنوان SEO غير موجود','يمكن استخدام عنوان المقالة، لكن العنوان المخصص يمنحك تحكمًا أفضل في نتيجة البحث.','عنوان Google','أضف عنوانًا دقيقًا دون حشو كلمات مفتاحية.','seo_title');
        elseif(mb_strlen($seoTitle)>65) $add('seo','suggestion','عنوان SEO طويل','قد يُقتطع العنوان في بعض نتائج البحث.','عنوان Google','اختصره مع الحفاظ على المعنى.','seo_title');
        if($seoDescription==='') $add('seo','review','وصف SEO غير موجود','لا يوجد وصف مخصص لنتائج البحث.','وصف Google','اكتب وصفًا صادقًا وموجزًا للمقالة.','seo_description');
        elseif(mb_strlen($seoDescription)>170) $add('seo','suggestion','وصف SEO طويل','قد يُقتطع الوصف في بعض نتائج البحث.','وصف Google','اختصره دون تغيير مضمون المقالة.','seo_description');

        $uploadName=$this->limit($draft['featured_upload_name']??'',255);
        if($uploadName!==''){
            $uploadSize=max(0,(int)($draft['featured_upload_size']??0));
            $uploadType=$this->limit($draft['featured_upload_type']??'',120);
            if(!in_array($uploadType,['image/jpeg','image/png','image/webp','image/avif'],true)) $add('media','technical','نوع الصورة غير مدعوم','الملف الجديد ليس من أنواع الصور المسموحة.','الصورة الرئيسية','اختر JPG أو PNG أو WebP أو AVIF.','featured_image');
            if($uploadSize>2*1024*1024) $add('media','suggestion','حجم الصورة كبير','يتجاوز الملف الجديد 2 ميغابايت وقد يؤثر في سرعة الصفحة.','الصورة الرئيسية','راجع نسخة محسنة يدويًا قبل الرفع.','featured_image');
            if($imageAlt==='') $add('media','review','النص البديل للصورة مفقود','أضفت صورة رئيسية جديدة دون وصف بديل.','النص البديل للصورة','اكتب وصفًا موجزًا ودقيقًا للصورة.','image_alt');
        } elseif($featured==='') $add('media','suggestion','لا توجد صورة رئيسية','الصورة الرئيسية اختيارية حاليًا، لكنها تحسن بطاقات العرض والمشاركة.','حقل الصورة الرئيسية','اختر صورة مناسبة من مكتبة الوسائط إن احتاج المقال إليها.','featured_image');
        else $this->inspectFeatured($featured,$imageAlt,$add);
        $this->inspectContentImages($content,$add);
        $this->inspectLinks($content,$locale,$add);

        if(preg_match('/(?<!\p{L})(غبي|أحمق|حقير|تافه|سافل)(?!\p{L})/u',$plain,$m)) $add('policy','review','عبارة شخصية حادة','ظهرت كلمة «'.$m[1].'». السياق هو الذي يحدد إن كانت اقتباسًا أو إساءة شخصية.','داخل المحتوى','راجع السياق وفق مبدأ مناقشة الفكرة وترك الأشخاص خارج الخصومة.','content');
        if(preg_match('/\b[\w.%+\-]+@[\w.\-]+\.[A-Za-z]{2,}\b/u',$plain) || preg_match('/(?:\+?\d[\s\-()]*){8,}/u',$plain)) $add('policy','review','بيانات تواصل محتملة','قد يحتوي النص بريدًا إلكترونيًا أو رقم هاتف لشخص.','داخل المحتوى','تحقق من ضرورة النشر ووجود موافقة مناسبة.','content');
        if(preg_match('/(منقول|المصدر غير معروف|جميع الحقوق محفوظة)/u',$plain)) $add('policy','review','نسبة المحتوى تحتاج توثيقًا','توجد عبارة قد تشير إلى نقل أو اقتباس من مصدر آخر.','داخل المحتوى','تحقق من الإذن والمصدر ووضّح النسبة قبل النشر.','content');

        foreach(self::CATEGORIES as $key=>$label) if(!array_filter($findings,fn($finding)=>$finding['category']===$key)) $findings[]=['category'=>$key,'severity'=>'passed','title'=>'اجتاز الفحص','explanation'=>'لم يرصد الفحص الحتمي مشكلة واضحة في هذا الجانب.','location'=>'—','suggestion'=>'تبقى المراجعة البشرية هي المرجع النهائي.','target'=>''];
        $technical=count(array_filter($findings,fn($f)=>$f['severity']==='technical'));
        $review=count(array_filter($findings,fn($f)=>$f['severity']==='review'));
        $readiness=$technical?'توجد متطلبات تقنية ناقصة':($review?'تحتاج إلى تحسينات':'جاهزة للمراجعة النهائية');
        return ['ok'=>true,'readiness'=>$readiness,'metrics'=>['words'=>count($wordList),'minutes'=>count($wordList)?max(1,(int)ceil(count($wordList)/180)):0,'paragraphs'=>count($paragraphs)],'categories'=>self::CATEGORIES,'findings'=>$findings,'scope'=>'فحص محلي حتمي للمسودة الحالية؛ لا يدّعي اكتشاف الأخطاء النحوية أو الانتحال، ولا يرسل المحتوى إلى طرف خارجي.'];
    }

    private function inspectFeatured(string $path,string $imageAlt,callable $add): void {
        if(preg_match('#^(?:https?:)?//#i',$path)){ $add('media','review','مصدر الصورة خارجي','تعذر التحقق محليًا من ملف الصورة وبيانات ترخيصه.','الصورة الرئيسية','راجع المصدر وحقوق الاستخدام والتوفر.','featured_image'); return; }
        $relative='/'.ltrim((string)(parse_url($path,PHP_URL_PATH)?:$path),'/');
        $file=BASE_PATH.str_replace('/',DIRECTORY_SEPARATOR,$relative);
        if(!is_file($file)) $add('media','technical','ملف الصورة الرئيسية غير موجود','المسار المختار لا يشير إلى ملف متاح على الخادم.','الصورة الرئيسية','اختر الصورة مجددًا من مكتبة الوسائط.','featured_image');
        try{$media=Database::one('SELECT alt_text,width,height,size_bytes FROM media WHERE path=? LIMIT 1',[$path]);}catch(\Throwable){$media=null;}
        if($imageAlt==='' && trim((string)($media['alt_text']??''))==='') $add('media','review','النص البديل للصورة مفقود','الصورة الرئيسية لا تملك وصفًا بديلًا في المقالة أو مكتبة الوسائط.','النص البديل للصورة','اكتب وصفًا موجزًا ودقيقًا للصورة.','image_alt');
        if((int)($media['size_bytes']??0)>2*1024*1024) $add('media','suggestion','حجم الصورة كبير','يتجاوز ملف الصورة 2 ميغابايت وقد يؤثر في سرعة الصفحة.','الصورة الرئيسية','راجع نسخة محسنة يدويًا دون تعديل الأصل أثناء الفحص.','featured_image');
        if((int)($media['width']??0)>0 && (int)$media['width']<1000) $add('media','suggestion','دقة الصورة محدودة','عرض الصورة أقل من 1000 بكسل.','الصورة الرئيسية','تأكد من وضوحها في البطاقات والشاشات الكبيرة.','featured_image');
    }

    private function inspectContentImages(string $html,callable $add): void {
        if(!preg_match_all('/<img\b([^>]*)>/iu',$html,$images)) return;
        $seen=[];
        foreach($images[1] as $index=>$attributes){
            preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/iu',$attributes,$srcMatch); $src=html_entity_decode((string)($srcMatch[2]??''),ENT_QUOTES|ENT_HTML5,'UTF-8');
            preg_match('/\balt\s*=\s*(["\'])(.*?)\1/iu',$attributes,$altMatch); $alt=trim(html_entity_decode((string)($altMatch[2]??''),ENT_QUOTES|ENT_HTML5,'UTF-8'));
            if($src==='' || preg_match('#^(javascript|data|file):#i',$src)) $add('media','technical','مرجع صورة غير صالح','توجد صورة بلا مصدر آمن.','الصورة '.($index+1).' داخل المحتوى','اختر الصورة من مكتبة الوسائط.','content');
            if($alt==='') $add('media','review','صورة بلا نص بديل','إحدى صور المحتوى لا تحتوي وصفًا بديلًا.','الصورة '.($index+1).' داخل المحتوى','أضف alt مناسبًا للصورة.','content');
            if($src!=='' && isset($seen[$src])) $add('media','suggestion','صورة مكررة','استُخدمت الصورة نفسها أكثر من مرة.','داخل المحتوى','تأكد أن التكرار ضروري.','content');
            $seen[$src]=true;
        }
    }

    private function inspectLinks(string $html,string $locale,callable $add): void {
        if(!preg_match_all('/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/iu',$html,$links)) return;
        foreach($links[2] as $index=>$raw){
            $href=trim(html_entity_decode($raw,ENT_QUOTES|ENT_HTML5,'UTF-8')); $location='الرابط '.($index+1).' داخل المحتوى';
            if($href==='' || preg_match('#^(javascript|data|file|vbscript):#i',$href)){ $add('links','technical','رابط غير آمن أو فارغ','صيغة الرابط غير مسموح بها.',$location,'احذف الرابط أو استبدله بعنوان HTTPS صالح.','content');continue; }
            if(str_starts_with($href,'#') || preg_match('#^(mailto|tel):#i',$href)) continue;
            if(preg_match('#^https?://#i',$href)){ if(!filter_var($href,FILTER_VALIDATE_URL))$add('links','technical','رابط خارجي غير صالح','تعذر تحليل عنوان الرابط.',$location,'صحح العنوان الكامل.','content'); continue; }
            if(!str_starts_with($href,'/')){$add('links','review','رابط نسبي غير واضح','الرابط لا يبدأ بشرطة مائلة ولا بعنوان كامل.',$location,'استخدم رابطًا داخليًا يبدأ بـ / أو رابط HTTPS كاملًا.','content');continue;}
            $path=(string)(parse_url($href,PHP_URL_PATH)?:''); $parts=array_values(array_filter(explode('/',trim($path,'/')))); if(isset($parts[0]) && preg_match('/^[a-z]{2,5}$/',$parts[0]))array_shift($parts);
            $kind=$parts[0]??'';$slug=$parts[1]??''; if($slug==='')continue;
            $map=['article'=>['article_translations','slug'],'page'=>['page_translations','slug'],'category'=>['category_translations','slug'],'writer'=>['users','username']];
            if(isset($map[$kind])){[$table,$column]=$map[$kind];try{$row=Database::one("SELECT {$column} FROM {$table} WHERE {$column}=? LIMIT 1",[$slug]);if(!$row)$add('links','technical','رابط داخلي غير موجود','لم يُعثر على الوجهة داخل قاعدة البيانات.',$location,'صحح الرابط أو اختر وجهة موجودة.','content');}catch(\Throwable){}}
        }
    }

    private function plain(string $html): string { return trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html)),ENT_QUOTES|ENT_HTML5,'UTF-8'))??''); }
    private function paragraphs(string $html): array { $parts=preg_split('/<\/?(?:p|div|h[1-6]|li|blockquote)\b[^>]*>|<br\s*\/?\s*>/iu',$html)?:[]; return array_values(array_filter(array_map(fn($part)=>$this->plain($part),$parts),fn($part)=>$part!=='')); }
    private function limit(mixed $value,int $length): string { return mb_substr(trim((string)$value),0,$length); }
}
