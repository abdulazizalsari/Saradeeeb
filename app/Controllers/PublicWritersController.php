<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\View;

final class PublicWritersController
{
    private function locale(string $locale): string
    {
        if(!valid_locale($locale)){http_response_code(404);View::render('errors/404',['locale'=>default_locale(),'title'=>'404'],'public');exit;}
        return $locale;
    }
    private function nameSql(string $locale): string
    {
        $translated="NULLIF(CASE WHEN wt.status='published' THEN wt.display_name END,'')";
        return ($locale==='ar'||translation_fallback_enabled($locale))?"COALESCE({$translated},u.name)":$translated;
    }
    private function fieldSql(string $locale,string $field,string $source): string
    {
        $translated="NULLIF(CASE WHEN wt.status='published' THEN wt.{$field} END,'')";
        return ($locale==='ar'||translation_fallback_enabled($locale))?"COALESCE({$translated},{$source})":$translated;
    }
    public function index(string $locale): void
    {
        $locale=$this->locale($locale); $q=trim((string)($_GET['q']??'')); $page=max(1,(int)($_GET['page']??1)); $perPage=12;
        $nameSql=$this->nameSql($locale); $bioSql=$this->fieldSql($locale,'bio','u.bio'); $titleSql=$this->fieldSql($locale,'professional_title','p.professional_title');
        $where=["u.status='active'","u.account_trashed_at IS NULL","u.writer_directory_hidden=0","u.role IN ('writer','trusted_writer','editor','admin')"]; $params=[];
        if($q!==''){ $like='%'.$q.'%'; $where[]="({$nameSql} LIKE ? OR u.username LIKE ? OR {$bioSql} LIKE ? OR {$titleSql} LIKE ? OR p.skills_json LIKE ?)"; array_push($params,$like,$like,$like,$like,$like); }
        $from="users u LEFT JOIN writer_profiles p ON p.user_id=u.id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=?";
        $countParams=array_merge([$locale],$params); $total=(int)(Database::one('SELECT COUNT(*) c FROM '.$from.' WHERE '.implode(' AND ',$where),$countParams)['c']??0);
        $pages=max(1,(int)ceil($total/$perPage)); if($page>$pages)$page=$pages; $offset=($page-1)*$perPage;
        $articlesCount="(SELECT COUNT(*) FROM articles a JOIN article_translations at ON at.article_id=a.id AND at.locale=? AND ".article_translation_publishable_sql('at')." WHERE a.author_id=u.id AND a.status='published')";
        $query="SELECT u.id,{$nameSql} name,u.username,{$bioSql} bio,u.avatar,u.writer_verified,u.writer_honored,{$titleSql} professional_title,{$articlesCount} articles_count FROM {$from} WHERE ".implode(' AND ',$where)." ORDER BY u.writer_honored DESC,u.writer_verified DESC,articles_count DESC,name LIMIT ? OFFSET ?";
        $rows=Database::all($query,array_merge([$locale,$locale],$params,[$perPage,$offset]));
        $meta=['ar'=>['title'=>'كتّاب سراديب','description'=>'تعرّف إلى كتّاب سراديب وأصواتهم الأدبية.'],'en'=>['title'=>'Saradeeb writers','description'=>'Meet Saradeeeb writers and discover their work.']][$locale]??['title'=>'Saradeeb writers','description'=>'Meet Saradeeb writers and discover their work.'];
        View::render('public/writers-directory',compact('locale','rows','q','page','pages','total')+['title'=>$meta['title'],'description'=>$meta['description']],'public');
    }
}
