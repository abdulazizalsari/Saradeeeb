<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Upload;
use App\Core\View;
use App\Services\TranslationCompletenessValidator;

final class WriterController {
    private function uniqueArticleSlug(string $slug,string $locale,int $articleId=0): string {
        $base=slugify($slug); $candidate=$base; $n=2;
        while(true){
            $row=Database::one('SELECT article_id FROM article_translations WHERE locale=? AND slug=? LIMIT 1',[$locale,$candidate]);
            if(!$row || (int)$row['article_id']===$articleId) return $candidate;
            $candidate=$base.'-'.$n++;
            if($n>1000) return $base.'-'.bin2hex(random_bytes(3));
        }
    }
    private function guard(): array {
        Auth::requireRole(['admin','editor','writer','trusted_writer']);
        $u = Auth::user();
        if (($u['status'] ?? '') !== 'active') {
            http_response_code(403);
            exit('الحساب غير مفعل.');
        }
        return $u;
    }

    public function dashboard(): void {
        $u = $this->guard();
        $bookRequest=null;
        $bookOfferId=0;
        if(in_array((string)($u['role']??''),['writer','trusted_writer'],true) && !empty($u['writer_verified'])) {
            try {$bookRequest=Database::one('SELECT id,status,created_at FROM book_service_requests WHERE writer_id=? AND active_slot=1',[(int)$u['id']]);} catch (\Throwable) {}
            if($bookRequest)try{$bookOfferId=(int)(Database::one("SELECT id FROM book_agreements WHERE request_id=? AND status='offered' ORDER BY version DESC LIMIT 1",[(int)$bookRequest['id']])['id']??0);}catch(\Throwable){}
        }
        $submissionStats=['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0];
        try {$submissionStats=Database::one("SELECT COUNT(*) total,SUM(status IN ('new','reviewed')) pending,SUM(status='accepted') approved,SUM(status='rejected') rejected FROM story_submissions WHERE author_user_id=?",[(int)$u['id']])?:$submissionStats; foreach($submissionStats as $k=>$v)$submissionStats[$k]=(int)$v;} catch (\Throwable) {}
        $default='ar';
        $articles = Database::all(
            "SELECT a.*, (SELECT title FROM article_translations t WHERE t.article_id=a.id AND t.locale=? LIMIT 1) title
             FROM articles a WHERE author_id=? ORDER BY updated_at DESC",
            [$default,$u['id']]
        );
        $notifications = [];
        try {
            $notifications = Database::all(
                "SELECT * FROM notifications WHERE user_id=? ORDER BY is_read ASC, created_at DESC LIMIT 20",
                [$u['id']]
            );
        } catch (\Throwable) {}
        $profile=[]; $articleStats=['total'=>0,'drafts'=>0,'review'=>0,'changes'=>0,'published'=>0,'views'=>0];
        $topArticles=[]; $monthlyActivity=[]; $commentCount=0;
        try {$profile=Database::one('SELECT * FROM writer_profiles WHERE user_id=?',[(int)$u['id']])?:[];} catch (\Throwable) {}
        try {
            $articleStats=Database::one("SELECT COUNT(*) total,SUM(status='draft') drafts,SUM(status='review') review,SUM(status='changes') changes,SUM(status='published') published,COALESCE(SUM(views),0) views FROM articles WHERE author_id=?",[(int)$u['id']])?:$articleStats;
            foreach($articleStats as $key=>$value)$articleStats[$key]=(int)$value;
            $topArticles=Database::all("SELECT a.id,a.views,a.published_at,(SELECT title FROM article_translations t WHERE t.article_id=a.id AND t.locale=? LIMIT 1) title FROM articles a WHERE a.author_id=? AND a.status='published' ORDER BY a.views DESC,a.published_at DESC LIMIT 5",[$default,(int)$u['id']]);
            $monthlyActivity=Database::all("SELECT DATE_FORMAT(COALESCE(published_at,created_at),'%Y-%m') month_key,COUNT(*) total FROM articles WHERE author_id=? AND created_at>=DATE_SUB(CURRENT_DATE,INTERVAL 11 MONTH) GROUP BY month_key ORDER BY month_key",[(int)$u['id']]);
            $commentCount=(int)(Database::one("SELECT COUNT(*) total FROM article_comments c JOIN articles a ON a.id=c.article_id WHERE a.author_id=? AND c.status='approved'",[(int)$u['id']])['total']??0);
        } catch (\Throwable) {}
        $unreadNotifications=0; foreach($notifications as $notification) if(empty($notification['is_read']))$unreadNotifications++;
        View::render('writer/book-dashboard', compact('u','articles','notifications','submissionStats','bookRequest','bookOfferId','profile','articleStats','topArticles','monthlyActivity','commentCount','unreadNotifications') + ['title'=>!empty($u['writer_vip'])?'استديو الكاتب':'لوحة الكاتب'], 'dashboard');
    }

    public function storySubmissions(): void {
        $u=$this->guard();
        $rows=Database::all("SELECT s.*,ct.name category_name FROM story_submissions s LEFT JOIN category_translations ct ON ct.category_id=s.category_id AND ct.locale=s.locale WHERE s.author_user_id=? ORDER BY s.created_at DESC,s.id DESC",[(int)$u['id']]);
        View::render('writer/story-submissions',compact('u','rows')+['title'=>'قصصي المرسلة'],'dashboard');
    }

    public function edit(?string $id=null): void {
        $u = $this->guard();
        $article = null;
        $translations = [];

        if ($id) {
            $article = Database::one('SELECT * FROM articles WHERE id=?', [(int)$id]);
            if (!$article || (!is_editor() && (int)$article['author_id'] !== (int)$u['id'])) {
                flash('error', $article ? 'لا تملك صلاحية تحرير هذا المحتوى.' : 'المقال غير موجود.');
                redirect(is_editor() ? '/admin/articles' : '/writer');
            }
            $translations = Database::all('SELECT * FROM article_translations WHERE article_id=?', [(int)$id]);
        }

        $profile=Database::one('SELECT primary_writing_language FROM writer_profiles WHERE user_id=?',[(int)$u['id']])?:[];
        $writingLocale=(string)($article['original_locale']??$profile['primary_writing_language']??'ar');
        if(!isset(article_locales()[$writingLocale])) $writingLocale='ar';
        $byLocale = [];
        foreach ($translations as $t) $byLocale[$t['locale']] = $t;

        $categories = Database::all(
            "SELECT c.id,c.sort_order,ct.locale,ct.name,ct.slug
             FROM categories c
             LEFT JOIN category_translations ct ON ct.category_id=c.id
             ORDER BY c.sort_order,c.id,ct.locale"
        );
        $categoryMatrix = [];
        foreach ($categories as $row) {
            $cid = (int)$row['id'];
            $categoryMatrix[$cid] ??= ['id'=>$cid,'sort_order'=>(int)$row['sort_order'],'translations'=>[]];
            if (!empty($row['locale'])) {
                $categoryMatrix[$cid]['translations'][$row['locale']] = [
                    'name'=>$row['name'],
                    'slug'=>$row['slug'],
                ];
            }
        }
        uasort($categoryMatrix, static fn($a,$b) => $a['sort_order'] <=> $b['sort_order']);

        $mediaLibrary=[]; if(is_editor()){ try{$mediaLibrary=Database::all('SELECT id,path,title,alt_text,original_name,width,height FROM media ORDER BY id DESC LIMIT 120');}catch(\Throwable){} }
        View::render(
            'writer/editor',
            ['writingLocale'=>$writingLocale] +
            compact('u','article','byLocale','categoryMatrix','mediaLibrary') + ['title'=>$article?'تحرير المقال':'مقال جديد'],
            'dashboard'
        );
    }


    public function preview(string $id, string $locale): void {
        $u = $this->guard();
        if (!valid_locale($locale)) { http_response_code(404); exit('Language not found'); }
        $article = Database::one(
            "SELECT a.*,t.title,t.slug,t.excerpt,t.content,t.seo_title,t.seo_description,t.seo_keywords,t.hashtags,t.og_image,
                    u.name author_name,u.username,u.bio author_bio,u.avatar author_avatar,
                    c.id category_id,ct.name category_name,ct.slug category_slug
             FROM articles a
             JOIN article_translations t ON t.article_id=a.id AND t.locale=?
             JOIN users u ON u.id=a.author_id
             LEFT JOIN categories c ON c.id=a.category_id
             LEFT JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=?
             WHERE a.id=? LIMIT 1",
            [$locale,$locale,(int)$id]
        );
        if (!$article || (!is_editor() && (int)$article['author_id'] !== (int)$u['id'])) {
            flash('error', $article ? 'لا تملك صلاحية معاينة هذا المحتوى.' : 'المقال غير موجود.');
            redirect(is_editor() ? '/admin/articles' : '/writer');
        }
        $translations = [['locale'=>$locale,'title'=>$article['title'],'slug'=>$article['slug']]];
        $related = [];
        $title = '[معاينة] '.$article['title'];
        $description = $article['excerpt'];
        View::render('public/article', compact('locale','article','translations','related','title','description'), 'public');
    }

    public function save(): void { $this->persist(false); }
    public function submit(): void { $this->persist(true); }

    public function validateTranslation(string $id,string $locale): void {
        $u=$this->guard(); Csrf::enforce();
        if(!is_editor()){http_response_code(403);exit('إعادة اعتماد الترجمات متاحة للإدارة فقط.');}
        $article=Database::one('SELECT id FROM articles WHERE id=?',[(int)$id]);
        if(!$article||$locale==='ar'||!isset(article_locales()[$locale])){flash('error','المقال أو لغة الترجمة غير صالح.');redirect('/writer/articles/'.(int)$id);}
        try{$report=(new TranslationCompletenessValidator())->validateArticle((int)$id,$locale,(int)$u['id'],true);flash($report['blocking']?'error':'success',$report['blocking']?'لم تجتز الترجمة الفحص. راجع التقرير التفصيلي.':'اجتازت الترجمة فحص الاكتمال الخادمي.');}
        catch(\Throwable $error){flash('error',$error->getMessage());}
        redirect('/writer/articles/'.(int)$id.'#translation-report-'.$locale);
    }

    public function resolveTranslationIssue(string $id,string $locale): void {
        $u=$this->guard(); Csrf::enforce();
        if(!is_editor()){http_response_code(403);exit('توثيق قرارات الترجمة متاح للإدارة فقط.');}
        try{$validator=new TranslationCompletenessValidator();$issueKey=(string)($_POST['issue_key']??'');$reasons=is_array($_POST['resolution_reason']??null)?$_POST['resolution_reason']:[];$validator->resolve((int)$id,$locale,$issueKey,(string)($reasons[$issueKey]??''),(int)$u['id']);$report=$validator->validateArticle((int)$id,$locale,(int)$u['id'],true);flash($report['blocking']?'error':'success',$report['blocking']?'تم توثيق القرار وبقيت نتائج أخرى تتطلب المعالجة.':'تم توثيق القرار واجتازت الترجمة الفحص.');}
        catch(\Throwable $error){flash('error',$error->getMessage());}
        redirect('/writer/articles/'.(int)$id.'#translation-report-'.$locale);
    }

    private function normalizeHashtags(string $value): string {
        $value = str_replace(["\r","\n",'طŒ',';'], ',', $value);
        $parts = preg_split('/[\s,]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            $part = ltrim($part, '#');
            $part = preg_replace('/[^\p{L}\p{N}_\-]+/u', '', $part) ?? '';
            if ($part === '') continue;
            $tag = '#'.$part;
            if (!in_array($tag, $out, true)) $out[] = $tag;
            if (count($out) >= 20) break;
        }
        return implode(' ', $out);
    }

    private function persist(bool $submit): void {
        $u = $this->guard();
        if(!is_editor() && Auth::writerPrivilegesSuspended($u)){ flash('error','صلاحيات الكاتب معلقة مؤقتًا حتى '.(string)$u['writer_suspended_until'].'.'); redirect('/writer'); }
        Csrf::enforce();
        $id = (int)($_POST['article_id'] ?? 0);
        $article = null;

        if ($id) {
            $article = Database::one('SELECT * FROM articles WHERE id=?', [$id]);
            if (!$article || (!is_editor() && (int)$article['author_id'] !== (int)$u['id'])) {
                flash('error', $article ? 'لا تملك صلاحية تعديل هذا المحتوى.' : 'المقال غير موجود.');
                redirect(is_editor() ? '/admin/articles' : '/writer');
            }
        }

        $profile=Database::one('SELECT primary_writing_language FROM writer_profiles WHERE user_id=?',[(int)$u['id']])?:[];
        $originalLocale=(string)($article['original_locale']??$profile['primary_writing_language']??'ar');
        if(!isset(article_locales()[$originalLocale])) $originalLocale='ar';
        $selectedLocale=trim((string)($_POST['translation_locale']??''));
        if($selectedLocale!=='' && !isset(article_locales()[$selectedLocale])) { http_response_code(422); exit('لغة ترجمة غير صالحة.'); }
        $translationOnly=$article && ($selectedLocale!=='' && $selectedLocale!==$originalLocale || (!array_key_exists($originalLocale,$_POST['title']??[]) && !array_key_exists($originalLocale,$_POST['content']??[])));
        $source=$id?Database::one("SELECT * FROM article_translations WHERE article_id=? AND locale=?",[$id,$originalLocale]):null;
        $sourceTitle=$translationOnly?(string)($source['title']??''):trim((string)($_POST['title'][$originalLocale]??$source['title']??''));
        $sourceContent=$translationOnly?(string)($source['content']??''):trim((string)($_POST['content'][$originalLocale]??$source['content']??''));
        if($sourceTitle==='' || trim(strip_tags(sanitize_html($sourceContent)))==='') {
            http_response_code(422); exit('يجب حفظ عنوان ومحتوى المقالة العربية الأم قبل إنشاء المقالة أو إضافة ترجماتها.');
        }

        // One category belongs to the article itself. Every translation inherits the same category ID.
        $category = (int)($_POST['category_id'] ?? 0) ?: null;
        $featured = null;
        try {
            $featured = Upload::image($_FILES['featured_image'] ?? []);
            // Editors can reuse a file already stored in the managed media library instead of re-uploading it.
            if (!$featured) {
                $existingMediaPath = trim((string)($_POST['featured_image_existing'] ?? ''));
                if ($existingMediaPath !== '') {
                    $media = Database::one('SELECT path FROM media WHERE path=? LIMIT 1', [$existingMediaPath]);
                    if ($media) $featured = (string)$media['path'];
                }
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect($id ? '/writer/articles/'.$id : '/writer/articles/new');
        }

        if ($translationOnly) {
            $status=(string)$article['status'];
        } elseif ($submit) {
            $status = is_editor() ? 'published' : 'review';
        } else {
            $status = (is_editor() && $article) ? (string)$article['status'] : 'draft';
        }
        $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;

        // A translation has a publication lifecycle of its own.  Keep any
        // incomplete translation as a draft, but report that fact to the
        // editor rather than returning a misleading "saved/published" result.
        $translationIssues = []; $touchedLocales=[]; $publishLocales=[];
        Database::transaction(function() use (&$id,$u,$category,$featured,$status,$publishedAt,$article,$translationOnly,$selectedLocale,$submit,$originalLocale,&$translationIssues,&$touchedLocales,&$publishLocales) {
            if (!$id) {
                $id = Database::insert(
                    'INSERT INTO articles(author_id,original_locale,category_id,status,is_featured,featured_image,published_at) VALUES(?,?,?,?,?,?,?)',
                    [$u['id'],$originalLocale,$category,$status,0,$featured,$publishedAt]
                );
            } elseif (!$translationOnly) {
                Database::exec(
                    'UPDATE articles SET category_id=?,status=?,featured_image=COALESCE(?,featured_image),published_at=CASE WHEN ?="published" THEN COALESCE(published_at,?) ELSE published_at END,updated_at=CURRENT_TIMESTAMP WHERE id=?',
                    [$category,$status,$featured,$status,$publishedAt,$id]
                );
            }

            foreach (article_locales() as $locale=>$info) {
                if(!is_editor() && $locale!==$originalLocale) continue;
                if($selectedLocale!=='' && $selectedLocale!==$locale) continue;
                if($translationOnly && $locale===$originalLocale) continue;
                if(!array_key_exists($locale,$_POST['title']??[]) && !array_key_exists($locale,$_POST['content']??[])) continue;
                $title = trim((string)($_POST['title'][$locale] ?? ''));
                $content = trim((string)($_POST['content'][$locale] ?? ''));
                $existing = Database::one('SELECT * FROM article_translations WHERE article_id=? AND locale=?', [$id,$locale]);
                if ($title === '' && $content === '') {
                    // Empty editor fields never delete a saved translation implicitly.
                    continue;
                }

                $slug = $this->uniqueArticleSlug((string)($_POST['slug'][$locale] ?? $title),$locale,$id);
                $excerpt = trim((string)($_POST['excerpt'][$locale] ?? ''));
                if ($excerpt === '') $excerpt = mb_substr(trim(strip_tags($content)), 0, 240);
                $seoTitle = trim((string)($_POST['seo_title'][$locale] ?? ''));
                $seoDesc = trim((string)($_POST['seo_description'][$locale] ?? ''));
                $seoKeywords = trim((string)($_POST['seo_keywords'][$locale] ?? ''));
                $hashtags = $this->normalizeHashtags((string)($_POST['hashtags'][$locale] ?? ''));
                $imageAlt = mb_substr(trim((string)($_POST['image_alt'][$locale] ?? '')),0,255);
                $imageCaption = mb_substr(trim((string)($_POST['image_caption'][$locale] ?? '')),0,500);
                $ogTitle = mb_substr(trim((string)($_POST['og_title'][$locale] ?? $seoTitle)),0,255);
                $ogDescription = mb_substr(trim((string)($_POST['og_description'][$locale] ?? $seoDesc)),0,320);
                $clean = sanitize_html($content);

                $requestedStatus=(string)($_POST['translation_status'][$locale]??'');
                $translationStatus=is_editor() && $status==='published' && in_array($requestedStatus,['draft','published'],true)?$requestedStatus:($locale==='ar' && $status==='published' && is_editor()?'published':(string)($existing['status']??'draft'));
                $wantsValidatedPublication=$locale!=='ar' && is_editor() && $status==='published' && $requestedStatus==='published';
                if($wantsValidatedPublication){$translationStatus='draft';$publishLocales[]=$locale;}
                if($locale==='ar' && $submit && !$translationOnly && is_editor()) $translationStatus='published';
                if(!is_editor()) $translationStatus='draft';
                $reviewState=is_editor()?((string)($_POST['review_state'][$locale]??($translationStatus==='published'?'approved':'draft'))):($submit?'review':'draft');
                if(!in_array($reviewState,['draft','review','approved'],true)) $reviewState='draft';
                // A translation is never marked public until its required public
                // fields exist.  The list/detail queries enforce the same rule.
                $completeForPublishing=$title!=='' && $slug!=='' && trim(strip_tags($clean))!=='';
                if($translationStatus==='published' && !$completeForPublishing){
                    $translationStatus='draft';
                    $reviewState='draft';
                    $translationIssues[] = strtoupper($locale);
                } elseif($translationStatus==='published') {
                    $reviewState='approved';
                }

                if ($existing) {
                    $before = $existing;
                    $changed =
                        (string)($before['title'] ?? '') !== $title ||
                        (string)($before['slug'] ?? '') !== $slug ||
                        (string)($before['excerpt'] ?? '') !== $excerpt ||
                        (string)($before['content'] ?? '') !== $clean ||
                        (string)($before['seo_title'] ?? '') !== $seoTitle ||
                        (string)($before['seo_description'] ?? '') !== $seoDesc ||
                        (string)($before['seo_keywords'] ?? '') !== $seoKeywords ||
                        (string)($before['hashtags'] ?? '') !== $hashtags ||
                        (string)($before['image_alt'] ?? '') !== $imageAlt ||
                        (string)($before['image_caption'] ?? '') !== $imageCaption ||
                        (string)($before['og_title'] ?? '') !== $ogTitle ||
                        (string)($before['og_description'] ?? '') !== $ogDescription ||
                        (string)($before['status'] ?? '') !== $translationStatus ||
                        (string)($before['review_state'] ?? '') !== $reviewState;
                    if ($changed) {
                        $touchedLocales[]=$locale;
                        if ((string)($before['slug'] ?? '') !== $slug && (string)($before['slug'] ?? '') !== '') {
                            $oldPath=parse_url(locale_url($locale,'article/'.(string)$before['slug']),PHP_URL_PATH)?:'/';
                            $newPath=parse_url(locale_url($locale,'article/'.$slug),PHP_URL_PATH)?:'/';
                            try { Database::exec('INSERT INTO redirects(source_path,target_url,http_code) VALUES(?,?,301) ON DUPLICATE KEY UPDATE target_url=VALUES(target_url),http_code=301',[$oldPath,$newPath]); } catch (\Throwable) {}
                        }
                        if (($before['title'] ?? '') !== $title || ($before['content'] ?? '') !== $clean) {
                            Database::insert('INSERT INTO article_revisions(article_id,user_id,locale,title,excerpt,content,seo_title,seo_description,seo_keywords,hashtags,featured_image,category_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$u['id'],$locale,$before['title'] ?? '',$before['excerpt'] ?? '',$before['content'] ?? '',$before['seo_title'] ?? '',$before['seo_description'] ?? '',$before['seo_keywords'] ?? '',$before['hashtags'] ?? '',$article['featured_image'] ?? '',$article['category_id'] ?? null]);
                        }
                        Database::exec(
                            'UPDATE article_translations SET title=?,slug=?,excerpt=?,content=?,seo_title=?,seo_description=?,seo_keywords=?,hashtags=?,image_alt=?,image_caption=?,og_title=?,og_description=?,status=?,review_state=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',
                            [$title,$slug,$excerpt,$clean,$seoTitle,$seoDesc,$seoKeywords,$hashtags,$imageAlt,$imageCaption,$ogTitle,$ogDescription,$translationStatus,$reviewState,$existing['id']]
                        );
                    }
                } else {
                    $touchedLocales[]=$locale;
                    Database::insert(
                        'INSERT INTO article_translations(article_id,locale,title,slug,excerpt,content,seo_title,seo_description,seo_keywords,hashtags,image_alt,image_caption,og_title,og_description,status,review_state) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        [$id,$locale,$title,$slug,$excerpt,$clean,$seoTitle,$seoDesc,$seoKeywords,$hashtags,$imageAlt,$imageCaption,$ogTitle,$ogDescription,$translationStatus,$reviewState]
                    );
                }
            }
        });

        // Every content change invalidates prior approval. Publication is only
        // restored after a fresh server-side completeness result passes.
        $validator=new TranslationCompletenessValidator();
        $touchedLocales=array_values(array_unique($touchedLocales));
        if(in_array('ar',$touchedLocales,true)){
            $validator->invalidate($id,'ar');
            $touchedLocales=array_values(array_unique(array_merge($touchedLocales,array_column(Database::all("SELECT locale FROM article_translations WHERE article_id=? AND locale<>'ar'",[$id]),'locale'))));
        }
        foreach($touchedLocales as $locale){
            if($locale==='ar')continue;
            try{
                $approve=in_array($locale,$publishLocales,true);
                $report=$validator->validateArticle($id,$locale,(int)$u['id'],$approve);
                if($approve && empty($report['blocking'])) Database::exec("UPDATE article_translations SET status='published',review_state='approved',completeness_state='passed' WHERE article_id=? AND locale=?",[$id,$locale]);
                elseif($approve){$translationIssues[]=strtoupper($locale);}
            }catch(\Throwable){if(in_array($locale,$publishLocales,true))$translationIssues[]=strtoupper($locale);}
        }

        if($status==='published' && !article_publication_ready($id)){
            Database::exec("UPDATE articles SET status='review',published_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$id]);
            $status='review';
            $translationIssues[]='AR';
        }

        if ($submit && !is_editor()) {
            try {
                $editors = Database::all("SELECT id FROM users WHERE role IN ('admin','editor') AND status='active'");
                foreach ($editors as $ed) {
                    Database::insert(
                        "INSERT INTO notifications(user_id,type,title,message,link) VALUES(?,?,?,?,?)",
                        [(int)$ed['id'],'article_review','مقال جديد بانتظار المراجعة','أرسل الكاتب مقالًا جديدًا للمراجعة.','/writer/articles/'.$id]
                    );
                }
            } catch (\Throwable) {}
        }

        audit_log($submit?'article_submit':'article_save','article',$id,$status);
        if ($translationIssues) {
            $codes=implode('، ',array_values(array_unique($translationIssues)));
            flash('error', "لم تُنشر ترجمات {$codes}: لم تجتز فحص اكتمال الترجمة. راجع التقرير، صحح النتائج المانعة، ثم أعد الفحص.");
        } else {
            flash('success', $submit ? ($status==='published' ? 'تم نشر العربية، ونُشرت فقط الترجمات التي اجتازت فحص الاكتمال.' : 'تم إرسال المقال للمراجعة، ولن يظهر للزوار قبل موافقة الإدارة.') : 'تم حفظ التعديلات وتحديث حالة فحص الترجمات.');
        }
        redirect('/writer/articles/'.$id);
    }

    public function account(): void {
        $u = $this->guard();
        $profile = Database::one('SELECT * FROM writer_profiles WHERE user_id=?', [$u['id']]) ?: [];
        $writingLanguages = article_locales();
        $profileTranslations=[];
        try{
            foreach(Database::all('SELECT * FROM writer_profile_translations WHERE user_id=?',[$u['id']]) as $row) $profileTranslations[(string)$row['locale']]=$row;
        }catch(\Throwable){}
        View::render('writer/account', compact('u','profile','profileTranslations','writingLanguages') + ['title'=>'ملفي المهني والسيرة الذاتية'], 'dashboard');
    }

    public function saveProfile(): void {
        $u=$this->guard(); Csrf::enforce();
        $currentProfile=Database::one('SELECT primary_writing_language FROM writer_profiles WHERE user_id=?',[(int)$u['id']])?:[];
        $requestedUsername=normalize_writer_username((string)($_POST['username']??$u['username']));
        if(!valid_writer_username($requestedUsername)){
            flash('error','اسم المستخدم يجب أن يبدأ بحرف إنجليزي، من 3 إلى 30 محرفًا، ويسمح بالأحرف الإنجليزية والأرقام و - و _ فقط.');
            redirect('/writer/account');
        }
        $taken=Database::one('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1',[$requestedUsername,$u['id']]);
        $oldAlias=null; try{$oldAlias=Database::one('SELECT user_id FROM writer_username_redirects WHERE old_username=? AND user_id<>? LIMIT 1',[$requestedUsername,$u['id']]);}catch(\Throwable){}
        if($taken||$oldAlias){ flash('error','اسم المستخدم محجوز. اختر اسمًا آخر.'); redirect('/writer/account'); }
        if($requestedUsername!==$u['username']){
            try{ Database::insert('INSERT IGNORE INTO writer_username_redirects(user_id,old_username) VALUES(?,?)',[$u['id'],$u['username']]); }catch(\Throwable){}
            Database::exec('UPDATE users SET username=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$requestedUsername,$u['id']]);
            $u['username']=$requestedUsername;
        }
        $cleanUrl=static function(string $v): string { $v=trim($v); return ($v==='' || filter_var($v,FILTER_VALIDATE_URL))?$v:''; };
        $jsonList=static function(string $v): string { $lines=preg_split('/\R/u',trim($v))?:[]; $lines=array_values(array_filter(array_map('trim',$lines),static fn($x)=>$x!=='')); return json_encode(array_slice($lines,0,60),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); };
        $title=mb_substr(trim((string)($_POST['professional_title']??'')),0,190);
        $summary=mb_substr(trim((string)($_POST['cv_summary']??'')),0,5000);
        $writingLanguage=(string)($_POST['primary_writing_language']??($currentProfile['primary_writing_language']??'ar'));
        if(!array_key_exists($writingLanguage,article_locales())){
            flash('error','لغة الكتابة الأساسية غير متاحة في إعدادات الموقع.');
            redirect('/writer/account');
        }
        $location=mb_substr(trim((string)($_POST['location']??'')),0,190);
        $website=$cleanUrl((string)($_POST['website']??'')); $linkedin=$cleanUrl((string)($_POST['linkedin']??'')); $portfolioUrl=$cleanUrl((string)($_POST['portfolio_url']??''));
        $contact=trim((string)($_POST['contact_email']??'')); if($contact!=='' && !filter_var($contact,FILTER_VALIDATE_EMAIL)) $contact='';
        $fields=['experience','education','skills','certificates','languages','achievements','portfolio']; $data=[]; foreach($fields as $x)$data[$x]=$jsonList((string)($_POST[$x]??''));
        Database::exec("INSERT INTO writer_profiles(user_id,professional_title,cv_summary,location,website,linkedin,portfolio_url,contact_email,primary_writing_language,experience_json,education_json,skills_json,certificates_json,languages_json,achievements_json,portfolio_json,is_public,show_contact) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE professional_title=VALUES(professional_title),cv_summary=VALUES(cv_summary),location=VALUES(location),website=VALUES(website),linkedin=VALUES(linkedin),portfolio_url=VALUES(portfolio_url),contact_email=VALUES(contact_email),primary_writing_language=VALUES(primary_writing_language),experience_json=VALUES(experience_json),education_json=VALUES(education_json),skills_json=VALUES(skills_json),certificates_json=VALUES(certificates_json),languages_json=VALUES(languages_json),achievements_json=VALUES(achievements_json),portfolio_json=VALUES(portfolio_json),is_public=VALUES(is_public),show_contact=VALUES(show_contact)",[$u['id'],$title,$summary,$location,$website,$linkedin,$portfolioUrl,$contact,$writingLanguage,$data['experience'],$data['education'],$data['skills'],$data['certificates'],$data['languages'],$data['achievements'],$data['portfolio'],isset($_POST['is_public'])?1:0,isset($_POST['show_contact'])?1:0]);
        Database::exec('UPDATE users SET bio=? WHERE id=?',[mb_substr($summary,0,1200),$u['id']]);
        Database::exec("INSERT INTO writer_profile_translations(user_id,locale,display_name,bio,professional_title,cv_summary,status) VALUES(?,?,?,?,?,?, 'published') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),bio=VALUES(bio),professional_title=VALUES(professional_title),cv_summary=VALUES(cv_summary),status='published'",[$u['id'],'ar',$u['name'],mb_substr($summary,0,1200),$title,$summary]);
        $translations=is_array($_POST['profile_translation']??null)?$_POST['profile_translation']:[];
        foreach(all_locales() as $code=>$info){
            if($code==='ar'||!isset($translations[$code])||!is_array($translations[$code])) continue;
            $row=$translations[$code];
            $displayName=mb_substr(trim((string)($row['display_name']??'')),0,190);
            $bio=mb_substr(trim((string)($row['bio']??'')),0,1200);
            $translatedTitle=mb_substr(trim((string)($row['professional_title']??'')),0,190);
            $translatedSummary=mb_substr(trim((string)($row['cv_summary']??'')),0,5000);
            if($displayName===''&&$bio===''&&$translatedTitle===''&&$translatedSummary==='') continue;
            $requestedStatus=(string)($row['status']??'review');
            $translationStatus=is_editor()&&in_array($requestedStatus,['draft','review','published'],true)?$requestedStatus:'review';
            Database::exec("INSERT INTO writer_profile_translations(user_id,locale,display_name,bio,professional_title,cv_summary,status) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),bio=VALUES(bio),professional_title=VALUES(professional_title),cv_summary=VALUES(cv_summary),status=VALUES(status)",[$u['id'],$code,$displayName,$bio,$translatedTitle,$translatedSummary,$translationStatus]);
        }
        audit_log('writer_profile_update','user',(int)$u['id'],'professional profile'); flash('success','تم حفظ ملفك المهني والسيرة الذاتية.'); redirect('/writer/account');
    }

    public function checkUsername(): void {
        $u=$this->guard();
        header('Content-Type: application/json; charset=utf-8');
        $username=normalize_writer_username((string)($_GET['username']??''));
        if(!valid_writer_username($username)){ echo json_encode(['ok'=>false,'available'=>false,'username'=>$username,'message'=>'استخدم 3–30 محرفًا إنجليزيًا، وابدأ بحرف.'],JSON_UNESCAPED_UNICODE); return; }
        $taken=Database::one('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1',[$username,$u['id']]); $alias=null; try{$alias=Database::one('SELECT user_id FROM writer_username_redirects WHERE old_username=? AND user_id<>? LIMIT 1',[$username,$u['id']]);}catch(\Throwable){} $busy=(bool)($taken||$alias);
        echo json_encode(['ok'=>true,'available'=>!$busy,'username'=>$username,'message'=>$busy?'اسم المستخدم محجوز':'اسم المستخدم متاح'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    public function shareCenter(): void { redirect('/writer/card',301); }

    public function uploadAvatar(): void { $u=$this->guard(); Csrf::enforce(); try{$path=Upload::image($_FILES['avatar']??[]);if(!$path)throw new \RuntimeException('اختر صورة أولًا.');Database::exec('UPDATE users SET avatar=? WHERE id=?',[$path,$u['id']]);flash('success','تم تحديث صورتك الشخصية.');}catch(\Throwable $e){flash('error',$e->getMessage());}redirect('/writer/account'); }

    public function changePassword(): void {
        $u = $this->guard();
        Csrf::enforce();
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirmation'] ?? '');
        $row = Database::one('SELECT password_hash FROM users WHERE id=?', [$u['id']]);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            flash('error','كلمة المرور الحالية غير صحيحة.');
            redirect('/writer/account');
        }
        if (mb_strlen($new) < 12 || $new !== $confirm) {
            flash('error','كلمة المرور الجديدة يجب أن تكون 12 محرفًا على الأقل ومتطابقة في الحقلين.');
            redirect('/writer/account');
        }
        $algo = in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        Database::exec('UPDATE users SET password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=?', [password_hash($new,$algo),$u['id']]);
        flash('success','تم تغيير كلمة المرور بنجاح.');
        redirect('/writer/account');
    }

    public function readNotification(): void {
        $u = $this->guard();
        Csrf::enforce();
        $id = (int)($_POST['id'] ?? 0);
        try { Database::exec('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', [$id,$u['id']]); } catch (\Throwable) {}
        redirect('/writer');
    }
}
