<?php
namespace App\Controllers;

use App\Core\Database;

final class AdminV2ContentController extends AdminV2BaseController
{
    public function articles(): void
    {
        $u=$this->admin(true); $q=trim((string)($_GET['q']??'')); $status=(string)($_GET['status']??''); $where=[]; $params=[];
        if(in_array($status,['draft','review','changes','published','archived'],true)){ $where[]='a.status=?'; $params[]=$status; }
        if($q!==''){ $where[]='(t.title LIKE ? OR t.slug LIKE ? OR u.name LIKE ?)'; array_push($params,'%'.$q.'%','%'.$q.'%','%'.$q.'%'); }
        $rows=[]; try { $rows=Database::all("SELECT a.id,a.status,a.is_featured,a.updated_at,a.published_at,u.name author_name,u.username,t.title,t.slug FROM articles a JOIN users u ON u.id=a.author_id LEFT JOIN article_translations t ON t.article_id=a.id AND t.locale='ar' WHERE ".($where?implode(' AND ',$where):'1=1')." ORDER BY a.updated_at DESC LIMIT 250",$params); } catch(\Throwable) {}
        $this->render('articles-v2',compact('rows','q','status','u'),'إدارة المقالات');
    }

    public function articleAction(): void
    {
        $u=$this->admin(true); $this->csrf(); $id=(int)($_POST['id']??0); $new=(string)($_POST['status']??'');
        if(!in_array($new,['draft','review','changes','published'],true))$this->redirect('/admin-v2/articles','الحالة غير صالحة أو غير مدعومة في مخطط المقالات الحالي.','error');
        $article=Database::one('SELECT id,status FROM articles WHERE id=?',[$id]); if(!$article)$this->redirect('/admin-v2/articles','المقال غير موجود.','error');
        if($new==='published' && !article_publication_ready($id)) $this->redirect('/admin-v2/articles','لا يمكن النشر قبل اكتمال واعتماد النسخة الأصلية والنسخة العربية.','error');
        Database::exec('UPDATE articles SET status=?,published_at=IF(?=\'published\',COALESCE(published_at,CURRENT_TIMESTAMP),published_at),updated_at=CURRENT_TIMESTAMP WHERE id=?',[$new,$new,$id]);
        audit_log('admin_v2_article_status','article',$id,($article['status']??'').' -> '.$new); $this->redirect('/admin-v2/articles','تم تحديث حالة المقال.');
    }

    public function translations(): void
    {
        $u=$this->admin(true);
        $rows=[]; $staff=[];
        try {
            $rows=Database::all("SELECT a.id,a.original_locale,u.name author_name,u.username,
                source.title original_title,source.review_state original_review,
                ar.status arabic_status,ar.review_state arabic_review,
                x.status assignment_status,x.notes assignment_notes,
                translator.name translator_name,reviewer.name reviewer_name
                FROM articles a
                JOIN users u ON u.id=a.author_id
                JOIN article_translations source ON source.article_id=a.id AND source.locale=COALESCE(NULLIF(a.original_locale,''),'ar')
                LEFT JOIN article_translations ar ON ar.article_id=a.id AND ar.locale='ar'
                LEFT JOIN content_translation_assignments x ON x.content_type='article' AND x.content_id=a.id AND x.target_locale='ar'
                LEFT JOIN users translator ON translator.id=x.translator_id
                LEFT JOIN users reviewer ON reviewer.id=x.reviewer_id
                WHERE COALESCE(NULLIF(a.original_locale,''),'ar')<>'ar'
                  AND source.review_state='approved'
                  AND (ar.id IS NULL OR ar.review_state<>'approved' OR ar.status<>'published')
                ORDER BY a.updated_at ASC,a.id ASC LIMIT 250");
            $staff=Database::all("SELECT DISTINCT u.id,u.name,u.username FROM users u
                LEFT JOIN admin_user_roles ur ON ur.user_id=u.id
                WHERE u.status='active' AND (u.role IN ('admin','editor') OR ur.user_id IS NOT NULL)
                ORDER BY u.name,u.id");
        } catch(\Throwable) {}
        $this->render('translations-v2',compact('rows','staff','u'),'الأعمال بانتظار الترجمة العربية');
    }

    public function assignTranslation(): void
    {
        $u=$this->admin(true); $this->csrf();
        $articleId=(int)($_POST['article_id']??0);
        $translatorId=(int)($_POST['translator_id']??0);
        $reviewerId=(int)($_POST['reviewer_id']??0);
        $status=(string)($_POST['assignment_status']??'queued');
        if(!in_array($status,['queued','in_progress','review','approved','cancelled'],true)) $status='queued';
        $article=Database::one("SELECT a.id FROM articles a JOIN article_translations t ON t.article_id=a.id AND t.locale=COALESCE(NULLIF(a.original_locale,''),'ar') WHERE a.id=? AND COALESCE(NULLIF(a.original_locale,''),'ar')<>'ar' AND t.review_state='approved'",[$articleId]);
        if(!$article) $this->redirect('/admin-v2/translations','العمل غير مؤهل لطابور الترجمة العربية.','error');
        foreach([$translatorId,$reviewerId] as $staffId){ if($staffId>0 && !Database::one("SELECT 1 FROM users WHERE id=? AND status='active'",[$staffId])) $this->redirect('/admin-v2/translations','عضو الفريق المختار غير متاح.','error'); }
        Database::exec("INSERT INTO content_translation_assignments(content_type,content_id,target_locale,translator_id,reviewer_id,assigned_by,status,notes) VALUES('article',?,'ar',?,?,?,?,?) ON DUPLICATE KEY UPDATE translator_id=VALUES(translator_id),reviewer_id=VALUES(reviewer_id),assigned_by=VALUES(assigned_by),status=VALUES(status),notes=VALUES(notes),updated_at=CURRENT_TIMESTAMP",[$articleId,$translatorId?:null,$reviewerId?:null,(int)$u['id'],$status,mb_substr(trim((string)($_POST['notes']??'')),0,2000)]);
        audit_log('translation_assignment','article',$articleId,'ar|'.$status);
        $this->redirect('/admin-v2/translations','تم حفظ تعيين الترجمة العربية.');
    }

    public function thoughts(): void
    {
        $u=$this->admin(true); $q=trim((string)($_GET['q']??'')); $status=(string)($_GET['status']??''); $where=[]; $params=[];
        if(in_array($status,['draft','review','changes','rejected','published'],true)){ $where[]='k.status=?'; $params[]=$status; }
        if($q!==''){ $where[]='(COALESCE(t.title,k.body) LIKE ? OR u.name LIKE ?)'; array_push($params,'%'.$q.'%','%'.$q.'%'); }
        $rows=[]; try { $rows=Database::all("SELECT k.id,k.status,k.created_at,k.updated_at,u.name author_name,u.username,t.title,t.slug FROM khawatir k JOIN users u ON u.id=k.author_id LEFT JOIN khawatir_translations t ON t.khawatir_id=k.id AND t.locale='ar' WHERE ".($where?implode(' AND ',$where):'1=1')." ORDER BY k.updated_at DESC LIMIT 250",$params); } catch(\Throwable) {}
        $this->render('thoughts-v2',compact('rows','q','status','u'),'إدارة الخواطر');
    }

    public function thoughtAction(): void
    {
        $u=$this->admin(true); $this->csrf(); $id=(int)($_POST['id']??0); $new=(string)($_POST['status']??'');
        if(!in_array($new,['draft','review','changes','rejected','published'],true))$this->redirect('/admin-v2/thoughts','الحالة غير صالحة أو غير مدعومة في مخطط الخواطر الحالي.','error');
        $row=Database::one('SELECT id,status FROM khawatir WHERE id=?',[$id]); if(!$row)$this->redirect('/admin-v2/thoughts','الخاطرة غير موجودة.','error');
        Database::exec('UPDATE khawatir SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$new,$id]); audit_log('admin_v2_thought_status','khawatir',$id,($row['status']??'').' -> '.$new); $this->redirect('/admin-v2/thoughts','تم تحديث حالة الخاطرة.');
    }
}
