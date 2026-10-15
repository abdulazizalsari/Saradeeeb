<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev') throw new RuntimeException('HTTP tests require the isolated development database.');
if(!extension_loaded('curl')) throw new RuntimeException('Enable the PHP curl extension.');
$base='http://127.0.0.1:8001';$ids=[];$passed=0;
function vipHttpExpect(bool $ok,string $label):void{global $passed;if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS: {$label}\n";}
function vipClient():CurlHandle{$h=curl_init();curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>20]);return $h;}
function vipHttp(CurlHandle $h,string $path,?array $post=null):array{global $base;curl_setopt($h,CURLOPT_URL,$base.$path);curl_setopt($h,CURLOPT_POST,$post!==null);if($post!==null)curl_setopt($h,CURLOPT_POSTFIELDS,http_build_query($post));$body=curl_exec($h);if($body===false)throw new RuntimeException(curl_error($h));return[(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE),(string)$body,(string)curl_getinfo($h,CURLINFO_REDIRECT_URL)];}
function vipToken(string $html):string{if(!preg_match('/name="_token" value="([^"]+)"/',$html,$m))throw new RuntimeException('CSRF token missing');return html_entity_decode($m[1],ENT_QUOTES);}
function vipLogin(CurlHandle $client,string $email,string $password,string $expected):void{[$status,$html]=vipHttp($client,'/login');vipHttpExpect($status===200,'login form available');[$status,,$redirect]=vipHttp($client,'/login',['_token'=>vipToken($html),'email'=>$email,'password'=>$password]);vipHttpExpect($status===302&&str_ends_with($redirect,$expected),'account redirects to its protected dashboard');}

$vip=vipClient();$regular=vipClient();$admin=vipClient();$suffix=bin2hex(random_bytes(5));$password=bin2hex(random_bytes(12));
try{
    $ids[]=($vipId=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,writer_verified,writer_vip) VALUES(?,?,?,?,'writer','active',1,1)",['كاتب VIP','vip'.$suffix,'vip'.$suffix.'@example.invalid',password_hash($password,PASSWORD_DEFAULT)]));
    $ids[]=($regularId=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,writer_vip) VALUES(?,?,?,?,'writer','active',0)",['كاتب عادي','regular'.$suffix,'regular'.$suffix.'@example.invalid',password_hash($password,PASSWORD_DEFAULT)]));
    $ids[]=($adminId=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,two_factor_enabled) VALUES(?,?,?,?,'admin','active',0)",['مدير اختبار','admin'.$suffix,'admin'.$suffix.'@example.invalid',password_hash($password,PASSWORD_DEFAULT)]));
    vipLogin($vip,'vip'.$suffix.'@example.invalid',$password,'/writer');
    vipLogin($regular,'regular'.$suffix.'@example.invalid',$password,'/writer');
    vipLogin($admin,'admin'.$suffix.'@example.invalid',$password,'/admin');
    [$status,$html]=vipHttp($vip,'/writer');vipHttpExpect($status===200&&str_contains($html,'class="vip-studio"')&&str_contains($html,'استديو الكاتب'),'eligible writer receives premium studio');
    [$status,$html]=vipHttp($regular,'/writer');vipHttpExpect($status===200&&!str_contains($html,'class="vip-studio"')&&str_contains($html,'writer-dashboard-identity'),'ordinary writer keeps existing dashboard');
    [$status]=vipHttp($vip,'/admin/writers/'.$vipId);vipHttpExpect($status===403,'writer cannot access VIP administration');
    [$status,$html]=vipHttp($admin,'/admin/writers/'.$vipId);vipHttpExpect($status===200&&str_contains($html,'name="writer_vip"'),'administrator sees VIP entitlement control');
    $token=vipToken($html);
    $payload=['_token'=>$token,'id'=>$vipId,'name'=>'كاتب VIP','username'=>'vip'.$suffix,'email'=>'vip'.$suffix.'@example.invalid','phone'=>'','role'=>'writer','status'=>'active','bio'=>'','professional_title'=>'','location'=>'','cv_summary'=>'','website'=>'','linkedin'=>'','portfolio_url'=>'','contact_email'=>'','writer_verified'=>'1','writer_vip'=>'1'];
    [$status]=vipHttp($admin,'/admin/writers/save',$payload);vipHttpExpect($status===302&&(int)(Database::one('SELECT writer_vip FROM users WHERE id=?',[$vipId])['writer_vip']??0)===1,'administrator can persist VIP entitlement');
    echo "{$passed} HTTP VIP writer studio checks passed.\n";
}finally{
    foreach($ids as $id){try{Database::exec('DELETE FROM notifications WHERE user_id=?',[$id]);}catch(Throwable){}try{Database::exec('DELETE FROM audit_logs WHERE user_id=?',[$id]);}catch(Throwable){}try{Database::exec('DELETE FROM writer_profiles WHERE user_id=?',[$id]);}catch(Throwable){}try{Database::exec('DELETE FROM users WHERE id=?',[$id]);}catch(Throwable){}}
    curl_close($vip);curl_close($regular);curl_close($admin);
}
