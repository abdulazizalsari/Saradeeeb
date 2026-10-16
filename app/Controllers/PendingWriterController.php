<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Upload;
use App\Core\View;

/** Private workspace for a verified writer applicant. It deliberately never grants writer routes. */
final class PendingWriterController {
    private function applicant(): array {
        $user=Auth::user();
        if (!$user) redirect('/login');
        $app=Database::one('SELECT * FROM writer_applications WHERE user_id=? ORDER BY id DESC LIMIT 1',[(int)$user['id']]);
        if (!$app || in_array((string)$app['status'],['approved'],true)) redirect('/writer');
        if (!in_array((string)($user['status']??''),['active','pending'],true)) {
            flash('error','يرجى تأكيد البريد الإلكتروني قبل فتح مساحة تجهيز الملف.');
            redirect('/writer-application');
        }
        if ((string)$app['status']==='suspended') { http_response_code(403); exit('الحساب موقوف.'); }
        return [$user,$app];
    }

    public function dashboard(string $locale='ar'): void {
        [$u,$application]=$this->applicant();
        $profile=Database::one('SELECT * FROM writer_profiles WHERE user_id=?',[(int)$u['id']])?:[];
        $messages=Database::all('SELECT m.*,u.name sender_name FROM writer_application_messages m LEFT JOIN users u ON u.id=m.sender_id WHERE m.application_id=? AND m.visibility=\'applicant\' ORDER BY m.created_at ASC',[(int)$application['id']]);
        $completion=0;
        foreach ([$u['avatar']??'', $application['bio']??'', $application['topics']??'', $profile['professional_title']??'', $profile['cv_summary']??'', $profile['website']??''] as $value) if(trim((string)$value)!=='') $completion+=16;
        View::render('writer/pending-dashboard',compact('u','application','profile','messages','completion','locale')+['title'=>'تجهيز الهوية الأدبية'],'public');
    }

    public function saveProfile(): void {
        [$u,$app]=$this->applicant(); Csrf::enforce();
        $bio=mb_substr(trim((string)($_POST['bio']??'')),0,5000);
        $topics=mb_substr(trim((string)($_POST['topics']??'')),0,500);
        $title=mb_substr(trim((string)($_POST['professional_title']??'')),0,190);
        $summary=mb_substr(trim((string)($_POST['cv_summary']??'')),0,5000);
        $location=mb_substr(trim((string)($_POST['location']??'')),0,190);
        $url=static function(string $value): string { $value=trim($value); return $value===''||filter_var($value,FILTER_VALIDATE_URL)?$value:''; };
        $website=$url((string)($_POST['website']??'')); $linkedin=$url((string)($_POST['linkedin']??'')); $portfolio=$url((string)($_POST['portfolio_url']??''));
        Database::transaction(function() use($u,$app,$bio,$topics,$title,$summary,$location,$website,$linkedin,$portfolio) {
            Database::exec('UPDATE writer_applications SET bio=?,topics=?,country=?,city=?,experience=?,sample_url=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$bio,$topics,mb_substr(trim((string)($_POST['country']??'')),0,120),mb_substr(trim((string)($_POST['city']??'')),0,120),mb_substr(trim((string)($_POST['experience']??'')),0,5000),$portfolio,(int)$app['id']]);
            Database::exec("INSERT INTO writer_profiles(user_id,professional_title,cv_summary,location,website,linkedin,portfolio_url,is_public,show_contact) VALUES(?,?,?,?,?,?,?,0,0) ON DUPLICATE KEY UPDATE professional_title=VALUES(professional_title),cv_summary=VALUES(cv_summary),location=VALUES(location),website=VALUES(website),linkedin=VALUES(linkedin),portfolio_url=VALUES(portfolio_url),is_public=0,show_contact=0",[(int)$u['id'],$title,$summary,$location,$website,$linkedin,$portfolio]);
            Database::exec('UPDATE users SET bio=? WHERE id=?',[mb_substr($bio?:$summary,0,1200),(int)$u['id']]);
        });
        audit_log('pending_writer_profile_update','writer_application',(int)$app['id'],'private profile');
        flash('success','تم حفظ ملفك الخاص. لن يُنشر قبل اعتماد العضوية.'); redirect('/writer-application/dashboard');
    }

    public function uploadAvatar(): void {
        [$u,$app]=$this->applicant(); Csrf::enforce();
        try { $path=Upload::image($_FILES['avatar']??[]); if(!$path) throw new \RuntimeException('اختر صورة صالحة.'); Database::exec('UPDATE users SET avatar=? WHERE id=?',[$path,(int)$u['id']]); audit_log('pending_writer_avatar_update','writer_application',(int)$app['id'],''); flash('success','تم حفظ الصورة في ملفك الخاص.'); }
        catch(\Throwable $e) { flash('error',$e->getMessage()); }
        redirect('/writer-application/dashboard');
    }

    public function sendMessage(): void {
        [$u,$app]=$this->applicant(); Csrf::enforce();
        $body=mb_substr(trim((string)($_POST['body']??'')),0,4000);
        if($body===''){ flash('error','اكتب رسالة أولًا.'); redirect('/writer-application/dashboard'); }
        Database::insert("INSERT INTO writer_application_messages(application_id,sender_id,visibility,body) VALUES(?,?, 'applicant',?)",[(int)$app['id'],(int)$u['id'],$body]);
        foreach(Database::all("SELECT id FROM users WHERE role IN ('admin','editor') AND status='active'") as $admin) Database::insert("INSERT INTO notifications(user_id,type,title,message,link) VALUES(?, 'writer_application_message','رسالة جديدة من متقدم','لدى متقدم الكاتب رسالة جديدة.','/admin/writer-applications')",[(int)$admin['id']]);
        audit_log('pending_writer_message','writer_application',(int)$app['id'],''); flash('success','تم إرسال رسالتك إلى الإدارة.'); redirect('/writer-application/dashboard');
    }

    public function changePassword(): void {
        [$u,$app]=$this->applicant(); Csrf::enforce();
        $current=(string)($_POST['current_password']??''); $next=(string)($_POST['new_password']??''); $confirm=(string)($_POST['new_password_confirmation']??'');
        $row=Database::one('SELECT password_hash FROM users WHERE id=?',[(int)$u['id']]);
        if(!$row || !password_verify($current,(string)$row['password_hash'])) { flash('error','كلمة المرور الحالية غير صحيحة.'); redirect('/writer-application/dashboard'); }
        if(mb_strlen($next)<12 || $next!==$confirm) { flash('error','كلمة المرور الجديدة يجب أن تكون 12 محرفًا على الأقل ومتطابقة.'); redirect('/writer-application/dashboard'); }
        $algo=in_array('argon2id',password_algos(),true)?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
        Database::exec('UPDATE users SET password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[password_hash($next,$algo),(int)$u['id']]);
        audit_log('pending_writer_password_changed','writer_application',(int)$app['id'],''); flash('success','تم تغيير كلمة المرور بنجاح.'); redirect('/writer-application/dashboard');
    }
}
