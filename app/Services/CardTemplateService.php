<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Secure storage and rendering of administrator-approved card templates. */
final class CardTemplateService {
    private const MAX_BYTES=524288;
    private const TEXT_FIELDS=['name','username','reason','profile_url'];
    private const SITE_FIELDS=['logo','medal','qr'];

    public static function types(): array { return ['award-card'=>'بطاقة تكريم الكاتب','writer-card'=>'بطاقة الكاتب','khawatir-card'=>'بطاقة الخاطرة']; }

    /** @return array{type:string,name:string,version:string,path:string} */
    public function upload(array $upload,string $requestedType): array {
        if(!isset(self::types()[$requestedType])) throw new \RuntimeException('نوع البطاقة غير صالح.');
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || empty($upload['tmp_name']) || !is_uploaded_file((string)$upload['tmp_name'])) throw new \RuntimeException('اختر ملف HTML صالحًا للرفع.');
        if(strtolower(pathinfo((string)($upload['name']??''),PATHINFO_EXTENSION))!=='html') throw new \RuntimeException('يسمح برفع ملفات HTML فقط.');
        if((int)($upload['size']??0)<1 || (int)$upload['size']>self::MAX_BYTES) throw new \RuntimeException('الحد الأقصى لحجم القالب هو 512 كيلوبايت.');
        $html=file_get_contents((string)$upload['tmp_name']);
        if($html===false || strlen($html)>self::MAX_BYTES) throw new \RuntimeException('تعذر قراءة ملف القالب.');
        $metadata=$this->metadata($html,$requestedType);
        $this->validateTemplate($html);
        if(Database::one('SELECT id FROM card_templates WHERE type=? AND version=? LIMIT 1',[$requestedType,$metadata['version']])) throw new \RuntimeException('رقم هذه النسخة مرفوع مسبقًا لهذا النوع.');
        $folder=BASE_PATH.'/storage/card-templates/'.$requestedType;
        if(!is_dir($folder) && !mkdir($folder,0750,true)) throw new \RuntimeException('تعذر تجهيز مساحة تخزين القوالب.');
        $file=date('YmdHis').'-'.bin2hex(random_bytes(8)).'.html'; $path=$folder.'/'.$file;
        if(file_put_contents($path,$html,LOCK_EX)===false) throw new \RuntimeException('تعذر حفظ ملف القالب.');
        @chmod($path,0640);
        return ['type'=>$requestedType,'name'=>$metadata['name'],'version'=>$metadata['version'],'path'=>$requestedType.'/'.$file];
    }

    public function deleteStored(string $relativePath): void {
        if(!preg_match('#^(?:[a-z0-9-]+)/[A-Za-z0-9-]+\.html$#',$relativePath)) return;
        $path=BASE_PATH.'/storage/card-templates/'.$relativePath;
        if(is_file($path)) @unlink($path);
    }

    public static function active(string $type): ?array {
        if(!isset(self::types()[$type])) return null;
        try { return Database::one('SELECT * FROM card_templates WHERE type=? AND is_active=1 ORDER BY id DESC LIMIT 1',[$type]); } catch(\Throwable) { return null; }
    }

    /** @param array<string,string> $values */
    public static function renderActive(string $type,array $values): string {
        $template=self::active($type); if(!$template) return '';
        $path=(string)($template['path']??'');
        if(!preg_match('#^'.preg_quote($type,'#').'/[A-Za-z0-9-]+\.html$#',$path)) return '';
        $html=@file_get_contents(BASE_PATH.'/storage/card-templates/'.$path);
        if(!is_string($html) || $html==='') return '';
        try { return (new self())->render($html,$values); } catch(\Throwable) { return ''; }
    }

    /** @param array<string,string> $values */
    public function render(string $html,array $values): string {
        $this->validateTemplate($html);
        $safe=[]; foreach(self::TEXT_FIELDS as $field) $safe[$field]=\e((string)($values[$field]??''));
        $logo=trim((string)\setting('brand_logo',''));
        $safe['logo']=$logo!==''?'<img class="wc-site-logo" src="'.\e($logo).'" alt="'.\e((string)\setting('site_name','سراديب')).'">':'';
        $safe['medal']='<img class="wc-site-medal" src="/assets/award-gold-medal.png" alt="" aria-hidden="true">';
        $safe['qr']='<span class="wc-site-qr" data-card-template-qr="'.\e((string)($values['profile_url']??'')).'" aria-label="رمز QR لرابط الكاتب"></span>';
        $rendered=preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/',static fn(array $m): string=>$safe[$m[1]]??'', $html);
        return '<div class="wc-template" data-card-template>'.$rendered.'</div>';
    }

    /** @return array{name:string,version:string} */
    private function metadata(string $html,string $type): array {
        $head=substr($html,0,4096); $data=null;
        if(preg_match('/<!--\s*card-template\s*:\s*(\{.*?\})\s*-->/is',$head,$m)) { try { $data=json_decode($m[1],true,16,JSON_THROW_ON_ERROR); } catch(\JsonException) { throw new \RuntimeException('تعليق بيانات القالب ليس JSON صالحًا.'); } }
        elseif(preg_match('/<!--\s*type\s*:\s*([^;]+);\s*name\s*:\s*([^;]+);\s*version\s*:\s*([^;\-]+)\s*-->/i',$head,$m)) $data=['type'=>trim($m[1]),'name'=>trim($m[2]),'version'=>trim($m[3])];
        if(!is_array($data)) throw new \RuntimeException('ابدأ الملف بتعليق يحدد type وname وversion.');
        $name=trim((string)($data['name']??'')); $version=trim((string)($data['version']??''));
        if(($data['type']??'')!==$type || !isset(self::types()[$type])) throw new \RuntimeException('نوع القالب في التعليق لا يطابق النوع المختار.');
        if($name==='' || mb_strlen($name)>190 || $version==='' || mb_strlen($version)>60 || !preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]*$/',$version)) throw new \RuntimeException('اسم القالب أو رقم النسخة غير صالح.');
        return ['name'=>$name,'version'=>$version];
    }

    private function validateTemplate(string $html): void {
        if(preg_match('/<\/?(?:html|head|body)\b|<\s*(?:script|iframe|object|embed|form)\b|<\?php|\son[a-z]+\s*=|javascript\s*:|@import\b|(?:https?:)?\/\//i',$html)) throw new \RuntimeException('القالب يحتوي على كود أو رابط خارجي غير مسموح.');
        if(!preg_match('/<style\b[^>]*>.*?<\/style>/is',$html)) throw new \RuntimeException('القالب يجب أن يحتوي وسم style داخليًا.');
        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is',$html,$styles);
        foreach($styles[1] as $css){ preg_match_all('/\.([A-Za-z_][A-Za-z0-9_-]*)/',$css,$classes); foreach(array_unique($classes[1]) as $class) if(!str_starts_with($class,'wc-')) throw new \RuntimeException('كل أصناف CSS يجب أن تبدأ بالبادئة .wc-.'); }
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/',$html,$matches); $allowed=array_fill_keys(array_merge(self::TEXT_FIELDS,self::SITE_FIELDS),true);
        foreach(array_unique($matches[1]) as $field) if(!isset($allowed[$field])) throw new \RuntimeException('المتغير {{'.$field.'}} غير مسموح.');
    }
}

