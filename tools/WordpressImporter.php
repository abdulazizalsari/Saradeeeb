<?php
namespace App\Tools;
use App\Core\Database;

final class WordpressImporter {
    public function import(string $file, int $fallbackAuthor): array {
        libxml_use_internal_errors(true);
        $xml=simplexml_load_file($file,'SimpleXMLElement',LIBXML_NOCDATA);
        if(!$xml) throw new \RuntimeException('ملف XML غير صالح.');
        $ns=$xml->getNamespaces(true);
        $posts=0; $pages=0; $categories=0; $categoryMap=[]; $attachments=[];

        // First pass: map WordPress attachment IDs to their original URLs.
        foreach($xml->channel->item as $item){
            $wp=$item->children($ns['wp']??'');
            if((string)$wp->post_type==='attachment'){
                $id=(string)$wp->post_id; $u=trim((string)$wp->attachment_url);
                if($id!=='' && $u!=='') $attachments[$id]=$u;
            }
        }

        foreach($xml->channel->item as $item){
            $wp=$item->children($ns['wp']??''); $contentNs=$item->children($ns['content']??'');
            $type=(string)$wp->post_type;
            if(!in_array($type,['post','page'],true)) continue;
            $title=trim((string)$item->title); if($title==='') continue;
            $slug=trim((string)$wp->post_name) ?: slugify($title); $body=(string)$contentNs->encoded;
            $status=((string)$wp->status)==='publish'?'published':'draft'; $date=(string)$wp->post_date_gmt ?: (string)$wp->post_date;

            $meta=[];
            foreach($wp->postmeta as $pm){ $meta[(string)$pm->meta_key]=(string)$pm->meta_value; }
            $seoTitle=$meta['_yoast_wpseo_title']??$meta['rank_math_title']??'';
            $seoDesc=$meta['_yoast_wpseo_metadesc']??$meta['rank_math_description']??'';
            $thumbId=(string)($meta['_thumbnail_id']??''); $featured=$attachments[$thumbId]??null;
            $legacyPath=parse_url((string)$item->link,PHP_URL_PATH) ?: null;

            if($type==='page'){
                $pid=Database::insert('INSERT INTO pages(status,sort_order) VALUES(?,0)',[$status]);
                Database::insert("INSERT INTO page_translations(page_id,locale,title,slug,content,seo_title,seo_description) VALUES(?,'ar',?,?,?,?,?)",[$pid,$title,$slug,sanitize_html($body),$seoTitle,$seoDesc]);
                if($legacyPath) Database::exec('INSERT IGNORE INTO redirects(source_path,target_url,http_code) VALUES(?,?,301)',[$legacyPath,'/ar/page/'.$slug]);
                $pages++; continue;
            }

            $catId=null;
            foreach($item->category as $cat){
                $domain=(string)$cat['domain']; if($domain!=='category') continue;
                $name=trim((string)$cat); $nicename=trim((string)$cat['nicename']) ?: slugify($name);
                if(!isset($categoryMap[$nicename])){
                    $row=Database::one("SELECT c.id FROM categories c JOIN category_translations ct ON ct.category_id=c.id WHERE ct.locale='ar' AND ct.slug=?",[$nicename]);
                    if($row) $cid=(int)$row['id'];
                    else { $cid=Database::insert('INSERT INTO categories(sort_order) VALUES(0)'); Database::insert("INSERT INTO category_translations(category_id,locale,name,slug,description) VALUES(?,'ar',?,?,?)",[$cid,$name,$nicename,'']); $categories++; }
                    $categoryMap[$nicename]=$cid;
                }
                $catId=$categoryMap[$nicename]; break;
            }

            $aid=Database::insert('INSERT INTO articles(author_id,category_id,status,featured_image,published_at,legacy_path) VALUES(?,?,?,?,?,?)',[$fallbackAuthor,$catId,$status,$featured,$date?:null,$legacyPath]);
            $excerpt=mb_substr(trim(strip_tags($body)),0,260);
            Database::insert("INSERT INTO article_translations(article_id,locale,title,slug,excerpt,content,seo_title,seo_description,status,review_state) VALUES(?,'ar',?,?,?,?,?,?,?,?)",[$aid,$title,$slug,$excerpt,sanitize_html($body),$seoTitle,$seoDesc,$status,$status==='published'?'approved':'draft']);
            if($legacyPath && $legacyPath!=='/ar/article/'.$slug) Database::exec('INSERT IGNORE INTO redirects(source_path,target_url,http_code) VALUES(?,?,301)',[$legacyPath,'/ar/article/'.$slug]);
            $posts++;
        }
        return compact('posts','pages','categories');
    }
}
