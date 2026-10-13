<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/** Editable content around the existing functional routes; forms remain in their views. */
final class ManagedPages
{
    public const ROUTES = ['story'=>'send-story','write'=>'writer-application','explore'=>'search','community'=>'community','newsletter'=>'newsletter'];

    public static function ids(): array { return json_decode(\setting('managed_page_ids_json','{}'),true) ?: []; }
    public static function key(int $id): ?string {
        if($id===BehindPage::id()) return 'behind';
        $key=array_search($id,self::ids(),false); return $key===false?null:(string)$key;
    }
    public static function footerKey(int $id): string { return self::key($id) ?? 'page:'.$id; }
    public static function ensure(): void {
        if(self::ids()) return;
        Database::transaction(static function(): void {
            Database::exec("INSERT IGNORE INTO settings(`key`,value) VALUES('managed_page_ids_json','{}')");
            $marker=Database::one("SELECT value FROM settings WHERE `key`='managed_page_ids_json' FOR UPDATE");
            if(json_decode($marker['value'],true)) return;
            $ids=[];
            foreach(self::ROUTES as $key=>$route){
                // Ordinary pages with similar slugs are never repurposed.
                {
                    $id=Database::insert("INSERT INTO pages(status,sort_order,template) VALUES('published',0,'standard')");
                    foreach(\article_locales() as $locale=>$info){
                        $copy=self::originalCopy($key,$locale);
                        $slug=$route;
                        if(Database::one('SELECT id FROM page_translations WHERE locale=? AND slug=?',[$locale,$slug])) $slug=$route.'-system-'.$id;
                        Database::insert("INSERT INTO page_translations(page_id,locale,title,slug,content,status,review_state) VALUES(?,?,?,?,?,'published','approved')",[$id,$locale,$copy[0],$slug,'<p>'.\e($copy[1]).'</p>']);
                    }
                }
                $ids[$key]=$id;
            }
            Database::exec("UPDATE settings SET value=? WHERE `key`='managed_page_ids_json'",[json_encode($ids)]);
            $menu=json_decode(\setting('footer_menu_json',''),true);
            if(!is_array($menu)) $menu=['story','explore','community','behind','contact'];
            if(\setting('footer_show_writer_button','1')==='1' && !in_array('write',$menu,true)) $menu[]='write';
            Database::exec("INSERT INTO settings(`key`,value) VALUES('footer_menu_json',?) ON DUPLICATE KEY UPDATE value=VALUES(value)",[json_encode($menu)]);
            $siteName=\setting('site_name','سراديب'); $tagline=\setting('site_tagline','ما وراء الظلام... أعظم مما تتخيل');
            $defaults=require BASE_PATH.'/app/Views/partials/footer-defaults.php';
            $copy=[];
            $fields=['title'=>'title','description'=>'description','menu_title'=>'menu','categories_title'=>'categories','cta_title'=>'cta_title','cta_text'=>'cta_text','copyright'=>'copyright'];
            foreach(\article_locales() as $locale=>$info){
                $copy[$locale]=['status'=>'published'];
                foreach($fields as $key=>$defaultKey){
                    $saved=Database::one('SELECT value FROM settings WHERE `key`=?',['footer_'.$key.($locale==='ar'?'':'_'.$locale)]);
                    $translated=Database::one('SELECT value FROM translation_strings WHERE namespace=? AND text_key=? AND locale=?',['footer',$defaultKey,$locale]);
                    $copy[$locale][$key]=$saved['value']??$translated['value']??$defaults[$locale][$defaultKey]??'';
                }
            }
            Database::exec("INSERT IGNORE INTO settings(`key`,value) VALUES('footer_copy_json',?)",[json_encode($copy,JSON_UNESCAPED_UNICODE)]);
        });
    }
    private static function originalCopy(string $key,string $locale): array {
        if($key==='story'){
            ob_start(); try { require BASE_PATH.'/app/Views/public/send-story.php'; } finally { ob_end_clean(); }
            return [$copy['title'],$copy['desc']];
        }
        if($key==='write'){ $a=\auth_ui($locale); return [$a['writer_title'],$a['writer_intro']]; }
        if($key==='community'){ $c=\community_ui($locale); return [$c['brand'],$c['description']]; }
        if($key==='explore') return [\tr('explore',$locale),\tr('search_intro',$locale)];
        $copy=[
            'ar'=>['القصص القادمة إلى بريدك','اشترك ليصلك جديد سراديب دون إزعاج.'],
            'en'=>['New stories in your inbox','Subscribe to receive new Saradeeeb stories.'],
            'es'=>['Nuevas historias en tu correo','Suscríbete para recibir nuevas historias de Saradeeeb.'],
            'fr'=>['Les histoires dans votre boîte mail','Abonnez-vous aux nouvelles histoires de Saradeeeb.'],
            'ru'=>['Новые истории на вашу почту','Подпишитесь на новые истории Saradeeeb.'],
        ];
        $result=$copy[$locale]??$copy['en'];
        foreach(['title','text'] as $index=>$field){
            $name='newsletter_'.$field;
            $saved=Database::one('SELECT value FROM settings WHERE `key`=?',['footer_'.$name.($locale==='ar'?'':'_'.$locale)]);
            $translated=Database::one('SELECT value FROM translation_strings WHERE namespace=? AND text_key=? AND locale=?',['footer',$name,$locale]);
            $result[$index]=$saved['value']??$translated['value']??$result[$index];
        }
        return $result;
    }
    public static function published(int $id,string $locale): ?array {
        return Database::one('SELECT p.*,pt.* FROM pages p JOIN page_translations pt ON pt.page_id=p.id WHERE p.id=? AND pt.locale=? AND '.\page_translation_publishable_sql('pt'),[$id,$locale]);
    }
    public static function forKey(string $key,string $locale): ?array {
        $id=$key==='behind'?BehindPage::id():(int)(self::ids()[$key]??0);
        return $id?self::published($id,$locale):null;
    }
    public static function pathKey(string $path): ?string {
        $parts=explode('/',trim(rawurldecode($path),'/'));
        if(isset($parts[0]) && isset(\all_locales()[$parts[0]])) array_shift($parts);
        if(count($parts)!==1) return null;
        $key=array_search($parts[0]??'',self::ROUTES,true); return $key===false?null:$key;
    }
    public static function url(int $id,string $locale,string $slug): string {
        $key=self::key($id);
        return \locale_url($locale,isset(self::ROUTES[$key??''])?self::ROUTES[$key]:'page/'.$slug);
    }
    public static function redirectTarget(string $locale,string $sourcePath): ?string {
        $path=$sourcePath; $seen=[];
        $prefix=rawurldecode((string)parse_url(\locale_url($locale,'page/'),PHP_URL_PATH));
        for($i=0;$i<10;$i++){
            if(isset($seen[$path])) return null;
            $seen[$path]=true;
            $row=Database::one('SELECT target_url FROM redirects WHERE source_path=?',[$path]);
            if(!$row) return null;
            $target=rawurldecode((string)parse_url($row['target_url'],PHP_URL_PATH));
            if(!str_starts_with($target,rtrim($prefix,'/').'/')) return null;
            $slug=substr($target,strlen(rtrim($prefix,'/'))+1);
            $page=Database::one('SELECT page_id,slug FROM page_translations pt WHERE locale=? AND slug=? AND '.\page_translation_publishable_sql('pt'),[$locale,$slug]);
            if($page) return self::url((int)$page['page_id'],$locale,$page['slug']);
            $path=$target;
        }
        return null;
    }
    public static function navigationUrl(string $href,string $locale): ?string {
        $host=parse_url($href,PHP_URL_HOST);
        if($host && strcasecmp((string)$host,(string)parse_url(\url('/'),PHP_URL_HOST))===0){
            $parsed=parse_url($href);
            $href=($parsed['path']??'/').(isset($parsed['query'])?'?'.$parsed['query']:'').(isset($parsed['fragment'])?'#'.$parsed['fragment']:'');
        }
        if(!str_starts_with($href,'/')) return $href;
        if(str_starts_with($href,'//')) return null;
        $parts=parse_url($href); $segments=explode('/',trim(rawurldecode($parts['path']??'/'),'/'));
        $sourceLocale=\default_locale();
        if(isset(\all_locales()[$segments[0]??''])) $sourceLocale=(string)array_shift($segments);
        $path=implode('/',$segments);
        if(($segments[0]??'')==='page' && isset($segments[1])){
            $source=Database::one('SELECT page_id FROM page_translations WHERE locale=? AND slug=? LIMIT 1',[$sourceLocale,$segments[1]]);
            if(!$source) return null;
            $page=self::published((int)$source['page_id'],$locale);
            return $page?self::url((int)$page['page_id'],$locale,$page['slug']):null;
        }
        if($path==='behind-saradeeb') return BehindPage::url($locale)?:null;
        $key=self::pathKey('/'.$path);
        if($key && !self::forKey($key,$locale)) return null;
        return \locale_url($locale,$path).(isset($parts['query'])?'?'.$parts['query']:'').(isset($parts['fragment'])?'#'.$parts['fragment']:'');
    }
    public static function body(?array $page): string {
        if(!$page) return '';
        $image=(string)($page['featured_image']??'');
        $featured=$image!=='' && preg_match('~^(?:https?://|/(?!/))~i',$image)?'<img class="page-featured-image" src="'.\e($image).'" alt="'.\e($page['title']).'" loading="lazy">':'';
        return '<div class="article-content managed-page-content">'.$featured.self::contentHtml($page,$page['locale']??'ar').'</div>';
    }
    public static function contentHtml(array $page,string $locale): string {
        $html=($page['content_mode']??'visual')==='html'?(string)$page['content']:\sanitize_page_html((string)$page['content']);
        if(($page['content_mode']??'visual')!=='html' && empty($page['custom_css']) && empty($page['custom_js'])) return $html;
        $bg=preg_match('/^#[0-9a-f]{6}$/i',$page['page_bg_color']??'')?$page['page_bg_color']:'transparent';
        $color=preg_match('/^#[0-9a-f]{6}$/i',$page['page_text_color']??'')?$page['page_text_color']:'#202522';
        $document='<!doctype html><html lang="'.\e($locale).'" dir="'.($locale==='ar'?'rtl':'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="'.\e(\url('/')).'"><link rel="stylesheet" href="'.\e(\url('/assets/css/app.css?v=724')).'"><style>body{margin:0;background:'.$bg.';color:'.$color.'}'.($page['custom_css']??'').'</style></head><body><div class="article-content standalone-page">'.$html.'</div><script>'.($page['custom_js']??'').'</script><script>new ResizeObserver(()=>parent.postMessage({saradeeebPageHeight:document.documentElement.scrollHeight},"*")).observe(document.body);</script></body></html>';
        return '<iframe class="managed-code-frame" title="'.\e($page['title']).'" sandbox="allow-scripts allow-forms allow-popups allow-top-navigation-by-user-activation" srcdoc="'.\e($document).'"></iframe>';
    }
    public static function placement(int $id): array {
        $all=json_decode(\setting('footer_page_links_json','{}'),true)?:[];
        $key=self::key($id);
        return $all[$id]??['column'=>$key==='newsletter'?'connect':(in_array($key,['story','write','behind'],true)?'brand':'menu'),'order'=>array_search($key,['story','write','behind','explore','community'],true)?:0,'labels'=>[]];
    }
    public static function footerLinks(string $locale): array {
        $groups=['brand'=>[],'menu'=>[],'connect'=>[]];
        foreach(\configured_navigation($locale,'footer') as $item){
            $column=$item['column']??'menu';
            if(!in_array($column,['brand','menu','connect'],true)) $column='menu';
            if(($item['key']??'')==='story' && \setting('footer_show_story_button','1')!=='1') continue;
            if(($item['key']??'')==='write' && \setting('footer_show_writer_button','1')!=='1') continue;
            if(($item['key']??'')==='behind' && \setting('footer_show_behind_button','1')!=='1') continue;
            // Retain explicitly configured action URLs; their target still obeys language availability.
            $key=$item['key']??'';
            if(in_array($key,['story','write','behind'],true)){
                $name=$key==='write'?'writer':$key;
                $route=$key==='behind'?'/behind-saradeeb':'/'.self::ROUTES[$key];
                $raw=\setting('footer_'.$name.'_url',$route);
                if($key!=='behind' || \setting('footer_behind_use_page','1')!=='1'){
                    $target=self::navigationUrl($raw,$locale);
                    if(!$target || !preg_match('~^(?:https?://|/(?!/)|#|mailto:|tel:)~i',$target)) continue;
                    $item['url']=$target;
                }
            }
            $groups[$column][]=$item;
        }
        return $groups;
    }
}
