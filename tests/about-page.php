<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
use App\Services\AboutPage;
use App\Services\BehindPage;
if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev') throw new RuntimeException('About tests require the isolated development database.');
if(!extension_loaded('curl')) throw new RuntimeException('Enable PHP curl.');
$base='http://127.0.0.1:8001'; $passed=0; $locale='ru'; $pageId=BehindPage::id();
function aboutExpect(bool $ok,string $label):void{global $passed;if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS: {$label}\n";}
function aboutRequest(CurlHandle $h,string $path,?array $post=null):array{global $base;curl_setopt($h,CURLOPT_URL,$base.$path);curl_setopt($h,CURLOPT_POST,$post!==null);if($post!==null)curl_setopt($h,CURLOPT_POSTFIELDS,http_build_query($post));$body=curl_exec($h);if($body===false)throw new RuntimeException(curl_error($h));return[(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE),(string)$body,(string)curl_getinfo($h,CURLINFO_REDIRECT_URL)];}
function aboutToken(string $html):string{if(!preg_match('/name="_token" value="([^"]+)"/',$html,$m))throw new RuntimeException('CSRF token missing');return html_entity_decode($m[1],ENT_QUOTES);}
function restoreRows(string $table,array $rows):void{foreach($rows as $row){$cols=array_keys($row);$quoted=array_map(static fn($c)=>'`'.$c.'`',$cols);Database::exec('INSERT INTO `'.$table.'`('.implode(',',$quoted).') VALUES('.implode(',',array_fill(0,count($cols),'?')).')',array_values($row));}}
$versions=Database::all('SELECT * FROM about_page_versions WHERE page_id=? AND locale=?',[$pageId,$locale]);
$translation=Database::one('SELECT * FROM page_translations WHERE page_id=? AND locale=?',[$pageId,$locale]);
$credentials=(string)file_get_contents(dirname(__DIR__).'/storage/dev-access.txt');
preg_match('/Email:\s*(\S+)/',$credentials,$emailMatch);preg_match('/Password:\s*(\S+)/',$credentials,$passwordMatch);
$client=curl_init();curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>30]);
try{
 [$status]=aboutRequest($client,'/admin/about');aboutExpect($status===302,'guest is redirected away from About administration');
 $writerPassword=bin2hex(random_bytes(10));$writerId=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,two_factor_enabled) VALUES(?,?,?,?,'writer','active',0)",['About test writer','aboutwriter'.bin2hex(random_bytes(4)),'about-writer-'.bin2hex(random_bytes(4)).'@example.invalid',password_hash($writerPassword,PASSWORD_DEFAULT)]);$writer=Database::one('SELECT email FROM users WHERE id=?',[$writerId]);$writerClient=curl_init();curl_setopt_array($writerClient,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>30]);[$writerStatus,$writerLogin]=aboutRequest($writerClient,'/login');[$writerStatus]=aboutRequest($writerClient,'/login',['_token'=>aboutToken($writerLogin),'email'=>$writer['email'],'password'=>$writerPassword]);[$writerStatus]=aboutRequest($writerClient,'/admin/about');aboutExpect(in_array($writerStatus,[302,403],true),'writer cannot access About administration');curl_close($writerClient);
 [$status,$html]=aboutRequest($client,'/login');aboutExpect($status===200,'login page is available');
 [$status,,$redirect]=aboutRequest($client,'/login',['_token'=>aboutToken($html),'email'=>$emailMatch[1]??'','password'=>$passwordMatch[1]??'']);aboutExpect($status===302&&str_ends_with($redirect,'/admin'),'administrator signs in');
 [$status,$html]=aboutRequest($client,'/admin/about?locale='.$locale);aboutExpect($status===200&&str_contains($html,'التعريف بمنصة سراديب'),'administrator can open structured About editor');$token=aboutToken($html);
 $current=AboutPage::editable($locale);$fields=$current['fields'];$marker='ABOUT-DRAFT-'.bin2hex(random_bytes(4));$fields['vision']='<p>'.$marker.'</p>';$fields['goals']='';
 $post=['_token'=>$token,'locale'=>$locale,'fields'=>$fields,'seo_title'=>'Saradeeeb About Test','seo_description'=>'Private draft test','image_path'=>$current['image_path']??'','image_alt'=>$current['image_alt']??''];
 [$status]=aboutRequest($client,'/admin/about/save',$post);aboutExpect($status===302&&AboutPage::version($locale,'draft')!==null,'draft saves separately');
 $publicPath='/'.$locale.'/page/'.rawurlencode((string)$translation['slug']);[$status,$publicBefore]=aboutRequest($client,$publicPath);aboutExpect($status===200&&!str_contains($publicBefore,$marker),'draft stays private on public page');
 [$status,$preview]=aboutRequest($client,'/admin/about/preview?locale='.$locale);aboutExpect($status===200&&str_contains($preview,$marker)&&str_contains($preview,'noindex,nofollow'),'private preview shows draft and is noindex');
 [$status]=aboutRequest($client,'/admin/about/publish',['_token'=>$token,'locale'=>$locale]);[$publicStatus,$publicAfter]=aboutRequest($client,$publicPath);aboutExpect($status===302&&$publicStatus===200&&str_contains($publicAfter,$marker),'publishing replaces the public version');
 aboutExpect(str_contains($publicAfter,'rel="canonical"')&&str_contains($publicAfter,'hreflang="ar"'),'public page exposes canonical and hreflang metadata');
 aboutExpect(!str_contains($publicAfter,'<h3>Цели</h3>'),'empty optional section stays hidden');
 $oldId=(int)($versions[0]['id']??0);[$status]=aboutRequest($client,'/admin/about/restore',['_token'=>$token,'locale'=>$locale,'version_id'=>$oldId]);[$statusPublic,$restored]=aboutRequest($client,$publicPath);aboutExpect($status===302&&$statusPublic===200&&!str_contains($restored,$marker),'previous published version can be restored');
 [$badStatus]=aboutRequest($client,'/admin/about/save',array_replace($post,['_token'=>'invalid']));aboutExpect($badStatus===419,'invalid CSRF token is rejected');
 echo "{$passed} About page checks passed.\n";
}finally{
 Database::exec('DELETE FROM about_page_versions WHERE page_id=? AND locale=?',[$pageId,$locale]);restoreRows('about_page_versions',$versions);
 if($translation){$sets=[];$values=[];foreach($translation as $key=>$value){if($key==='id')continue;$sets[]='`'.$key.'`=?';$values[]=$value;}$values[]=$translation['id'];Database::exec('UPDATE page_translations SET '.implode(',',$sets).' WHERE id=?',$values);}
 if(isset($writerId)){try{Database::exec('DELETE FROM audit_logs WHERE user_id=?',[$writerId]);}catch(Throwable){}try{Database::exec('DELETE FROM users WHERE id=?',[$writerId]);}catch(Throwable){}}
 curl_close($client);
}
