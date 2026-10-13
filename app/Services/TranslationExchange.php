<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use DOMDocument;
use RuntimeException;
use ZipArchive;

final class TranslationExchange {
    private static function configs(): array {
        return [
            'article'=>['table'=>'article_translations','id'=>'article_id','fields'=>['title','slug','excerpt','content','seo_title','seo_description','seo_keywords','hashtags','image_alt','image_caption','og_title','og_description'],'title'=>'title'],
            'page'=>['table'=>'page_translations','id'=>'page_id','fields'=>['title','slug','content','seo_title','seo_description'],'title'=>'title'],
            'category'=>['table'=>'category_translations','id'=>'category_id','fields'=>['name','slug','description','seo_title','seo_description'],'title'=>'name'],
            'tag'=>['table'=>'tag_translations','id'=>'tag_id','fields'=>['name','slug','description','seo_title','seo_description'],'title'=>'name'],
            'podcast'=>['table'=>'podcast_episode_translations','id'=>'episode_id','fields'=>['title','slug','excerpt','content','seo_title','seo_description','og_title','og_description'],'title'=>'title'],
            'khawatir'=>['table'=>'khawatir_translations','id'=>'khawatir_id','fields'=>['title','slug','body'],'title'=>'title'],
            'khawatir_category'=>['table'=>'khawatir_category_translations','id'=>'category_id','fields'=>['name','slug','description'],'title'=>'name'],
            // Writer profiles are translated content too, but deliberately have no slug.
            'writer_profile'=>['table'=>'writer_profile_translations','id'=>'user_id','fields'=>['display_name','bio','professional_title','cv_summary'],'title'=>'display_name','slug_field'=>false],
        ];
    }

    public static function normalizeTypes(array $types): array {
        $map=['articles'=>'article','pages'=>'page','categories'=>'category','tags'=>'tag','podcasts'=>'podcast','khawatir'=>'khawatir','khawatir_categories'=>'khawatir_category','thought_categories'=>'khawatir_category','writers'=>'writer_profile','writer_profiles'=>'writer_profile','interface'=>'ui','ui'=>'ui','header'=>'header','footer'=>'footer','seo'=>'seo'];
        $out=[];
        foreach($types as $type){
            if($type==='all') return ['ui','article','page','category','tag','podcast','khawatir','khawatir_category','writer_profile'];
            if(isset($map[$type])) $out[]=$map[$type];
        }
        return array_values(array_unique($out ?: ['ui','article','page','category','tag','podcast','khawatir','khawatir_category','writer_profile']));
    }

    public static function rows(array $types,array $locales,string $filter='all'): array {
        $types=self::normalizeTypes($types);
        $available=array_keys(\all_locales()); $sourceLocale=\default_locale();
        $requestedLocales=array_values(array_intersect($available,$locales));
        $locales=array_values(array_filter(array_intersect($available,$locales),fn($v)=>$v!==$sourceLocale));
        if(!$locales)$locales=array_values(array_filter($available,fn($v)=>$v!==$sourceLocale));
        $rows=[]; $states=self::states();
        if(array_intersect($types,['ui','header','footer'])){
            foreach(\translation_field_definitions() as $definition){
                $namespace=(string)$definition['namespace'];
                if(in_array('header',$types,true)&&!in_array('ui',$types,true)&&!str_contains($namespace,'nav')&&!str_contains($namespace,'header'))continue;
                if(in_array('footer',$types,true)&&!in_array('ui',$types,true)&&!str_contains($namespace,'footer')&&!str_contains($namespace,'newsletter'))continue;
                $key='ui.'.$namespace.'.'.$definition['key'];$values=[];
                foreach($locales as $locale){$record=Database::one('SELECT value FROM translation_strings WHERE namespace=? AND text_key=? AND locale=?',[$namespace,$definition['key'],$locale]);$values[$locale]=(string)($record['value']??'');}
                $text=(string)$definition['fallback'];
                $row=['translation_key'=>$key,'entity_type'=>'ui','entity_id'=>0,'field'=>'value','source_locale'=>$sourceLocale,'source_text'=>$text,'source_hash'=>hash('sha256',$text),'values'=>$values];
                if(self::passesFilter($row,$filter,$states))$rows[]=$row;
            }
        }
        $seoOnly=in_array('seo',$types,true);
        foreach(self::configs() as $entity=>$config){
            if(!$seoOnly&&!in_array($entity,$types,true))continue;
            $entitySourceLocale=in_array($entity,['article','page'],true)?'ar':$sourceLocale;
            $entityLocales=in_array($entity,['article','page'],true)?array_values(array_filter($requestedLocales?:$available,static fn($code)=>$code!=='ar')):$locales;
            $sourceRows=Database::all('SELECT * FROM '.$config['table'].' WHERE locale=?',[$entitySourceLocale]);$targets=[];
            if($entityLocales){$marks=implode(',',array_fill(0,count($entityLocales),'?'));foreach(Database::all('SELECT * FROM '.$config['table'].' WHERE locale IN ('.$marks.')',$entityLocales) as $target)$targets[(int)$target[$config['id']]][(string)$target['locale']]=$target;}
            foreach($sourceRows as $source){
                $id=(int)$source[$config['id']];
                foreach($config['fields'] as $field){
                    $isSeo=str_starts_with($field,'seo_')||str_starts_with($field,'og_');if($seoOnly&&!$isSeo)continue;
                    $text=(string)($source[$field]??'');$values=[];$rowStatuses=[];
                    foreach($entityLocales as $locale){$target=$targets[$id][$locale]??[];$values[$locale]=(string)($target[$field]??'');$rowStatuses[$locale]=(string)($target['review_state']??$target['status']??'');}
                    $key=$entity.'.'.$id.'.'.$field;
                    $row=['translation_key'=>$key,'entity_type'=>$entity,'entity_id'=>$id,'field'=>$field,'source_locale'=>$entitySourceLocale,'source_text'=>$text,'source_hash'=>hash('sha256',$text),'values'=>$values,'row_statuses'=>$rowStatuses];
                    if(self::passesFilter($row,$filter,$states))$rows[]=$row;
                }
            }
        }
        return $rows;
    }

    private static function passesFilter(array $row,string $filter,array $states): bool {
        if($filter==='untranslated'){foreach($row['values'] as $value)if(trim((string)$value)==='')return true;return false;}
        if($filter==='review'){foreach($row['values'] as $locale=>$value)if(($states[$row['translation_key']][$locale]??'')==='needs_review'||($row['row_statuses'][$locale]??'')==='review')return true;return false;}
        return true;
    }
    private static function states(): array {$out=[];try{foreach(Database::all('SELECT translation_key,locale,state FROM translation_entry_states') as $row)$out[$row['translation_key']][$row['locale']]=$row['state'];}catch(\Throwable){}return $out;}
    public static function headers(array $locales): array {return array_merge(['translation_key','entity_type','entity_id','field','source_locale','source_text','source_hash'],$locales);}
    public static function tabular(array $rows,array $locales): array {$table=[self::headers($locales)];foreach($rows as $row){$line=[$row['translation_key'],$row['entity_type'],$row['entity_id'],$row['field'],$row['source_locale'],$row['source_text'],$row['source_hash']];foreach($locales as $locale)$line[]=(string)($row['values'][$locale]??'');$table[]=$line;}return $table;}

    public static function writeCsv(array $rows,array $locales,string $path): void {$h=fopen($path,'wb');if(!$h)throw new RuntimeException('تعذر إنشاء CSV.');fwrite($h,"\xEF\xBB\xBF");foreach(self::tabular($rows,$locales) as $line)fputcsv($h,$line);fclose($h);}
    public static function writeJson(array $rows,array $locales,string $path): void {
        $payload=['format'=>'saradeeeb-translations-v1','exported_at'=>date(DATE_ATOM),'source_locale'=>\default_locale(),'languages'=>$locales,'rows'=>[]];
        foreach($rows as $row){$item=array_diff_key($row,['values'=>true,'row_statuses'=>true]);foreach($locales as $locale)$item[$locale]=(string)($row['values'][$locale]??'');$payload['rows'][]=$item;}
        file_put_contents($path,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    }

    public static function writeXlsx(array $rows,array $locales,string $path): void {
        $zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('تعذر إنشاء XLSX.');
        $table=self::tabular($rows,$locales);$sheet=['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>','<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>'];
        foreach(self::headers($locales) as $i=>$header){$width=$header==='source_text'?70:(in_array($header,$locales,true)?55:22);$sheet[]='<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>';}
        $sheet[]='</cols><sheetData>';
        foreach($table as $ri=>$line){$sheet[]='<row r="'.($ri+1).'">';foreach($line as $ci=>$value){$ref=self::columnName($ci+1).($ri+1);$style=$ri===0?' s="1"':' s="2"';$sheet[]='<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::xml((string)$value).'</t></is></c>';}$sheet[]='</row>';}
        $last=self::columnName(count(self::headers($locales))).max(1,count($table));$sheet[]='</sheetData><autoFilter ref="A1:'.$last.'"/></worksheet>';
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Translations" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF127D82"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml',implode('',$sheet));$zip->close();
    }

    public static function parseFile(string $path,string $extension): array {
        return match(strtolower($extension)){'csv'=>self::readCsv($path),'json'=>self::readJson($path),'xlsx'=>self::readXlsx($path),default=>throw new RuntimeException('صيغة الملف غير مدعومة.')};
    }
    private static function readCsv(string $path): array {
        $h=fopen($path,'rb');if(!$h)throw new RuntimeException('تعذر قراءة CSV.');$table=[];
        while(($row=fgetcsv($h))!==false){if(isset($row[0]))$row[0]=preg_replace('/^\xEF\xBB\xBF/','',$row[0]);$table[]=$row;}fclose($h);
        return self::rowsFromTable($table);
    }
    private static function readJson(string $path): array {
        $data=json_decode((string)file_get_contents($path),true);if(!is_array($data))throw new RuntimeException('ملف JSON غير صالح.');
        $rows=$data['rows']??$data;if(!is_array($rows))throw new RuntimeException('لا توجد صفوف ترجمة.');return array_values(array_filter($rows,'is_array'));
    }
    private static function readXlsx(string $path): array {
        $zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('تعذر فتح XLSX.');
        $sheetInfo=$zip->statName('xl/worksheets/sheet1.xml');if(!$sheetInfo||($sheetInfo['size']??0)>50*1024*1024){$zip->close();throw new RuntimeException('ورقة XLSX مفقودة أو كبيرة جدًا.');}
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');if($sheet===false){$zip->close();throw new RuntimeException('ورقة XLSX الأولى غير موجودة.');}
        $shared=[];$sharedXml=$zip->getFromName('xl/sharedStrings.xml');
        if(stripos($sheet,'<!DOCTYPE')!==false||($sharedXml!==false&&stripos($sharedXml,'<!DOCTYPE')!==false)){$zip->close();throw new RuntimeException('ملف XLSX يحتوي بنية XML غير مسموحة.');}
        if($sharedXml!==false){$sharedInfo=$zip->statName('xl/sharedStrings.xml');if(($sharedInfo['size']??0)>20*1024*1024){$zip->close();throw new RuntimeException('قاموس XLSX كبير جدًا.');}$doc=new DOMDocument();@$doc->loadXML($sharedXml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);foreach((new \DOMXPath($doc))->query('//*[local-name()="si"]') as $node)$shared[]=$node->textContent;}
        $zip->close();$doc=new DOMDocument();if(!@$doc->loadXML($sheet,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING))throw new RuntimeException('تعذر قراءة ورقة XLSX.');
        $xpath=new \DOMXPath($doc);$table=[];
        foreach($xpath->query('//*[local-name()="row"]') as $rowNode){$row=[];
            foreach($xpath->query('./*[local-name()="c"]',$rowNode) as $cell){
                $ref=$cell->attributes?->getNamedItem('r')?->nodeValue??'A1';preg_match('/^[A-Z]+/',$ref,$m);$index=self::columnIndex($m[0]??'A')-1;
                $type=$cell->attributes?->getNamedItem('t')?->nodeValue??'';$value='';
                if($type==='inlineStr'){foreach($xpath->query('.//*[local-name()="t"]',$cell) as $node)$value.=$node->textContent;}
                else{$v=$xpath->query('./*[local-name()="v"]',$cell)->item(0)?->textContent??'';$value=$type==='s'?(string)($shared[(int)$v]??''):$v;}
                $row[$index]=$value;
            }
            if($row){ksort($row);$max=max(array_keys($row));$table[]=array_map(fn($i)=>(string)($row[$i]??''),range(0,$max));}
        }
        return self::rowsFromTable($table);
    }
    private static function rowsFromTable(array $table): array {
        if(!$table)return [];$headers=array_map(fn($v)=>trim((string)$v),array_shift($table));$rows=[];
        foreach($table as $line){$row=[];foreach($headers as $i=>$header)if($header!=='')$row[$header]=(string)($line[$i]??'');if(array_filter($row,fn($v)=>trim((string)$v)!==''))$rows[]=$row;}return $rows;
    }

    public static function catalog(): array {$rows=self::rows(['all'],[],'all');$out=[];foreach($rows as $row)$out[$row['translation_key']]=$row;return $out;}
    public static function placeholders(string $text): array {preg_match_all('/\{[A-Za-z0-9_.-]+\}|:[A-Za-z_][A-Za-z0-9_]*|%[A-Za-z0-9_]+%/u',$text,$m);$out=array_values(array_unique($m[0]??[]));sort($out);return $out;}

    public static function writeTranslation(array $meta,string $locale,string $value,int $userId,string $state='needs_review'): void {
        $key=(string)$meta['translation_key'];$value=trim($value);if($value==='')return;
        if($meta['entity_type']==='page'){
            if($locale==='ar') throw new RuntimeException('العربية هي الأصل؛ عدّلها من محرر الصفحات.');
            $source=Database::one("SELECT title,content FROM page_translations WHERE page_id=? AND locale='ar'",[(int)$meta['entity_id']]);
            if(!$source || trim($source['title'])==='' || trim($source['content'])==='') throw new RuntimeException('لا يمكن إضافة ترجمة دون صفحة عربية أصلية.');
        }
        if($meta['entity_type']==='article'){
            if($locale==='ar') throw new RuntimeException('العربية هي الأصل؛ عدّلها من محرر المقالة، وليس باستيراد ترجمة.');
            $source=Database::one("SELECT title,content FROM article_translations WHERE article_id=? AND locale='ar'",[(int)$meta['entity_id']]);
            if(!$source || trim((string)$source['title'])==='' || trim(strip_tags((string)$source['content']))==='') throw new RuntimeException('لا يمكن إضافة ترجمة دون مقالة عربية أم مكتملة.');
        }
        if($meta['entity_type']==='ui'){
            $parts=explode('.',$key,3);if(count($parts)!==3)throw new RuntimeException('مفتاح واجهة غير صالح.');
            Database::exec('INSERT INTO translation_strings(namespace,text_key,locale,value) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=CURRENT_TIMESTAMP',[$parts[1],$parts[2],$locale,$value]);
        }else{
            $configs=self::configs();$config=$configs[$meta['entity_type']]??null;if(!$config||!in_array($meta['field'],$config['fields'],true))throw new RuntimeException('حقل ترجمة غير صالح.');
            $table=$config['table'];$idCol=$config['id'];$id=(int)$meta['entity_id'];$field=$meta['field'];
            $existing=Database::one('SELECT * FROM '.$table.' WHERE '.$idCol.'=? AND locale=?',[$id,$locale]);
            if(!$existing){
                if($field!==$config['title'])throw new RuntimeException('يجب استيراد العنوان قبل بقية حقول هذا العنصر.');
                $needsSlug=(bool)($config['slug_field']??true);
                $slug=$needsSlug?self::uniqueSlug($table,$locale,\slugify($value)?:$meta['entity_type'].'-'.$id,$idCol,$id):'';
                self::insertShell($meta['entity_type'],$id,$locale,$value,$slug);
                $existing=Database::one('SELECT * FROM '.$table.' WHERE '.$idCol.'=? AND locale=?',[$id,$locale]);
            }
            if(!$existing)throw new RuntimeException('تعذر إنشاء سجل الترجمة.');
            if(($config['slug_field']??true) && $field==='slug')$value=self::uniqueSlug($table,$locale,\slugify($value)?:$meta['entity_type'].'-'.$id,$idCol,$id);
            Database::exec('UPDATE '.$table.' SET '.$field.'=?,updated_at=CURRENT_TIMESTAMP WHERE '.$idCol.'=? AND locale=?',[$value,$id,$locale]);
            self::markContentReview($meta['entity_type'],$table,$idCol,$id,$locale);
        }
        Database::exec("INSERT INTO translation_entry_states(translation_key,locale,state,source_hash,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE state=VALUES(state),source_hash=VALUES(source_hash),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP",[$key,$locale,$state,$meta['source_hash'],$userId]);
    }
    private static function insertShell(string $entity,int $id,string $locale,string $title,string $slug): void {
        match($entity){
            'article'=>Database::exec("INSERT INTO article_translations(article_id,locale,title,slug,status,review_state) VALUES(?,?,?,?,'draft','review')",[$id,$locale,$title,$slug]),
            'page'=>Database::exec("INSERT INTO page_translations(page_id,locale,title,slug,content,status,review_state) VALUES(?,?,?,?,'','draft','review')",[$id,$locale,$title,$slug]),
            'category'=>Database::exec("INSERT INTO category_translations(category_id,locale,name,slug,status) VALUES(?,?,?,?,'review')",[$id,$locale,$title,$slug]),
            'tag'=>Database::exec("INSERT INTO tag_translations(tag_id,locale,name,slug,status) VALUES(?,?,?,?,'review')",[$id,$locale,$title,$slug]),
            'podcast'=>Database::exec("INSERT INTO podcast_episode_translations(episode_id,locale,title,slug,status) VALUES(?,?,?,?,'review')",[$id,$locale,$title,$slug]),
            'khawatir'=>Database::exec("INSERT INTO khawatir_translations(khawatir_id,locale,title,slug,body,status) VALUES(?,?,?,?,?,'review')",[$id,$locale,$title,$slug,'']),
            'khawatir_category'=>Database::exec("INSERT INTO khawatir_category_translations(category_id,locale,name,slug,status) VALUES(?,?,?,?,'review')",[$id,$locale,$title,$slug]),
            'writer_profile'=>Database::exec("INSERT INTO writer_profile_translations(user_id,locale,display_name,status) VALUES(?,?,?,'review')",[$id,$locale,$title]),
            default=>throw new RuntimeException('نوع محتوى غير صالح.')
        };
    }
    private static function markContentReview(string $entity,string $table,string $idCol,int $id,string $locale): void {
        if(in_array($entity,['article','page'],true))Database::exec("UPDATE ".$table." SET status='draft',review_state='review' WHERE ".$idCol."=? AND locale=?",[$id,$locale]);
        elseif(in_array($entity,['category','tag','podcast','khawatir','khawatir_category','writer_profile'],true))Database::exec("UPDATE ".$table." SET status='review' WHERE ".$idCol."=? AND locale=?",[$id,$locale]);
    }
    private static function uniqueSlug(string $table,string $locale,string $slug,string $idCol,int $id): string {$base=mb_substr($slug,0,170);$candidate=$base;$n=2;while(Database::one('SELECT '.$idCol.' FROM '.$table.' WHERE locale=? AND slug=? AND '.$idCol.'<>? LIMIT 1',[$locale,$candidate,$id]))$candidate=$base.'-'.$n++;return $candidate;}
    private static function xml(string $value): string {return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8');}
    private static function columnName(int $number): string {$name='';while($number>0){$number--;$name=chr(65+($number%26)).$name;$number=intdiv($number,26);}return $name;}
    private static function columnIndex(string $name): int {$number=0;foreach(str_split($name) as $char)$number=$number*26+(ord($char)-64);return $number;}
}
