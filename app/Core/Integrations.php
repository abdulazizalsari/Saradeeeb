<?php
namespace App\Core;

use InvalidArgumentException;
use Throwable;

final class Integrations
{
    public static function registry(): array
    {
        return [
            'search_console' => [
                'name' => 'Google Search Console',
                'description' => 'إثبات ملكية الموقع ومتابعة الفهرسة والأداء في نتائج بحث Google.',
                'docs' => 'من Search Console اختر إضافة موقع ← بادئة عنوان URL ← علامة HTML، ثم انسخ قيمة content فقط.',
                'consent' => null,
                'fields' => [
                    'verification_token' => ['label' => 'رمز التحقق', 'type' => 'text', 'required' => true, 'masked_after_save' => true, 'placeholder' => 'مثال: abcDEF_123...'],
                ],
            ],
            'google_analytics' => [
                'name' => 'Google Analytics 4',
                'description' => 'قياس الزيارات ومصادرها وتفاعل القرّاء مع صفحات الموقع.',
                'docs' => 'من Google Analytics: المشرف ← مصادر البيانات ← الويب ← Measurement ID.',
                'consent' => 'analytics',
                'fields' => [
                    'measurement_id' => ['label' => 'Measurement ID', 'type' => 'text', 'required' => true, 'placeholder' => 'G-XXXXXXXXXX'],
                ],
            ],
            'google_tag_manager' => [
                'name' => 'Google Tag Manager',
                'description' => 'إدارة وسوم القياس والتسويق من حاوية واحدة دون تعديل ملفات الموقع كل مرة.',
                'docs' => 'من Google Tag Manager افتح الحاوية وانسخ Container ID الذي يبدأ بـ GTM-.',
                'consent' => 'marketing',
                'fields' => [
                    'container_id' => ['label' => 'Container ID', 'type' => 'text', 'required' => true, 'placeholder' => 'GTM-XXXXXXX'],
                ],
            ],
            'meta_pixel' => [
                'name' => 'Meta Pixel',
                'description' => 'قياس الزيارات والتحويلات القادمة من Facebook وInstagram.',
                'docs' => 'من Meta Events Manager افتح مصدر البيانات ثم انسخ Pixel ID الرقمي.',
                'consent' => 'marketing',
                'fields' => [
                    'pixel_id' => ['label' => 'Pixel ID', 'type' => 'text', 'required' => true, 'placeholder' => '123456789012345'],
                ],
            ],
            'tiktok_pixel' => [
                'name' => 'TikTok Pixel',
                'description' => 'قياس الحملات والزيارات القادمة من TikTok.',
                'docs' => 'من TikTok Ads Manager ← Events Manager ← Web Events ثم انسخ Pixel ID.',
                'consent' => 'marketing',
                'fields' => [
                    'pixel_id' => ['label' => 'Pixel ID', 'type' => 'text', 'required' => true, 'placeholder' => 'مثال: CXXXXXXXXXXXXXXX'],
                ],
            ],
            'adsense' => [
                'name' => 'Google AdSense',
                'description' => 'تحميل مكتبة AdSense للموقع. أما أماكن الإعلانات نفسها فتدار من مركز الإعلانات.',
                'docs' => 'من AdSense ← الحساب ← معلومات الحساب، انسخ Publisher ID الذي يبدأ بـ ca-pub-.',
                'consent' => 'ads',
                'fields' => [
                    'publisher_id' => ['label' => 'Publisher ID', 'type' => 'text', 'required' => true, 'placeholder' => 'ca-pub-1234567890123456'],
                    'auto_ads' => ['label' => 'تفعيل Auto Ads', 'type' => 'checkbox', 'required' => false],
                ],
            ],
        ];
    }

    public static function serviceExists(string $service): bool
    {
        return isset(self::registry()[$service]);
    }

    public static function record(string $service): array
    {
        try {
            $row = Database::one('SELECT service_key,enabled,config_json,updated_at FROM integrations WHERE service_key=? LIMIT 1', [$service]);
            if (!$row) return ['service_key'=>$service,'enabled'=>0,'config'=>[],'updated_at'=>null];
            $cfg = json_decode((string)($row['config_json'] ?? ''), true);
            return [
                'service_key'=>$service,
                'enabled'=>(int)($row['enabled'] ?? 0),
                'config'=>is_array($cfg) ? $cfg : [],
                'updated_at'=>$row['updated_at'] ?? null,
            ];
        } catch (Throwable) {
            return ['service_key'=>$service,'enabled'=>0,'config'=>[],'updated_at'=>null];
        }
    }

    public static function records(): array
    {
        $out=[];
        foreach (array_keys(self::registry()) as $service) $out[$service]=self::record($service);
        return $out;
    }

    public static function configured(string $service, ?array $record=null): bool
    {
        $spec=self::registry()[$service]??null;
        if(!$spec) return false;
        $record=$record??self::record($service); $cfg=$record['config']??[];
        foreach($spec['fields'] as $key=>$field){
            if(empty($field['required'])) continue;
            if(trim((string)($cfg[$key]??''))==='') return false;
        }
        return true;
    }

    public static function save(string $service, array $input, int $userId): void
    {
        $registry=self::registry();
        if(!isset($registry[$service])) throw new InvalidArgumentException('خدمة التكامل غير معروفة.');
        $spec=$registry[$service];
        $existing=self::record($service); $cfg=$existing['config']??[];
        foreach($spec['fields'] as $key=>$field){
            $type=$field['type']??'text';
            if($type==='checkbox'){$cfg[$key]=isset($input[$key])?1:0; continue;}
            $raw=trim((string)($input[$key]??''));
            if($raw==='' && !empty($field['masked_after_save']) && !empty($cfg[$key])) continue;
            $cfg[$key]=self::sanitizeField($service,$key,$raw);
        }
        $enabled=isset($input['enabled'])?1:0;
        if($enabled){
            foreach($spec['fields'] as $key=>$field){
                if(!empty($field['required']) && trim((string)($cfg[$key]??''))==='') throw new InvalidArgumentException('أكمل الحقول المطلوبة قبل تشغيل التكامل.');
            }
        }
        $json=json_encode($cfg,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $row=Database::one('SELECT service_key FROM integrations WHERE service_key=?',[$service]);
        if($row) Database::exec('UPDATE integrations SET enabled=?,config_json=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE service_key=?',[$enabled,$json,$userId,$service]);
        else Database::insert('INSERT INTO integrations(service_key,enabled,config_json,updated_by) VALUES(?,?,?,?)',[$service,$enabled,$json,$userId]);
    }

    private static function sanitizeField(string $service,string $key,string $value): string
    {
        if($value==='') return '';
        return match($service.':'.$key){
            'search_console:verification_token'=>self::sanitizeSearchConsole($value),
            'google_analytics:measurement_id'=>self::validatePattern(strtoupper($value),'/^G-[A-Z0-9]{4,20}$/','معرّف Google Analytics غير صالح. يجب أن يبدأ بـ G-.'),
            'google_tag_manager:container_id'=>self::validatePattern(strtoupper($value),'/^GTM-[A-Z0-9]{4,20}$/','معرّف Google Tag Manager غير صالح. يجب أن يبدأ بـ GTM-.'),
            'meta_pixel:pixel_id'=>self::validatePattern($value,'/^\d{5,30}$/','Pixel ID الخاص بـ Meta يجب أن يكون أرقامًا فقط.'),
            'tiktok_pixel:pixel_id'=>self::validatePattern(strtoupper($value),'/^[A-Z0-9_-]{6,40}$/','TikTok Pixel ID غير صالح.'),
            'adsense:publisher_id'=>self::sanitizeAdsense($value),
            default=>mb_substr(strip_tags($value),0,500),
        };
    }

    private static function validatePattern(string $value,string $pattern,string $message): string
    {
        if(!preg_match($pattern,$value)) throw new InvalidArgumentException($message);
        return $value;
    }

    private static function sanitizeSearchConsole(string $value): string
    {
        if(str_contains($value,'google-site-verification')){
            if(preg_match('/content=["\']([^"\']+)["\']/i',$value,$m)) $value=$m[1];
        }
        $value=trim($value);
        if(!preg_match('/^[A-Za-z0-9_+\-=.:]{6,255}$/',$value)) throw new InvalidArgumentException('رمز التحقق من Search Console غير صالح. انسخ قيمة content فقط.');
        return $value;
    }

    private static function sanitizeAdsense(string $value): string
    {
        $value=strtolower(trim($value));
        if(str_starts_with($value,'pub-')) $value='ca-'.$value;
        if(!preg_match('/^ca-pub-\d{10,25}$/',$value)) throw new InvalidArgumentException('Publisher ID غير صالح. يجب أن يكون مثل ca-pub-1234567890123456.');
        return $value;
    }

    public static function consentRequired(): bool
    {
        return \setting('tracking_consent_enabled','0')==='1';
    }

    public static function consentVersion(): string
    {
        return trim(\setting('tracking_consent_version','1')) ?: '1';
    }

    public static function consentState(): array
    {
        $base=['v'=>self::consentVersion(),'essential'=>true,'analytics'=>false,'marketing'=>false,'ads'=>false];
        if(!self::consentRequired()) return ['v'=>self::consentVersion(),'essential'=>true,'analytics'=>true,'marketing'=>true,'ads'=>true];
        $raw=(string)($_COOKIE['saradeeeb_consent']??''); if($raw==='') return $base;
        $data=json_decode(urldecode($raw),true); if(!is_array($data) || (string)($data['v']??'')!==self::consentVersion()) return $base;
        foreach(['analytics','marketing','ads'] as $k) $base[$k]=!empty($data[$k]);
        return $base;
    }

    public static function consentGranted(?string $category): bool
    {
        if($category===null || $category==='' || $category==='essential') return true;
        $state=self::consentState(); return !empty($state[$category]);
    }

    public static function headMarkup(): string
    {
        $out=[];
        $search=self::record('search_console');
        if(!empty($search['enabled']) && self::configured('search_console',$search)){
            $token=(string)($search['config']['verification_token']??'');
            $out[]='<meta name="google-site-verification" content="'.htmlspecialchars($token,ENT_QUOTES,'UTF-8').'">';
        }
        $ga=self::record('google_analytics');
        if(!empty($ga['enabled']) && self::configured('google_analytics',$ga) && self::consentGranted('analytics')){
            $id=(string)$ga['config']['measurement_id'];
            $out[]='<script async src="https://www.googletagmanager.com/gtag/js?id='.rawurlencode($id).'"></script>';
            $out[]='<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",'.json_encode($id).');</script>';
        }
        $gtm=self::record('google_tag_manager');
        if(!empty($gtm['enabled']) && self::configured('google_tag_manager',$gtm) && self::consentGranted('marketing')){
            $id=(string)$gtm['config']['container_id'];
            $out[]='<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer",'.json_encode($id).');</script>';
        }
        $meta=self::record('meta_pixel');
        if(!empty($meta['enabled']) && self::configured('meta_pixel',$meta) && self::consentGranted('marketing')){
            $id=(string)$meta['config']['pixel_id'];
            $out[]='<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");fbq("init",'.json_encode($id).');fbq("track","PageView");</script>';
        }
        $tt=self::record('tiktok_pixel');
        if(!empty($tt['enabled']) && self::configured('tiktok_pixel',$tt) && self::consentGranted('marketing')){
            $id=(string)$tt['config']['pixel_id'];
            $out[]='<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=r;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};var a=document.createElement("script");a.type="text/javascript";a.async=!0;a.src=r+"?sdkid="+e+"&lib="+t;var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(a,s)};ttq.load('.json_encode($id).');ttq.page();}(window,document,"ttq");</script>';
        }
        $ads=self::record('adsense');
        if(!empty($ads['enabled']) && self::configured('adsense',$ads) && self::consentGranted('ads') && !empty($ads['config']['auto_ads'])){
            $id=(string)$ads['config']['publisher_id'];
            $out[]='<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='.rawurlencode($id).'" crossorigin="anonymous"></script>';
        }
        $custom=self::customCodeMarkup('head'); if($custom!=='') $out[]=$custom;
        return implode("\n",$out);
    }

    public static function bodyStartMarkup(): string
    {
        $out=[];
        $gtm=self::record('google_tag_manager');
        if(!empty($gtm['enabled']) && self::configured('google_tag_manager',$gtm) && self::consentGranted('marketing')){
            $id=(string)$gtm['config']['container_id'];
            $out[]='<noscript><iframe src="https://www.googletagmanager.com/ns.html?id='.rawurlencode($id).'" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
        }
        $meta=self::record('meta_pixel');
        if(!empty($meta['enabled']) && self::configured('meta_pixel',$meta) && self::consentGranted('marketing')){
            $id=(string)$meta['config']['pixel_id'];
            $out[]='<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id='.rawurlencode($id).'&ev=PageView&noscript=1"></noscript>';
        }
        $custom=self::customCodeMarkup('body_start'); if($custom!=='') $out[]=$custom;
        return implode("\n",$out);
    }

    public static function bodyEndMarkup(): string
    {
        return self::customCodeMarkup('body_end');
    }

    private static function customCodeMarkup(string $location): string
    {
        try{
            $rows=Database::all('SELECT code,consent_category FROM custom_tracking_codes WHERE active=1 AND location=? ORDER BY id',[$location]);
            $out=[]; foreach($rows as $row){ if(self::consentGranted((string)($row['consent_category']??'marketing'))) $out[]=(string)$row['code']; }
            return implode("\n",$out);
        }catch(Throwable){return '';}
    }

    public static function maskValue(string $value): string
    {
        $len=mb_strlen($value); if($len<=8) return str_repeat('•',max(6,$len));
        return mb_substr($value,0,4).str_repeat('•',max(6,$len-8)).mb_substr($value,-4);
    }
}
