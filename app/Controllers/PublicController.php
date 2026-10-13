<?php
namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Upload;
use App\Core\View;
use App\Core\Auth;

final class PublicController {
    public function root(): void { $this->home(default_locale()); }
    public function behindSaradeeb(string $locale): void {
        $locale=$this->locale($locale);
        $url=\App\Services\BehindPage::url($locale);
        if($url!=='') redirect($url);
        http_response_code(404);
        View::render('errors/404',compact('locale')+['title'=>'404'],'public');
    }
    private function locale(string $locale): string {
        if(!valid_locale($locale)){
            http_response_code(404);
            View::render('errors/404',['locale'=>default_locale(),'title'=>'404'],'public');
            exit;
        }
        return $locale;
    }
    private function categorySql(string $locale,string $field): string {
        $current="NULLIF(CASE WHEN ct.status='published' THEN ct.{$field} END,'')";
        $arabic="NULLIF(CASE WHEN ar.status='published' THEN ar.{$field} END,'')";
        return translation_fallback_enabled($locale) ? "COALESCE({$current},{$arabic})" : $current;
    }
    private function writerNameSql(string $locale): string {
        $translated="NULLIF(CASE WHEN wt.status='published' THEN wt.display_name END,'')";
        return ($locale==='ar' || translation_fallback_enabled($locale))
            ? "COALESCE({$translated},u.name)"
            : "COALESCE({$translated},CASE WHEN u.username REGEXP '^[A-Za-z0-9_-]+$' THEN u.username ELSE CONCAT('Writer ',u.id) END)";
    }
    private function writerFieldSql(string $locale,string $field,string $source): string {
        $translated="NULLIF(CASE WHEN wt.status='published' THEN wt.{$field} END,'')";
        return ($locale==='ar' || translation_fallback_enabled($locale))
            ? "COALESCE({$translated},{$source})"
            : $translated;
    }
    public function home(string $locale): void {
        $locale=$this->locale($locale);
        $browser=\App\Core\HomeCategoryBrowser::load($locale);
        $descriptions=[
            'ar'=>'ما وراء الظلام… أعظم مما تتخيل',
            'en'=>'Stories, mysteries and unsettling files from Saradeeeb.',
            'es'=>'Historias, misterios y expedientes inquietantes de Saradeeeb.',
            'fr'=>'Histoires, mystères et dossiers troublants de Saradeeeb.',
            'ru'=>'Истории, тайны и тревожные материалы Saradeeeb.',
        ];
        $description=translation_text('home','description',$locale,$descriptions[$locale]??$descriptions['en']);
        $homeBooks=\App\Services\Books::homePromotions();
        $storyOfNight=(new \App\Services\StoryOfNight())->current($locale);
        View::render('public/home-with-books',$browser+['locale'=>$locale,'title'=>setting('site_name',config('name')),'description'=>$description,'homeBooks'=>$homeBooks,'storyOfNight'=>$storyOfNight],'public');
    }
    public function article(string $locale,string $slug): void {
        $locale=$this->locale($locale);
        $translationPublished=article_translation_publishable_sql('t');
        $categoryNameSql=$this->categorySql($locale,'name'); $categorySlugSql=$this->categorySql($locale,'slug');
        $writerNameSql=$this->writerNameSql($locale); $writerBioSql=$this->writerFieldSql($locale,'bio','u.bio'); $writerTitleSql=$this->writerFieldSql($locale,'professional_title','p.professional_title');
        $slug=rawurldecode(trim($slug));
        $article=Database::one("SELECT a.*,t.title,t.slug,t.excerpt,t.content,t.seo_title,t.seo_description,t.seo_keywords,t.hashtags,t.og_image,t.image_alt,t.image_caption,t.og_title,t.og_description,{$writerNameSql} author_name,u.username,{$writerBioSql} author_bio,u.avatar author_avatar,u.writer_verified,u.writer_honored,{$writerTitleSql} professional_title,(SELECT COUNT(*) FROM articles a2 JOIN article_translations at2 ON at2.article_id=a2.id AND at2.locale=t.locale AND ".article_translation_publishable_sql('at2')." WHERE a2.author_id=u.id AND a2.status='published') author_article_count,c.id category_id,{$categoryNameSql} category_name,{$categorySlugSql} category_slug FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id LEFT JOIN writer_profiles p ON p.user_id=u.id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? LEFT JOIN categories c ON c.id=a.category_id LEFT JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=c.id AND ar.locale='ar' WHERE t.locale=? AND t.slug=? AND {$translationPublished} AND a.status='published' LIMIT 1",[$locale,$locale,$locale,$slug]);
        if(!$article){
            $encodedSlug=rawurlencode($slug);
            if($encodedSlug!==$slug){
                $article=Database::one("SELECT a.*,t.title,t.slug,t.excerpt,t.content,t.seo_title,t.seo_description,t.seo_keywords,t.hashtags,t.og_image,t.image_alt,t.image_caption,t.og_title,t.og_description,{$writerNameSql} author_name,u.username,{$writerBioSql} author_bio,u.avatar author_avatar,u.writer_verified,u.writer_honored,{$writerTitleSql} professional_title,(SELECT COUNT(*) FROM articles a2 JOIN article_translations at2 ON at2.article_id=a2.id AND at2.locale=t.locale AND ".article_translation_publishable_sql('at2')." WHERE a2.author_id=u.id AND a2.status='published') author_article_count,c.id category_id,{$categoryNameSql} category_name,{$categorySlugSql} category_slug FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id LEFT JOIN writer_profiles p ON p.user_id=u.id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? LEFT JOIN categories c ON c.id=a.category_id LEFT JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=c.id AND ar.locale='ar' WHERE t.locale=? AND t.slug=? AND {$translationPublished} AND a.status='published' LIMIT 1",[$locale,$locale,$locale,$encodedSlug]);
                if($article) $slug=(string)$article['slug'];
            }
        }
        if(!$article){
            // Preserve documented legacy links only within the requested language.
            $legacy=Database::one("SELECT t.slug FROM articles a JOIN article_translations t ON t.article_id=a.id WHERE t.locale=? AND ".article_translation_publishable_sql('t')." AND (a.legacy_path=? OR a.legacy_path=?) LIMIT 1",[$locale,'/article/'.$slug,$slug]);
            if($legacy && (string)$legacy['slug']!==$slug) redirect(locale_url($locale,'article/'.(string)$legacy['slug']),301);
            $oldPath=parse_url(locale_url($locale,'article/'.$slug),PHP_URL_PATH)?:'/';
            $redirect=Database::one('SELECT target_url FROM redirects WHERE source_path=? LIMIT 1',[$oldPath]);
            if($redirect){
                $targetPath=(string)parse_url((string)$redirect['target_url'],PHP_URL_PATH);
                $prefix=(string)parse_url(locale_url($locale,'article/'),PHP_URL_PATH);
                if(str_starts_with($targetPath,rtrim($prefix,'/').'/')){
                    $targetSlug=rawurldecode(substr($targetPath,strlen(rtrim($prefix,'/'))+1));
                    $published=Database::one("SELECT slug FROM article_translations t WHERE t.locale=? AND t.slug=? AND ".article_translation_publishable_sql('t')." LIMIT 1",[$locale,$targetSlug]);
                    if($published && $targetSlug!==$slug) redirect(locale_url($locale,'article/'.$published['slug']),301);
                }
            }
            http_response_code(404); View::render('errors/404',compact('locale')+['title'=>'404'],'public'); return;
        }
        Database::exec('UPDATE articles SET views=views+1 WHERE id=?',[$article['id']]); $article['views']=(int)$article['views']+1;
        $translations=Database::all("SELECT locale,title,slug FROM article_translations WHERE article_id=? AND ".article_translation_publishable_sql('article_translations'),[$article['id']]);
        $enabledCodes=array_keys(article_locales()); $translations=array_values(array_filter($translations,static fn($t)=>in_array($t['locale'],$enabledCodes,true)));
        $related=Database::all("SELECT a.id,a.featured_image,a.published_at,a.views,t.title,t.slug,t.excerpt,{$writerNameSql} author_name,u.username,u.writer_verified,u.writer_honored,{$categoryNameSql} category_name,{$categorySlugSql} category_slug FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND ".article_translation_publishable_sql('t')." JOIN users u ON u.id=a.author_id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? LEFT JOIN category_translations ct ON ct.category_id=a.category_id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=a.category_id AND ar.locale='ar' WHERE a.status='published' AND (a.published_at IS NULL OR a.published_at<=CURRENT_TIMESTAMP) AND a.id<>? AND a.category_id <=> ? ORDER BY a.published_at DESC,a.id DESC LIMIT 3",[$locale,$locale,$locale,$article['id'],$article['category_id']]);
        if(count($related)<3){
            $relatedIds=array_map(static fn(array $row): int => (int)$row['id'], $related);
            $relatedIds[]=(int)$article['id'];
            $placeholders=implode(',',array_fill(0,count($relatedIds),'?'));
            $fallbackParams=array_merge([$locale,$locale,$locale],$relatedIds,[3-count($related)]);
            $fallback=Database::all("SELECT a.id,a.featured_image,a.published_at,a.views,t.title,t.slug,t.excerpt,{$writerNameSql} author_name,u.username,u.writer_verified,u.writer_honored,{$categoryNameSql} category_name,{$categorySlugSql} category_slug FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND ".article_translation_publishable_sql('t')." JOIN users u ON u.id=a.author_id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? LEFT JOIN category_translations ct ON ct.category_id=a.category_id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=a.category_id AND ar.locale='ar' WHERE a.status='published' AND (a.published_at IS NULL OR a.published_at<=CURRENT_TIMESTAMP) AND a.id NOT IN ($placeholders) ORDER BY a.published_at DESC,a.id DESC LIMIT ?",$fallbackParams);
            $related=array_merge($related,$fallback);
        }
        $title=$article['seo_title']?:$article['title']; $description=$article['seo_description']?:$article['excerpt']; $ogTitle=$article['og_title']?:$title; $ogDescription=$article['og_description']?:$description; $ogImage=$article['og_image']?:$article['featured_image'];
        $thank=writer_thank((int)$article['author_id']);
        $comments=[];
        if(setting('comments_enabled','1')==='1') $comments=Database::all("SELECT c.*,u.name user_name,u.username,u.avatar FROM article_comments c LEFT JOIN users u ON u.id=c.user_id WHERE c.article_id=? AND c.status='approved' ORDER BY c.created_at ASC,c.id ASC",[$article['id']]);
        $podcastEpisode=null;
        try{ $podcastEpisode=Database::one("SELECT * FROM podcast_episodes WHERE article_id=? AND status='published' AND (published_at IS NULL OR published_at<=CURRENT_TIMESTAMP) ORDER BY published_at DESC,id DESC LIMIT 1",[$article['id']]); }catch(\Throwable){}
        View::render('public/article',compact('locale','article','translations','related','title','description','ogTitle','ogDescription','ogImage','thank','comments','podcastEpisode'),'public');
    }

    public function podcast(string $locale): void {
        $locale=$this->locale($locale);
        if($locale!=='ar'){ redirect(locale_url('ar','podcast'),302); return; }
        $categoryNameSql=$this->categorySql($locale,'name'); $categorySlugSql=$this->categorySql($locale,'slug'); $categoryDescriptionSql=$this->categorySql($locale,'description');
        if(setting('podcast_enabled','1')!=='1'){ http_response_code(404); View::render('errors/404',compact('locale')+['title'=>'404'],'public'); return; }
        $publishedWhere="p.status='published' AND (p.published_at IS NULL OR p.published_at<=CURRENT_TIMESTAMP)";
        if(translation_fallback_enabled($locale)){
            $episodes=Database::all("SELECT p.*,COALESCE(NULLIF(pt.title,''),pa.title) title,COALESCE(NULLIF(pt.slug,''),pa.slug) slug,COALESCE(pt.excerpt,pa.excerpt) excerpt,COALESCE(pt.content,pa.content) content,COALESCE(pt.seo_title,pa.seo_title) seo_title,COALESCE(pt.seo_description,pa.seo_description) seo_description,{$categoryNameSql} category_name,{$categorySlugSql} category_slug FROM podcast_episodes p LEFT JOIN podcast_episode_translations pt ON pt.episode_id=p.id AND pt.locale=? AND pt.status='published' JOIN podcast_episode_translations pa ON pa.episode_id=p.id AND pa.locale='ar' AND pa.status='published' LEFT JOIN category_translations ct ON ct.category_id=p.category_id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=p.category_id AND ar.locale='ar' WHERE {$publishedWhere} ORDER BY COALESCE(p.published_at,p.created_at) DESC,p.id DESC LIMIT 60",[$locale,$locale]);
        } else {
            $episodes=Database::all("SELECT p.*,pt.title,pt.slug,pt.excerpt,pt.content,pt.seo_title,pt.seo_description,pt.og_title,pt.og_description,{$categoryNameSql} category_name,{$categorySlugSql} category_slug FROM podcast_episodes p JOIN podcast_episode_translations pt ON pt.episode_id=p.id AND pt.locale=? AND pt.status='published' LEFT JOIN category_translations ct ON ct.category_id=p.category_id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=p.category_id AND ar.locale='ar' WHERE {$publishedWhere} ORDER BY COALESCE(p.published_at,p.created_at) DESC,p.id DESC LIMIT 60",[$locale,$locale]);
        }
        // Categories come from the existing taxonomy and are counted from published episodes only.
        $podcastCategories=Database::all("SELECT c.id,c.sort_order,{$categoryNameSql} name,{$categorySlugSql} slug,{$categoryDescriptionSql} description,COUNT(DISTINCT p.id) episode_count FROM categories c LEFT JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=c.id AND ar.locale='ar' LEFT JOIN podcast_episodes p ON p.category_id=c.id AND p.status='published' AND (p.published_at IS NULL OR p.published_at<=CURRENT_TIMESTAMP) WHERE {$categoryNameSql} IS NOT NULL GROUP BY c.id,c.sort_order,{$categoryNameSql},{$categorySlugSql},{$categoryDescriptionSql} HAVING COUNT(DISTINCT p.id)>0 ORDER BY c.sort_order,c.id LIMIT 30",[$locale]);
        if(!$episodes) $podcastCategories=[];
        $featured=$episodes[0]??null;
        $episodeImages=[];
        foreach($episodes as $episode){$categoryId=(int)($episode['category_id']??0);if($categoryId>0 && !isset($episodeImages[$categoryId]) && trim((string)($episode['cover_image']??''))!=='')$episodeImages[$categoryId]=(string)$episode['cover_image'];}
        $series=[]; foreach(array_slice($podcastCategories,0,3) as $category){$series[]=$category+['image'=>$episodeImages[(int)$category['id']]??''];}
        $moods=array_slice($podcastCategories,0,4);
        $podcastMeta=[
          'ar'=>['eyebrow'=>'بودكاست سراديب','badge'=>'حلقة جديدة كل أسبوع','title'=>'قصص تُسمع… ولا تنتهي عند آخر كلمة.','description'=>'حكايات من سراديب بصوتها الخاص؛ حلقات تجمع الغموض والتاريخ والتأمل في تجربة استماع واحدة.','seo'=>'حلقات بودكاست سراديب المنشورة.'],
          'en'=>['eyebrow'=>'Saradeeeb podcast','badge'=>'A new episode every week','title'=>'Stories you hear… and that never end at the last word.','description'=>'Saradeeeb stories in their own voice: episodes of mystery, history and reflection in one listening experience.','seo'=>'Published Saradeeeb podcast episodes.'],
          'es'=>['eyebrow'=>'Podcast Saradeeeb','badge'=>'Un episodio nuevo cada semana','title'=>'Historias que se escuchan… y no terminan en la última palabra.','description'=>'Historias de Saradeeeb con voz propia: misterio, historia y reflexión en una experiencia de escucha.','seo'=>'Episodios publicados del podcast Saradeeeb.'],
          'fr'=>['eyebrow'=>'Podcast Saradeeeb','badge'=>'Un nouvel épisode chaque semaine','title'=>'Des histoires à écouter… qui ne finissent pas au dernier mot.','description'=>'Les histoires de Saradeeeb dans leur propre voix : mystère, histoire et réflexion réunis dans une expérience audio.','seo'=>'Épisodes publiés du podcast Saradeeeb.'],
          'ru'=>['eyebrow'=>'Подкаст Saradeeeb','badge'=>'Новый выпуск каждую неделю','title'=>'Истории, которые звучат… и не заканчиваются последним словом.','description'=>'Истории Saradeeeb своим голосом: тайны, история и размышления в одном аудиопутешествии.','seo'=>'Опубликованные выпуски подкаста Saradeeeb.'],
        ][$locale]??[];
        $heroEyebrow=translation_text('podcast','hero_eyebrow',$locale,$podcastMeta['eyebrow']);
        $heroBadge=translation_text('podcast','hero_badge',$locale,$podcastMeta['badge']);
        $heroTitle=translation_text('podcast','hero_title',$locale,$podcastMeta['title']);
        $heroDescription=translation_text('podcast','hero_description',$locale,$podcastMeta['description']);
        $title=translation_text('podcast','title',$locale,$podcastMeta['eyebrow']); $description=translation_text('podcast','description',$locale,$podcastMeta['seo']);
        View::render('public/podcast',compact('locale','episodes','podcastCategories','featured','series','moods','heroEyebrow','heroBadge','heroTitle','heroDescription','title','description'),'public');
    }

    public function podcastEpisode(string $locale,string $slug): void {
        $locale=$this->locale($locale);
        if($locale!=='ar'){ redirect(locale_url('ar','podcast'),302); return; }
        $categoryNameSql=$this->categorySql($locale,'name'); $categorySlugSql=$this->categorySql($locale,'slug');
        if(setting('podcast_enabled','1')!=='1'){ http_response_code(404); View::render('errors/404',compact('locale')+['title'=>'404'],'public'); return; }
        $episode=Database::one("SELECT p.*,pt.title,pt.slug,pt.excerpt,pt.content,pt.seo_title,pt.seo_description,pt.og_title,pt.og_description,{$categoryNameSql} category_name,{$categorySlugSql} category_slug,t.title article_title,t.slug article_slug FROM podcast_episodes p JOIN podcast_episode_translations pt ON pt.episode_id=p.id AND pt.locale=? AND pt.status='published' LEFT JOIN category_translations ct ON ct.category_id=p.category_id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=p.category_id AND ar.locale='ar' LEFT JOIN article_translations t ON t.article_id=p.article_id AND t.locale=? AND ".article_translation_publishable_sql('t')." WHERE pt.slug=? AND p.status='published' AND (p.published_at IS NULL OR p.published_at<=CURRENT_TIMESTAMP) LIMIT 1",[$locale,$locale,$locale,$slug]);
        if(!$episode && translation_fallback_enabled($locale)) $episode=Database::one("SELECT p.*,pt.title,pt.slug,pt.excerpt,pt.content,pt.seo_title,pt.seo_description,pt.og_title,pt.og_description,{$categoryNameSql} category_name,{$categorySlugSql} category_slug,t.title article_title,t.slug article_slug FROM podcast_episodes p JOIN podcast_episode_translations pt ON pt.episode_id=p.id AND pt.locale='ar' AND pt.status='published' LEFT JOIN category_translations ct ON ct.category_id=p.category_id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=p.category_id AND ar.locale='ar' LEFT JOIN article_translations t ON t.article_id=p.article_id AND t.locale=? AND ".article_translation_publishable_sql('t')." WHERE pt.slug=? AND p.status='published' AND (p.published_at IS NULL OR p.published_at<=CURRENT_TIMESTAMP) LIMIT 1",[$locale,$locale,$slug]);
        if(!$episode){ http_response_code(404); View::render('errors/404',compact('locale')+['title'=>'404'],'public'); return; }
        Database::exec('UPDATE podcast_episodes SET views=views+1 WHERE id=?',[$episode['id']]); $episode['views']=(int)$episode['views']+1;
        $related=Database::all("SELECT p.*,pt.title,pt.slug,pt.excerpt,pt.content,pt.seo_title,pt.seo_description FROM podcast_episodes p JOIN podcast_episode_translations pt ON pt.episode_id=p.id AND pt.locale=? AND pt.status='published' WHERE p.status='published' AND (p.published_at IS NULL OR p.published_at<=CURRENT_TIMESTAMP) AND p.id<>? AND (p.category_id <=> ?) ORDER BY COALESCE(p.published_at,p.created_at) DESC,p.id DESC LIMIT 3",[$locale,$episode['id'],$episode['category_id']]);
        $title=$episode['seo_title']?:$episode['title']; $description=$episode['seo_description']?:$episode['excerpt'];
        View::render('public/podcast-episode',compact('locale','episode','related','title','description'),'public');
    }
    public function submitComment(string $locale,string $slug): void {
        $locale=$this->locale($locale); Csrf::enforce();
        $back=locale_url($locale,'article/'.$slug).'#comments';
        if(setting('comments_enabled','1')!=='1'){ flash('error','التعليقات مغلقة حاليًا.'); redirect($back); }
        $article=Database::one("SELECT a.id FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? WHERE t.slug=? AND ".article_translation_publishable_sql('t')." AND a.status='published' LIMIT 1",[$locale,$slug]);
        if(!$article){ http_response_code(404); return; }
        if(setting('security_spam_honeypot','1')==='1' && trim((string)($_POST['website']??''))!==''){ redirect($back); }
        $content=trim((string)($_POST['content']??''));
        if(mb_strlen($content)<2 || mb_strlen($content)>5000){ flash('error','اكتب تعليقًا بين حرفين و5000 حرف.'); redirect($back); }
        $maxLinks=max(0,(int)setting('comments_max_links','2')); $links=preg_match_all('~https?://|www\\.~iu',$content,$m);
        $blocked=array_filter(array_map('trim',preg_split('/[\\r\\n,]+/u',setting('comments_blocked_words',''))?:[]));
        $spam=$links>$maxLinks; foreach($blocked as $word){ if($word!=='' && mb_stripos($content,$word)!==false){$spam=true;break;} }
        $u=Auth::user(); $name=''; $email='';
        if(!$u){
            if(setting('comments_allow_guests','1')!=='1'){ flash('error','سجّل الدخول للمشاركة بالتعليقات.'); redirect($back); }
            $name=mb_substr(trim((string)($_POST['name']??'')),0,120); $email=mb_strtolower(mb_substr(trim((string)($_POST['email']??'')),0,190));
            if($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)){ flash('error','اكتب الاسم وبريدًا إلكترونيًا صحيحًا.'); redirect($back); }
        }
        $parent=(int)($_POST['parent_id']??0);
        if($parent>0){
            if(setting('comments_allow_replies','1')!=='1')$parent=0;
            elseif(!Database::one("SELECT id FROM article_comments WHERE id=? AND article_id=? AND status='approved'",[$parent,$article['id']]))$parent=0;
        }
        $rate=max(10,(int)setting('comments_rate_seconds','45')); $last=(int)($_SESSION['last_article_comment_at']??0);
        if(setting('security_form_rate_limit','1')==='1' && $last && time()-$last<$rate){ flash('error','يرجى الانتظار قليلًا قبل إرسال تعليق آخر.'); redirect($back); }
        $status='pending';
        if($spam)$status='spam';
        elseif(setting('comments_require_moderation','1')!=='1')$status='approved';
        elseif($u && setting('comments_registered_autoapprove','1')==='1')$status='approved';
        $ip=(string)($_SERVER['REMOTE_ADDR']??''); $ipHash=$ip!==''?hash('sha256',$ip.'|'.config('key','saradeeeb')):null;
        Database::insert('INSERT INTO article_comments(article_id,parent_id,user_id,guest_name,guest_email,content,status,ip_hash,user_agent) VALUES(?,?,?,?,?,?,?,?,?)',[(int)$article['id'],$parent?:null,$u['id']??null,$u?null:$name,$u?null:$email,$content,$status,$ipHash,mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255)]);
        $_SESSION['last_article_comment_at']=time();
        flash('success',$status==='approved'?'تم نشر تعليقك.':'تم استلام تعليقك وسيظهر بعد المراجعة.'); redirect($back);
    }

    public function category(string $locale,string $slug): void {
        $locale=$this->locale($locale);
        $categoryNameSql=$this->categorySql($locale,'name'); $categorySlugSql=$this->categorySql($locale,'slug'); $categoryDescriptionSql=$this->categorySql($locale,'description');
        $slug=rawurldecode(trim($slug));
        // A legacy category row may contain an already URL-encoded slug. Match
        // both representations after the router has decoded the request path.
        $slugCandidates=array_values(array_unique([$slug,rawurlencode($slug),strtolower(rawurlencode($slug)),urlencode($slug)]));
        $slugPlaceholders=implode(',',array_fill(0,count($slugCandidates),'?'));
        $category=Database::one("SELECT c.id,{$categoryNameSql} name,{$categorySlugSql} slug,{$categoryDescriptionSql} description FROM categories c LEFT JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=? LEFT JOIN category_translations ar ON ar.category_id=c.id AND ar.locale='ar' WHERE {$categorySlugSql} IN ($slugPlaceholders) LIMIT 1",array_merge([$locale],$slugCandidates));
        if(!$category){ http_response_code(404); View::render('errors/404',compact('locale')+['title'=>'404'],'public'); return; }
        $writerNameSql=$this->writerNameSql($locale);
        $articles=Database::all("SELECT a.*,t.title,t.slug,t.excerpt,{$writerNameSql} author_name FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND ".article_translation_publishable_sql('t')." JOIN users u ON u.id=a.author_id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? WHERE a.category_id=? AND a.status='published' AND (a.published_at IS NULL OR a.published_at<=CURRENT_TIMESTAMP) ORDER BY a.published_at DESC,a.id DESC LIMIT 60",[$locale,$locale,$category['id']]);
        View::render('public/list',compact('locale','articles','category')+['title'=>$category['name'],'heading'=>$category['name'],'intro'=>$category['description']],'public');
    }
    public function author(string $locale,string $username): void {
        $locale=$this->locale($locale);
        $rawUsername=trim($username); $normalized=normalize_writer_username($rawUsername); $lookup=$normalized!==''?$normalized:$rawUsername;
        $writerNameSql=$this->writerNameSql($locale); $writerBioSql=$this->writerFieldSql($locale,'bio','u.bio'); $writerTitleSql=$this->writerFieldSql($locale,'professional_title','p.professional_title'); $writerSummarySql=$this->writerFieldSql($locale,'cv_summary','p.cv_summary');
        $visibility=Database::one("SELECT id FROM users WHERE username=? AND status='active' AND account_trashed_at IS NULL AND writer_directory_hidden=0 AND role IN ('writer','trusted_writer','editor','admin') LIMIT 1",[$lookup]);
        if(!$visibility){http_response_code(404); View::render('errors/404',compact('locale')+['title'=>'404'],'public'); return;}
        $author=Database::one("SELECT u.id,{$writerNameSql} name,u.username,{$writerBioSql} bio,u.avatar,u.writer_verified,u.writer_honored,p.*,{$writerTitleSql} professional_title,{$writerSummarySql} cv_summary FROM users u LEFT JOIN writer_profiles p ON p.user_id=u.id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? WHERE u.username=? AND u.status='active' AND u.account_trashed_at IS NULL AND u.writer_directory_hidden=0 AND u.role IN ('writer','trusted_writer','editor','admin')",[$locale,$lookup]);
        if(!$author){
            try{
                $alias=Database::one("SELECT u.username FROM writer_username_redirects r JOIN users u ON u.id=r.user_id WHERE r.old_username=? AND u.status='active' LIMIT 1",[$lookup]);
                if($alias) redirect(writer_url((string)$alias['username'],$locale),301);
            }catch(\Throwable){}
            http_response_code(404); View::render('errors/404',compact('locale')+['title'=>'404'],'public'); return;
        }
        $articles=Database::all("SELECT a.*,t.title,t.slug,t.excerpt FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND ".article_translation_publishable_sql('t')." WHERE a.author_id=? AND a.status='published' ORDER BY a.published_at DESC LIMIT 50",[$locale,$author['id']]);
        $khawatir=[];
        try{$khawatir=Database::all("SELECT k.*,kt.title,kt.slug,kt.body,kct.name category_name FROM khawatir k JOIN khawatir_translations kt ON kt.khawatir_id=k.id AND kt.locale=? AND kt.status='published' LEFT JOIN khawatir_category_translations kct ON kct.category_id=k.category_id AND kct.locale=? AND kct.status='published' WHERE k.author_id=? AND k.status='published' AND k.published_at<=CURRENT_TIMESTAMP ORDER BY k.published_at DESC LIMIT 30",[$locale,$locale,$author['id']]);}catch(\Throwable){}
        $decode=static fn($v)=>is_array($x=json_decode((string)$v,true))?$x:[];
        foreach(['experience','education','skills','certificates','languages','achievements','portfolio'] as $x)$author[$x]=$decode($author[$x.'_json']??'');
        $awards=writer_awards((int)$author['id']);
        // The public profile may use an approved writer-card template without
        // exposing the writer's private studio preferences.
        $writerCard='';
        if(setting('writer_cards_enabled','1')==='1'){
            $writerCard=\App\Services\CardTemplateService::renderActive('writer-card',[
                'name'=>(string)$author['name'],
                'username'=>(string)$author['username'],
                'reason'=>(string)($author['cv_summary']?:$author['bio']?:'كاتب في سراديب'),
                'profile_url'=>(string)writer_url((string)$author['username'],$locale),
            ]);
        }
        $books=\App\Services\Books::publicBooks((int)$author['id']);
        $title=$author['name'].(!empty($author['professional_title'])?' — '.$author['professional_title']:'');
        $description=$author['cv_summary']?:$author['bio'];
        View::render('public/author-with-books',compact('locale','articles','khawatir','author','awards','books','writerCard','title','description'),'public');
    }
    public function writers(string $locale): void {
        $locale=$this->locale($locale);
        $writerNameSql=$this->writerNameSql($locale); $writerBioSql=$this->writerFieldSql($locale,'bio','u.bio'); $writerTitleSql=$this->writerFieldSql($locale,'professional_title','p.professional_title');
        $writers=Database::all("SELECT u.id,{$writerNameSql} name,u.username,{$writerBioSql} bio,u.avatar,u.writer_verified,u.writer_honored,{$writerTitleSql} professional_title,(SELECT COUNT(*) FROM articles a JOIN article_translations at ON at.article_id=a.id AND at.locale=? AND ".article_translation_publishable_sql('at')." WHERE a.author_id=u.id AND a.status='published') articles_count FROM users u LEFT JOIN writer_profiles p ON p.user_id=u.id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? WHERE u.status='active' AND u.writer_directory_hidden=0 AND u.role IN ('writer','trusted_writer','editor','admin') ORDER BY u.writer_honored DESC,u.writer_verified DESC,articles_count DESC,name",[$locale,$locale]);
        $meta=[
            'ar'=>['title'=>'كتّاب سراديب','description'=>'تعرّف إلى كتّاب سراديب واكتشف مقالاتهم وخواطرهم.'],
            'en'=>['title'=>'Saradeeeb writers','description'=>'Meet Saradeeeb writers and discover their articles and reflections.'],
            'es'=>['title'=>'Autores Saradeeeb','description'=>'Conoce a los autores de Saradeeeb y descubre sus artículos y reflexiones.'],
            'fr'=>['title'=>'Auteurs Saradeeeb','description'=>'Découvrez les auteurs de Saradeeeb, leurs articles et leurs réflexions.'],
            'ru'=>['title'=>'Авторы Saradeeeb','description'=>'Познакомьтесь с авторами Saradeeeb и их статьями и размышлениями.'],
        ][$locale]??['title'=>'Saradeeeb writers','description'=>'Meet Saradeeeb writers and discover their articles and reflections.'];
        View::render('public/writers',compact('locale','writers')+['title'=>$meta['title'],'description'=>$meta['description']],'public');
    }
    public function search(string $locale): void {
        $locale=$this->locale($locale); $q=trim((string)($_GET['q']??'')); $articles=[];
        if(mb_strlen($q)>=2){ $like='%'.$q.'%'; $writerNameSql=$this->writerNameSql($locale); $articles=Database::all("SELECT a.*,t.title,t.slug,t.excerpt,{$writerNameSql} author_name FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND ".article_translation_publishable_sql('t')." JOIN users u ON u.id=a.author_id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? WHERE a.status='published' AND (t.title LIKE ? OR t.excerpt LIKE ? OR t.content LIKE ? OR t.hashtags LIKE ?) ORDER BY a.published_at DESC LIMIT 60",[$locale,$locale,$like,$like,$like,$like]); }
        View::render('public/list',compact('locale','articles','q')+['title'=>tr('search',$locale),'heading'=>tr('results',$locale),'intro'=>$q?tr('results_for',$locale).' '.$q:tr('search_intro',$locale),'robots'=>'noindex,follow'],'public');
    }
    public function page(string $locale,string $slug): void {
        $locale=$this->locale($locale);
        $page=Database::one("SELECT p.*,pt.title,pt.slug,pt.content,pt.content_mode,pt.custom_css,pt.custom_js,pt.featured_image,pt.seo_title,pt.seo_description,pt.canonical_url,pt.show_title,pt.page_bg_color,pt.page_text_color,pt.content_width FROM pages p JOIN page_translations pt ON pt.page_id=p.id AND pt.locale=? WHERE ".page_translation_publishable_sql('pt')." AND pt.slug=? LIMIT 1",[$locale,$slug]);
        if(!$page){
            // Generic content routes are matched before Router's redirect
            // fallback.  Resolve a stored 301 here so renamed page slugs do
            // not become 404s merely because they are managed pages.
            $oldPath=parse_url(locale_url($locale,'page/'.$slug),PHP_URL_PATH)?:'/';
            $target=\App\Services\ManagedPages::redirectTarget($locale,$oldPath);
            if($target) redirect($target,301);
            http_response_code(404); View::render('errors/404',compact('locale')+['title'=>'404'],'public'); return;
        }
        $systemKey=\App\Services\ManagedPages::key((int)$page['id']);
        if($systemKey && isset(\App\Services\ManagedPages::ROUTES[$systemKey])) redirect(\App\Services\ManagedPages::url((int)$page['id'],$locale,$page['slug']),301);
        $translations=Database::all('SELECT locale,title,slug FROM page_translations pt WHERE page_id=? AND '.page_translation_publishable_sql('pt'),[$page['id']]);
        $enabledCodes=array_keys(article_locales()); $translations=array_values(array_filter($translations,static fn($t)=>in_array($t['locale'],$enabledCodes,true)));
        if((int)$page['id']===\App\Services\BehindPage::id() && ($version=\App\Services\AboutPage::version($locale,'published'))){
            $about=$version['fields'];
            // The public heading belongs to the managed page. This means an
            // editor's title change (for example, "من خلف سراديب") is always
            // reflected on the live page instead of being overwritten by an
            // older About-editor field.
            $title=$page['seo_title']?:$page['title'];
            $description=$page['seo_description']?:($version['seo_description']?:$about['short_intro']);
            $canonical=$page['canonical_url']?:\App\Services\BehindPage::url($locale);
            $ogImage=$version['image_path']??'';
            View::render('public/about',compact('locale','page','about','version','translations','title','description','canonical','ogImage'),'public');
            return;
        }
        $title=$page['seo_title']?:$page['title']; $description=$page['seo_description']?:mb_substr(trim(strip_tags($page['content'])),0,220);
        View::render('public/page',compact('locale','page','translations','title','description'),'public');
    }

    /**
     * Native privacy policy page.  It remains available even when an editor
     * has not created a managed page record yet, so consent links never lead
     * to a broken 404 page.
     */
    public function privacyPolicy(string $locale): void {
        $locale=$this->locale($locale);
        // The privacy policy is managed from the Pages screen (record 22).
        // Keep one public source so edits in the dashboard are reflected here.
        $managed=Database::one("SELECT p.*,pt.title,pt.slug,pt.content,pt.content_mode,pt.custom_css,pt.custom_js,pt.featured_image,pt.seo_title,pt.seo_description,pt.canonical_url,pt.show_title,pt.page_bg_color,pt.page_text_color,pt.content_width FROM pages p JOIN page_translations pt ON pt.page_id=p.id AND pt.locale=? WHERE p.id=22 AND p.deleted_at IS NULL LIMIT 1",[$locale]);
        if($managed && trim((string)$managed['content'])!==''){
            Database::exec("UPDATE pages SET status='published' WHERE id=22 AND status<>'published'");
            $translations=Database::all('SELECT locale,title,slug FROM page_translations WHERE page_id=? AND status=\'published\'',[(int)$managed['id']]);
            $title=$managed['seo_title']?:$managed['title'];
            $description=$managed['seo_description']?:mb_substr(trim(strip_tags((string)$managed['content'])),0,220);
            View::render('public/page',compact('locale','translations')+['page'=>$managed,'title'=>$title,'description'=>$description],'public');
            return;
        }
        $copy=[
            'ar'=>[
                'title'=>'سياسة الخصوصية','description'=>'كيف يجمع موقع سراديب بياناتك ويستخدمها ويحميها.',
                'intro'=>'نحترم خصوصيتك ونلتزم بالتعامل مع بياناتك بوضوح ومسؤولية. توضح هذه السياسة البيانات التي قد نجمعها وسبب استخدامها وحقوقك تجاهها.',
                'sections'=>[
                    ['title'=>'البيانات التي نجمعها','body'=>'قد نجمع البيانات التي تقدمها طوعًا عند إنشاء حساب أو إرسال قصة أو مراسلتنا، مثل الاسم والبريد الإلكتروني ومحتوى الرسالة. كما قد نسجل بيانات تقنية أساسية مثل نوع الجهاز والصفحات التي تمت زيارتها لتحسين أداء الموقع.'],
                    ['title'=>'كيف نستخدم البيانات','body'=>'نستخدم البيانات لتقديم خدمات سراديب، وإدارة الحسابات والمساهمات، والرد على الرسائل، وتحسين المحتوى والأمان، وإرسال النشرات البريدية عند اشتراكك فيها. لا نبيع بياناتك الشخصية ولا نستخدمها لأغراض مخالفة لما ورد هنا.'],
                    ['title'=>'ملفات الارتباط','body'=>'قد نستخدم ملفات ارتباط ضرورية لتشغيل الموقع وحفظ تفضيلاتك، وملفات قياس اختيارية بعد موافقتك. يمكنك حذف ملفات الارتباط أو منعها من إعدادات متصفحك، وقد يؤثر ذلك في بعض الوظائف.'],
                    ['title'=>'مشاركة البيانات والحماية','body'=>'لا نشارك بياناتك إلا عند الحاجة لتشغيل خدمة طلبتها، أو للامتثال لالتزام قانوني، أو لحماية حقوق الموقع ومستخدميه. نطبق إجراءات تقنية وتنظيمية معقولة لحماية البيانات، مع العلم أن النقل عبر الإنترنت لا يخلو من المخاطر.'],
                    ['title'=>'حقوقك','body'=>'يمكنك طلب الاطلاع على بياناتك أو تصحيحها أو حذفها، كما يمكنك إلغاء الاشتراك في النشرة البريدية في أي وقت. للتواصل بشأن الخصوصية استخدم صفحة اتصل بنا أو البريد المعلن في الموقع.'],
                    ['title'=>'تحديث السياسة','body'=>'قد نحدّث هذه السياسة عند تغير الخدمات أو المتطلبات القانونية. سنعرض تاريخ آخر تحديث في أسفل الصفحة، ويعد استمرار استخدام الموقع بعد التحديث قبولًا للنص المعدل.'],
                ],
                'updated'=>'آخر تحديث: 11 أكتوبر 2026'
            ],
            'en'=>[
                'title'=>'Privacy Policy','description'=>'How Saradeeeb collects, uses, and protects your information.',
                'intro'=>'We respect your privacy and handle your information transparently and responsibly. This policy explains what we may collect, why we use it, and your rights.',
                'sections'=>[
                    ['title'=>'Information we collect','body'=>'We may collect information you voluntarily provide when you create an account, submit a story, or contact us, such as your name, email, and message. We may also record basic technical data to improve site performance.'],
                    ['title'=>'How we use information','body'=>'We use information to operate Saradeeeb, manage accounts and submissions, answer messages, improve content and security, and send newsletters when you subscribe. We do not sell personal information.'],
                    ['title'=>'Cookies','body'=>'We may use essential cookies to operate the site and remember preferences, plus optional analytics cookies after consent. You can control cookies in your browser.'],
                    ['title'=>'Sharing and security','body'=>'We share information only when needed to provide a requested service, comply with law, or protect our rights and users. We apply reasonable technical and organizational safeguards.'],
                    ['title'=>'Your rights','body'=>'You may request access, correction, or deletion of your information and unsubscribe from newsletters at any time. Contact us through the Contact page for privacy requests.'],
                    ['title'=>'Policy updates','body'=>'We may update this policy when our services or legal requirements change. The last update date appears below.'],
                ],
                'updated'=>'Last updated: October 11, 2026'
            ],
        ];
        $fallback=$copy['en'];
        $data=$copy[$locale]??$fallback;
        // Keep the policy page readable even if an old translation record or
        // corrupted legacy copy is returned by a previous deployment.
        if(empty($data['sections']) || !is_array($data['sections'])){
            $data=$locale==='ar'?[
                'title'=>'سياسة الخصوصية','description'=>'كيف يجمع موقع سراديب بياناتك ويستخدمها ويحميها.','intro'=>'نحترم خصوصيتك ونلتزم بالتعامل مع بياناتك بوضوح ومسؤولية. توضح هذه السياسة البيانات التي قد نجمعها وسبب استخدامها وحقوقك تجاهها.',
                'sections'=>[
                    ['title'=>'البيانات التي نجمعها','body'=>'قد نجمع البيانات التي تقدمها طوعًا عند إنشاء حساب أو إرسال قصة أو مراسلتنا، مثل الاسم والبريد الإلكتروني ومحتوى الرسالة.'],
                    ['title'=>'كيف نستخدم البيانات','body'=>'نستخدم البيانات لتشغيل خدمات سراديب وإدارة الحسابات والمشاركات والرد على الرسائل وتحسين المحتوى والأمان وإرسال النشرة عند الاشتراك.'],
                    ['title'=>'ملفات الارتباط','body'=>'قد نستخدم ملفات ارتباط ضرورية لتشغيل الموقع وحفظ تفضيلاتك، وملفات قياس اختيارية بعد موافقتك.'],
                    ['title'=>'حقوقك','body'=>'يمكنك طلب الاطلاع على بياناتك أو تصحيحها أو حذفها، كما يمكنك إلغاء الاشتراك في النشرة البريدية في أي وقت.'],
                    ['title'=>'تحديث السياسة','body'=>'قد نحدّث هذه السياسة عند تغير الخدمات أو المتطلبات القانونية، وسنعرض تاريخ آخر تحديث أسفل الصفحة.'],
                ],'updated'=>'آخر تحديث: 11 أكتوبر 2026'
            ]:$fallback;
        }
        View::render('public/privacy-policy',compact('locale','data')+['title'=>$data['title'],'description'=>$data['description']],'public');
    }

    public function sendStoryForm(string $locale): void {
        $locale=$this->locale($locale);
        $meta=[
            'ar'=>['أرسل قصتك','أرسل قصتك إلى فريق سراديب للمراجعة.'],
            'en'=>['Send your story','Send your story to the Saradeeeb team for review.'],
            'es'=>['Envía tu historia','Envía tu historia al equipo de Saradeeeb para su revisión.'],
            'fr'=>['Envoyez votre histoire','Envoyez votre histoire à l’équipe Saradeeeb pour examen.'],
            'ru'=>['Отправить историю','Отправьте историю команде Saradeeeb для рассмотрения.'],
        ][$locale]??['Send your story',''];
        $meta[0]=translation_text('send_story','title',$locale,$meta[0]); $meta[1]=translation_text('send_story','desc',$locale,$meta[1]);
        $categories=Database::all("SELECT c.id,ct.name FROM categories c JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=? AND ct.status='published' WHERE c.active=1 ORDER BY c.sort_order,c.id",[$locale]);
        $writingLanguages=article_locales();
        View::render('public/send-story',compact('locale','categories','writingLanguages')+['title'=>$meta[0],'description'=>$meta[1]],'public');
    }
    public function submitStory(string $locale): void {
        $locale=$this->locale($locale); Csrf::enforce();
        $msg=[
            'ar'=>['invalid'=>'أكمل الاسم والعنوان، واجعل نص القصة 120 حرفًا على الأقل.','wait'=>'انتظر دقيقة قبل إرسال قصة أخرى.','ok'=>'شكرًا لإرسالك قصتك يا مبدع! وصلت مشاركتك إلى فريق سراديب بنجاح، وسنراجعها بعناية قبل نشرها. إذا استوفت معايير النشر ولم تتضمن أي مشكلة، ستجدها قريبًا بين قصص الموقع.'],
            'en'=>['invalid'=>'Complete your name, email and title, and make the story at least 120 characters.','wait'=>'Please wait one minute before sending another story.','ok'=>'Your story reached Saradeeeb. Our team will review it.'],
            'es'=>['invalid'=>'Completa el nombre, correo y título, y escribe al menos 120 caracteres.','wait'=>'Espera un minuto antes de enviar otra historia.','ok'=>'Tu historia llegó a Saradeeeb. Nuestro equipo la revisará.'],
            'fr'=>['invalid'=>'Renseignez le nom, l’e-mail et le titre, avec au moins 120 caractères pour l’histoire.','wait'=>'Veuillez attendre une minute avant d’envoyer une autre histoire.','ok'=>'Votre histoire est arrivée chez Saradeeeb. Notre équipe va l’examiner.'],
            'ru'=>['invalid'=>'Заполните имя, почту и заголовок; текст истории должен содержать не менее 120 символов.','wait'=>'Подождите минуту перед отправкой следующей истории.','ok'=>'История получена Saradeeeb. Команда рассмотрит её.'],
        ][$locale]??['invalid'=>'Invalid submission.','wait'=>'Please wait.','ok'=>'Story received.'];
        foreach($msg as $k=>$fallback)$msg[$k]=translation_text('send_story',$k,$locale,(string)$fallback);
        if($locale==='ar') $msg['ok']='شكرًا لإرسالك قصتك يا مبدع! وصلت مشاركتك إلى فريق سراديب بنجاح، وسنراجعها بعناية قبل نشرها. إذا استوفت معايير النشر ولم تتضمن أي مشكلة، ستجدها قريبًا بين قصص الموقع.';
        if(setting('security_spam_honeypot','1')==='1' && !empty($_POST['website']??'')){ redirect(locale_url($locale,'send-story')); }
        $name=preg_replace('/\s+/u',' ',trim((string)($_POST['name']??''))) ?? '';
        $email=strtolower(trim((string)($_POST['email']??'')));
        $phone=trim((string)($_POST['phone']??''));
        $categoryId=(int)($_POST['category_id']??0);
        $submissionType=(string)($_POST['submission_type']??'experience');
        if(!in_array($submissionType,['experience','fiction','analysis'],true))$submissionType='experience';
        $originalLocale=(string)($_POST['original_locale']??$locale);
        if(!array_key_exists($originalLocale,article_locales())) $originalLocale=$locale;
        $title=trim((string)($_POST['title']??''));
        $story=trim((string)($_POST['story']??''));
        if(mb_strlen($name)<2 || mb_strlen($name)>80 || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) || mb_strlen($title)<4 || mb_strlen($story)<120 || !$categoryId || !isset($_POST['rights'])){
            flash('error',$locale==='ar'?'يرجى كتابة اسم الكاتب أو الاسم المستعار (من حرفين إلى 80 حرفًا).':($msg['invalid']??'Invalid submission.'));
            set_old(['name'=>$name,'email'=>$email,'phone'=>$phone,'title'=>$title,'story'=>$story]);
            redirect(locale_url($locale,'send-story'));
        }
        $last=(int)($_SESSION['last_story_submission_at']??0); if(setting('security_form_rate_limit','1')==='1' && $last>0 && time()-$last<60){ flash('error',$msg['wait']??'Please wait.'); redirect(locale_url($locale,'send-story')); }
        $image=null;
        try { if(!empty($_FILES['image']['name'])) $image=Upload::image($_FILES['image']); } catch(\Throwable $e){ flash('error',$e->getMessage()); redirect(locale_url($locale,'send-story')); }
        $authenticatedUser=Auth::user();
        $authorUserId=$authenticatedUser && in_array((string)($authenticatedUser['role']??''),['writer','trusted_writer'],true)?(int)$authenticatedUser['id']:null;
        $trackingToken='SRD-'.strtoupper(bin2hex(random_bytes(8))).'-'.strtoupper(bin2hex(random_bytes(8)));
        $trackingHash=hash('sha256',$trackingToken);
        Database::insert("INSERT INTO story_submissions(author_user_id,name,email,phone,title,category_id,submission_type,story,image,locale,status,terms_accepted_at,tracking_hash) VALUES(?,?,?,?,?,?,?,?,?,?, 'new',CURRENT_TIMESTAMP,?)",[
            $authorUserId,mb_substr($name,0,80),mb_substr($email,0,190),mb_substr($phone,0,60),mb_substr($title,0,220),$categoryId,$submissionType,mb_substr($story,0,100000),$image,$originalLocale,$trackingHash
        ]);
        $_SESSION['last_story_submission_at']=time(); clear_old();
        $target=trim(setting('story_submission_email',setting('contact_email','')));
        if(setting('mail_notifications','0')==='1' && filter_var($target,FILTER_VALIDATE_EMAIL)){
            send_site_mail($target,'قصة جديدة في سراديب',"وصلت قصة جديدة.\n\nالاسم: {$name}\nالبريد: {$email}\nالعنوان: {$title}\nاللغة: {$locale}\n\nراجعها من لوحة الإدارة.");
        }
        flash('success',($msg['ok']??'Story received.').' رمز المتابعة الخاص بك: '.$trackingToken.' — احتفظ به لمتابعة حالة قصتك.');
        redirect(locale_url($locale,'send-story'));
    }
    public function storyStatusForm(string $locale): void {
        $locale=$this->locale($locale);
        $labels=[
            'ar'=>['title'=>'متابعة قصتك','desc'=>'أدخل رمز المتابعة الذي ظهر لك بعد إرسال قصتك لمعرفة آخر تحديث من فريق سراديب.','placeholder'=>'مثال: SRD-XXXXXXXX-XXXXXXXX','submit'=>'عرض حالة القصة'],
            'en'=>['title'=>'Track your story','desc'=>'Enter the tracking code shown after submission to see the latest update from Saradeeeb.','placeholder'=>'Example: SRD-XXXXXXXX-XXXXXXXX','submit'=>'View story status'],
            'es'=>['title'=>'Sigue tu historia','desc'=>'Introduce el código de seguimiento que apareció después del envío para ver la última actualización.','placeholder'=>'Ejemplo: SRD-XXXXXXXX-XXXXXXXX','submit'=>'Ver estado'],
            'fr'=>['title'=>'Suivre votre histoire','desc'=>'Saisissez le code affiché après l’envoi pour consulter la dernière mise à jour.','placeholder'=>'Exemple : SRD-XXXXXXXX-XXXXXXXX','submit'=>'Voir le statut'],
            'ru'=>['title'=>'Отслеживание истории','desc'=>'Введите код отслеживания, показанный после отправки, чтобы увидеть последнее обновление.','placeholder'=>'Например: SRD-XXXXXXXX-XXXXXXXX','submit'=>'Показать статус'],
        ][$locale]??[];
        View::render('public/story-status',compact('locale','labels')+['title'=>$labels['title'],'description'=>$labels['desc'],'result'=>null],'public');
    }
    public function storyStatus(string $locale): void {
        $locale=$this->locale($locale);
        $token=strtoupper(trim((string)($_POST['tracking_code']??'')));
        $token=preg_replace('/[^A-Z0-9-]/','',$token)??'';
        $labels=['ar'=>['title'=>'متابعة قصتك','desc'=>'أدخل رمز المتابعة الذي ظهر لك بعد إرسال قصتك لمعرفة آخر تحديث من فريق سراديب.','placeholder'=>'مثال: SRD-XXXXXXXX-XXXXXXXX','submit'=>'عرض حالة القصة'],'en'=>['title'=>'Track your story','desc'=>'Enter the tracking code shown after submission to see the latest update from Saradeeeb.','placeholder'=>'Example: SRD-XXXXXXXX-XXXXXXXX','submit'=>'View story status'],'es'=>['title'=>'Sigue tu historia','desc'=>'Introduce el código de seguimiento que apareció después del envío para ver la última actualización.','placeholder'=>'Ejemplo: SRD-XXXXXXXX-XXXXXXXX','submit'=>'Ver estado'],'fr'=>['title'=>'Suivre votre histoire','desc'=>'Saisissez le code affiché après l’envoi pour consulter la dernière mise à jour.','placeholder'=>'Exemple : SRD-XXXXXXXX-XXXXXXXX','submit'=>'Voir le statut'],'ru'=>['title'=>'Отслеживание истории','desc'=>'Введите код отслеживания, показанный после отправки, чтобы увидеть последнее обновление.','placeholder'=>'Например: SRD-XXXXXXXX-XXXXXXXX','submit'=>'Показать статус']][$locale]??[];
        $last=(int)($_SESSION['story_tracking_at']??0);
        if($last>0 && time()-$last<3){ flash('error',$locale==='ar'?'انتظر لحظات قبل المحاولة مرة أخرى.':'Please wait a moment before trying again.'); redirect(locale_url($locale,'story-status')); }
        $_SESSION['story_tracking_at']=time();
        $result=null; $error='';
        if(!preg_match('/^SRD-[A-F0-9]{16}-[A-F0-9]{16}$/',$token)) $error=$locale==='ar'?'رمز المتابعة غير صحيح.':'The tracking code is invalid.';
        else {
            $result=Database::one('SELECT title,name,status,admin_note,created_at,updated_at FROM story_submissions WHERE tracking_hash=? LIMIT 1',[hash('sha256',$token)]);
            if(!$result) $error=$locale==='ar'?'لم نعثر على قصة بهذا الرمز. تأكد من كتابته كما ظهر لك.':'No story was found for this code. Check it and try again.';
        }
        View::render('public/story-status',compact('locale','labels','result','error','token')+['title'=>$labels['title'],'description'=>$labels['desc']],'public');
    }
    public function storyEditForm(string $locale,string $token): void {
        $locale=$this->locale($locale); $token=strtoupper(trim($token));
        try{$row=Database::one('SELECT id,title,name,story,status FROM story_submissions WHERE tracking_hash=? LIMIT 1',[hash('sha256',$token)]);}catch(\Throwable){$row=null;}
        if(!$row || $row['status']!=='changes_requested'){http_response_code(404); View::render('errors/404',['locale'=>$locale,'title'=>'404'],'public'); return;}
        View::render('public/story-edit',compact('locale','token','row')+['title'=>'تعديل المشاركة','description'=>'حدّث قصتك وأعد إرسالها للمراجعة.'],'public');
    }
    public function storyEdit(string $locale,string $token): void {
        $locale=$this->locale($locale); Csrf::enforce(); $token=strtoupper(trim($token));
        try{$row=Database::one('SELECT id,title,name,story,status FROM story_submissions WHERE tracking_hash=? LIMIT 1',[hash('sha256',$token)]);}catch(\Throwable){$row=null;}
        if(!$row || $row['status']!=='changes_requested'){http_response_code(404); View::render('errors/404',['locale'=>$locale,'title'=>'404'],'public'); return;}
        $title=trim((string)($_POST['title']??'')); $name=preg_replace('/\s+/u',' ',trim((string)($_POST['name']??'')))??''; $story=trim((string)($_POST['story']??''));
        if(mb_strlen($name)<2||mb_strlen($name)>80||mb_strlen($title)<4||mb_strlen($story)<120){flash('error','تحقق من الاسم والعنوان وأن يكون نص القصة 120 حرفًا على الأقل.'); redirect('/story-status/edit/'.rawurlencode($token));}
        $next=Database::one('SELECT COALESCE(MAX(revision_no),0)+1 n FROM story_submission_revisions WHERE submission_id=?',[(int)$row['id']]);
        Database::insert('INSERT INTO story_submission_revisions(submission_id,revision_no,title,name,story) VALUES(?,?,?,?,?)',[(int)$row['id'],(int)($next['n']??1),mb_substr($title,0,220),mb_substr($name,0,160),mb_substr($story,0,100000)]);
        Database::exec("UPDATE story_submissions SET title=?,name=?,story=?,status='new',admin_note=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?",[mb_substr($title,0,220),mb_substr($name,0,160),mb_substr($story,0,100000),(int)$row['id']]);
        flash('success','تم حفظ التعديل وإعادة قصتك إلى قائمة المراجعة.'); redirect(locale_url($locale,'story-status'));
    }
    public function contactForm(string $locale): void {
        $locale=$this->locale($locale);
        $labels=[
            'ar'=>['title'=>'تواصل معنا','desc'=>'أرسل رسالتك إلى فريق سراديب.'],
            'en'=>['title'=>'Contact us','desc'=>'Send your message to the Saradeeeb team.'],
            'es'=>['title'=>'Contáctanos','desc'=>'Envía tu mensaje al equipo de Saradeeeb.'],
            'fr'=>['title'=>'Contactez-nous','desc'=>'Envoyez votre message à l’équipe Saradeeeb.'],
            'ru'=>['title'=>'Связаться с нами','desc'=>'Отправьте сообщение команде Saradeeeb.'],
        ][$locale]??['title'=>'Contact','desc'=>''];
        $labels['title']=translation_text('contact','title',$locale,$labels['title']); $labels['desc']=translation_text('contact','desc',$locale,$labels['desc']);
        $labels=['ar'=>['title'=>'اتصل بنا','desc'=>'يسعدنا استقبال أسئلتك وملاحظاتك واقتراحاتك.'], 'en'=>['title'=>'Contact us','desc'=>'We welcome your questions, feedback and suggestions.'], 'es'=>['title'=>'Contáctanos','desc'=>'Nos complace recibir tus preguntas, comentarios y sugerencias.'], 'fr'=>['title'=>'Contactez-nous','desc'=>'Nous accueillons vos questions, remarques et suggestions.'], 'ru'=>['title'=>'Свяжитесь с нами','desc'=>'Мы рады получить ваши вопросы, отзывы и предложения.']][$locale]??['title'=>'Contact us','desc'=>''];
        View::render('public/contact',compact('locale','labels')+['title'=>$labels['title'],'description'=>$labels['desc']],'public');
    }
    public function submitContact(string $locale): void {
        $locale=$this->locale($locale); Csrf::enforce();
        $msg=[
          'ar'=>['invalid'=>'تحقق من الاسم والبريد واكتب رسالة واضحة.','wait'=>'انتظر قليلًا قبل إرسال رسالة أخرى.','ok'=>'تم إرسال رسالتك بنجاح.'],
          'en'=>['invalid'=>'Check your name and email and enter a clear message.','wait'=>'Please wait before sending another message.','ok'=>'Your message was sent successfully.'],
          'es'=>['invalid'=>'Comprueba tu nombre y correo y escribe un mensaje claro.','wait'=>'Espera un momento antes de enviar otro mensaje.','ok'=>'Tu mensaje se envió correctamente.'],
          'fr'=>['invalid'=>'Vérifiez votre nom et votre e-mail et saisissez un message clair.','wait'=>'Veuillez patienter avant d’envoyer un autre message.','ok'=>'Votre message a bien été envoyé.'],
          'ru'=>['invalid'=>'Проверьте имя и почту и напишите понятное сообщение.','wait'=>'Подождите немного перед отправкой следующего сообщения.','ok'=>'Сообщение успешно отправлено.'],
        ][$locale]??['invalid'=>'Invalid message.','wait'=>'Please wait.','ok'=>'Message sent.'];
        foreach($msg as $k=>$fallback)$msg[$k]=translation_text('contact',$k,$locale,(string)$fallback);
        $msg=['ar'=>['invalid'=>'تحقق من الاسم والبريد واكتب رسالة واضحة.','wait'=>'انتظر قليلًا قبل إرسال رسالة أخرى.','ok'=>'تم إرسال رسالتك بنجاح.'], 'en'=>['invalid'=>'Check your name and email and enter a clear message.','wait'=>'Please wait before sending another message.','ok'=>'Your message was sent successfully.'], 'es'=>['invalid'=>'Comprueba tu nombre y correo y escribe un mensaje claro.','wait'=>'Espera un momento antes de enviar otro mensaje.','ok'=>'Tu mensaje se envió correctamente.'], 'fr'=>['invalid'=>'Vérifiez votre nom et votre e-mail et saisissez un message clair.','wait'=>'Veuillez patienter avant d’envoyer un autre message.','ok'=>'Votre message a bien été envoyé.'], 'ru'=>['invalid'=>'Проверьте имя и электронную почту и напишите понятное сообщение.','wait'=>'Пожалуйста, подождите перед отправкой нового сообщения.','ok'=>'Ваше сообщение успешно отправлено.']][$locale]??['invalid'=>'Invalid message.','wait'=>'Please wait.','ok'=>'Message sent.'];
        if(setting('security_spam_honeypot','1')==='1' && !empty($_POST['website']??'')) redirect(locale_url($locale,'contact'));
        $name=trim((string)($_POST['name']??'')); $email=strtolower(trim((string)($_POST['email']??'')));
        $subject=trim((string)($_POST['subject']??'')); $message=trim((string)($_POST['message']??'')); $category=trim((string)($_POST['category']??'')); $reportDetails=trim((string)($_POST['report_details']??''));
        if($category==='copyright') $reportDetails=mb_substr($reportDetails,0,20000);
        if(mb_strlen($name)<2 || !filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($message)<10){
            flash('error',$msg['invalid']??'Invalid message.'); set_old(compact('name','email','subject','message')); redirect(locale_url($locale,'contact'));
        }
        $last=(int)($_SESSION['last_contact_at']??0); if(setting('security_form_rate_limit','1')==='1' && $last>0 && time()-$last<45){ flash('error',$msg['wait']??'Please wait.'); redirect(locale_url($locale,'contact')); }
        $caseReference=$category==='copyright'?'CR-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4))):null;
        Database::insert("INSERT INTO contact_messages(name,email,subject,message,report_details,category,locale,status,case_reference) VALUES(?,?,?,?,?,?,?,'new',?)",[mb_substr($name,0,160),mb_substr($email,0,190),mb_substr($subject,0,220),mb_substr($message,0,20000),$reportDetails,$category,$locale,$caseReference]);
        $_SESSION['last_contact_at']=time(); clear_old();
        $target=trim(setting('contact_form_email',setting('contact_email','')));
        if(setting('mail_notifications','0')==='1' && filter_var($target,FILTER_VALIDATE_EMAIL)) send_site_mail($target,'رسالة جديدة من سراديب',"الاسم: {$name}\\nالبريد: {$email}\\nالموضوع: {$subject}\\nاللغة: {$locale}\\n\\n{$message}");
        flash('success',$msg['ok']??'Message sent.'); redirect(locale_url($locale,'contact'));
    }

    public function newsletterPage(string $locale): void {
        $locale=$this->locale($locale);
        View::render('public/newsletter',compact('locale'),'public');
    }
    public function subscribeNewsletter(string $locale): void {
        $locale=$this->locale($locale); Csrf::enforce();
        if(!\App\Services\ManagedPages::forKey('newsletter',$locale)){http_response_code(404);View::render('errors/404',compact('locale')+['title'=>'404'],'public');return;}
        $nmsg=[
          'ar'=>['invalid'=>'أدخل بريدًا صحيحًا ووافق على الاشتراك.','ok'=>'تم اشتراك بريدك بنجاح.','fail'=>'تعذر حفظ الاشتراك الآن. حاول مجددًا.'],
          'en'=>['invalid'=>'Enter a valid email and accept the subscription consent.','ok'=>'Your email was subscribed successfully.','fail'=>'We could not save your subscription now. Please try again.'],
          'es'=>['invalid'=>'Introduce un correo válido y acepta el consentimiento de suscripción.','ok'=>'Tu correo se ha suscrito correctamente.','fail'=>'No pudimos guardar la suscripción. Inténtalo de nuevo.'],
          'fr'=>['invalid'=>'Saisissez un e-mail valide et acceptez le consentement d’abonnement.','ok'=>'Votre adresse e-mail a bien été abonnée.','fail'=>'Impossible d’enregistrer l’abonnement pour le moment. Réessayez.'],
        ][$locale]??['invalid'=>'Invalid subscription.','ok'=>'Subscribed.','fail'=>'Could not subscribe.'];
        foreach($nmsg as $k=>$fallback)$nmsg[$k]=translation_text('newsletter',$k,$locale,(string)$fallback);
        $email=strtolower(trim((string)($_POST['email']??'')));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || !isset($_POST['consent'])){ flash('error',$nmsg['invalid']??'Invalid subscription.'); $this->returnBack($locale); }
        try {
            Database::exec("INSERT INTO newsletter_subscribers(email,locale,status,source,consented_at) VALUES(?,?,'active','footer',CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE locale=VALUES(locale),status='active',source='footer',consented_at=CURRENT_TIMESTAMP",[$email,$locale]);
            flash('success',$nmsg['ok']??'Subscribed.');
        } catch(\Throwable) { flash('error',$nmsg['fail']??'Could not subscribe.'); }
        $this->returnBack($locale);
    }
    private function returnBack(string $locale): never {
        $to=(string)($_POST['return_to']??'');
        if($to==='' || !str_starts_with($to,'/') || str_starts_with($to,'//')) $to=parse_url(locale_url($locale),PHP_URL_PATH)?:'/';
        redirect($to);
    }
    public function sitemap(): void {
        header('Content-Type: application/xml; charset=utf-8');
        $rows=Database::all("SELECT t.article_id,t.locale,t.slug,a.updated_at FROM article_translations t JOIN articles a ON a.id=t.article_id WHERE a.status='published' AND ".article_translation_publishable_sql('t'));
        $pages=Database::all("SELECT pt.page_id,pt.locale,pt.slug,p.updated_at FROM page_translations pt JOIN pages p ON p.id=pt.page_id WHERE ".page_translation_publishable_sql('pt'));
        $categories=Database::all("SELECT ct.locale,ct.slug FROM category_translations ct JOIN categories c ON c.id=ct.category_id WHERE c.active=1 AND ct.status='published' AND TRIM(ct.slug)<>''");
        $podcasts=[]; try{$podcasts=Database::all("SELECT pt.locale,pt.slug,p.updated_at FROM podcast_episode_translations pt JOIN podcast_episodes p ON p.id=pt.episode_id WHERE p.status='published' AND pt.status='published' AND (p.published_at IS NULL OR p.published_at<=CURRENT_TIMESTAMP)");}catch(\Throwable){}
        $khawatir=[]; try{$khawatir=Database::all("SELECT kt.locale,kt.slug,k.updated_at FROM khawatir_translations kt JOIN khawatir k ON k.id=kt.khawatir_id WHERE k.status='published' AND kt.status='published' AND k.published_at<=CURRENT_TIMESTAMP");}catch(\Throwable){}
        $communityTopics=[]; $communityCategories=[]; if(setting('community_enabled','1')==='1'){ try{$communityTopics=Database::all("SELECT locale,slug,updated_at FROM community_topics WHERE status IN ('open','closed')");$communityCategories=Database::all("SELECT ct.locale,ct.slug FROM community_category_translations ct JOIN community_categories c ON c.id=ct.category_id WHERE c.active=1");}catch(\Throwable){} }
        $publicBooks=\App\Services\Books::publicSitemap();
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">";
        foreach(enabled_locales() as $code=>$info) echo '<url><loc>'.e(locale_url($code)).'</loc></url>';
        if($publicBooks){echo '<url><loc>'.e(url('/books')).'</loc></url>';foreach($publicBooks as $book)echo '<url><loc>'.e(url('/books/'.$book['slug'])).'</loc><lastmod>'.e(date('c',strtotime($book['updated_at']))).'</lastmod></url>';}
        $enabled=array_keys(enabled_locales()); $articleEnabled=array_keys(article_locales());
        // Keep alternate-language annotations tied to the same canonical content record.
        // This prevents search engines from treating translated pages as unrelated duplicates.
        $articleGroups=[];
        foreach($rows as $r) if(in_array($r['locale'],$articleEnabled,true)) $articleGroups[(int)$r['article_id']][]=$r;
        foreach($articleGroups as $group) foreach($group as $r){
            echo '<url><loc>'.e(locale_url($r['locale'],'article/'.$r['slug'])).'</loc><lastmod>'.e(date('c',strtotime($r['updated_at']))).'</lastmod>';
            foreach($group as $alternate) echo '<xhtml:link rel="alternate" hreflang="'.e($alternate['locale']).'" href="'.e(locale_url($alternate['locale'],'article/'.$alternate['slug'])).'"/>';
            $defaultAlternate=$group[0];
            foreach($group as $alternate) if($alternate['locale']==='ar') {$defaultAlternate=$alternate; break;}
            echo '<xhtml:link rel="alternate" hreflang="x-default" href="'.e(locale_url($defaultAlternate['locale'],'article/'.$defaultAlternate['slug'])).'"/>';
            echo '</url>';
        }
        foreach($categories as $r) if(in_array($r['locale'],$enabled,true)) echo '<url><loc>'.e(locale_url($r['locale'],'category/'.$r['slug'])).'</loc></url>';
        $pageGroups=[];
        foreach($pages as $r) if(in_array($r['locale'],$articleEnabled,true)) $pageGroups[(int)$r['page_id']][]=$r;
        foreach($pageGroups as $group) foreach($group as $r){
            echo '<url><loc>'.e(\App\Services\ManagedPages::url((int)$r['page_id'],$r['locale'],$r['slug'])).'</loc><lastmod>'.e(date('c',strtotime($r['updated_at']))).'</lastmod>';
            foreach($group as $alternate) echo '<xhtml:link rel="alternate" hreflang="'.e($alternate['locale']).'" href="'.e(\App\Services\ManagedPages::url((int)$alternate['page_id'],$alternate['locale'],$alternate['slug'])).'"/>';
            $defaultAlternate=$group[0];
            foreach($group as $alternate) if($alternate['locale']==='ar') {$defaultAlternate=$alternate; break;}
            echo '<xhtml:link rel="alternate" hreflang="x-default" href="'.e(\App\Services\ManagedPages::url((int)$defaultAlternate['page_id'],$defaultAlternate['locale'],$defaultAlternate['slug'])).'"/>';
            echo '</url>';
        }
        // Podcast and reflections are currently Arabic-only public sections.
        // Their localized controller routes intentionally redirect to Arabic,
        // so only emit canonical URLs that respond with content in the sitemap.
        echo '<url><loc>'.e(locale_url('ar','podcast')).'</loc></url>';
        foreach($podcasts as $r) if($r['locale']==='ar') echo '<url><loc>'.e(locale_url('ar','podcast/'.$r['slug'])).'</loc><lastmod>'.e(date('c',strtotime($r['updated_at']))).'</lastmod></url>';
        echo '<url><loc>'.e(locale_url('ar','khawatir')).'</loc></url>';
        foreach($khawatir as $r) if($r['locale']==='ar') echo '<url><loc>'.e(locale_url('ar','khawatir/'.$r['slug'])).'</loc><lastmod>'.e(date('c',strtotime($r['updated_at']))).'</lastmod></url>';
        if(setting('community_enabled','1')==='1'){
            // Community roots are included through their managed page translations above.
            foreach($communityCategories as $r) if(in_array($r['locale'],$enabled,true)) echo '<url><loc>'.e(locale_url($r['locale'],'community/'.$r['slug'])).'</loc></url>';
            foreach($communityTopics as $r) if(in_array($r['locale'],$enabled,true)) echo '<url><loc>'.e(locale_url($r['locale'],'community/topic/'.$r['slug'])).'</loc><lastmod>'.e(date('c',strtotime($r['updated_at']))).'</lastmod></url>';
        }
        echo '</urlset>';
    }
    public function robots(): void {
        header('Content-Type: text/plain; charset=utf-8');
        $base="User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /writer\nDisallow: /login\nDisallow: /register\nDisallow: /forgot-password\nDisallow: /search\nDisallow: /*/search\nDisallow: /community/notifications\nDisallow: /community/saved\nDisallow: /community/preferences\nDisallow: /community/account\nDisallow: /*/community/notifications\nDisallow: /*/community/saved\nDisallow: /*/community/preferences\nDisallow: /*/community/account\n";
        $policy=setting('ai_crawler_policy','search-only');
        $searchBots=['OAI-SearchBot','ChatGPT-User','Claude-SearchBot','Claude-User','PerplexityBot','Googlebot','Bingbot','Applebot'];
        foreach($searchBots as $bot) $base.="\nUser-agent: {$bot}\nAllow: /\n";
        if($policy!=='allow-all'){
            foreach(['GPTBot','ClaudeBot','Google-Extended','Applebot-Extended','CCBot'] as $bot) $base.="\nUser-agent: {$bot}\nDisallow: /\n";
        }
        echo $base."\nSitemap: ".url('/sitemap.xml')."\n";
    }
    public function llms(): void {
        header('Content-Type: text/plain; charset=utf-8');
        $name=setting('site_name',config('name','Saradeeeb')); $description=trim(setting('site_description','Stories, mysteries and cultural articles from Saradeeeb.'));
        $lines=["# {$name}",'',"> {$description}",'','## Primary content','- Articles: '.url('/sitemap.xml'),'- RSS feed: '.url('/feed.xml'),'- About: '.locale_url(default_locale(),'page/من-خلف-سراديب'),'- Contact: '.locale_url(default_locale(),'contact'),'','## Use policy','Content is publicly readable where allowed by robots.txt. Cite the canonical page URL and preserve the author attribution.'];
        echo implode("\n",$lines)."\n";
    }
    public function llmsFull(): void {
        header('Content-Type: text/plain; charset=utf-8');
        $locale=default_locale(); $rows=Database::all("SELECT t.title,t.slug,t.excerpt,a.updated_at FROM article_translations t JOIN articles a ON a.id=t.article_id WHERE t.locale=? AND a.status='published' AND ".article_translation_publishable_sql('t')." ORDER BY a.published_at DESC,a.id DESC LIMIT 200",[$locale]);
        echo '# '.setting('site_name',config('name','Saradeeeb'))."\n\n## Articles\n";
        foreach($rows as $row) echo '- ['.str_replace(["\r","\n"],' ',(string)$row['title']).']('.locale_url($locale,'article/'.$row['slug']).') — '.str_replace(["\r","\n"],' ',mb_substr(strip_tags((string)$row['excerpt']),0,220))."\n";
    }
    public function feed(): void {
        header('Content-Type: application/rss+xml; charset=utf-8');
        $locale=default_locale(); $rows=Database::all("SELECT a.published_at,a.updated_at,t.title,t.slug,t.excerpt,u.name author_name FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND ".article_translation_publishable_sql('t')." JOIN users u ON u.id=a.author_id WHERE a.status='published' ORDER BY a.published_at DESC,a.id DESC LIMIT 30",[$locale]);
        $name=setting('site_name',config('name','Saradeeeb')); echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>'.e($name).'</title><link>'.e(locale_url($locale)).'</link><description>'.e(setting('site_description',$name)).'</description>';
        foreach($rows as $row){$link=locale_url($locale,'article/'.$row['slug']);echo '<item><title>'.e($row['title']).'</title><link>'.e($link).'</link><guid isPermaLink="true">'.e($link).'</guid><description>'.e($row['excerpt']).'</description><author>'.e($row['author_name']).'</author><pubDate>'.e(date(DATE_RSS,strtotime((string)$row['published_at']))).'</pubDate></item>';}
        echo '</channel></rss>';
    }
}
