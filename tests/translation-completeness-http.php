<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev')throw new RuntimeException('HTTP tests require the isolated development database.');
if(!extension_loaded('curl'))throw new RuntimeException('Enable PHP curl.');
$base='http://127.0.0.1:8001';$ids=[];$passed=0;
function gateExpect(bool $ok,string $label):void{global $passed;if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS: {$label}\n";}
function gateHttp(CurlHandle $h,string $path,?array $post=null):array{global $base;curl_setopt($h,CURLOPT_URL,$base.$path);curl_setopt($h,CURLOPT_POST,$post!==null);if($post!==null)curl_setopt($h,CURLOPT_POSTFIELDS,http_build_query($post));$body=curl_exec($h);if($body===false)throw new RuntimeException(curl_error($h));return[(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE),(string)$body,(string)curl_getinfo($h,CURLINFO_REDIRECT_URL)];}
function gateToken(string $html):string{if(!preg_match('/name="_token" value="([^"]+)"/',$html,$m))throw new RuntimeException('CSRF token missing');return html_entity_decode($m[1],ENT_QUOTES);}
$client=curl_init();curl_setopt_array($client,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>30]);$suffix=bin2hex(random_bytes(5));$password=bin2hex(random_bytes(12));
try{
 $ids[]=($adminId=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,two_factor_enabled) VALUES(?,?,?,?,'admin','active',0)",['مدير الترجمة','translationadmin'.$suffix,'translationadmin'.$suffix.'@example.invalid',password_hash($password,PASSWORD_DEFAULT)]));
 $articleId=Database::insert("INSERT INTO articles(author_id,status,published_at) VALUES(?,'published',CURRENT_TIMESTAMP)",[$adminId]);
 $source='<p>دخل الكاتب البيت عند منتصف الليل.</p><p>وسمع صوتًا غريبًا خلف الباب.</p>';
 Database::insert("INSERT INTO article_translations(article_id,locale,title,slug,excerpt,content,status,review_state) VALUES(?,?,?,?,?,?,'published','approved')",[$articleId,'ar','البيت المهجور','gate-source-'.$suffix,'قصة عربية أصلية مكتملة.',$source]);
 Database::insert("INSERT INTO article_translations(article_id,locale,title,slug,excerpt,content,status,review_state) VALUES(?,?,?,?,?,?,'draft','review')",[$articleId,'en','The Abandoned House','gate-target-'.$suffix,'A complete translated introduction.','<p>The writer entered the house at midnight.</p><p>وسمع صوتًا غريبًا خلف الباب.</p>']);
 [$status,$html]=gateHttp($client,'/login');gateExpect($status===200,'login form available');[$status,,$redirect]=gateHttp($client,'/login',['_token'=>gateToken($html),'email'=>'translationadmin'.$suffix.'@example.invalid','password'=>$password]);gateExpect($status===302&&str_ends_with($redirect,'/admin'),'administrator login succeeds');
 [$status,$html]=gateHttp($client,'/writer/articles/'.$articleId);gateExpect($status===200&&str_contains($html,'فحص إلزامي قبل النشر'),'editor shows the completeness report area');$token=gateToken($html);
 $post=['_token'=>$token,'article_id'=>$articleId,'translation_locale'=>'en','title'=>['en'=>'The Abandoned House'],'slug'=>['en'=>'gate-target-'.$suffix],'excerpt'=>['en'=>'A complete translated introduction.'],'content'=>['en'=>'<p>The writer entered the house at midnight.</p><p>وسمع صوتًا غريبًا خلف الباب.</p>'],'translation_status'=>['en'=>'published'],'review_state'=>['en'=>'approved']];
 [$status]=gateHttp($client,'/writer/articles/submit',$post);$row=Database::one("SELECT status,review_state,completeness_state FROM article_translations WHERE article_id=? AND locale='en'",[$articleId]);gateExpect($status===302&&$row['status']==='draft'&&$row['completeness_state']==='incomplete','server blocks publication with untranslated paragraph');
 Database::exec("UPDATE article_translations SET status='published',review_state='approved' WHERE article_id=? AND locale='en'",[$articleId]);$publicLeak=Database::one("SELECT id FROM article_translations t WHERE article_id=? AND locale='en' AND ".article_translation_publishable_sql('t'),[$articleId]);gateExpect(!$publicLeak,'public query gate rejects a forcibly published translation that failed completeness validation');
 $post['content']['en']='<p>The writer entered the house at midnight.</p><p>He heard a strange voice behind the door.</p>';
 [$status]=gateHttp($client,'/writer/articles/submit',$post);$row=Database::one("SELECT status,review_state,completeness_state FROM article_translations WHERE article_id=? AND locale='en'",[$articleId]);gateExpect($status===302&&$row['status']==='published'&&$row['review_state']==='approved'&&$row['completeness_state']==='passed','corrected translation publishes only after fresh server validation');
 echo "{$passed} HTTP translation gate checks passed.\n";
}finally{if(isset($articleId))try{Database::exec('DELETE FROM articles WHERE id=?',[$articleId]);}catch(Throwable){}foreach($ids as $id){try{Database::exec('DELETE FROM notifications WHERE user_id=?',[$id]);}catch(Throwable){}try{Database::exec('DELETE FROM audit_logs WHERE user_id=?',[$id]);}catch(Throwable){}try{Database::exec('DELETE FROM users WHERE id=?',[$id]);}catch(Throwable){}}curl_close($client);}
