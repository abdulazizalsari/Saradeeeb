<?php
namespace App\Core;

final class Router {
    private array $routes=[];
    public function get(string $pattern, array|callable $handler): void { $this->add('GET',$pattern,$handler); }
    public function post(string $pattern, array|callable $handler): void { $this->add('POST',$pattern,$handler); }
    private function add(string $method,string $pattern,array|callable $handler): void {
        $this->routes[] = compact('method','pattern','handler');
    }
    public function dispatch(string $method,string $uri): void {
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        // Browser-generated Arabic links may arrive encoded once or twice.
        $decodedPath = rawurldecode($path);
        if ($decodedPath !== $path) $path = $decodedPath;
        $path = '/' . trim($path, '/'); if ($path === '//') $path='/';
        $managedKey=\App\Services\ManagedPages::pathKey($path);
        if($managedKey){
            $first=explode('/',trim($path,'/'))[0]??'';
            $locale=\valid_locale($first)?$first:\default_locale();
            if(!\App\Services\ManagedPages::forKey($managedKey,$locale)){
                http_response_code(404); View::render('errors/404',['locale'=>$locale,'title'=>'404'],'public'); return;
            }
        }
        foreach($this->routes as $route){
            if($route['method']!==strtoupper($method)) continue;
            $regex=preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', fn($m)=>'(?P<'.$m[1].'>[^/]+)', $route['pattern']);
            $regex='#^'.rtrim($regex,'/').'/?$#u';
            if(preg_match($regex,$path,$m)){
                $params=[]; foreach($m as $k=>$v) if(is_string($k)) $params[$k]=$v;
                $handler=$route['handler'];
                if(is_array($handler)){ [$class,$fn]=$handler; (new $class())->$fn(...array_values($params)); }
                else $handler(...array_values($params));
                return;
            }
        }
        try {
            $redirect=Database::one('SELECT target_url,http_code FROM redirects WHERE source_path=? LIMIT 1',[$path]);
            if($redirect){
                $target=(string)$redirect['target_url']; $code=(int)($redirect['http_code']??301);
                if(!in_array($code,[301,302,307,308],true)) $code=301;
                header('Location: '.$target,true,$code); exit;
            }
        } catch(\Throwable) {}
        // Try to recover common content links whose language prefix or slug changed.
        try {
            $segments=array_values(array_filter(explode('/',trim($path,'/')),fn($v)=>$v!==''));
            $requestedLocale=default_locale();
            if(isset($segments[0]) && valid_locale((string)$segments[0])) $requestedLocale=(string)array_shift($segments);
            $kind=$segments[0]??''; $slug=$segments[1]??'';
            if($slug!=='' && in_array($kind,['category'],true)){
                $table=$kind==='article'?'article_translations':($kind==='page'?'page_translations':'category_translations');
                // Do not revive a draft/incomplete article through the generic
                // recovery route. Articles need the same public gate as every
                // normal listing and detail request.
                $where=$kind==='article'
                    ? "slug=? AND ".\article_translation_publishable_sql('article_translations')
                    : 'slug=?';
                $row=Database::one("SELECT locale,slug FROM {$table} WHERE {$where} LIMIT 1",[$slug]);
                if($row){ $target=parse_url(locale_url((string)$row['locale'],$kind.'/'.(string)$row['slug']),PHP_URL_PATH)?:'/'; if($target!==$path){ header('Location: '.$target,true,301); exit; } }
            }
        } catch(\Throwable) {}
        http_response_code(404);
        $locale=default_locale();
        $first=explode('/',trim($path,'/'))[0]??'';
        if($first!=='' && valid_locale($first)) $locale=$first;
        try {
            $ref=mb_substr((string)($_SERVER['HTTP_REFERER']??''),0,700);
            Database::exec('INSERT INTO not_found_logs(path,locale,referrer,hits,last_seen_at) VALUES(?,?,?,1,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE hits=hits+1,locale=VALUES(locale),referrer=VALUES(referrer),last_seen_at=CURRENT_TIMESTAMP',[mb_substr($path,0,700),$locale,$ref]);
        } catch(\Throwable) {}
        View::render('errors/404', ['title'=>'404','locale'=>$locale], 'public');
    }
}
