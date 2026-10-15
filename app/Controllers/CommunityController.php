<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Upload;
use App\Core\View;
use App\Services\CommunityModeration;
use Throwable;

final class CommunityController {
    private function locale(string $locale): string {
        if(!valid_locale($locale)){
            http_response_code(404);
            View::render('errors/404',['locale'=>default_locale(),'title'=>'404'],'public');
            exit;
        }
        if(setting('community_enabled','1')!=='1'){
            http_response_code(404);
            View::render('errors/404',['locale'=>$locale,'title'=>'404'],'public');
            exit;
        }
        return $locale;
    }

    private function requireUser(string $locale): array {
        $u=Auth::user();
        if(!$u){ flash('error','سجّل الدخول أولًا للمشاركة في منتدى سراديب.'); redirect(locale_url($locale,'login')); }
        if(($u['status']??'')!=='active'){ http_response_code(403); exit('الحساب غير نشط.'); }
        $this->ensureNotBanned((int)$u['id']);
        return $u;
    }

    private function requireTopicAuthor(string $locale): array {
        $user=$this->requireUser($locale);
        if (!in_array((string)($user['role']??''),['writer','trusted_writer','editor','admin'],true)) {
            flash('error','النشر في منتدى سراديب، سواء بموضوع أو رد، متاح للكتّاب المعتمدين وفريق التحرير فقط. يمكنك القراءة والتفاعل بالإعجاب والحفظ والمتابعة والإبلاغ.');
            redirect(locale_url($locale,'community'));
        }
        return $user;
    }

    private function ensureNotBanned(int $userId): void {
        try {
            $ban=Database::one("SELECT * FROM community_bans WHERE user_id=? AND (expires_at IS NULL OR expires_at>CURRENT_TIMESTAMP) LIMIT 1",[$userId]);
            if($ban){ http_response_code(403); exit('تم تعليق مشاركتك في منتدى سراديب.'.(!empty($ban['reason'])?' السبب: '.e($ban['reason']):'')); }
        } catch(Throwable) {}
    }

    private function pagination(int $total,int $perPage): array {
        $page=max(1,(int)($_GET['page']??1));
        $pages=max(1,(int)ceil(max(0,$total)/max(1,$perPage)));
        if($page>$pages)$page=$pages;
        return ['page'=>$page,'pages'=>$pages,'offset'=>($page-1)*$perPage,'perPage'=>$perPage,'total'=>$total];
    }

    private function heat(int $score,string $locale='ar'): array {
        $ui=community_ui($locale);
        $warm=max(1,(int)setting('community_heat_warm','30'));
        $hot=max($warm+1,(int)setting('community_heat_hot','100'));
        $blazing=max($hot+1,(int)setting('community_heat_blazing','300'));
        if($score >= $blazing) return ['label'=>$ui['blazing'],'class'=>'blazing','icon'=>'🔥'];
        if($score >= $hot) return ['label'=>$ui['hot'],'class'=>'hot','icon'=>'🟠'];
        if($score >= $warm) return ['label'=>$ui['warm'],'class'=>'warm','icon'=>'🟡'];
        return ['label'=>$ui['calm'],'class'=>'calm','icon'=>'🟦'];
    }

    private function addActivity(int $topicId,string $type): void {
        $column=['view'=>'views','reply'=>'replies','like'=>'likes'][$type]??null; if(!$column)return;
        try {
            Database::exec("INSERT INTO community_topic_activity(topic_id,activity_date,{$column}) VALUES(?,CURRENT_DATE,1) ON DUPLICATE KEY UPDATE {$column}={$column}+1",[$topicId]);
        } catch(Throwable) {}
    }

    private function recentScoreSql(string $topicAlias='t'): string {
        $days=max(1,min(90,(int)setting('community_heat_days','7')));
        return "(SELECT COALESCE(SUM(ca.views + ca.replies*8 + ca.likes*3),0) FROM community_topic_activity ca WHERE ca.topic_id={$topicAlias}.id AND ca.activity_date>=DATE_SUB(CURRENT_DATE,INTERVAL {$days} DAY))";
    }

    private function topicCardRows(string $locale,array $options=[]): array {
        $filter=(string)($options['filter']??'latest');
        $categoryId=(int)($options['category_id']??0);
        $q=trim((string)($options['q']??''));
        $userId=(int)($options['user_id']??0);
        $score=$this->recentScoreSql('t');
        $where=["t.locale=?","t.status IN ('open','closed')","t.moderation_status='approved'"];
        $params=[$locale];
        $joins='';
        if($categoryId>0){$where[]='t.category_id=?';$params[]=$categoryId;}
        if($filter==='unanswered')$where[]='t.replies_count=0';
        if($filter==='following'){
            if(!$userId)return [];
            $joins.=' JOIN community_follows cf ON cf.topic_id=t.id AND cf.user_id=? ';
            array_unshift($params,$userId);
        }
        if($q!==''){
            $like='%'.$q.'%';
            $where[]='(t.title LIKE ? OR t.content LIKE ? OR u.name LIKE ? OR u.username LIKE ? OR EXISTS(SELECT 1 FROM community_replies cr WHERE cr.topic_id=t.id AND cr.status=\'visible\' AND cr.content LIKE ?))';
            array_push($params,$like,$like,$like,$like,$like);
        }
        $order=match($filter){
            'discussed'=>'t.pinned DESC,t.replies_count DESC,t.last_activity_at DESC',
            'views'=>'t.pinned DESC,t.views DESC,t.last_activity_at DESC',
            'active'=>'t.pinned DESC,activity_score DESC,t.last_activity_at DESC',
            default=>'t.pinned DESC,t.last_activity_at DESC,t.id DESC',
        };
        $per=max(6,min(30,(int)setting('community_topics_per_page','15')));
        $countSql='SELECT COUNT(*) c FROM community_topics t JOIN users u ON u.id=t.author_id '.$joins.' WHERE '.implode(' AND ',$where);
        $total=(int)(Database::one($countSql,$params)['c']??0);
        $pg=$this->pagination($total,$per);
        $sql="SELECT t.*,u.name author_name,u.username,u.avatar,ct.name category_name,ct.slug category_slug,{$score} activity_score
              FROM community_topics t JOIN users u ON u.id=t.author_id {$joins}
              LEFT JOIN community_category_translations ct ON ct.category_id=t.category_id AND ct.locale=t.locale
              WHERE ".implode(' AND ',$where)." ORDER BY {$order} LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
        $rows=Database::all($sql,$params);
        foreach($rows as &$row)$row['heat']=$this->heat((int)($row['activity_score']??0),$locale);
        unset($row);
        return ['rows'=>$rows,'pagination'=>$pg];
    }

    public function index(string $locale='ar'): void {
        $locale=$this->locale($locale);
        $user=Auth::user();
        $filter=(string)($_GET['filter']??'latest'); if(!in_array($filter,['latest','discussed','unanswered','following','views','active'],true))$filter='latest';
        if($filter==='following' && !$user)$filter='latest';
        $q=trim((string)($_GET['q']??''));
        $data=$this->topicCardRows($locale,['filter'=>$filter,'q'=>$q,'user_id'=>(int)($user['id']??0)]);
        $categories=Database::all("SELECT c.id,ct.name,ct.slug,ct.description,(SELECT COUNT(*) FROM community_topics t WHERE t.category_id=c.id AND t.locale=? AND t.status IN ('open','closed') AND t.moderation_status='approved') topic_count FROM community_categories c JOIN community_category_translations ct ON ct.category_id=c.id AND ct.locale=? WHERE c.active=1 ORDER BY c.sort_order,c.id",[$locale,$locale]);
        $week=Database::one("SELECT t.*,u.name author_name,u.username,ct.name category_name,ct.slug category_slug FROM community_topics t JOIN users u ON u.id=t.author_id LEFT JOIN community_category_translations ct ON ct.category_id=t.category_id AND ct.locale=t.locale WHERE t.locale=? AND t.featured_week=1 AND t.status IN ('open','closed') AND t.moderation_status='approved' ORDER BY t.updated_at DESC LIMIT 1",[$locale]);
        $communityStats=Database::one("SELECT COUNT(*) topics,COALESCE(SUM(replies_count),0) replies,COUNT(DISTINCT author_id) contributors FROM community_topics WHERE locale=? AND status IN ('open','closed') AND moderation_status='approved'",[$locale])?:['topics'=>0,'replies'=>0,'contributors'=>0];
        $communityCopy=community_ui($locale); $title=$communityCopy['brand']; $description=$communityCopy['description'];
        View::render('community/index',compact('locale','user','filter','q','categories','week','communityStats','title','description')+$data,'public');
    }

    public function category(string $locale,string $slug): void {
        $locale=$this->locale($locale);
        $category=Database::one("SELECT c.id,ct.name,ct.slug,ct.description FROM community_categories c JOIN community_category_translations ct ON ct.category_id=c.id AND ct.locale=? WHERE c.active=1 AND ct.slug=? LIMIT 1",[$locale,$slug]);
        if(!$category){ http_response_code(404); View::render('errors/404',compact('locale')+['title'=>'404'],'public'); return; }
        $user=Auth::user(); $filter=(string)($_GET['filter']??'latest'); if(!in_array($filter,['latest','discussed','unanswered','views','active'],true))$filter='latest';
        $data=$this->topicCardRows($locale,['filter'=>$filter,'category_id'=>(int)$category['id'],'user_id'=>(int)($user['id']??0)]);
        $communityCopy=community_ui($locale); $title=$category['name'].' | '.$communityCopy['brand']; $description=$category['description']?:$communityCopy['description'];
        View::render('community/category',compact('locale','user','filter','category','title','description')+$data,'public');
    }

    public function topic(string $locale,string $slug): void {
        $locale=$this->locale($locale);
        $topic=Database::one("SELECT t.*,u.name author_name,u.username,u.avatar,u.role,(SELECT COUNT(*) FROM articles aa WHERE aa.author_id=u.id AND aa.status='published') author_articles,ct.name category_name,ct.slug category_slug,a.featured_image article_image,at.title article_title,at.slug article_slug
            FROM community_topics t JOIN users u ON u.id=t.author_id
            LEFT JOIN community_category_translations ct ON ct.category_id=t.category_id AND ct.locale=t.locale
            LEFT JOIN articles a ON a.id=t.article_id
            LEFT JOIN article_translations at ON at.article_id=t.article_id AND at.locale=t.locale AND ".article_translation_publishable_sql('at')."
            WHERE t.locale=? AND t.slug=? AND t.status<>'hidden' AND t.moderation_status='approved' LIMIT 1",[$locale,$slug]);
        if(!$topic){ http_response_code(404); View::render('errors/404',compact('locale')+['title'=>'404'],'public'); return; }
        $topicId=(int)$topic['id'];
        $last=(int)($_SESSION['community_viewed'][$topicId]??0);
        if($last<time()-1800){
            Database::exec('UPDATE community_topics SET views=views+1 WHERE id=?',[$topicId]);
            $_SESSION['community_viewed'][$topicId]=time(); $topic['views']=(int)$topic['views']+1; $this->addActivity($topicId,'view');
        }
        $per=max(10,min(80,(int)setting('community_replies_per_page','30')));
        $total=(int)(Database::one("SELECT COUNT(*) c FROM community_replies WHERE topic_id=? AND status='visible'",[$topicId])['c']??0);
        $pg=$this->pagination($total,$per);
        $replies=Database::all("SELECT r.*,u.name,u.username,u.avatar,u.role,(SELECT COUNT(*) FROM articles aa WHERE aa.author_id=u.id AND aa.status='published') author_articles,pr.content parent_content,pu.name parent_name,pu.username parent_username
            FROM community_replies r JOIN users u ON u.id=r.user_id
            LEFT JOIN community_replies pr ON pr.id=r.reply_to_reply_id
            LEFT JOIN users pu ON pu.id=pr.user_id
            WHERE r.topic_id=? AND r.status='visible' ORDER BY r.id ASC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}",[$topicId]);
        $score=(int)(Database::one("SELECT {$this->recentScoreSql('t')} score FROM community_topics t WHERE t.id=?",[$topicId])['score']??0);
        $heat=$this->heat($score,$locale); $user=Auth::user();
        // The template consumes the conventional $pagination variable.
        // Keep the query's local $pg name, but expose it explicitly to avoid
        // passing an undefined value into community_pagination().
        $pagination=$pg;
        $state=['liked'=>false,'following'=>false,'saved'=>false];
        if($user){
            $uid=(int)$user['id'];
            $state['liked']=(bool)Database::one("SELECT 1 x FROM community_likes WHERE user_id=? AND target_type='topic' AND target_id=?",[$uid,$topicId]);
            $state['following']=(bool)Database::one("SELECT 1 x FROM community_follows WHERE user_id=? AND topic_id=?",[$uid,$topicId]);
            $state['saved']=(bool)Database::one("SELECT 1 x FROM community_saves WHERE user_id=? AND topic_id=?",[$uid,$topicId]);
        }
        $title=$topic['title']; $description=mb_substr(trim(strip_tags((string)$topic['content'])),0,220);
        $canonical=locale_url($locale,'community/topic/'.$topic['slug']);
        View::render('community/topic',compact('locale','topic','replies','pagination','heat','user','state','title','description','canonical'),'public');
    }

    public function createForm(string $locale='ar'): void {
        $locale=$this->locale($locale); $user=$this->requireTopicAuthor($locale);
        $categories=Database::all("SELECT c.id,ct.name FROM community_categories c JOIN community_category_translations ct ON ct.category_id=c.id AND ct.locale=? WHERE c.active=1 ORDER BY c.sort_order,c.id",[$locale]);
        View::render('community/create',compact('locale','user','categories')+['title'=>'ابدأ نقاشًا'],'public');
    }

    public function storeTopic(string $locale='ar'): void {
        $locale=$this->locale($locale); Csrf::enforce(); $user=$this->requireTopicAuthor($locale); $uid=(int)$user['id'];
        $limit=max(1,(int)setting('community_max_topics_hour','5'));
        $recent=(int)(Database::one("SELECT COUNT(*) c FROM community_topics WHERE author_id=? AND created_at>=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 HOUR)",[$uid])['c']??0);
        if($recent>=$limit){ flash('error','وصلت إلى الحد المؤقت لإنشاء النقاشات. حاول لاحقًا.'); redirect(locale_url($locale,'community/new')); }
        $title=trim((string)($_POST['title']??'')); $categoryId=(int)($_POST['category_id']??0); $content=sanitize_html((string)($_POST['content']??'')); $tags=trim((string)($_POST['tags']??''));
        if(mb_strlen($title)<6 || mb_strlen(strip_tags($content))<20 || $categoryId<1){ flash('error','اكتب عنوانًا واضحًا، اختر قسمًا، وأضف محتوى كافيًا.'); redirect(locale_url($locale,'community/new')); }
        $maxLinks=max(0,(int)setting('community_max_links_topic','5')); preg_match_all('#https?://#i',$content,$m); if(count($m[0])>$maxLinks){ flash('error','عدد الروابط في النقاش أكبر من الحد المسموح.'); redirect(locale_url($locale,'community/new')); }
        $cat=Database::one("SELECT c.id FROM community_categories c JOIN community_category_translations ct ON ct.category_id=c.id AND ct.locale=? WHERE c.id=? AND c.active=1",[$locale,$categoryId]); if(!$cat){flash('error','القسم غير صالح.');redirect(locale_url($locale,'community/new'));}
        $image=null; try{ if(!empty($_FILES['image']['name']))$image=Upload::image($_FILES['image']); }catch(Throwable $e){flash('error',$e->getMessage());redirect(locale_url($locale,'community/new'));}
        $base=slugify($title); if($base==='')$base='topic'; $slug=$base.'-'.substr(bin2hex(random_bytes(4)),0,7);
        $excerpt=mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags($content))??''),0,220);
        $moderation=CommunityModeration::inspect($title."\n".$content);
        if($moderation['level']==='block') { flash('error','تعذر نشر النص لأنه يخالف قواعد منتدى سراديب. راجع صياغته ثم أرسله مجددًا.'); redirect(locale_url($locale,'community/new')); }
        if($moderation['level']==='warning') { flash('error','يرجى مراجعة صياغة المشاركة قبل الإرسال والتأكد من احترام قواعد المنتدى.'); redirect(locale_url($locale,'community/new')); }
        $moderationStatus=$moderation['level']==='review'?'pending':'approved';
        $id=Database::insert("INSERT INTO community_topics(author_id,category_id,locale,title,slug,excerpt,content,image,tags,status,moderation_status,last_activity_at) VALUES(?,?,?,?,?,?,?,?,?,'open',?,CURRENT_TIMESTAMP)",[$uid,$categoryId,$locale,$title,$slug,$excerpt,$content,$image,$tags,$moderationStatus]);
        CommunityModeration::event('topic',$id,$uid,$moderation,$title."\n".$content);
        Database::insert('INSERT IGNORE INTO community_follows(user_id,topic_id) VALUES(?,?)',[$uid,$id]);
        $this->notifyMentions($content,$uid,$id,$locale,null);
        audit_log('community_topic_created','community_topic',$id,$title);
        if($moderationStatus==='pending'){ flash('success','تم استلام نقاشك وهو الآن بانتظار مراجعة فريق الإشراف.'); redirect(locale_url($locale,'community')); }
        redirect(locale_url($locale,'community/topic/'.$slug));
    }

    public function storeReply(string $locale='ar'): void {
        $locale=$this->locale($locale); Csrf::enforce(); $user=$this->requireTopicAuthor($locale); $uid=(int)$user['id'];
        $topicId=(int)($_POST['topic_id']??0); $content=sanitize_html((string)($_POST['content']??'')); $replyTo=(int)($_POST['reply_to_id']??0);
        $topic=Database::one("SELECT t.*,u.email owner_email FROM community_topics t JOIN users u ON u.id=t.author_id WHERE t.id=? AND t.locale=? AND t.status IN ('open','closed')",[$topicId,$locale]);
        if(!$topic){http_response_code(404);exit('الموضوع غير موجود.');} if($topic['status']==='closed'){flash('error','هذا النقاش مغلق.');redirect(locale_url($locale,'community/topic/'.$topic['slug']));}
        $limit=max(1,(int)setting('community_max_replies_hour','30')); $recent=(int)(Database::one("SELECT COUNT(*) c FROM community_replies WHERE user_id=? AND created_at>=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 HOUR)",[$uid])['c']??0);
        if($recent>=$limit){flash('error','تم بلوغ حد الردود المؤقت. حاول لاحقًا.');redirect(locale_url($locale,'community/topic/'.$topic['slug']));}
        if(mb_strlen(strip_tags($content))<2){flash('error','اكتب ردًا قبل الإرسال.');redirect(locale_url($locale,'community/topic/'.$topic['slug']));}
        $parent=null; if($replyTo>0)$parent=Database::one('SELECT r.id,r.user_id,u.name,u.username FROM community_replies r JOIN users u ON u.id=r.user_id WHERE r.id=? AND r.topic_id=?',[$replyTo,$topicId]);
        $moderation=CommunityModeration::inspect($content);
        if($moderation['level']==='block'){flash('error','تعذر إرسال الرد لأنه يخالف قواعد منتدى سراديب.');redirect(locale_url($locale,'community/topic/'.$topic['slug']));}
        if($moderation['level']==='warning'){flash('error','يرجى مراجعة صياغة الرد قبل الإرسال.');redirect(locale_url($locale,'community/topic/'.$topic['slug']));}
        $pending=$moderation['level']==='review';
        $id=Database::insert("INSERT INTO community_replies(topic_id,user_id,reply_to_reply_id,content,status,moderation_status) VALUES(?,?,?,?,'visible',?)",[$topicId,$uid,$parent['id']??null,$content,$pending?'pending':'approved']);
        CommunityModeration::event('reply',$id,$uid,$moderation,$content);
        if($pending){Database::exec("UPDATE community_replies SET status='hidden' WHERE id=?",[$id]); flash('success','تم استلام ردك وهو بانتظار مراجعة فريق الإشراف.'); redirect(locale_url($locale,'community/topic/'.$topic['slug']));}
        Database::exec('UPDATE community_topics SET replies_count=replies_count+1,last_activity_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$topicId]); $this->addActivity($topicId,'reply');
        $notified=[];
        if((int)$topic['author_id']!==$uid){$this->notify((int)$topic['author_id'],'community_reply','رد جديد على نقاشك','أضاف '.$user['name'].' ردًا جديدًا على نقاشك.',locale_url($locale,'community/topic/'.$topic['slug']).'#reply-'.$id,true);$notified[]=(int)$topic['author_id'];}
        if($parent && (int)$parent['user_id']!==$uid && !in_array((int)$parent['user_id'],$notified,true)){$this->notify((int)$parent['user_id'],'community_reply','رد على تعليقك','رد '.$user['name'].' على تعليقك.',locale_url($locale,'community/topic/'.$topic['slug']).'#reply-'.$id,true);$notified[]=(int)$parent['user_id'];}
        $followers=Database::all('SELECT user_id FROM community_follows WHERE topic_id=? AND user_id<>?',[$topicId,$uid]); foreach($followers as $f){$fid=(int)$f['user_id'];if(in_array($fid,$notified,true))continue;$this->notify($fid,'community_follow','رد جديد في نقاش تتابعه','هناك نشاط جديد في «'.$topic['title'].'».',locale_url($locale,'community/topic/'.$topic['slug']).'#reply-'.$id,true);$notified[]=$fid;}
        $this->notifyMentions($content,$uid,$topicId,$locale,$id);
        redirect(locale_url($locale,'community/topic/'.$topic['slug']).'#reply-'.$id);
    }

    public function toggleLike(string $locale='ar'): void {
        $locale=$this->locale($locale); Csrf::enforce(); $u=$this->requireUser($locale); $uid=(int)$u['id']; $type=(string)($_POST['target_type']??'topic'); $id=(int)($_POST['target_id']??0);
        if(!in_array($type,['topic','reply'],true)||$id<1)redirect(locale_url($locale,'community'));
        $validTarget=$type==='topic'
            ? Database::one("SELECT id FROM community_topics WHERE id=? AND locale=? AND status IN ('open','closed')",[$id,$locale])
            : Database::one("SELECT r.id FROM community_replies r JOIN community_topics t ON t.id=r.topic_id WHERE r.id=? AND r.status='visible' AND t.locale=? AND t.status IN ('open','closed')",[$id,$locale]);
        if(!$validTarget) redirect(locale_url($locale,'community'));
        $exists=Database::one('SELECT 1 x FROM community_likes WHERE user_id=? AND target_type=? AND target_id=?',[$uid,$type,$id]);
        if($exists){Database::exec('DELETE FROM community_likes WHERE user_id=? AND target_type=? AND target_id=?',[$uid,$type,$id]); if($type==='topic')Database::exec('UPDATE community_topics SET likes_count=IF(likes_count>0,likes_count-1,0) WHERE id=?',[$id]); else Database::exec('UPDATE community_replies SET likes_count=IF(likes_count>0,likes_count-1,0) WHERE id=?',[$id]);}
        else {Database::insert('INSERT INTO community_likes(user_id,target_type,target_id) VALUES(?,?,?)',[$uid,$type,$id]); if($type==='topic'){Database::exec('UPDATE community_topics SET likes_count=likes_count+1 WHERE id=?',[$id]);$this->addActivity($id,'like');}else Database::exec('UPDATE community_replies SET likes_count=likes_count+1 WHERE id=?',[$id]);}
        $this->back($locale);
    }

    public function toggleFollow(string $locale='ar'): void {
        $locale=$this->locale($locale); Csrf::enforce(); $u=$this->requireUser($locale); $uid=(int)$u['id']; $topicId=(int)($_POST['topic_id']??0);
        if(!$this->visibleTopic($topicId,$locale)) redirect(locale_url($locale,'community'));
        $e=Database::one('SELECT 1 x FROM community_follows WHERE user_id=? AND topic_id=?',[$uid,$topicId]);
        if($e)Database::exec('DELETE FROM community_follows WHERE user_id=? AND topic_id=?',[$uid,$topicId]); else Database::insert('INSERT INTO community_follows(user_id,topic_id) VALUES(?,?)',[$uid,$topicId]);
        $this->back($locale);
    }
    public function toggleSave(string $locale='ar'): void {
        $locale=$this->locale($locale); Csrf::enforce(); $u=$this->requireUser($locale); $uid=(int)$u['id']; $topicId=(int)($_POST['topic_id']??0);
        if(!$this->visibleTopic($topicId,$locale)) redirect(locale_url($locale,'community'));
        $e=Database::one('SELECT 1 x FROM community_saves WHERE user_id=? AND topic_id=?',[$uid,$topicId]);
        if($e)Database::exec('DELETE FROM community_saves WHERE user_id=? AND topic_id=?',[$uid,$topicId]); else Database::insert('INSERT INTO community_saves(user_id,topic_id) VALUES(?,?)',[$uid,$topicId]);
        $this->back($locale);
    }

    public function report(string $locale='ar'): void {
        $locale=$this->locale($locale); Csrf::enforce(); $u=$this->requireUser($locale); $type=(string)($_POST['target_type']??'topic'); $targetId=(int)($_POST['target_id']??0); $reason=(string)($_POST['reason']??'other'); $details=trim((string)($_POST['details']??''));
        $allowed=['abuse','spam','offtopic','copyright','other']; if(!in_array($reason,$allowed,true))$reason='other'; if(!in_array($type,['topic','reply'],true)||$targetId<1)$this->back($locale);
        $validTarget=$type==='topic'
            ? $this->visibleTopic($targetId,$locale)
            : Database::one("SELECT r.id FROM community_replies r JOIN community_topics t ON t.id=r.topic_id WHERE r.id=? AND r.status='visible' AND t.locale=? AND t.status IN ('open','closed')",[$targetId,$locale]);
        if(!$validTarget){flash('error','العنصر الذي تريد الإبلاغ عنه غير متاح.');$this->back($locale);}
        $duplicate=Database::one("SELECT id FROM community_reports WHERE user_id=? AND target_type=? AND target_id=? AND status='open' LIMIT 1",[(int)$u['id'],$type,$targetId]);
        if($duplicate){flash('success','بلاغك عن هذا العنصر قيد المراجعة بالفعل.');$this->back($locale);}
        Database::insert("INSERT INTO community_reports(user_id,target_type,target_id,reason,details,status) VALUES(?,?,?,?,?,'open')",[(int)$u['id'],$type,$targetId,$reason,mb_substr($details,0,1000)]); flash('success','تم إرسال البلاغ إلى فريق الإشراف.'); $this->back($locale);
    }

    public function notifications(string $locale='ar'): void {
        $locale=$this->locale($locale); $u=$this->requireUser($locale); $rows=Database::all("SELECT * FROM notifications WHERE user_id=? AND type LIKE 'community_%' ORDER BY created_at DESC,id DESC LIMIT 80",[(int)$u['id']]);
        View::render('community/notifications',compact('locale','u','rows')+['title'=>'إشعارات المنتدى','robots'=>'noindex,nofollow'],'public');
    }
    public function markNotificationsRead(string $locale='ar'): void { $locale=$this->locale($locale); Csrf::enforce(); $u=$this->requireUser($locale); Database::exec("UPDATE notifications SET is_read=1 WHERE user_id=? AND type LIKE 'community_%'",[(int)$u['id']]); redirect(locale_url($locale,'community/notifications')); }

    public function saved(string $locale='ar'): void {
        $locale=$this->locale($locale); $u=$this->requireUser($locale); $uid=(int)$u['id']; $score=$this->recentScoreSql('t');
        $rows=Database::all("SELECT t.*,usr.name author_name,usr.username,usr.avatar,ct.name category_name,ct.slug category_slug,{$score} activity_score FROM community_saves s JOIN community_topics t ON t.id=s.topic_id JOIN users usr ON usr.id=t.author_id LEFT JOIN community_category_translations ct ON ct.category_id=t.category_id AND ct.locale=t.locale WHERE s.user_id=? AND t.locale=? AND t.status IN ('open','closed') ORDER BY s.created_at DESC LIMIT 100",[$uid,$locale]); foreach($rows as &$r)$r['heat']=$this->heat((int)$r['activity_score'],$locale); unset($r);
        View::render('community/saved',compact('locale','u','rows')+['title'=>'المواضيع المحفوظة','robots'=>'noindex,nofollow'],'public');
    }

    public function preferences(string $locale='ar'): void { $locale=$this->locale($locale); $u=$this->requireUser($locale); $prefs=Database::one('SELECT * FROM community_user_preferences WHERE user_id=?',[(int)$u['id']])?:[]; View::render('community/preferences',compact('locale','u','prefs')+['title'=>'إعدادات إشعارات المنتدى','robots'=>'noindex,nofollow'],'public'); }
    public function savePreferences(string $locale='ar'): void { $locale=$this->locale($locale); Csrf::enforce(); $u=$this->requireUser($locale); $vals=[];foreach(['email_replies','email_mentions','email_follows','email_admin'] as $k)$vals[$k]=!empty($_POST[$k])?1:0; Database::exec("INSERT INTO community_user_preferences(user_id,email_replies,email_mentions,email_follows,email_admin) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE email_replies=VALUES(email_replies),email_mentions=VALUES(email_mentions),email_follows=VALUES(email_follows),email_admin=VALUES(email_admin)",[(int)$u['id'],$vals['email_replies'],$vals['email_mentions'],$vals['email_follows'],$vals['email_admin']]); flash('success','تم حفظ إعدادات الإشعارات.'); redirect(locale_url($locale,'community/preferences')); }

    public function account(string $locale='ar'): void {
        $locale=$this->locale($locale); $u=$this->requireUser($locale);
        $profile=Database::one('SELECT id,name,username,email,bio,avatar,role,created_at FROM users WHERE id=?',[(int)$u['id']])?:$u;
        View::render('community/account',compact('locale','profile')+['title'=>'حسابي في منتدى سراديب','robots'=>'noindex,nofollow'],'public');
    }

    public function saveAccount(string $locale='ar'): void {
        $locale=$this->locale($locale); Csrf::enforce(); $u=$this->requireUser($locale); $uid=(int)$u['id'];
        $name=trim((string)($_POST['name']??'')); $bio=trim((string)($_POST['bio']??''));
        if(mb_strlen($name)<2 || mb_strlen($name)>120 || mb_strlen($bio)>1000){flash('error','تحقق من الاسم والنبذة.');redirect(locale_url($locale,'community/account'));}
        $avatar=(string)($u['avatar']??'');
        try { if(!empty($_FILES['avatar']['name'])) $avatar=(string)(Upload::image($_FILES['avatar'])??$avatar); }
        catch(Throwable $e){flash('error',$e->getMessage());redirect(locale_url($locale,'community/account'));}
        Database::exec('UPDATE users SET name=?,bio=?,avatar=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$name,$bio,$avatar!==''?$avatar:null,$uid]);
        audit_log('community_profile_updated','user',$uid,'');
        flash('success','تم تحديث ملفك في منتدى سراديب.');
        redirect(locale_url($locale,'community/account'));
    }

    public function member(string $locale,string $username): void {
        $locale=$this->locale($locale); $member=Database::one("SELECT id,name,username,bio,avatar,role,created_at FROM users WHERE username=? AND status='active'",[$username]); if(!$member){http_response_code(404);View::render('errors/404',compact('locale')+['title'=>'404'],'public');return;}
        $memberId=(int)$member['id']; $topics=Database::all("SELECT t.*,ct.name category_name,ct.slug category_slug FROM community_topics t LEFT JOIN community_category_translations ct ON ct.category_id=t.category_id AND ct.locale=t.locale WHERE t.author_id=? AND t.locale=? AND t.status IN ('open','closed') ORDER BY t.last_activity_at DESC LIMIT 30",[$memberId,$locale]);
        $replies=Database::all("SELECT r.id,r.content,r.created_at,t.title,t.slug FROM community_replies r JOIN community_topics t ON t.id=r.topic_id WHERE r.user_id=? AND r.status='visible' AND t.locale=? ORDER BY r.created_at DESC LIMIT 30",[$memberId,$locale]);
        $articles=Database::all("SELECT a.featured_image,t.title,t.slug,t.excerpt,a.published_at FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND ".article_translation_publishable_sql('t')." WHERE a.author_id=? AND a.status='published' ORDER BY a.published_at DESC LIMIT 30",[$locale,$memberId]);
        $counts=['topics'=>(int)(Database::one("SELECT COUNT(*) c FROM community_topics WHERE author_id=? AND status<>'hidden'",[$memberId])['c']??0),'replies'=>(int)(Database::one("SELECT COUNT(*) c FROM community_replies WHERE user_id=? AND status='visible'",[$memberId])['c']??0),'articles'=>(int)(Database::one("SELECT COUNT(*) c FROM articles WHERE author_id=? AND status='published'",[$memberId])['c']??0)];
        View::render('community/member',compact('locale','member','topics','replies','articles','counts')+['title'=>$member['name'].' | منتدى سراديب'],'public');
    }

    public function articleDiscussion(string $locale,string $id): void {
        $locale=$this->locale($locale); $articleId=(int)$id;
        $article=Database::one("SELECT a.id,a.author_id,a.featured_image,t.title,t.slug,t.excerpt FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND ".article_translation_publishable_sql('t')." WHERE a.id=? AND a.status='published' LIMIT 1",[$locale,$articleId]);
        if(!$article){http_response_code(404);View::render('errors/404',compact('locale')+['title'=>'404'],'public');return;}
        $topic=Database::one("SELECT slug FROM community_topics WHERE article_id=? AND locale=? AND status<>'hidden' LIMIT 1",[$articleId,$locale]);
        if(!$topic){
            $cat=Database::one("SELECT c.id FROM community_categories c JOIN community_category_translations ct ON ct.category_id=c.id AND ct.locale=? WHERE ct.slug IN ('حول-مقالات-سراديب','about-saradeeeb-articles','sobre-articulos-saradeeeb','об-статьях-saradeeeb') LIMIT 1",[$locale]);
            $title=($locale==='ar'?'نقاش حول مقال: ':'Discuss: ').$article['title']; $slug=slugify($article['title']).'-discussion-'.$articleId; if($slug==='')$slug='article-discussion-'.$articleId;
            $content='<p>'.e($locale==='ar'?'هذه مساحة لمناقشة المقالة وتبادل الآراء حولها.':'A space to discuss this article and exchange perspectives.').'</p>';
            try{ Database::insert("INSERT INTO community_topics(author_id,category_id,locale,article_id,title,slug,excerpt,content,status,last_activity_at) VALUES(?,?,?,?,?,?,?,?,'open',CURRENT_TIMESTAMP)",[(int)$article['author_id'],$cat['id']??null,$locale,$articleId,$title,$slug,$article['excerpt']??'',$content]); }
            catch(Throwable){}
            $topic=Database::one("SELECT slug FROM community_topics WHERE article_id=? AND locale=? AND status<>'hidden' LIMIT 1",[$articleId,$locale]);
        }
        redirect(locale_url($locale,'community/topic/'.$topic['slug']));
    }

    private function notifyMentions(string $content,int $actorId,int $topicId,string $locale,?int $replyId): void {
        preg_match_all('/@([\p{L}\p{N}_\-.]{2,120})/u',strip_tags($content),$m); $names=array_values(array_unique($m[1]??[])); if(!$names)return;
        $topic=Database::one('SELECT title,slug FROM community_topics WHERE id=?',[$topicId]); if(!$topic)return;
        foreach(array_slice($names,0,20) as $username){$u=Database::one("SELECT id FROM users WHERE username=? AND status='active'",[$username]); if(!$u||(int)$u['id']===$actorId)continue; $this->notify((int)$u['id'],'community_mention','تم ذكرك في منتدى سراديب','ذكرك أحد الأعضاء في نقاش «'.$topic['title'].'».',locale_url($locale,'community/topic/'.$topic['slug']).($replyId?'#reply-'.$replyId:''),true);}
    }

    private function notify(int $userId,string $type,string $title,string $message,string $link,bool $maybeEmail=false): void {
        Database::insert('INSERT INTO notifications(user_id,type,title,message,link,is_read) VALUES(?,?,?,?,?,0)',[$userId,$type,$title,$message,$link]);
        if(!$maybeEmail || setting('community_email_notifications','0')!=='1')return;
        $prefMap=['community_reply'=>'email_replies','community_mention'=>'email_mentions','community_follow'=>'email_follows','community_admin'=>'email_admin']; $field=$prefMap[$type]??''; if($field==='')return;
        $pref=Database::one('SELECT '.$field.' allowed FROM community_user_preferences WHERE user_id=?',[$userId]); if(!$pref && $field!=='email_admin')return; if($pref && (int)$pref['allowed']!==1)return;
        $u=Database::one('SELECT email FROM users WHERE id=?',[$userId]); if($u && filter_var($u['email'],FILTER_VALIDATE_EMAIL))send_site_mail($u['email'],$title,$message."\n\n".$link);
    }

    private function visibleTopic(int $topicId,string $locale): ?array {
        if($topicId<1)return null;
        return Database::one("SELECT id,slug FROM community_topics WHERE id=? AND locale=? AND status IN ('open','closed') LIMIT 1",[$topicId,$locale]);
    }

    private function back(string $locale): void { $ref=(string)($_SERVER['HTTP_REFERER']??''); if($ref!=='' && str_contains($ref,(string)($_SERVER['HTTP_HOST']??''))){header('Location: '.$ref);exit;} redirect(locale_url($locale,'community')); }
}
