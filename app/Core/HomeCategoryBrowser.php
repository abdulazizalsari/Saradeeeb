<?php
namespace App\Core;

final class HomeCategoryBrowser {
    public static function load(string $locale): array {
        // Category identity is stable; translations only supply its display name and URL.
        $allowFallback=translation_fallback_enabled($locale);
        $writerNameSql=($locale==='ar'||$allowFallback)
            ? "COALESCE(NULLIF(CASE WHEN wt.status='published' THEN wt.display_name END,''),u.name)"
            : "COALESCE(NULLIF(CASE WHEN wt.status='published' THEN wt.display_name END,''),CASE WHEN u.username REGEXP '^[A-Za-z0-9_-]+$' THEN u.username ELSE CONCAT('Writer ',u.id) END)";
        $rows=Database::all("SELECT c.id,c.sort_order,ct.name,ct.slug
            FROM categories c LEFT JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=? AND ct.status='published'
            WHERE c.active=1
            ORDER BY c.sort_order,c.id",[$locale]);
        $taxonomy=[];
        foreach($rows as $row){
            $id=(int)$row['id'];
            if(!isset($taxonomy[$id])) $taxonomy[$id]=['id'=>$id,'name'=>'','slug'=>'','article_count'=>0,'sort_order'=>(int)$row['sort_order']];
            if($taxonomy[$id]['name']===''&&trim((string)$row['name'])!==''){
                $taxonomy[$id]['name']=trim($row['name']);
                $taxonomy[$id]['slug']=(string)$row['slug'];
            }
        }
        if($allowFallback){
            $fallbackRows=Database::all("SELECT category_id,name,slug FROM category_translations WHERE locale='ar' AND status='published'");
            foreach($fallbackRows as $row){
                $id=(int)$row['category_id'];
                if(isset($taxonomy[$id]) && $taxonomy[$id]['name']==='' && trim((string)$row['name'])!==''){
                    $taxonomy[$id]['name']=trim((string)$row['name']);
                    $taxonomy[$id]['slug']=(string)$row['slug'];
                }
            }
        }
        $published="a.status='published' AND (a.published_at IS NULL OR a.published_at<=CURRENT_TIMESTAMP)";
        $translationPublished=\article_translation_publishable_sql('t');
        $counts=Database::all("SELECT c.id,COUNT(*) article_count FROM articles a
            JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND {$translationPublished}
            JOIN users u ON u.id=a.author_id LEFT JOIN categories c ON c.id=a.category_id
            WHERE $published GROUP BY c.id",[$locale]);
        $totalPublished=0;$uncategorizedCount=0;
        foreach($counts as $count){
            $number=(int)$count['article_count'];$totalPublished+=$number;
            if($count['id']===null) $uncategorizedCount=$number;
            elseif(isset($taxonomy[(int)$count['id']])) $taxonomy[(int)$count['id']]['article_count']=$number;
        }
        $browserArticles=Database::all("SELECT a.id,c.id category_id,a.is_featured,a.featured_image,a.published_at,a.views,
            {$writerNameSql} author_name,u.username,u.writer_verified,u.writer_honored,t.title,t.slug,t.excerpt,t.content
            FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND {$translationPublished}
            JOIN users u ON u.id=a.author_id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=?
            LEFT JOIN categories c ON c.id=a.category_id
            WHERE $published ORDER BY a.published_at DESC,a.id DESC",[$locale,$locale]);
        foreach($browserArticles as &$article){
            $category=$taxonomy[(int)$article['category_id']]??null;
            $article['category_name']=$category && $category['name']!=='' ? $category['name'] : ($article['category_id']===null ? tr('uncategorized',$locale) : '');
            $article['category_slug']=$category['slug']??null;
        }
        unset($article);
        $categories=array_values(array_filter($taxonomy,static fn($c)=>$c['article_count']>0));
        $featured=$browserArticles;
        // Preserve the latest-first order within the featured and ordinary groups.
        usort($featured,static fn($a,$b)=>(int)$b['is_featured']<=>(int)$a['is_featured']);
        return compact('categories','browserArticles','featured','totalPublished','uncategorizedCount');
    }
}
