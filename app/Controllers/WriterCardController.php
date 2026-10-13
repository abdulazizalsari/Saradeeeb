<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;

final class WriterCardController {
    private const SIZES=['post','business','pdf','square','horizontal','compact'];

    private static function styleDefaults(): array {
        return [
            'font_family'=>'plex',
            'background'=>'#031c1f',
            'overlay_color'=>'#031c1f',
            'overlay_opacity'=>64,
            'accent'=>'#69d5d8',
            'primary_text'=>'#ffffff',
            'secondary_text'=>'#bfd3d5',
            'panel_color'=>'#031c1f',
            'panel_opacity'=>70,
            'border_color'=>'#69d5d8',
            'card_radius'=>34,
            'card_padding'=>6,
            'card_gap'=>12,
            'panel_radius'=>28,
            'name_size'=>42,
            'name_weight'=>700,
            'title_size'=>15,
            'title_weight'=>700,
            'quote_size'=>13,
            'quote_weight'=>500,
            'quote_line_height'=>180,
            'handle_size'=>15,
            'avatar_border'=>4,
            'avatar_radius'=>50,
            'qr_size'=>118,
            'logo_width'=>150,
            'shadow_strength'=>42,
            'watermark_opacity'=>10,
            'scene_position_y'=>65,
            'scene_brightness'=>92,
            'scene_saturation'=>105,
        ];
    }

    private static function color(string $value,string $fallback): string {
        $value=trim($value);
        return preg_match('/^#[0-9a-fA-F]{6}$/',$value)?strtolower($value):$fallback;
    }

    private static function clampInt(mixed $value,int $min,int $max,int $fallback): int {
        if(!is_numeric($value)) return $fallback;
        return max($min,min($max,(int)$value));
    }

    private static function sanitizeStyle(array $input): array {
        $d=self::styleDefaults();
        $font=(string)($input['font_family']??$d['font_family']);
        if(!in_array($font,['plex','tajawal','kufi','tahoma','serif'],true)) $font=$d['font_family'];
        return [
            'font_family'=>$font,
            'background'=>self::color((string)($input['background']??''),$d['background']),
            'overlay_color'=>self::color((string)($input['overlay_color']??''),$d['overlay_color']),
            'overlay_opacity'=>self::clampInt($input['overlay_opacity']??null,0,92,$d['overlay_opacity']),
            'accent'=>self::color((string)($input['accent']??''),$d['accent']),
            'primary_text'=>self::color((string)($input['primary_text']??''),$d['primary_text']),
            'secondary_text'=>self::color((string)($input['secondary_text']??''),$d['secondary_text']),
            'panel_color'=>self::color((string)($input['panel_color']??''),$d['panel_color']),
            'panel_opacity'=>self::clampInt($input['panel_opacity']??null,0,95,$d['panel_opacity']),
            'border_color'=>self::color((string)($input['border_color']??''),$d['border_color']),
            'card_radius'=>self::clampInt($input['card_radius']??null,0,54,$d['card_radius']),
            'card_padding'=>self::clampInt($input['card_padding']??null,3,10,$d['card_padding']),
            'card_gap'=>self::clampInt($input['card_gap']??null,4,28,$d['card_gap']),
            'panel_radius'=>self::clampInt($input['panel_radius']??null,0,42,$d['panel_radius']),
            'name_size'=>self::clampInt($input['name_size']??null,22,64,$d['name_size']),
            'name_weight'=>self::clampInt($input['name_weight']??null,300,900,$d['name_weight']),
            'title_size'=>self::clampInt($input['title_size']??null,10,28,$d['title_size']),
            'title_weight'=>self::clampInt($input['title_weight']??null,300,900,$d['title_weight']),
            'quote_size'=>self::clampInt($input['quote_size']??null,10,28,$d['quote_size']),
            'quote_weight'=>self::clampInt($input['quote_weight']??null,300,900,$d['quote_weight']),
            'quote_line_height'=>self::clampInt($input['quote_line_height']??null,120,240,$d['quote_line_height']),
            'handle_size'=>self::clampInt($input['handle_size']??null,9,24,$d['handle_size']),
            'avatar_border'=>self::clampInt($input['avatar_border']??null,0,10,$d['avatar_border']),
            'avatar_radius'=>self::clampInt($input['avatar_radius']??null,0,50,$d['avatar_radius']),
            'qr_size'=>self::clampInt($input['qr_size']??null,84,170,$d['qr_size']),
            'logo_width'=>self::clampInt($input['logo_width']??null,80,240,$d['logo_width']),
            'shadow_strength'=>self::clampInt($input['shadow_strength']??null,0,100,$d['shadow_strength']),
            'watermark_opacity'=>self::clampInt($input['watermark_opacity']??null,0,30,$d['watermark_opacity']),
            'scene_position_y'=>self::clampInt($input['scene_position_y']??null,0,100,$d['scene_position_y']),
            'scene_brightness'=>self::clampInt($input['scene_brightness']??null,50,130,$d['scene_brightness']),
            'scene_saturation'=>self::clampInt($input['scene_saturation']??null,0,170,$d['scene_saturation']),
        ];
    }

    public function index(): void {
        Auth::requireRole(['writer','trusted_writer','editor','admin']);
        if(setting('writer_cards_enabled','1')!=='1'){ http_response_code(404); View::render('errors/404',['title'=>'404'],'public'); return; }
        $u=Auth::user();
        $profile=Database::one('SELECT * FROM writer_profiles WHERE user_id=?',[$u['id']])?:[];
        $prefs=Database::one('SELECT * FROM writer_card_preferences WHERE user_id=?',[$u['id']])?:['template'=>'cinematic','card_size'=>'post','show_qr'=>1,'show_stats'=>1,'show_socials'=>1,'show_photo'=>1,'quote_text'=>'','style_json'=>null];
        $decoded=json_decode((string)($prefs['style_json']??''),true);
        $cardStyle=self::sanitizeStyle(is_array($decoded)?$decoded:[]);
        $stats=Database::one("SELECT COUNT(*) articles,COALESCE(SUM(views),0) views FROM articles WHERE author_id=? AND status='published'",[$u['id']])?:[];
        $articles=Database::all("SELECT a.views,t.title,t.slug,COALESCE(NULLIF(ct.name,''),NULLIF(ar.name,'')) category_name FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND ".article_translation_publishable_sql('t')." LEFT JOIN category_translations ct ON ct.category_id=a.category_id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=a.category_id AND ar.locale='ar' WHERE a.author_id=? AND a.status='published' ORDER BY a.views DESC,a.published_at DESC LIMIT 5",[default_locale(),default_locale(),$u['id']]);
        $awards=[]; try{$awards=Database::all("SELECT title,message,award_type FROM writer_thanks WHERE user_id=? AND active=1 ORDER BY created_at DESC LIMIT 6",[$u['id']]);}catch(\Throwable){}
        View::render('writer/share-center',compact('u','profile','prefs','cardStyle','stats','articles','awards')+['title'=>'بطاقاتي'],'dashboard');
    }

    public function save(): void {
        Auth::requireRole(['writer','trusted_writer','editor','admin']); Csrf::enforce(); $u=Auth::user();
        $size=(string)($_POST['card_size']??'post');
        $size=['square'=>'post','horizontal'=>'business','compact'=>'business'][$size]??$size;
        if(!in_array($size,['post','business','pdf'],true))$size='post';
        $template=['post'=>'cinematic','business'=>'executive','pdf'=>'editorial'][$size];
        $quote=mb_substr(trim((string)($_POST['quote_text']??'')),0,180);
        $showQr=setting('writer_cards_qr','1')==='1' && isset($_POST['show_qr'])?1:0;
        $showStats=setting('writer_cards_stats','1')==='1' && isset($_POST['show_stats'])?1:0;
        $showSocials=isset($_POST['show_socials'])?1:0;
        $showPhoto=isset($_POST['show_photo'])?1:0;
        $style=self::sanitizeStyle(is_array($_POST['style']??null)?$_POST['style']:[]);
        $styleJson=json_encode($style,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        Database::exec('INSERT INTO writer_card_preferences(user_id,template,card_size,show_qr,show_stats,show_socials,show_photo,quote_text,style_json) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE template=VALUES(template),card_size=VALUES(card_size),show_qr=VALUES(show_qr),show_stats=VALUES(show_stats),show_socials=VALUES(show_socials),show_photo=VALUES(show_photo),quote_text=VALUES(quote_text),style_json=VALUES(style_json)',[$u['id'],$template,$size,$showQr,$showStats,$showSocials,$showPhoto,$quote,$styleJson]);
        flash('success','تم حفظ تصميم بطاقة الكاتب.'); redirect('/writer/card');
    }

    public function admin(): void {
        Auth::requireRole(['admin']);
        $writers=Database::all("SELECT id,name,username,role,status,writer_verified,writer_honored,writer_directory_hidden FROM users WHERE role IN ('writer','trusted_writer','editor','admin') ORDER BY name");
        View::render('admin/writer-cards',compact('writers')+['title'=>'بطاقات الكتّاب'],'dashboard');
    }

    public function adminSave(): void {
        Auth::requireRole(['admin']); Csrf::enforce();
        foreach(['writer_cards_enabled','writer_cards_download','writer_cards_pdf','writer_cards_share','writer_cards_qr','writer_cards_stats'] as $key) Database::exec('INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,isset($_POST[$key])?'1':'0']);
        $visible=array_map('intval',$_POST['visible_writers']??[]);
        Database::exec("UPDATE users SET writer_directory_hidden=1 WHERE role IN ('writer','trusted_writer','editor','admin')");
        if($visible){$marks=implode(',',array_fill(0,count($visible),'?')); Database::exec("UPDATE users SET writer_directory_hidden=0 WHERE id IN ($marks)",$visible);}
        flash('success','تم حفظ إعدادات بطاقات الكتّاب.'); redirect('/admin/writer-cards');
    }
}
