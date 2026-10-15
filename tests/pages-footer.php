<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
use App\Services\ManagedPages;
use App\Services\DatabaseBackup;
if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev') throw new RuntimeException('Only the isolated local development database is supported.');
$prefix='pagetest-'.bin2hex(random_bytes(4)); $passed=0;$failures=[];$ids=[];$userId=0;
$backup=DatabaseBackup::saveSnapshot('before-pages-footer-tests');
$settings=Database::all('SELECT `key`,value FROM settings');
$systemRows=Database::all('SELECT * FROM page_translations WHERE page_id IN ('.implode(',',array_merge(array_values(ManagedPages::ids()),[\App\Services\BehindPage::id()])).')');
$parents=Database::all('SELECT * FROM pages WHERE id IN ('.implode(',',array_merge(array_values(ManagedPages::ids()),[\App\Services\BehindPage::id()])).')');
$http=curl_init(); curl_setopt($http,CURLOPT_COOKIEFILE,'');
function expect(bool $ok,string $label): void {global $passed,$failures;if($ok)$passed++;else{$failures[]=$label;echo "FAIL: $label\n";}}
function request(string $path,?array $post=null): array {global $http;curl_setopt_array($http,[CURLOPT_URL=>'http://127.0.0.1:8001'.$path,CURLOPT_HTTPGET=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>30]);if($post!==null)curl_setopt_array($http,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);$body=curl_exec($http);if($body===false)throw new RuntimeException(curl_error($http));return [(int)curl_getinfo($http,CURLINFO_RESPONSE_CODE),$body];}
function token(string $html): string {if(!preg_match('/name="_token" value="([^"]+)"/',$html,$m))throw new RuntimeException('Missing CSRF token');return $m[1];}
function pathFor(string $locale,string $path): string {return ($locale==='ar'?'':'/'.$locale).'/'.$path;}
function settingWrite(string $key,string $value): void {Database::exec('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]);}
function footer(string $html): string {return substr($html,strpos($html,'<footer class=')?:strlen($html));}
function pagePost(int $id,string $slug,string $locale='ar',string $status='published'): array {global $prefix,$csrf;return ['_token'=>$csrf,'page_id'=>$id,'status'=>'published','sort_order'=>5,'template'=>'standard','show_in_footer'=>1,'footer_column'=>'menu','footer_order'=>5,'footer_label'=>[$locale=>$prefix.' link '.$locale],'title'=>[$locale=>$prefix.' title '.$locale],'slug'=>[$locale=>$slug],'content'=>[$locale=>'<p>'.$prefix.' body '.$locale.'</p>'],'content_mode'=>[$locale=>'visual'],'translation_status'=>[$locale=>$status],'review_state'=>[$locale=>$status==='published'?'approved':'draft'],'show_title'=>[$locale=>1]];}
try{
    [$status]=request('/admin/pages');expect($status===302,'Guest cannot access Pages');
    [$status]=request('/admin/pages/save',['title'=>['ar'=>'test']]);expect(in_array($status,[302,403],true),'Guest cannot save Pages');
    foreach(['ar','en','es','ru'] as $locale){
        foreach(['send-story','writer-application','search','community','newsletter'] as $route){[$status,$html]=request(pathFor($locale,$route));expect($status===200,"System page $locale $route 200");expect(!preg_match('/Warning:|Fatal error:|Notice:/',$html),"System page $locale $route no PHP warnings");}
        [$status,$html]=request(pathFor($locale,'send-story'));expect(str_contains($html,'name="story"')&&str_contains($html,'name="rights"')&&str_contains($html,'multipart/form-data'),"Story form $locale intact");
        [$status,$html]=request(pathFor($locale,'writer-application'));expect(str_contains($html,'name="bio"')&&str_contains($html,'name="phone"'),"Writer form $locale intact");
        [$status,$html]=request(pathFor($locale,'newsletter'));expect(str_contains($html,'name="consent"')&&str_contains($html,'name="email"'),"Newsletter form $locale intact");
    }
    // Real story and subscription persistence without sending notifications or email.
    settingWrite('mail_notifications','0');
    [$status,$html]=request('/send-story');
    [$status]=request('/send-story',['_token'=>token($html),'name'=>$prefix,'email'=>$prefix.'@example.invalid','title'=>$prefix,'story'=>str_repeat('Test story content ',12),'rights'=>1]);
    expect($status===302 && Database::one('SELECT id FROM story_submissions WHERE email=?',[$prefix.'@example.invalid'])!==null,'Story form saves to original table');
    [$status,$html]=request('/newsletter');$nt=token($html);
    [$status]=request('/newsletter/subscribe',['_token'=>$nt,'email'=>$prefix.'@example.invalid','return_to'=>'/newsletter']);
    expect(Database::one('SELECT id FROM newsletter_subscribers WHERE email=?',[$prefix.'@example.invalid'])===null,'Newsletter rejects missing consent');
    [$status]=request('/newsletter/subscribe',['_token'=>$nt,'email'=>$prefix.'@example.invalid','consent'=>1,'return_to'=>'/newsletter']);
    expect($status===302 && Database::one("SELECT id FROM newsletter_subscribers WHERE email=? AND consented_at IS NOT NULL",[$prefix.'@example.invalid'])!==null,'Newsletter saves consent to original table');
    [$status,$html]=request('/writer-application');
    [$status]=request('/writer-application',['_token'=>token($html),'name'=>'','email'=>$prefix.'@example.invalid']);
    expect($status===302 && Database::one('SELECT id FROM writer_applications WHERE email=?',[$prefix.'@example.invalid'])===null,'Writer application original validation active');
    // Reserved .invalid recipient and local SMTP only: validate persistence without external delivery.
    if(!in_array((string)ini_get('SMTP'),['localhost','127.0.0.1',''],true))throw new RuntimeException('Writer submission test requires local-only native mail.');
    settingWrite('smtp_enabled','0');
    [$status,$html]=request('/writer-application');
    [$status]=request('/writer-application',['_token'=>token($html),'name'=>$prefix,'email'=>$prefix.'@example.invalid','phone'=>'1234567890','bio'=>str_repeat('Test author introduction. ',4)]);
    $application=Database::one('SELECT status,verification_token_hash,email_verified_at FROM writer_applications WHERE email=?',[$prefix.'@example.invalid']);
    expect($status===302 && $application!==null && !empty($application['verification_token_hash']) && $application['email_verified_at']===null,'Writer application saves with original email-verification workflow');
    $access=file_get_contents(BASE_PATH.'/storage/dev-access.txt');preg_match('/Email: (.+)/',$access,$email);preg_match('/Password: (.+)/',$access,$password);
    [$status,$html]=request('/login');[$status]=request('/login',['_token'=>token($html),'email'=>trim($email[1]),'password'=>trim($password[1])]);expect($status===302,'Admin login');
    [$status,$html]=request('/admin/pages/new');$csrf=token($html);expect($status===200 && str_contains($html,'footer_column'),'Pages editor has placement fields');
    [$status]=request('/admin/pages/save',['_token'=>$csrf,'title'=>['en'=>'Foreign only'],'content'=>['en'=>'<p>Foreign</p>']]);expect($status===422,'Foreign page without Arabic rejected');
    [$status]=request('/admin/pages/save',['_token'=>$csrf,'translation_locale'=>'en','title'=>['ar'=>'Arabic','en'=>'Foreign'],'content'=>['ar'=>'<p>Arabic</p>','en'=>'<p>Foreign</p>']]);expect($status===422,'New translation-only request cannot skip Arabic insertion');
    $post=pagePost(0,$prefix.'-ar');$post['status']='draft';$post['translation_status']['ar']='draft';$post['review_state']['ar']='draft';
    [$status,$body]=request('/admin/pages/save',$post);$id=(int)(Database::one("SELECT page_id FROM page_translations WHERE locale='ar' AND slug=?",[$prefix.'-ar'])['page_id']??0);if(!$id)throw new RuntimeException('Creation failed: HTTP '.$status.' '.mb_substr(strip_tags($body),0,500));$ids[]=$id;
    expect($status===302,'Create draft page');[$status]=request('/page/'.$prefix.'-ar');expect($status===404,'Draft Arabic page 404');
    $post=pagePost($id,$prefix.'-ar');[$status]=request('/admin/pages/save',$post);expect($status===302,'Publish Arabic page');
    [$status,$html]=request('/page/'.$prefix.'-ar');expect($status===200 && str_contains($html,$prefix.' body ar'),'Published source rendered');expect(str_contains(footer($html),$prefix.' link ar'),'Independent footer label rendered');
    foreach(['en','es','ru'] as $locale){[$status]=request(pathFor($locale,'page/'.$prefix.'-ar'));expect($status===404,"No $locale fallback");[$status,$html]=request(pathFor($locale,''));expect(!str_contains(footer($html),$prefix),"Footer $locale hides untranslated page");}
    $source=Database::one("SELECT * FROM page_translations WHERE page_id=? AND locale='ar'",[$id]);$parent=Database::one('SELECT * FROM pages WHERE id=?',[$id]);
    $en=pagePost($id,$prefix.'-en','en','draft');$en['translation_locale']='en';$en['title']['ar']='Must not change source';$en['content']['ar']='<p>Must not change source</p>';
    [$status]=request('/admin/pages/save',$en);expect($status===302,'Save independent English draft');
    expect(Database::one("SELECT * FROM page_translations WHERE page_id=? AND locale='ar'",[$id])===$source,'Translation preserves every Arabic field');expect(Database::one('SELECT * FROM pages WHERE id=?',[$id])===$parent,'Translation preserves parent fields');
    [$status]=request('/en/page/'.$prefix.'-en');expect($status===404,'English draft 404');
    $en['translation_status']['en']='published';$en['review_state']['en']='approved';[$status]=request('/admin/pages/save',$en);expect($status===302,'Publish independent English');
    [$status,$html]=request('/en/page/'.$prefix.'-en');expect($status===200,'English page available');expect(str_contains(footer($html),$prefix.' link en'),'English footer independent label');
    expect(str_contains($html,'hreflang="ar"')&&str_contains($html,'hreflang="en"')&&!str_contains($html,'hreflang="ru"'),'Page alternates published translations only');
    $post['content']['ar']='<h2>'.$prefix.' changed content</h2>';[$status]=request('/admin/pages/save',$post);[$status,$html]=request('/page/'.$prefix.'-ar');expect($status===200&&str_contains($html,'changed content'),'Content changes without changing URL');
    // Changing placement and order is stored in settings, with no content/ID rewrite.
    $post['footer_column']='connect';$post['footer_order']=-12;[$status]=request('/admin/pages/save',$post);[$status,$html]=request('/');$dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
    expect($xp->query('//section[contains(@class,"footer-v45-connect")]//a[contains(text(),"'.$prefix.' link ar")]')->length===1,'Footer link moved to connect column');
    $second=pagePost(0,$prefix.'-second');$second['footer_column']='connect';$second['footer_order']=-15;$second['footer_label']['ar']=$prefix.' first';[$status]=request('/admin/pages/save',$second);$secondId=(int)Database::one("SELECT page_id FROM page_translations WHERE locale='ar' AND slug=?",[$prefix.'-second'])['page_id'];$ids[]=$secondId;
    [$status,$html]=request('/');$f=footer($html);expect(strpos($f,$prefix.' first')<strpos($f,$prefix.' link ar'),'Footer order applied');
    $post['slug']['ar']=$prefix.'-renamed';[$status]=request('/admin/pages/save',$post);[$status]=request('/page/'.$prefix.'-ar');expect($status===301,'Old slug preserves 301');[$status]=request('/page/'.$prefix.'-renamed');expect($status===200,'Renamed URL available');
    $en['translation_status']['en']='draft';$en['review_state']['en']='draft';[$status]=request('/admin/pages/save',$en);[$status]=request('/en/page/'.$prefix.'-en');expect($status===404,'Unpublished translation 404');[$status]=request('/page/'.$prefix.'-renamed');expect($status===200,'Source remains published after English unpublish');
    [$status,$xml]=request('/sitemap.xml');expect(str_contains($xml,$prefix.'-renamed')&&!str_contains($xml,$prefix.'-en'),'Sitemap excludes drafts');
    [$status]=request('/admin/pages/footer',['_token'=>$csrf,'id'=>$id]);[$status,$html]=request('/');expect(!str_contains(footer($html),$prefix.' link ar'),'Hide footer link without deleting page');[$status]=request('/page/'.$prefix.'-renamed');expect($status===200,'Page survives hiding');
    foreach(array_merge(array_values(ManagedPages::ids()),[\App\Services\BehindPage::id()]) as $systemId){[$status]=request('/admin/pages/trash',['_token'=>$csrf,'id'=>$systemId]);expect($status===422,"System page $systemId cannot be deleted");}
    [$status]=request('/admin/pages/trash',['_token'=>$csrf,'id'=>$secondId]);expect($status===302,'Ordinary page soft deletion');expect(Database::one('SELECT id FROM page_translations WHERE page_id=?',[$secondId])!==null,'Deleted page keeps translations');[$status]=request('/admin/pages/restore',['_token'=>$csrf,'id'=>$secondId]);expect($status===302,'Restore ordinary page');
    [$status]=request('/admin/pages/save',array_merge($post,['_token'=>'invalid']));expect($status===419 || $status===403,'CSRF rejects invalid token');
    foreach(['story','write','explore','community','newsletter','behind'] as $key){
        $systemId=$key==='behind'?\App\Services\BehindPage::id():(int)ManagedPages::ids()[$key];
        $before=Database::one("SELECT * FROM page_translations WHERE page_id=? AND locale='ar'",[$systemId]);
        Database::exec("UPDATE page_translations SET status='draft',review_state='draft' WHERE page_id=? AND locale='en'",[$systemId]);
        $route=$key==='behind'?'behind-saradeeb':ManagedPages::ROUTES[$key];[$status]=request('/en/'.$route);expect($status===404,"System $key unpublished English is 404");
        [$status,$html]=request('/en/');$systemUrl=$key==='behind'?'/en/page/':('/en/'.$route);expect(!str_contains(footer($html),$systemUrl),"System $key unpublished removed from footer");
        [$status]=request('/'.$route);expect(in_array($status,[200,301,302],true),"System $key Arabic remains active");expect(Database::one("SELECT * FROM page_translations WHERE page_id=? AND locale='ar'",[$systemId])===$before,"System $key Arabic unchanged");
    }
    $unsafe='<unknown><img src="/test.png" onerror="alert(1)"><a href="javascript:alert(1)">bad</a></unknown><script>alert(1)</script>';
    $clean=sanitize_page_html($unsafe);expect(!str_contains($clean,'onerror')&&!str_contains($clean,'javascript:')&&!str_contains($clean,'<script'),'Nested editor XSS sanitized');
    $raw=pagePost($id,$prefix.'-renamed');$raw['content_mode']['ar']='html';$raw['content']['ar']='<h2>Custom HTML</h2><script>alert(1)</script>';$raw['custom_css']['ar']='h2{color:red}';$raw['custom_js']['ar']='console.log("isolated")';[$status]=request('/admin/pages/save',$raw);[$status,$html]=request('/page/'.$prefix.'-renamed');expect($status===200&&str_contains($html,'sandbox="allow-scripts')&&!str_contains($html,'allow-same-origin'),'Advanced code preserved in isolated iframe');
    [$status,$html]=request('/admin/footer');expect($status===200&&str_contains($html,'footer_page_placement')&&str_contains($html,'footer_copy[en]'),'Footer editor multilingual placement controls');
    $css=file_get_contents(BASE_PATH.'/assets/css/app.css');expect(str_contains($css,'@media(max-width:650px)')&&str_contains($css,'grid-template-columns:1fr!important'),'Existing mobile footer single-column rules retained (static check)');
    // Authenticated writer must not gain Page management access.
    $pw=bin2hex(random_bytes(12));$userId=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status) VALUES(?,?,?,?,'writer','active')",[$prefix,$prefix,$prefix.'-writer@example.invalid',password_hash($pw,PASSWORD_DEFAULT)]);
    curl_close($http);$http=curl_init();curl_setopt($http,CURLOPT_COOKIEFILE,'');[$status,$html]=request('/login');request('/login',['_token'=>token($html),'email'=>$prefix.'-writer@example.invalid','password'=>$pw]);[$status]=request('/admin/pages');expect($status===403 || $status===302,'Writer cannot access Pages');
}catch(Throwable $e){$failures[]=$e->getMessage();echo 'ERROR: '.$e->getMessage()."\n";}
finally{
    foreach($systemRows as $row){$values=$row;$rowId=$values['id'];unset($values['id']);$set=implode(',',array_map(fn($key)=>'`'.$key.'`=?',array_keys($values)));Database::exec('UPDATE page_translations SET '.$set.' WHERE id=?',array_merge(array_values($values),[$rowId]));}
    foreach($parents as $row){Database::exec('UPDATE pages SET status=?,sort_order=?,template=?,deleted_at=?,updated_at=? WHERE id=?',[$row['status'],$row['sort_order'],$row['template'],$row['deleted_at'],$row['updated_at'],$row['id']]);}
    $originalKeys=array_column($settings,'key');foreach(Database::all('SELECT `key` FROM settings') as $row)if(!in_array($row['key'],$originalKeys,true))Database::exec('DELETE FROM settings WHERE `key`=?',[$row['key']]);foreach($settings as $row)settingWrite($row['key'],$row['value']);
    foreach($ids as $id){Database::exec('DELETE FROM page_translations WHERE page_id=?',[$id]);Database::exec('DELETE FROM pages WHERE id=?',[$id]);}
    Database::exec('DELETE FROM redirects WHERE source_path LIKE ?',['%'.$prefix.'%']);Database::exec('DELETE FROM story_submissions WHERE email=?',[$prefix.'@example.invalid']);Database::exec('DELETE FROM newsletter_subscribers WHERE email=?',[$prefix.'@example.invalid']);Database::exec('DELETE FROM writer_applications WHERE email=?',[$prefix.'@example.invalid']);
    if($userId)Database::exec('DELETE FROM users WHERE id=?',[$userId]);
    if($http)curl_close($http);
}
$result=['passed'=>$passed,'failed'=>count($failures),'failures'=>$failures,'backup'=>$backup,'visual_mobile_check'=>'Not available; responsive CSS and HTML checked','writer_email_check'=>'Application persistence and verification token checked with reserved .invalid address; external email delivery not tested'];file_put_contents(BASE_PATH.'/storage/page-footer-tests.json',json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
