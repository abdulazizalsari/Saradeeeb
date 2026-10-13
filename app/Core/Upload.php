<?php
namespace App\Core;

final class Upload {
    public static function image(array $file): ?string {
        $info=self::imageDetailed($file,true); return $info['path']??null;
    }

    public static function imageDetailed(array $file,bool $preferWebp=true): ?array {
        if(($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
        if(($file['error'] ?? UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new \RuntimeException('فشل رفع الصورة.');
        if(($file['size'] ?? 0) > (int)config('max_upload_bytes')) throw new \RuntimeException('حجم الصورة أكبر من الحد المسموح.');
        $strictValidation=\setting('security_upload_validation','1')==='1';
        if($strictValidation && PHP_SAPI!=='cli' && !is_uploaded_file((string)($file['tmp_name']??''))) throw new \RuntimeException('تعذر التحقق من مصدر الملف المرفوع.');
        $finfo=new \finfo(FILEINFO_MIME_TYPE); $mime=(string)$finfo->file($file['tmp_name']);
        $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/avif'=>'avif','image/gif'=>'gif'];
        if(!isset($allowed[$mime])) throw new \RuntimeException('صيغة الصورة غير مدعومة.');
        $size=@getimagesize($file['tmp_name']); if($size===false) throw new \RuntimeException('ملف الصورة غير صالح.');
        [$width,$height]=$size;
        if($strictValidation && ($width<1 || $height<1 || $width>12000 || $height>12000 || ($width*$height)>60000000)) throw new \RuntimeException('أبعاد الصورة غير آمنة أو أكبر من الحد المسموح.');
        $dir=BASE_PATH.'/uploads/'.date('Y/m'); if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new \RuntimeException('تعذر إنشاء مجلد الصور.');
        $base=bin2hex(random_bytes(16)); $ext=$allowed[$mime]; $outMime=$mime; $dest=$dir.'/'.$base.'.'.$ext;
        $converted=false;
        if($preferWebp && $mime!=='image/webp' && $mime!=='image/gif' && function_exists('imagewebp')){
            $img=match($mime){
                'image/jpeg'=>function_exists('imagecreatefromjpeg')?@imagecreatefromjpeg($file['tmp_name']):false,
                'image/png'=>function_exists('imagecreatefrompng')?@imagecreatefrompng($file['tmp_name']):false,
                default=>false,
            };
            if($img){
                if($mime==='image/png'){ imagepalettetotruecolor($img); imagealphablending($img,true); imagesavealpha($img,true); }
                $dest=$dir.'/'.$base.'.webp';
                if(@imagewebp($img,$dest,84)){ $ext='webp'; $outMime='image/webp'; $converted=true; }
                imagedestroy($img);
            }
        }
        if(!$converted && !move_uploaded_file($file['tmp_name'],$dest)) throw new \RuntimeException('تعذر حفظ الصورة.');
        $relative='/uploads/'.date('Y/m').'/'.basename($dest);
        return ['path'=>$relative,'mime'=>$outMime,'width'=>(int)$width,'height'=>(int)$height,'size_bytes'=>(int)(@filesize($dest)?:0),'original_name'=>substr((string)($file['name']??''),0,240),'kind'=>'image'];
    }

    /**
     * Safe WordPress-like media upload for non-executable files.
     * Images still pass through imageDetailed() so validation/optimisation stays identical.
     */
    public static function fileDetailed(array $file): ?array {
        if(($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
        if(($file['error'] ?? UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new \RuntimeException('فشل رفع الملف.');
        $tmp=(string)($file['tmp_name']??'');
        $strictValidation=\setting('security_upload_validation','1')==='1';
        if($strictValidation && PHP_SAPI!=='cli' && !is_uploaded_file($tmp)) throw new \RuntimeException('تعذر التحقق من مصدر الملف المرفوع.');
        $finfo=new \finfo(FILEINFO_MIME_TYPE); $mime=(string)$finfo->file($tmp);
        if(str_starts_with($mime,'image/')) return self::imageDetailed($file,true);

        // Use both the detected MIME and the original extension. OOXML files (docx/xlsx/pptx)
        // are ZIP containers and are often reported as application/zip by finfo.
        $original=(string)($file['name']??'');
        $originalExt=strtolower(pathinfo($original,PATHINFO_EXTENSION));
        $rules=[
            'pdf'=>['kind'=>'document','mimes'=>['application/pdf']],
            'txt'=>['kind'=>'document','mimes'=>['text/plain']],
            'csv'=>['kind'=>'document','mimes'=>['text/csv','text/plain','application/csv','application/vnd.ms-excel']],
            'rtf'=>['kind'=>'document','mimes'=>['application/rtf','text/rtf','text/plain']],
            'doc'=>['kind'=>'document','mimes'=>['application/msword','application/octet-stream']],
            'docx'=>['kind'=>'document','mimes'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream']],
            'xls'=>['kind'=>'document','mimes'=>['application/vnd.ms-excel','application/octet-stream']],
            'xlsx'=>['kind'=>'document','mimes'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream']],
            'ppt'=>['kind'=>'document','mimes'=>['application/vnd.ms-powerpoint','application/octet-stream']],
            'pptx'=>['kind'=>'document','mimes'=>['application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip','application/octet-stream']],
            'mp3'=>['kind'=>'audio','mimes'=>['audio/mpeg','audio/mp3','application/octet-stream']],
            'wav'=>['kind'=>'audio','mimes'=>['audio/wav','audio/x-wav','audio/wave','application/octet-stream']],
            'ogg'=>['kind'=>'audio','mimes'=>['audio/ogg','application/ogg']],
            'm4a'=>['kind'=>'audio','mimes'=>['audio/mp4','audio/x-m4a','video/mp4','application/octet-stream']],
            'mp4'=>['kind'=>'video','mimes'=>['video/mp4','application/octet-stream']],
            'webm'=>['kind'=>'video','mimes'=>['video/webm','application/octet-stream']],
            'ogv'=>['kind'=>'video','mimes'=>['video/ogg','application/ogg']],
            'zip'=>['kind'=>'archive','mimes'=>['application/zip','application/x-zip-compressed','application/octet-stream']],
        ];
        if(!isset($rules[$originalExt]) || !in_array($mime,$rules[$originalExt]['mimes'],true)) throw new \RuntimeException('نوع الملف غير مدعوم أو لا يطابق امتداده.');
        $max=25*1024*1024;
        if((int)($file['size']??0)>$max) throw new \RuntimeException('حجم الملف يجب ألا يتجاوز 25MB.');
        $kind=$rules[$originalExt]['kind'];
        $dir=BASE_PATH.'/uploads/files/'.date('Y/m');
        if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new \RuntimeException('تعذر إنشاء مجلد الملفات.');
        $dest=$dir.'/'.bin2hex(random_bytes(16)).'.'.$originalExt;
        if(!move_uploaded_file($tmp,$dest)) throw new \RuntimeException('تعذر حفظ الملف.');
        return [
            'path'=>'/uploads/files/'.date('Y/m').'/'.basename($dest),
            'mime'=>$mime,'width'=>null,'height'=>null,'size_bytes'=>(int)(@filesize($dest)?:0),
            'original_name'=>substr($original,0,240),'kind'=>$kind,
        ];
    }
}
