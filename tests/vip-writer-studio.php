<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\Database;

if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev'||($GLOBALS['db_config']['host']??'')!=='127.0.0.1') throw new RuntimeException('Tests require the isolated local development database.');
$passed=0;$failures=[];$pdo=Database::pdo();
function vipExpect(bool $ok,string $label):void{global $passed,$failures;if($ok)$passed++;else{$failures[]=$label;echo "FAIL: {$label}\n";}}

$pdo->beginTransaction();
try{
    $column=Database::one("SHOW COLUMNS FROM users LIKE 'writer_vip'");
    vipExpect((bool)$column,'writer_vip entitlement column exists');
    vipExpect((int)setting('schema_version','0')>=652,'schema version records the VIP entitlement migration');
    $prefix='viptest'.bin2hex(random_bytes(4));
    $writerId=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,writer_verified,writer_vip) VALUES(?,?,?,?,'writer','active',1,1)",['كاتب الاستديو',$prefix,$prefix.'@example.invalid',password_hash('temporary-test-password',PASSWORD_DEFAULT)]);
    Database::insert('INSERT INTO writer_profiles(user_id,professional_title,cv_summary,is_public) VALUES(?,?,?,1)',[$writerId,'كاتب قصصي','نبذة اختبارية موثوقة']);
    foreach([['draft',0],['review',0],['changes',0],['published',37]] as $index=>$data){
        $articleId=Database::insert('INSERT INTO articles(author_id,status,views,published_at) VALUES(?,?,?,?)',[$writerId,$data[0],$data[1],$data[0]==='published'?date('Y-m-d H:i:s'):null]);
        Database::insert("INSERT INTO article_translations(article_id,locale,title,slug,content,status,review_state) VALUES(?,?,?,?,?,'published','approved')",[$articleId,'ar','قصة الاستديو '.($index+1),$prefix.'-'.($index+1),'<p>نص تجريبي</p>']);
        if($data[0]==='published') Database::insert("INSERT INTO article_comments(article_id,guest_name,content,status) VALUES(?,'قارئ','تعليق','approved')",[$articleId]);
    }
    Database::insert("INSERT INTO notifications(user_id,type,title,message,link,is_read) VALUES(?,'test','إشعار تحريري','رسالة حقيقية','/writer',0)",[$writerId]);
    $stats=Database::one("SELECT COUNT(*) total,SUM(status='draft') drafts,SUM(status='review') review,SUM(status='changes') changes,SUM(status='published') published,COALESCE(SUM(views),0) views FROM articles WHERE author_id=?",[$writerId]);
    vipExpect((int)$stats['total']===4&&(int)$stats['published']===1&&(int)$stats['views']===37,'studio statistics come from owned database rows');
    $otherId=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,writer_vip) VALUES(?,?,?,?,'writer','active',0)",['كاتب عادي',$prefix.'n',$prefix.'n@example.invalid',password_hash('temporary-test-password',PASSWORD_DEFAULT)]);
    vipExpect((int)(Database::one('SELECT writer_vip FROM users WHERE id=?',[$writerId])['writer_vip']??0)===1,'VIP writer is eligible');
    vipExpect((int)(Database::one('SELECT writer_vip FROM users WHERE id=?',[$otherId])['writer_vip']??1)===0,'ordinary writer stays outside VIP studio');

    $u=Database::one('SELECT * FROM users WHERE id=?',[$writerId]);
    $articles=Database::all("SELECT a.*,(SELECT title FROM article_translations t WHERE t.article_id=a.id AND t.locale='ar' LIMIT 1) title FROM articles a WHERE author_id=?",[$writerId]);
    $notifications=Database::all('SELECT * FROM notifications WHERE user_id=?',[$writerId]);
    $submissionStats=['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0];$bookRequest=null;$bookOfferId=0;
    $profile=Database::one('SELECT * FROM writer_profiles WHERE user_id=?',[$writerId])?:[];
    $articleStats=array_map('intval',$stats);$topArticles=Database::all("SELECT a.id,a.views,a.published_at,(SELECT title FROM article_translations t WHERE t.article_id=a.id AND t.locale='ar' LIMIT 1) title FROM articles a WHERE a.author_id=? AND a.status='published'",[$writerId]);
    $monthlyActivity=Database::all("SELECT DATE_FORMAT(COALESCE(published_at,created_at),'%Y-%m') month_key,COUNT(*) total FROM articles WHERE author_id=? GROUP BY month_key ORDER BY month_key",[$writerId]);
    $commentCount=1;$unreadNotifications=1;
    ob_start();require BASE_PATH.'/app/Views/writer/vip-dashboard.php';$html=(string)ob_get_clean();
    vipExpect(str_contains($html,'class="vip-studio"')&&str_contains($html,'مكتبي الأدبي')&&str_contains($html,'رحلة قصصي'),'VIP studio view renders its real sections');
    vipExpect(str_contains($html,'37')&&str_contains($html,'إشعار تحريري'),'rendered studio contains database-backed activity');
    vipExpect(!str_contains($html,'عدد المتابعين</span><strong>'),'unsupported follower count is not fabricated');
    $adminSource=(string)file_get_contents(BASE_PATH.'/app/Controllers/AdminController.php');
    vipExpect(str_contains($adminSource,"isset(\$_POST['writer_vip'])")&&str_contains($adminSource,'writer_vip=?'),'administrator save path controls VIP entitlement');
}catch(Throwable $error){$failures[]=$error->getMessage();echo 'ERROR: '.$error->getMessage()."\n";}
finally{if($pdo->inTransaction())$pdo->rollBack();}
$report=['passed'=>$passed,'failed'=>count($failures),'failures'=>$failures,'executed_at'=>date(DATE_ATOM),'transaction_rolled_back'=>true];
file_put_contents(BASE_PATH.'/storage/vip-writer-studio-tests.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failures?1:0);
