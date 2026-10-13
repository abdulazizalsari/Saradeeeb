<?php
namespace App\Services;

use App\Core\Database;

final class TranslationCompletenessValidator {
    private const LABELS=['title'=>'العنوان','excerpt'=>'المقدمة المختصرة','content'=>'نص المقال','image_alt'=>'النص البديل للصورة','image_caption'=>'وصف الصورة','seo_description'=>'وصف محركات البحث','og_description'=>'وصف المشاركة'];

    public function validateArticle(int $articleId,string $locale,?int $userId=null,bool $approve=false): array {
        if($locale==='ar') return ['state'=>'passed','label'=>'اجتازت فحص الاكتمال','blocking'=>0,'issues'=>[],'totals'=>['required'=>0,'translated'=>0,'missing'=>0,'suspicious'=>0]];
        $source=Database::one("SELECT * FROM article_translations WHERE article_id=? AND locale='ar'",[$articleId]);
        $target=Database::one('SELECT * FROM article_translations WHERE article_id=? AND locale=?',[$articleId,$locale]);
        if(!$source||!$target) throw new \RuntimeException('النسخة العربية أو الترجمة المطلوبة غير موجودة.');
        $resolutions=[];
        try{foreach(Database::all('SELECT issue_key,reason,source_hash,translation_hash FROM translation_validation_resolutions WHERE article_id=? AND locale=?',[$articleId,$locale]) as $row)$resolutions[(string)$row['issue_key']]=$row;}catch(\Throwable){}
        $report=$this->inspect($source,$target,'ar',$locale,$resolutions,$approve);
        $reportJson=json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        Database::exec("UPDATE article_translations SET completeness_state=?,completeness_source_hash=?,completeness_translation_hash=?,completeness_report=?,completeness_checked_at=CURRENT_TIMESTAMP,completeness_checked_by=?,status=CASE WHEN ?>0 THEN 'draft' ELSE status END,review_state=CASE WHEN ?>0 THEN 'review' ELSE review_state END WHERE article_id=? AND locale=?",[$report['state'],$report['source_hash'],$report['translation_hash'],$reportJson,$userId,(int)$report['blocking'],(int)$report['blocking'],$articleId,$locale]);
        return $report;
    }

    public function invalidate(int $articleId,string $locale): void {
        if($locale==='ar') Database::exec("UPDATE article_translations SET completeness_state='complete_ready',completeness_source_hash=NULL,completeness_translation_hash=NULL,completeness_report=NULL,completeness_checked_at=NULL,completeness_checked_by=NULL,status=IF(locale='ar',status,'draft'),review_state=IF(locale='ar',review_state,'review') WHERE article_id=? AND locale<>'ar'",[$articleId]);
        else Database::exec("UPDATE article_translations SET completeness_state='complete_ready',completeness_source_hash=NULL,completeness_translation_hash=NULL,completeness_report=NULL,completeness_checked_at=NULL,completeness_checked_by=NULL,status='draft',review_state='review' WHERE article_id=? AND locale=?",[$articleId,$locale]);
    }

    public function resolve(int $articleId,string $locale,string $issueKey,string $reason,int $userId): void {
        $reason=trim($reason);if(mb_strlen($reason)<10)throw new \RuntimeException('اكتب سببًا واضحًا من 10 محارف على الأقل.');
        $translation=Database::one('SELECT completeness_report FROM article_translations WHERE article_id=? AND locale=?',[$articleId,$locale]);
        $report=json_decode((string)($translation['completeness_report']??''),true)?:[];$issue=null;
        foreach($report['issues']??[] as $candidate)if(hash_equals((string)($candidate['key']??''),$issueKey)){$issue=$candidate;break;}
        if(!$issue||($issue['severity']??'')!=='ambiguous')throw new \RuntimeException('يمكن توثيق النتائج الاحتمالية فقط؛ الأخطاء المؤكدة يجب تصحيحها.');
        Database::exec('INSERT INTO translation_validation_resolutions(article_id,locale,issue_key,reason,source_hash,translation_hash,resolved_by) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason),source_hash=VALUES(source_hash),translation_hash=VALUES(translation_hash),resolved_by=VALUES(resolved_by),resolved_at=CURRENT_TIMESTAMP',[$articleId,$locale,$issueKey,mb_substr($reason,0,1000),(string)$report['source_hash'],(string)$report['translation_hash'],$userId]);
    }

    public function inspect(array $source,array $target,string $sourceLocale,string $targetLocale,array $resolutions=[],bool $approve=false): array {
        $fields=['title','excerpt','content'];
        foreach(['image_alt','image_caption','seo_description','og_description'] as $field)if(trim(strip_tags((string)($source[$field]??'')))!=='')$fields[]=$field;
        $sourceHash=$this->contentHash($source,$fields);$translationHash=$this->contentHash($target,$fields);$issues=[];$translated=0;
        foreach($fields as $field){
            $src=$this->plain((string)($source[$field]??''));$dst=$this->plain((string)($target[$field]??''));$label=self::LABELS[$field]??$field;
            if($dst===''){$issues[]=$this->issue('missing',$field,0,$src,$dst,'confirmed','الحقل المطلوب فارغ.','أدخل ترجمة كاملة لهذا الحقل.');continue;}
            $translated++;
            if(preg_match('/\b(?:TODO|TRANSLATE|UNTRANSLATED|TBD)\b|(?:بحاجة\s+إلى\s+ترجمة|غير\s+مترجم)/iu',$dst,$m))$issues[]=$this->issue('placeholder',$field,0,$src,$m[0],'confirmed','وُجدت علامة مؤقتة تدل على أن الترجمة لم تكتمل.','استبدل العلامة بالنص المترجم.');
            if($src!==''&&$this->equivalent($src,$dst)){
                $short=count($this->words($src))<=3;
                $issues[]=$this->issue('copied_source',$field,0,$src,$dst,$short?'ambiguous':'confirmed',$short?'النص القصير مطابق للأصل وقد يكون اسمًا أو عنوانًا مقصودًا.':'النص مطابق للأصل دون ترجمة.',$short?'وثّق أنه اسم أو عنوان مقصود، أو ترجمه.':'ترجم النص بدل نسخه من الأصل.');
            } elseif($src!==''&&($left=$this->retainedSourceWords($src,$dst))) {
                $issues[]=$this->issue('retained_words',$field,0,$src,implode('، ',$left),'ambiguous','توجد كلمة أو عبارة من الأصل داخل الترجمة: '.implode('، ',$left),'تحقق مما إذا كانت اسمًا أو مصطلحًا مقصودًا؛ وإلا فترجمها.');
            }
            if($field==='content')$issues=array_merge($issues,$this->inspectStructure((string)($source[$field]??''),(string)($target[$field]??''),$sourceLocale,$targetLocale));
            elseif($foreign=$this->foreignSignal($dst,$targetLocale,$sourceLocale))$issues[]=$this->issue('language_signal',$field,0,$src,$dst,'ambiguous',$foreign,'راجع العبارة، ثم صححها أو وثّق سبب إبقائها بلغتها الأصلية.');
        }
        foreach($issues as &$issue){$saved=$resolutions[$issue['key']]??null;$resolved=$issue['severity']==='ambiguous'&&$saved&&hash_equals((string)($saved['source_hash']??''),$sourceHash)&&hash_equals((string)($saved['translation_hash']??''),$translationHash);$issue['resolved']=$resolved;$issue['resolution_reason']=$resolved?(string)$saved['reason']:'';$issue['blocking']=$issue['severity']==='confirmed'||!$resolved;}unset($issue);
        $confirmed=count(array_filter($issues,fn($i)=>$i['severity']==='confirmed'));
        $suspicious=count(array_filter($issues,fn($i)=>$i['severity']==='ambiguous'&&!$i['resolved']));
        $blocking=count(array_filter($issues,fn($i)=>$i['blocking']));
        $state=$confirmed>0?'incomplete':($suspicious>0?'needs_review':($approve?'passed':'complete_ready'));
        return ['state'=>$state,'label'=>$this->stateLabel($state),'blocking'=>$blocking,'source_locale'=>$sourceLocale,'target_locale'=>$targetLocale,'source_hash'=>$sourceHash,'translation_hash'=>$translationHash,'totals'=>['required'=>count($fields),'translated'=>$translated,'missing'=>$confirmed,'suspicious'=>$suspicious],'issues'=>$issues,'limitations'=>'الفحص لغوي احتمالي؛ الأسماء والاقتباسات والمصطلحات قد تحتاج قرارًا بشريًا موثقًا.'];
    }

    private function inspectStructure(string $sourceHtml,string $targetHtml,string $sourceLocale,string $targetLocale): array {
        $source=$this->segments($sourceHtml);$target=$this->segments($targetHtml);$issues=[];
        if(count($target)<count($source))$issues[]=$this->issue('structure','content',count($target)+1,implode(' ',array_slice($source,count($target))), '', 'confirmed','عدد فقرات أو كتل الترجمة أقل من النص الأصلي.','أكمل الفقرات أو العناوين أو عناصر القائمة المفقودة.');
        foreach($target as $index=>$segment){$src=$source[$index]??'';if($segment==='')continue;$location=$index+1;
            if($src!==''&&$this->equivalent($src,$segment)){
                $short=count($this->words($src))<=3;$issues[]=$this->issue('copied_segment','content',$location,$src,$segment,$short?'ambiguous':'confirmed',$short?'كتلة قصيرة مطابقة للأصل وقد تكون اسمًا أو اقتباسًا.':'فقرة أو جملة منسوخة من الأصل دون ترجمة.',$short?'وثّق سبب الإبقاء عليها أو ترجمها.':'ترجم الكتلة كاملة.');continue;
            }
            if($src!==''&&($left=$this->retainedSourceWords($src,$segment)))$issues[]=$this->issue('retained_words','content',$location,$src,implode('، ',$left),'ambiguous','توجد كلمة أو عبارة من الأصل داخل الفقرة المترجمة: '.implode('، ',$left),'تحقق منها وصححها أو وثّق أنها اسم أو اقتباس أو مصطلح مقصود.');
            if($signal=$this->foreignSignal($segment,$targetLocale,$sourceLocale))$issues[]=$this->issue('language_signal','content',$location,$src,$segment,'ambiguous',$signal,'راجع هذه الكتلة وصحح الجزء غير المترجم أو وثّق أنه مقصود.');
        }
        return $issues;
    }

    private function segments(string $html): array {$html=trim($html);if($html==='')return[];$doc=new \DOMDocument('1.0','UTF-8');$loaded=@$doc->loadHTML('<?xml encoding="UTF-8"><div id="root">'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);if(!$loaded)return array_values(array_filter(preg_split('/\R{2,}/u',$this->plain($html))?:[]));$xp=new \DOMXPath($doc);$out=[];foreach($xp->query('//*[@id="root"]//*[self::p or self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or self::li or self::figcaption or self::blockquote]') as $node){$text=$this->plain($node->textContent);if($text!==''&&!in_array($text,$out,true))$out[]=$text;}if(!$out){$text=$this->plain($html);if($text!=='')$out[]=$text;}return$out;}
    private function foreignSignal(string $text,string $target,string $source): ?string {$clean=preg_replace('~https?://\S+|[\w.+-]+@[\w.-]+~u',' ',$text)??$text;$letters=max(1,preg_match_all('/\p{L}/u',$clean));$arabic=preg_match_all('/\p{Arabic}/u',$clean);$cyrillic=preg_match_all('/\p{Cyrillic}/u',$clean);
        if($target!=='ar'&&$arabic/$letters>.18)return 'توجد أحرف أو كلمات عربية بنسبة تستدعي مراجعة بشرية.';
        if(!in_array($target,['ru'],true)&&$cyrillic/$letters>.12)return 'توجد كلمات روسية أو سيريليّة محتملة داخل الترجمة.';
        if($target==='ru'&&$letters>12&&$cyrillic/$letters<.45)return 'نسبة الكتابة السيريليّة منخفضة في ترجمة روسية.';
        $lower=mb_strtolower($clean);$words=$this->words($lower);
        $sets=['fr'=>['une','des','dans','avec','pour','mais','cette','être','avait','nuit','voix','étrange','alors','comme'],'en'=>['the','and','with','from','this','that','was','were','have','into','night','voice'],'tr'=>['bir','ve','ile','için','bu','olan','gece','ses','ama','gibi']];
        foreach($sets as $lang=>$list){if($lang===$target)continue;$hits=count(array_intersect($words,$list));if($hits>=2)return 'توجد مفردات متتابعة توحي بأن جزءًا من النص بقي باللغة '.strtoupper($lang).'.';}
        if(in_array($target,['en','fr'],true)&&preg_match('/\b[\p{L}]*[ğışİöüç][\p{L}]*\b/iu',$lower))return 'توجد كلمة تركية محتملة داخل الترجمة.';
        return null;}
    private function issue(string $type,string $field,int $location,string $source,string $translation,string $severity,string $reason,string $action): array {$key=hash('sha256',implode('|',[$type,$field,$location,$source,$translation]));return ['key'=>$key,'type'=>$type,'field'=>$field,'field_label'=>self::LABELS[$field]??$field,'location'=>$location,'source'=>$source,'translation'=>$translation,'severity'=>$severity,'reason'=>$reason,'action'=>$action];}
    private function contentHash(array $row,array $fields): string {$values=[];foreach($fields as $field)$values[$field]=(string)($row[$field]??'');return hash('sha256',json_encode($values,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    private function equivalent(string $a,string $b): bool {$a=$this->normalize($a);$b=$this->normalize($b);if($a===''||$b==='')return false;if($a===$b)return true;$aw=$this->words($a);$bw=$this->words($b);if(count($aw)<4||count($bw)<4)return false;$intersection=count(array_intersect(array_unique($aw),array_unique($bw)));return $intersection/max(count(array_unique($aw)),count(array_unique($bw)))>=.88;}
    private function normalize(string $text): string {$text=mb_strtolower($this->plain($text));$text=preg_replace('/[\p{P}\p{S}\s]+/u',' ',$text)??$text;return trim($text);}
    private function plain(string $text): string {$text=html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8');return trim(preg_replace('/\s+/u',' ',$text)??$text);}
    private function words(string $text): array {return preg_split('/[^\p{L}\p{N}_-]+/u',$text,-1,PREG_SPLIT_NO_EMPTY)?:[];}
    private function retainedSourceWords(string $source,string $target): array {$sourceWords=array_values(array_unique(array_filter($this->words(mb_strtolower($source)),fn($word)=>mb_strlen($word)>=4)));$targetWords=array_values(array_unique($this->words(mb_strtolower($target))));return array_slice(array_values(array_intersect($sourceWords,$targetWords)),0,8);}
    private function stateLabel(string $state): string {return ['complete_ready'=>'مكتملة وجاهزة للمراجعة','incomplete'=>'ترجمة غير مكتملة','needs_review'=>'تحتاج إلى تدقيق لغوي','passed'=>'اجتازت فحص الاكتمال'][$state]??$state;}
}
