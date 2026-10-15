<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\GoogleOAuth;
use App\Core\View;

final class AuthController {
    public function googleStart(string $locale='ar'): void {
        if(!valid_locale($locale)) $locale=default_locale();
        if(!GoogleOAuth::enabled()){
            flash('error',$locale==='ar'?'تسجيل الدخول باستخدام Google غير متاح حاليًا.':'Google sign-in is not configured yet.');
            redirect(locale_url($locale,'login'));
        }
        try { header('Location: '.GoogleOAuth::authorizationUrl($locale),true,302); exit; }
        catch(\Throwable) { flash('error',$locale==='ar'?'تعذر بدء تسجيل الدخول باستخدام Google.':'Google sign-in could not be started.'); redirect(locale_url($locale,'login')); }
    }

    public function googleCallback(): void {
        $fallback=default_locale();
        try {
            $result=GoogleOAuth::complete((string)($_GET['code']??''),(string)($_GET['state']??''));
            $locale=valid_locale((string)($result['locale']??''))?(string)$result['locale']:$fallback;
            if(Auth::twoFactorPending()){ $_SESSION['remember_after_two_factor']=false; redirect('/two-factor'); }
            $u=Auth::user();
            if($u && in_array($u['role'],['admin','editor'],true)) redirect('/admin');
            if($u && in_array($u['role'],['writer','trusted_writer'],true)) redirect('/writer');
            if($u){
                $pendingApp=Database::one("SELECT id FROM writer_applications WHERE user_id=? AND status IN ('pending','under_review','changes_requested') LIMIT 1",[(int)$u['id']]);
                if($pendingApp) redirect('/writer-application/dashboard');
            }
            redirect(locale_url($locale,'community'));
        } catch(\Throwable) {
            flash('error',$fallback==='ar'?'تعذر التحقق من حساب Google. لم يتم إنشاء أي حساب جديد.':'Google sign-in could not be verified. No account was created.');
            redirect(locale_url($fallback,'login'));
        }
    }

    public function loginForm(string $locale='ar'): void {
        if(!valid_locale($locale)) $locale=default_locale();
        View::render('auth/login',compact('locale')+['title'=>tr('login',$locale)],'public');
    }

    public function login(string $locale='ar'): void {
        Csrf::enforce();
        $email=trim((string)($_POST['email']??''));
        $pass=(string)($_POST['password']??'');
        $remember=!empty($_POST['remember_me']);
        if(Auth::attempt($email,$pass)){
            clear_old();
            if(Auth::twoFactorPending()){
                $_SESSION['remember_after_two_factor']=$remember;
                redirect('/two-factor');
            }
            Auth::setRememberMe($remember);
            $u=Auth::user();
            if(in_array($u['role'],['admin','editor'],true)) redirect('/admin');
            if(in_array($u['role'],['writer','trusted_writer'],true)) redirect('/writer');
            $pendingApp=Database::one("SELECT id FROM writer_applications WHERE user_id=? AND status IN ('pending','under_review','changes_requested') LIMIT 1",[(int)$u['id']]);
            if($pendingApp) redirect('/writer-application/dashboard');
            redirect(locale_url($locale,'community'));
        }
        set_old(['email'=>$email]);
        flash('error',auth_ui($locale)['invalid_login']);
        redirect(locale_url($locale,'login'));
    }

    public function registerForm(string $locale='ar'): void {
        if(!valid_locale($locale)) $locale=default_locale();
        View::render('auth/member-register',compact('locale')+['title'=>auth_ui($locale)['register_title']],'public');
    }

    public function register(string $locale='ar'): void {
        Csrf::enforce();
        if(!valid_locale($locale)) $locale=default_locale();
        $A=auth_ui($locale);
        $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
        $key=hash('sha256','community_register|'.$ip);
        if(setting('security_login_throttle','1')==='1') try {
            $th=Database::one('SELECT attempts,last_attempt_at,locked_until FROM auth_throttles WHERE throttle_key=?',[$key]);
            if($th && !empty($th['locked_until']) && strtotime((string)$th['locked_until'])>time()){
                flash('error',$A['register_blocked']);
                redirect(locale_url($locale,'register'));
            }
            $attempts=($th && strtotime((string)$th['last_attempt_at'])>time()-3600)?(int)$th['attempts']:0;
            $attempts++;
            $locked=$attempts>=5?date('Y-m-d H:i:s',time()+3600):null;
            Database::exec("INSERT INTO auth_throttles(throttle_key,attempts,last_attempt_at,locked_until) VALUES(?,?,CURRENT_TIMESTAMP,?) ON DUPLICATE KEY UPDATE attempts=VALUES(attempts),last_attempt_at=CURRENT_TIMESTAMP,locked_until=VALUES(locked_until)",[$key,$attempts,$locked]);
        } catch (\Throwable) {}

        $name=trim((string)($_POST['name']??''));
        $username=slugify((string)($_POST['username']??''));
        $email=mb_strtolower(trim((string)($_POST['email']??'')));
        set_old(['name'=>$name,'username'=>$username,'email'=>$email]);
        if(mb_strlen($name)<2 || mb_strlen($username)<3 || !filter_var($email,FILTER_VALIDATE_EMAIL)){
            flash('error',$A['invalid_register']);
            redirect(locale_url($locale,'register'));
        }
        if(Database::one('SELECT id FROM users WHERE email=? OR username=? LIMIT 1',[$email,$username])){
            flash('error',$A['account_exists']);
            redirect(locale_url($locale,'register'));
        }
        $token=bin2hex(random_bytes(32));
        $algo=in_array('argon2id',password_algos(),true)?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
        $random=password_hash(bin2hex(random_bytes(24)),$algo);
        Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,activation_token_hash,activation_expires_at) VALUES(?,?,?,?,'member','pending',?,?)",[$name,$username,$email,$random,hash('sha256',$token),date('Y-m-d H:i:s',time()+86400)]);
        $link=locale_url($locale,'activate').'?token='.rawurlencode($token);
        $sent=send_site_mail($email,'فعّل حسابك في سراديب',"مرحبًا {$name}،\n\nفعّل بريدك وأنشئ كلمة المرور من الرابط التالي خلال 24 ساعة:\n{$link}\n\nإذا لم تنشئ هذا الحساب فتجاهل الرسالة.");
        clear_old();
        flash('success',$A['register_ok'].((!$sent&&config('debug',false))?' Local preview: '.$link:''));
        redirect(locale_url($locale,'login'));
    }

    public function applicationForm(string $locale='ar'): void {
        if(!valid_locale($locale)) $locale=default_locale();
        View::render('auth/register',compact('locale')+['title'=>auth_ui($locale)['writer_title']],'public');
    }

    public function submitApplication(string $locale='ar'): void {
        Csrf::enforce();
        if(!valid_locale($locale)) $locale=default_locale();
        $A=auth_ui($locale);
        $name=trim((string)($_POST['name']??''));
        $username=preg_replace('/[^A-Za-z0-9_.-]/','',(string)($_POST['username']??''));
        $email=mb_strtolower(trim((string)($_POST['email']??'')));
        $password=(string)($_POST['password']??''); $confirmation=(string)($_POST['password_confirmation']??'');
        $phone=trim((string)($_POST['phone']??''));
        $country=trim((string)($_POST['country']??''));
        $city=trim((string)($_POST['city']??''));
        $topics=trim((string)($_POST['topics']??''));
        $experience=trim((string)($_POST['experience']??''));
        $sampleUrl=trim((string)($_POST['sample_url']??''));
        $bio=trim((string)($_POST['bio']??''));
        set_old(['name'=>$name,'username'=>$username,'email'=>$email,'phone'=>$phone,'country'=>$country,'city'=>$city,'topics'=>$topics,'experience'=>$experience,'sample_url'=>$sampleUrl,'bio'=>$bio]);

        if(setting('writer_applications_enabled','1')!=='1'){
            flash('error','التقديم لعضوية الكاتب غير متاح حاليًا.');
            redirect(locale_url($locale,'writer-application'));
        }
        if(mb_strlen($name)<2){
            flash('error','اكتب الاسم الكامل بحرفين على الأقل.');
            redirect(locale_url($locale,'writer-application'));
        }
        if(!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{2,29}$/',$username)){
            flash('error','اسم المستخدم يجب أن يبدأ بحرف إنجليزي وأن يتكون من 3 إلى 30 رمزًا.');
            redirect(locale_url($locale,'writer-application'));
        }
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
            flash('error','أدخل بريدًا إلكترونيًا صحيحًا.');
            redirect(locale_url($locale,'writer-application'));
        }
        if(mb_strlen($password)<12){
            flash('error','كلمة المرور يجب أن تتكون من 8 أحرف على الأقل.');
            redirect(locale_url($locale,'writer-application'));
        }
        if($password!==$confirmation){
            flash('error','تأكيد كلمة المرور لا يطابق كلمة المرور.');
            redirect(locale_url($locale,'writer-application'));
        }
        if(empty($_POST['consent'])){
            flash('error','يرجى الموافقة على الشروط وسياسة الخصوصية للمتابعة.');
            redirect(locale_url($locale,'writer-application'));
        }
        if(mb_strlen($phone)>60){
            flash('error','رقم الهاتف طويل جدًا.');
            redirect(locale_url($locale,'writer-application'));
        }
        if($bio!=='' && mb_strlen($bio)<10){
            flash('error','إذا أضفت نبذة، اكتب 10 أحرف على الأقل.');
            redirect(locale_url($locale,'writer-application'));
        }
        $duplicate=Database::one('SELECT id FROM users WHERE username=? OR LOWER(name)=LOWER(?) LIMIT 1',[$username,$name]);
        if(!$duplicate && $email!=='') $duplicate=Database::one('SELECT id FROM users WHERE email=? LIMIT 1',[$email]);
        if(!$duplicate) $duplicate=$email!=='' ? Database::one("SELECT id FROM writer_applications WHERE email=? AND status IN ('pending','under_review','changes_requested') LIMIT 1",[$email]) : Database::one("SELECT id FROM writer_applications WHERE username=? AND status IN ('pending','under_review','changes_requested') LIMIT 1",[$username]);
        if($duplicate){
            flash('error','البريد أو اسم المستخدم مستخدم مسبقًا أو لديك طلب قيد المراجعة.'); redirect(locale_url($locale,'writer-application'));
        }
        $algo=in_array('argon2id',password_algos(),true)?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
        // Writer membership does not use email verification; email is retained only
        // as a unique contact field for password recovery.
        $uid=Database::transaction(function() use($name,$username,$email,$password,$phone,$country,$city,$topics,$experience,$sampleUrl,$bio,$algo){
            $uid=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status) VALUES(?,?,?,?,'member','pending')",[$name,$username,$email,password_hash($password,$algo)]);
            $aid=Database::insert("INSERT INTO writer_applications(user_id,username,name,email,phone,country,city,topics,experience,sample_url,bio,terms_accepted_at,status,submitted_at,last_submitted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,'pending',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)",[$uid,$username,$name,$email,$phone,$country,$city,$topics,$experience,$sampleUrl,$bio]);
            Database::insert("INSERT INTO writer_application_history(application_id,to_status,note) VALUES(?,'pending','تم إنشاء الطلب')",[$aid]); return $uid;
        });
        foreach(Database::all("SELECT id FROM users WHERE role='admin' AND status='active'") as $admin){
            try { Database::insert("INSERT INTO notifications(user_id,type,title,message,link,is_read) VALUES(?, 'writer_application','طلب انضمام كاتب جديد','وصل طلب عضوية كاتب جديد إلى الإدارة للمراجعة.','/admin/writer-applications',0)",[(int)$admin['id']]); } catch(\Throwable) {}
        }
        clear_old();
        flash('success','تم إرسال طلبك مباشرة إلى إدارة سراديب. سيظهر القرار في حسابك بعد المراجعة.');
        if(!Auth::loginUserById((int)$uid)) {
            flash('error','تم حفظ الطلب، لكن تعذر بدء جلستك تلقائيًا. سجّل الدخول بنفس بياناتك.');
            redirect(locale_url($locale,'login'));
        }
        flash('success','تم إنشاء حسابك وحفظ طلب العضوية. طلب الانتساب قيد المراجعة.');
        redirect(locale_url($locale,'writer-application/dashboard'));
        /* Retired membership email-verification flow. It is retained below
           only as historical context and is deliberately unreachable. */
        /*
        $verifyLink=locale_url($locale,'verify-writer-application').'?token=';
        $sent=send_site_mail($email,'تأكيد بريد طلب الانضمام إلى سراديب',"مرحبًا {$name}،

لتأكيد بريدك وإرسال طلبك إلى إدارة سراديب افتح الرابط التالي خلال 24 ساعة:
{$verifyLink}

إذا لم ترسل هذا الطلب فتجاهل الرسالة.");
        clear_old();
        flash('success',$A['application_ok'].((!$sent&&config('debug',false))?' Local preview: '.$verifyLink:''));
        redirect(locale_url($locale,'writer-application'));
        */
    }


    public function verifyWriterApplication(string $locale='ar'): void {
        if(!valid_locale($locale)) $locale=default_locale();
        $A=auth_ui($locale);
        $token=(string)($_GET['token']??'');
        if($token===''||strlen($token)<32){ flash('error',$A['invalid_link']); redirect(locale_url($locale,'writer-application')); }
        $app=Database::one('SELECT * FROM writer_applications WHERE verification_token_hash=? AND verification_expires_at>CURRENT_TIMESTAMP LIMIT 1',[hash('sha256',$token)]);
        if(!$app){ flash('error',$A['expired_link']); redirect(locale_url($locale,'writer-application')); }
        Database::exec('UPDATE writer_applications SET email_verified_at=CURRENT_TIMESTAMP,verification_token_hash=NULL,verification_expires_at=NULL WHERE id=?',[$app['id']]);
        if(!empty($app['user_id'])) Database::exec('UPDATE users SET email_verified_at=CURRENT_TIMESTAMP,status=\'active\',activation_token_hash=NULL,activation_expires_at=NULL WHERE id=?',[(int)$app['user_id']]);
        // In-app notifications are the reliable queue signal; email remains optional.
        foreach(Database::all("SELECT id FROM users WHERE role='admin' AND status='active'") as $admin){
            Database::insert("INSERT INTO notifications(user_id,type,title,message,link,is_read) VALUES(?, 'writer_application','طلب انضمام كاتب جديد','وصل طلب انضمام جديد إلى كتّاب سراديب بعد تأكيد البريد.','/admin/writer-applications',0)",[(int)$admin['id']]);
        }
        if(false){ // Membership policy: never send application email.
        $target=setting('writer_application_email',setting('contact_email',''));
        if(setting('mail_notifications','0')==='1' && filter_var($target,FILTER_VALIDATE_EMAIL)){
            $body="طلب كاتب مؤكد البريد في سراديب\n\nالاسم: {$app['name']}\nالبريد: {$app['email']}\nالهاتف: {$app['phone']}\nالدولة: {$app['country']}\nالمدينة: {$app['city']}\nالمواضيع: {$app['topics']}\nالخبرة: {$app['experience']}\nنموذج كتابة: {$app['sample_url']}\n\nالنبذة:\n{$app['bio']}\n";
            send_site_mail($target,'طلب انضمام كاتب مؤكد - سراديب',$body);
        }
        }
        flash('success',$A['application_verified']);
        redirect(locale_url($locale,'writer-application'));
    }

    public function writerApplicationStatus(string $locale='ar'): void {
        if(!Auth::check()) redirect(locale_url($locale,'login'));
        $u=Auth::user();
        $application=Database::one('SELECT * FROM writer_applications WHERE user_id=? ORDER BY id DESC LIMIT 1',[(int)$u['id']]);
        if(!$application){ redirect(locale_url($locale,'writer-application')); }
        if(in_array((string)$application['status'],['pending','under_review','changes_requested','rejected','suspended'],true)) redirect(locale_url($locale,'writer-application/dashboard'));
        if((string)$application['status']==='approved' && in_array((string)$u['role'],['writer','trusted_writer'],true)) redirect('/writer');
        $history=Database::all('SELECT h.*,u.name admin_name FROM writer_application_history h LEFT JOIN users u ON u.id=h.admin_id WHERE h.application_id=? ORDER BY h.created_at DESC',[(int)$application['id']]);
        View::render('auth/writer-application-status',compact('application','history','locale')+['title'=>'حالة طلب الكاتب'],'public');
    }

    public function resubmitWriterApplication(string $locale='ar'): void {
        Csrf::enforce(); if(!Auth::check()) redirect(locale_url($locale,'login')); $u=Auth::user();
        $app=Database::one('SELECT * FROM writer_applications WHERE user_id=? ORDER BY id DESC LIMIT 1',[(int)$u['id']]);
        if(!$app || !in_array((string)$app['status'],['changes_requested','rejected'],true)){ redirect('/writer-application/dashboard'); }
        $bio=trim((string)($_POST['bio']??'')); $topics=trim((string)($_POST['topics']??'')); $sample=trim((string)($_POST['sample_url']??''));
        if(mb_strlen($bio)<30){ flash('error','يرجى كتابة نبذة لا تقل عن 30 محرفًا.'); redirect('/writer-application/status'); }
        Database::transaction(function() use($app,$bio,$topics,$sample){ Database::exec("UPDATE writer_applications SET bio=?,topics=?,sample_url=?,status='pending',admin_note=NULL,last_submitted_at=CURRENT_TIMESTAMP WHERE id=?",[$bio,$topics,$sample,(int)$app['id']]); Database::insert("INSERT INTO writer_application_history(application_id,from_status,to_status,note) VALUES(?,?,'pending','إعادة إرسال الطلب')",[(int)$app['id'],$app['status']]); });
        flash('success','تمت إعادة إرسال الطلب للمراجعة.'); redirect('/writer-application/status');
    }

    public function twoFactorForm(): void {
        $pending=Auth::pendingTwoFactorUser(); if(!$pending) redirect('/login');
        View::render('auth/two-factor',['pending'=>$pending,'locale'=>default_locale(),'title'=>'التحقق بخطوتين'],'public');
    }
    public function twoFactor(): void {
        Csrf::enforce(); $u=Auth::pendingTwoFactorUser(); if(!$u) redirect('/login');
        $code=trim((string)($_POST['code']??'')); $ok=totp_verify((string)$u['two_factor_secret'],$code);
        if(!$ok){
            $codes=json_decode((string)($u['two_factor_recovery_codes']??'[]'),true)?:[];
            $needle=hash('sha256',strtoupper(str_replace([' ','-'],'',$code))); $idx=array_search($needle,$codes,true);
            if($idx!==false){ unset($codes[$idx]); Database::exec('UPDATE users SET two_factor_recovery_codes=? WHERE id=?',[json_encode(array_values($codes)),(int)$u['id']]); $ok=true; }
        }
        if(!$ok){ flash('error','رمز التحقق غير صحيح.'); redirect('/two-factor'); }
        Auth::completeTwoFactor(); audit_log('two_factor_login','user',(int)$u['id'],''); redirect('/admin');
    }

    public function activationForm(string $locale='ar'): void {
        if(!valid_locale($locale)) $locale=default_locale();
        $token=(string)($_GET['token']??'');
        $valid=$this->tokenUser($token,'activation_token_hash','activation_expires_at');
        View::render('auth/activate',compact('locale','token','valid')+['title'=>'تفعيل حساب سراديب'],'public');
    }

    public function activate(string $locale='ar'): void {
        Csrf::enforce();
        if(!valid_locale($locale)) $locale=default_locale();
        $token=(string)($_POST['token']??'');
        $u=$this->tokenUser($token,'activation_token_hash','activation_expires_at');
        $password=(string)($_POST['password']??'');
        $confirm=(string)($_POST['password_confirmation']??'');
        if(!$u){ flash('error','رابط التفعيل غير صالح أو انتهت صلاحيته.'); redirect(locale_url($locale,'activate').'?token='.rawurlencode($token)); }
        if(mb_strlen($password)<12 || $password!==$confirm){ flash('error','اكتب كلمة مرور من 12 محرفًا على الأقل وتأكد من تطابقها.'); redirect(locale_url($locale,'activate').'?token='.rawurlencode($token)); }
        $algo=in_array('argon2id',password_algos(),true)?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
        Database::exec(
            'UPDATE users SET password_hash=?,status=\'active\',email_verified_at=CURRENT_TIMESTAMP,activation_token_hash=NULL,activation_expires_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?',
            [password_hash($password,$algo),$u['id']]
        );
        flash('success','تم تأكيد بريدك وإنشاء كلمة المرور. يمكنك تسجيل الدخول الآن.');
        redirect(locale_url($locale,'login'));
    }

    public function forgotForm(string $locale='ar'): void {
        if(!valid_locale($locale)) $locale=default_locale();
        View::render('auth/forgot',compact('locale')+['title'=>auth_ui($locale)['forgot_title']],'public');
    }

    public function forgot(string $locale='ar'): void {
        Csrf::enforce();
        if(!valid_locale($locale)) $locale=default_locale();
        $A=auth_ui($locale);
        $email=mb_strtolower(trim((string)($_POST['email']??'')));
        $u=filter_var($email,FILTER_VALIDATE_EMAIL)?Database::one('SELECT id,name,email FROM users WHERE email=? AND status<>\'blocked\' LIMIT 1',[$email]):null;
        $debugLink='';
        if($u){
            $token=bin2hex(random_bytes(32));
            Database::exec('UPDATE users SET password_reset_token_hash=?,password_reset_expires_at=? WHERE id=?',[hash('sha256',$token),date('Y-m-d H:i:s',time()+3600),$u['id']]);
            $link=locale_url($locale,'reset-password').'?token='.rawurlencode($token);
            $sent=send_site_mail($u['email'],'استعادة كلمة مرور سراديب',"مرحبًا {$u['name']}،\n\nاستخدم الرابط التالي خلال ساعة واحدة لإنشاء كلمة مرور جديدة:\n$link\n\nإذا لم تطلب ذلك فتجاهل الرسالة.");
            if(!$sent && config('debug',false)) $debugLink=$link;
        }
        $message=$A['forgot_ok'];
        if($debugLink!=='') $message.=' Local preview: '.$debugLink;
        flash('success',$message);
        redirect(locale_url($locale,'forgot-password'));
    }

    public function resetForm(string $locale='ar'): void {
        if(!valid_locale($locale)) $locale=default_locale();
        $token=(string)($_GET['token']??'');
        $valid=$this->tokenUser($token,'password_reset_token_hash','password_reset_expires_at');
        View::render('auth/reset',compact('locale','token','valid')+['title'=>auth_ui($locale)['reset_title']],'public');
    }

    public function reset(string $locale='ar'): void {
        Csrf::enforce();
        if(!valid_locale($locale)) $locale=default_locale();
        $token=(string)($_POST['token']??'');
        $u=$this->tokenUser($token,'password_reset_token_hash','password_reset_expires_at');
        $password=(string)($_POST['password']??'');
        $confirm=(string)($_POST['password_confirmation']??'');
        if(!$u){ flash('error','الرابط غير صالح أو انتهت صلاحيته.'); redirect(locale_url($locale,'forgot-password')); }
        if(mb_strlen($password)<12 || $password!==$confirm){ flash('error','كلمة المرور يجب أن تكون 12 محرفًا على الأقل ومتطابقة.'); redirect(locale_url($locale,'reset-password').'?token='.rawurlencode($token)); }
        $algo=in_array('argon2id',password_algos(),true)?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
        Database::exec('UPDATE users SET password_hash=?,password_reset_token_hash=NULL,password_reset_expires_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?',[password_hash($password,$algo),$u['id']]);
        flash('success','تم تغيير كلمة المرور.');
        redirect(locale_url($locale,'login'));
    }

    private function tokenUser(string $token,string $column,string $expiry): ?array {
        if($token==='' || strlen($token)<32) return null;
        $allowed=['activation_token_hash'=>'activation_expires_at','password_reset_token_hash'=>'password_reset_expires_at'];
        if(!isset($allowed[$column]) || $allowed[$column]!==$expiry) return null;
        return Database::one("SELECT id,name,email,role FROM users WHERE {$column}=? AND {$expiry}>CURRENT_TIMESTAMP LIMIT 1",[hash('sha256',$token)]);
    }

    public function stopImpersonation(): void {
        Csrf::enforce();
        if(!Auth::isImpersonating()) redirect('/');
        $adminId=Auth::impersonatorId();
        if(Auth::stopImpersonation()){
            audit_log('admin_impersonate_stop','user',$adminId,'Returned to admin account');
            flash('success','تمت العودة إلى حساب الإدارة.');
            redirect('/admin');
        }
        redirect('/');
    }

    public function logout(): void {
        Csrf::enforce(); Auth::logout(); redirect('/');
    }
}
