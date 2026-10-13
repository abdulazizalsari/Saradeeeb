<?php
namespace App\Core;
final class View {
    public static function render(string $view, array $data=[], string $layout='public'): void {
        $viewPath=BASE_PATH.'/app/Views/'.$view.'.php';
        if(!is_file($viewPath)) throw new \RuntimeException('View not found: '.$view);
        $managedPage=null;
        if($layout==='public' && $view!=='errors/404'){
            $key=\App\Services\ManagedPages::pathKey((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH));
            if($key) $managedPage=\App\Services\ManagedPages::forKey($key,$data['locale']??'ar');
            if($managedPage){
                $data['title']=$managedPage['seo_title']?:$managedPage['title'];
                $data['description']=$managedPage['seo_description']?:mb_substr(strip_tags($managedPage['content']),0,220);
                if($key==='write') $data['description']=\auth_ui((string)($data['locale']??'ar'))['writer_intro'];
            }
        }
        extract($data, EXTR_SKIP);
        ob_start(); require $viewPath; $content=ob_get_clean();
        if($managedPage){
            $canonical=$managedPage['canonical_url']?:\App\Services\ManagedPages::url((int)$managedPage['page_id'],$locale,$managedPage['slug']);
            $headExtra=$headExtra??'';
            foreach(Database::all('SELECT locale,slug FROM page_translations pt WHERE page_id=? AND '.\page_translation_publishable_sql('pt'),[$managedPage['page_id']]) as $alternate){
                if(!isset(\article_locales()[$alternate['locale']])) continue;
                $href=\App\Services\ManagedPages::url((int)$managedPage['page_id'],$alternate['locale'],$alternate['slug']);
                $headExtra.='<link rel="alternate" hreflang="'.\e($alternate['locale']).'" href="'.\e($href).'">';
                if($alternate['locale']==='ar') $headExtra.='<link rel="alternate" hreflang="x-default" href="'.\e($href).'">';
            }
        }
        $layoutPath=BASE_PATH.'/app/Views/layouts/'.$layout.'.php';
        if(is_file($layoutPath)) require $layoutPath; else echo $content;
    }
}
