<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Upload;
use App\Core\View;

final class EditorialMessagesController {
    private function user(): array {
        Auth::requireRole(['admin','editor','writer','trusted_writer']);
        $u=Auth::user();
        if(($u['status']??'')!=='active'){ http_response_code(403); exit('الحساب غير مفعل.'); }
        return $u;
    }
    private function staff(array $u): bool { return in_array($u['role']??'', ['admin','editor'], true); }

    private function cleanBody(string $body): string {
        $body=trim($body); if($body==='') return '';
        $body=sanitize_html($body);
        if(mb_strlen(strip_tags($body))>12000) $body=mb_substr($body,0,18000);
        return $body;
    }
    private function normalizedFiles(string $key='attachments'): array {
        $f=$_FILES[$key]??null; if(!$f || !isset($f['name'])) return [];
        if(!is_array($f['name'])) return [$f];
        $out=[]; foreach($f['name'] as $i=>$name)$out[]=['name'=>$name,'type'=>$f['type'][$i]??'','tmp_name'=>$f['tmp_name'][$i]??'','error'=>$f['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$f['size'][$i]??0];
        return $out;
    }
    private function saveAttachments(int $messageId): void {
        $files=$this->normalizedFiles(); if(count($files)>6) throw new \RuntimeException('الحد الأقصى 6 مرفقات في الرسالة الواحدة.');
        foreach($files as $file){
            if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
            if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new \RuntimeException('تعذر رفع أحد المرفقات.');
            if((int)($file['size']??0)>10*1024*1024) throw new \RuntimeException('حجم المرفق يجب ألا يتجاوز 10MB.');
            $finfo=new \finfo(FILEINFO_MIME_TYPE); $mime=(string)$finfo->file((string)$file['tmp_name']); $path=''; $size=0;
            if(in_array($mime,['image/jpeg','image/png','image/webp','image/avif','image/gif'],true)){
                $saved=Upload::imageDetailed($file,true); if(!$saved) continue; $path=(string)$saved['path'];$mime=(string)$saved['mime'];$size=(int)$saved['size_bytes'];
            } elseif($mime==='application/pdf') {
                if(setting('security_upload_validation','1')==='1' && PHP_SAPI!=='cli' && !is_uploaded_file((string)$file['tmp_name'])) throw new \RuntimeException('تعذر التحقق من المرفق.');
                $dir=BASE_PATH.'/uploads/editorial/'.date('Y/m'); if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new \RuntimeException('تعذر إنشاء مجلد المرفقات.');
                $name=bin2hex(random_bytes(16)).'.pdf';$dest=$dir.'/'.$name;if(!move_uploaded_file((string)$file['tmp_name'],$dest))throw new \RuntimeException('تعذر حفظ ملف PDF.');
                $path='/uploads/editorial/'.date('Y/m').'/'.$name;$size=(int)(@filesize($dest)?:0);
            } else throw new \RuntimeException('المرفقات المسموحة: الصور وPDF فقط.');
            Database::insert('INSERT INTO editorial_message_attachments(message_id,path,original_name,mime_type,size_bytes) VALUES(?,?,?,?,?)',[$messageId,$path,mb_substr((string)($file['name']??'file'),0,240),$mime,$size]);
        }
    }
    private function storeMessage(int $threadId,int $senderId,string $body): ?int {
        $body=$this->cleanBody($body);$hasFiles=false;foreach($this->normalizedFiles() as $f){if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$hasFiles=true;break;}}
        if($body===''&&!$hasFiles)return null;
        $messageId=Database::insert('INSERT INTO editorial_messages(thread_id,sender_id,body) VALUES(?,?,?)',[$threadId,$senderId,$body]);
        try{$this->saveAttachments($messageId);}catch(\Throwable $e){Database::exec('DELETE FROM editorial_messages WHERE id=?',[$messageId]);throw $e;}
        Database::exec("UPDATE editorial_threads SET updated_at=NOW(),status='open' WHERE id=?",[$threadId]);return $messageId;
    }

    public function index(): void {
        $u=$this->user();$staff=$this->staff($u);$threadId=(int)($_GET['thread']??0);$base=$staff?'/admin':'/writer';
        $q=trim((string)($_GET['q']??''));$state=(string)($_GET['state']??'open');$writerFilter=(int)($_GET['writer']??0);
        $sql="SELECT t.*,u.name writer_name,u.username,u.avatar writer_avatar,(SELECT body FROM editorial_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) last_message,(SELECT created_at FROM editorial_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) last_message_at,(SELECT COUNT(*) FROM editorial_messages m WHERE m.thread_id=t.id AND m.sender_id<>? AND m.read_at IS NULL) unread FROM editorial_threads t JOIN users u ON u.id=t.writer_id";
        $params=[(int)$u['id']];$where=[];
        if($staff){
            if($writerFilter>0){$where[]='t.writer_id=?';$params[]=$writerFilter;}
            if($q!==''){$like='%'.$q.'%';$where[]='(u.name LIKE ? OR u.username LIKE ? OR t.subject LIKE ? OR EXISTS(SELECT 1 FROM editorial_messages mx WHERE mx.thread_id=t.id AND mx.body LIKE ?))';array_push($params,$like,$like,$like,$like);}
            if($state==='archived')$where[]="t.status='archived'";elseif($state==='unread')$where[]='EXISTS(SELECT 1 FROM editorial_messages mu WHERE mu.thread_id=t.id AND mu.sender_id<>? AND mu.read_at IS NULL)';else $where[]="t.status='open'";
            if($state==='unread')$params[]=(int)$u['id'];
        } else {$where[]='t.writer_id=?';$params[]=(int)$u['id'];if($state==='archived')$where[]="t.status='archived'";else $where[]="t.status='open'";}
        $threads=Database::all($sql.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY t.updated_at DESC LIMIT 250',$params);
        if(!$threadId&&$threads)$threadId=(int)$threads[0]['id'];$thread=null;$messages=[];$attachments=[];
        if($threadId){
            $thread=Database::one("SELECT t.*,u.name writer_name,u.username,u.avatar writer_avatar,(SELECT title FROM article_translations WHERE article_id=t.article_id ORDER BY id LIMIT 1) article_title,(SELECT title FROM story_submissions ss WHERE ss.id=t.story_submission_id AND ss.author_user_id=t.writer_id LIMIT 1) story_submission_title FROM editorial_threads t JOIN users u ON u.id=t.writer_id WHERE t.id=?",[$threadId]);
            if(!$thread||(!$staff&&(int)$thread['writer_id']!==(int)$u['id'])){http_response_code(403);exit('غير مصرح.');}
            Database::exec('UPDATE editorial_messages SET read_at=COALESCE(read_at,NOW()) WHERE thread_id=? AND sender_id<>?',[$threadId,$u['id']]);
            $messages=Database::all('SELECT m.*,u.name sender_name,u.avatar sender_avatar,u.role sender_role FROM editorial_messages m JOIN users u ON u.id=m.sender_id WHERE m.thread_id=? ORDER BY m.id',[$threadId]);
            try{foreach(Database::all('SELECT a.* FROM editorial_message_attachments a JOIN editorial_messages m ON m.id=a.message_id WHERE m.thread_id=? ORDER BY a.id',[$threadId]) as $a)$attachments[(int)$a['message_id']][]=$a;}catch(\Throwable){}
        }
        $writers=$staff?Database::all("SELECT id,name,username FROM users WHERE role IN ('writer','trusted_writer') AND status='active' ORDER BY name"):[];$writerSubmissions=Database::all("SELECT id,title,status,author_user_id FROM story_submissions WHERE author_user_id IS NOT NULL ORDER BY created_at DESC LIMIT 300");$writerSubmissions=$staff?[]:Database::all("SELECT id,title,created_at,status FROM story_submissions WHERE author_user_id=? ORDER BY created_at DESC LIMIT 100",[(int)$u['id']]);
        View::render('editorial/messages',compact('u','staff','threads','thread','messages','attachments','writers','writerSubmissions','base','q','state','writerFilter')+['title'=>$staff?'مراسلات الكتّاب':'مراسلات فريق التحرير'],'dashboard');
    }

    public function create():void {
        $u=$this->user();if(!$this->staff($u)){http_response_code(403);exit;}Csrf::enforce();$w=(int)($_POST['writer_id']??0);$s=mb_substr(trim((string)($_POST['subject']??'مراسلة مع الكاتب')),0,190);
        if(!Database::one("SELECT id FROM users WHERE id=? AND role IN ('writer','trusted_writer')",[$w])){flash('error','الكاتب غير موجود.');redirect('/admin/editorial-messages');}
        $id=Database::insert('INSERT INTO editorial_threads(writer_id,article_id,subject,created_by) VALUES(?,NULL,?,?)',[$w,$s?:'مراسلة مع الكاتب',$u['id']]);redirect('/admin/editorial-messages?thread='.$id);
    }
    public function writerStart():void {
        $u=$this->user();if($this->staff($u)){http_response_code(403);exit;}Csrf::enforce();$subject=mb_substr(trim((string)($_POST['subject']??'رسالة إلى فريق التحرير')),0,190);$body=(string)($_POST['body']??'');$submissionId=(int)($_POST['story_submission_id']??0);if($submissionId>0&&!Database::one('SELECT id FROM story_submissions WHERE id=? AND author_user_id=?',[$submissionId,(int)$u['id']]))$submissionId=0;
        $admin=Database::one("SELECT id FROM users WHERE role IN ('admin','editor') AND status='active' ORDER BY (role='admin') DESC,id ASC LIMIT 1");if(!$admin){flash('error','لا يوجد محرر متاح حاليًا.');redirect('/writer/editorial-messages');}
        $id=Database::insert('INSERT INTO editorial_threads(writer_id,article_id,story_submission_id,subject,created_by) VALUES(?,NULL,?,?,?)',[$u['id'],$submissionId?:null,$subject?:'رسالة إلى فريق التحرير',$admin['id']]);
        try{$this->storeMessage($id,(int)$u['id'],$body);}catch(\Throwable $e){Database::exec('DELETE FROM editorial_threads WHERE id=?',[$id]);flash('error',$e->getMessage());redirect('/writer/editorial-messages');}
        $preview=mb_substr(trim(strip_tags($this->cleanBody($body))),0,180)?:'أرسل الكاتب مرفقًا جديدًا.';try{Database::insert('INSERT INTO notifications(user_id,type,title,message,link) VALUES(?,?,?,?,?)',[$admin['id'],'editorial_message','رسالة جديدة من كاتب',$preview,'/admin/editorial-messages?thread='.$id]);}catch(\Throwable){}
        flash('success','تم إرسال رسالتك إلى فريق التحرير.');redirect('/writer/editorial-messages?thread='.$id);
    }
    public function send():void {
        $u=$this->user();$staff=$this->staff($u);Csrf::enforce();$id=(int)($_POST['thread_id']??0);$body=(string)($_POST['body']??'');$submissionId=(int)($_POST['story_submission_id']??0);if($submissionId>0&&!Database::one('SELECT id FROM story_submissions WHERE id=? AND author_user_id=?',[$submissionId,(int)$u['id']]))$submissionId=0;$t=Database::one('SELECT * FROM editorial_threads WHERE id=?',[$id]);if(!$t||(!$staff&&(int)$t['writer_id']!==(int)$u['id'])){http_response_code(403);exit;}
        try{$mid=$this->storeMessage($id,(int)$u['id'],$body);}catch(\Throwable $e){flash('error',$e->getMessage());redirect(($staff?'/admin':'/writer').'/editorial-messages?thread='.$id);}
        if($mid){$target=$staff?(int)$t['writer_id']:(int)$t['created_by'];$preview=mb_substr(trim(strip_tags($this->cleanBody($body))),0,180)?:'تم إرسال مرفق جديد.';try{Database::insert('INSERT INTO notifications(user_id,type,title,message,link) VALUES(?,?,?,?,?)',[$target,'editorial_message','رسالة تحريرية جديدة',$preview,($staff?'/writer':'/admin').'/editorial-messages?thread='.$id]);}catch(\Throwable){}}
        redirect(($staff?'/admin':'/writer').'/editorial-messages?thread='.$id);
    }
    public function archive(): void {
        $u=$this->user();if(!$this->staff($u)){http_response_code(403);exit;}Csrf::enforce();$id=(int)($_POST['thread_id']??0);$action=(string)($_POST['action']??'archive');
        Database::exec("UPDATE editorial_threads SET status=? WHERE id=?",[$action==='open'?'open':'archived',$id]);audit_log('editorial_thread_'.$action,'editorial_thread',$id,'');redirect('/admin/editorial-messages'.($action==='open'?'?thread='.$id:'?state=archived'));
    }
}
