<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\Database;

if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev') throw new RuntimeException('Visibility tests require the isolated development database.');
if(!extension_loaded('curl')) throw new RuntimeException('Enable PHP curl.');

$base='http://127.0.0.1:8001';
$passed=0;
function visibilityExpect(bool $ok,string $label):void { global $passed; if(!$ok) throw new RuntimeException($label); $passed++; echo "PASS: {$label}\n"; }
function visibilityRequest(CurlHandle $client,string $path,?array $post=null):array { global $base; curl_setopt($client,CURLOPT_URL,$base.$path); curl_setopt($client,CURLOPT_POST,$post!==null); if($post!==null) curl_setopt($client,CURLOPT_POSTFIELDS,http_build_query($post)); $body=curl_exec($client); if($body===false) throw new RuntimeException(curl_error($client)); return [(int)curl_getinfo($client,CURLINFO_RESPONSE_CODE),(string)$body,(string)curl_getinfo($client,CURLINFO_REDIRECT_URL)]; }
function visibilityToken(string $html):string { if(!preg_match('/name="_token" value="([^"]+)"/',$html,$match)) throw new RuntimeException('CSRF token missing'); return html_entity_decode($match[1],ENT_QUOTES); }

$client=curl_init();
curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>30]);
$suffix=bin2hex(random_bytes(5));
$userId=0;
$articleId=0;

try {
    $userId=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status) VALUES(?,?,?,?,'writer','active')",['Visibility test writer','visibility'.$suffix,'visibility-'.$suffix.'@example.invalid',password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]);
    $articleId=Database::insert("INSERT INTO articles(author_id,status,published_at) VALUES(?,'published',CURRENT_TIMESTAMP)",[$userId]);
    $slug='visibility-'.$suffix;
    Database::insert("INSERT INTO article_translations(article_id,locale,title,slug,excerpt,content,status,review_state) VALUES(?,?,?,?,?,?, 'published','approved')",[$articleId,'ar','مقال اختبار الإخفاء',$slug,'ملخص اختبار','<p>محتوى عربي مكتمل للاختبار.</p>']);

    [$status,$login]=visibilityRequest($client,'/login'); visibilityExpect($status===200,'login page is available');
    $access=(string)file_get_contents(dirname(__DIR__).'/storage/dev-access.txt');
    preg_match('/Email:\s*(\S+)/',$access,$email); preg_match('/Password:\s*(\S+)/',$access,$password);
    [$status,,$redirect]=visibilityRequest($client,'/login',['_token'=>visibilityToken($login),'email'=>$email[1]??'','password'=>$password[1]??'']);
    visibilityExpect($status===302&&str_ends_with($redirect,'/admin'),'administrator signs in');

    [$status,$articles]=visibilityRequest($client,'/admin/articles'); visibilityExpect($status===200,'articles administration is available');
    $token=visibilityToken($articles);
    [$status]=visibilityRequest($client,'/admin/articles/visibility',['_token'=>$token,'id'=>$articleId]);
    visibilityExpect($status===302&&(Database::one('SELECT status FROM articles WHERE id=?',[$articleId])['status']??'')==='archived','published article is hidden as archived');
    [$status]=visibilityRequest($client,'/article/'.$slug); visibilityExpect($status===404,'hidden article is absent from the public route');
    [$status,$articles]=visibilityRequest($client,'/admin/articles');
    [$status]=visibilityRequest($client,'/admin/articles/visibility',['_token'=>visibilityToken($articles),'id'=>$articleId]);
    visibilityExpect($status===302&&(Database::one('SELECT status FROM articles WHERE id=?',[$articleId])['status']??'')==='published','hidden article can be shown again');
    [$status]=visibilityRequest($client,'/article/'.$slug); visibilityExpect($status===200,'shown article returns to the public route');
    echo "{$passed} Article visibility checks passed.\n";
} finally {
    if($articleId) Database::exec('DELETE FROM articles WHERE id=?',[$articleId]);
    if($userId) { try{Database::exec('DELETE FROM audit_logs WHERE user_id=?',[$userId]);}catch(Throwable){} Database::exec('DELETE FROM users WHERE id=?',[$userId]); }
    curl_close($client);
}
