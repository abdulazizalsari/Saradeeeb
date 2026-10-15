<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev') throw new RuntimeException('HTTP book tests require the isolated development database.');
if(!extension_loaded('curl')) throw new RuntimeException('Enable the PHP curl extension.');
$base='http://127.0.0.1:8001';$ids=['users'=>[],'requests'=>[],'agreements'=>[],'books'=>[],'media'=>[]];$passed=0;
function checkBook(bool $ok,string $label):void {global $passed;if(!$ok)throw new RuntimeException($label);$passed++;echo "PASS: $label\n";}
function clientBook():CurlHandle {$h=curl_init();curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>30]);return $h;}
function httpBook(CurlHandle $h,string $path,?array $post=null):array {global $base;curl_setopt($h,CURLOPT_URL,$base.$path);curl_setopt($h,CURLOPT_POST,$post!==null);if($post!==null){$multipart=false;foreach($post as $v)if($v instanceof CURLFile)$multipart=true;curl_setopt($h,CURLOPT_POSTFIELDS,$multipart?$post:http_build_query($post));} $body=curl_exec($h);if($body===false)throw new RuntimeException(curl_error($h));return [(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE),(string)$body,(string)curl_getinfo($h,CURLINFO_REDIRECT_URL)];}
function csrfBook(string $html):string {if(!preg_match('/name="_token" value="([^"]+)"/',$html,$m))throw new RuntimeException('CSRF token missing');return html_entity_decode($m[1],ENT_QUOTES);}
function loginBook(CurlHandle $h,string $email,string $password,string $destination):string {[$status,$html]=httpBook($h,'/login');checkBook($status===200,'login form available');$token=csrfBook($html);[$status,,$redirect]=httpBook($h,'/login',['_token'=>$token,'email'=>$email,'password'=>$password]);checkBook($status===302&&str_ends_with($redirect,$destination),'role login redirects correctly');return $token;}
$writerClient=clientBook();$adminClient=clientBook();$pendingClient=clientBook();$suffix=bin2hex(random_bytes(5));$password=bin2hex(random_bytes(12));$coverTemp=tempnam(sys_get_temp_dir(),'saradeeeb-book-cover-');
try {
    $writer=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,writer_verified) VALUES(?,?,?,?, 'writer','active',1)",["Book Writer $suffix","bookwriter$suffix","bookwriter$suffix@example.invalid",password_hash($password,PASSWORD_DEFAULT)]);$ids['users'][]=$writer;
    $admin=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,two_factor_enabled) VALUES(?,?,?,?, 'admin','active',0)",["Book Admin $suffix","bookadmin$suffix","bookadmin$suffix@example.invalid",password_hash($password,PASSWORD_DEFAULT)]);$ids['users'][]=$admin;
    $pending=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,writer_verified) VALUES(?,?,?,?, 'writer','active',0)",["Pending Book Writer $suffix","pendingbook$suffix","pendingbook$suffix@example.invalid",password_hash($password,PASSWORD_DEFAULT)]);$ids['users'][]=$pending;
    $writerToken=loginBook($writerClient,"bookwriter$suffix@example.invalid",$password,'/writer');
    $adminToken=loginBook($adminClient,"bookadmin$suffix@example.invalid",$password,'/admin');
    loginBook($pendingClient,"pendingbook$suffix@example.invalid",$password,'/writer');
    [$status,$pendingHtml]=httpBook($pendingClient,'/writer');checkBook($status===200&&!str_contains($pendingHtml,'بيع كتابي عبر المنصة'),'unapproved writer does not see service button');
    [$status]=httpBook($pendingClient,'/writer/book-service/request',['_token'=>csrfBook($pendingHtml)]);checkBook($status===403,'unapproved writer request denied by server');
    [$status,$html]=httpBook($writerClient,'/writer');checkBook($status===200&&str_contains($html,'بيع كتابي عبر المنصة'),'approved writer sees the one service button');
    $writerToken=csrfBook($html);
    [$status]=httpBook($writerClient,'/writer/book-service/request',['_token'=>$writerToken]);checkBook($status===302,'writer request accepted');
    $request=(int)(Database::one('SELECT id FROM book_service_requests WHERE writer_id=? AND active_slot=1',[$writer])['id']??0);checkBook($request>0,'request persisted');$ids['requests'][]=$request;
    [$status]=httpBook($writerClient,'/writer/book-service/request',['_token'=>$writerToken]);checkBook($status===302&&(int)(Database::one('SELECT COUNT(*) c FROM book_service_requests WHERE writer_id=?',[$writer])['c']??0)===1,'repeat request does not duplicate');
    checkBook((bool)Database::one("SELECT id FROM notifications WHERE user_id=? AND type='book_service' AND link='/admin/books/requests'",[$admin]),'admin notification stored');
    [$status]=httpBook($writerClient,'/admin/books/requests/'.$request);checkBook($status===403,'writer denied admin request');
    [$status,$html]=httpBook($adminClient,'/admin/books/requests/'.$request);checkBook($status===200,'admin opens request');$adminToken=csrfBook($html);
    $offer=['_token'=>$adminToken,'request_id'=>$request,'terms'=>str_repeat('Agreed publication, promotion and author rights. ',2),'fee'=>'12.00','currency'=>'USD','payment_terms'=>'Payment after author approval','promo_scope'=>'Directory and author profile','rights_terms'=>'Author retains original intellectual property','cancellation_terms'=>'Either party may request cancellation','allow_home'=>'1','service_start'=>date('Y-m-d'),'service_end'=>date('Y-m-d',strtotime('+30 days'))];
    [$status]=httpBook($adminClient,'/admin/books/agreements/offer',$offer);checkBook($status===302,'admin sends offer');
    $agreement=(int)(Database::one('SELECT id FROM book_agreements WHERE request_id=?',[$request])['id']??0);checkBook($agreement>0,'agreement persisted');$ids['agreements'][]=$agreement;
    [$status]=httpBook($adminClient,'/writer/book-offer/'.$agreement);checkBook($status===403,'admin cannot accept as author');
    [$status,$html]=httpBook($writerClient,'/writer/book-offer/'.$agreement);checkBook($status===200&&str_contains($html,'أوافق على الاتفاق'),'writer reads offered terms');$writerToken=csrfBook($html);
    [$status]=httpBook($writerClient,'/writer/book-offer/decision',['_token'=>$writerToken,'agreement_id'=>$agreement,'decision'=>'accepted']);checkBook($status===302,'writer accepts agreement');
    $accepted=Database::one('SELECT status,accepted_snapshot,accepted_at FROM book_agreements WHERE id=?',[$agreement]);checkBook($accepted['status']==='accepted'&&!empty($accepted['accepted_snapshot'])&&!empty($accepted['accepted_at']),'acceptance snapshot persisted');
    [$status]=httpBook($adminClient,'/admin/books/agreements/payment',['_token'=>$adminToken,'agreement_id'=>$agreement,'payment_status'=>'verified','payment_reference'=>'test-'.$suffix]);checkBook($status===302,'admin verifies payment');
    file_put_contents($coverTemp,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9V7S0AAAAASUVORK5CYII='));
    $slug='http-book-test-'.$suffix;$bookPost=['_token'=>$adminToken,'agreement_id'=>$agreement,'title'=>'HTTP test book','slug'=>$slug,'description'=>str_repeat('A book description for HTTP verification. ',2),'front_cover_file'=>new CURLFile($coverTemp,'image/png','front.png'),'back_cover_file'=>new CURLFile($coverTemp,'image/png','back.png'),'purchase_url'=>'https://example.org/book','purchase_label'=>'Buy this book','promo_directory'=>'1','promo_profile'=>'1'];
    [$status]=httpBook($adminClient,'/admin/books/save',$bookPost);checkBook($status===302,'admin saves book draft');
    $book=(int)(Database::one('SELECT id FROM books WHERE slug=?',[$slug])['id']??0);checkBook($book>0,'book draft persisted');$ids['books'][]=$book;
    $covers=Database::one('SELECT front_media_id,back_media_id FROM books WHERE id=?',[$book]);$ids['media']=[(int)$covers['front_media_id'],(int)$covers['back_media_id']];checkBook($ids['media'][0]>0&&$ids['media'][1]>0,'front and back covers uploaded into media library');
    [$status]=httpBook($writerClient,'/admin/books/status',['_token'=>$writerToken,'id'=>$book,'status'=>'published']);checkBook($status===403,'writer cannot publish');
    [$status]=httpBook($adminClient,'/admin/books/status',['_token'=>$adminToken,'id'=>$book,'status'=>'published']);checkBook($status===302,'admin publishes book');
    [$status,$html]=httpBook($writerClient,'/books/'.$slug);checkBook($status===200&&str_contains($html,'Buy this book'),'public detail displays configured purchase button');
    $visualDir=(string)(getenv('BOOK_VISUAL_DIR')?:'');
    if($visualDir!=='' && is_dir($visualDir)){
        $chrome='C:/Program Files/Google/Chrome/Application/chrome.exe';
        foreach([375,1280] as $width){
            $shot=$visualDir.'/book-'.$width.'.png';$profile=$visualDir.'/chrome-'.$width;
            $proc=proc_open([$chrome,'--headless=new','--disable-gpu','--no-first-run','--no-sandbox','--user-data-dir='.$profile,'--window-size='.$width.',900','--screenshot='.$shot,$base.'/books/'.$slug],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(is_resource($proc)){stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);}
            checkBook(is_file($shot)&&filesize($shot)>1000,"$width px book screenshot rendered");
        }
    }
    [$status,$html]=httpBook($writerClient,'/books');checkBook($status===200&&str_contains($html,$slug),'book appears in directory');
    [$status,$html]=httpBook($writerClient,'/writer/bookwriter'.$suffix);checkBook($status===200&&str_contains($html,$slug),'book appears in author profile');
    $offer['terms']=str_repeat('Proposed amendment retaining the prior agreement until accepted. ',2);
    [$status]=httpBook($adminClient,'/admin/books/agreements/offer',$offer);checkBook($status===302,'admin proposes amendment');
    $amendment=(int)(Database::one('SELECT id FROM book_agreements WHERE request_id=? ORDER BY version DESC LIMIT 1',[$request])['id']??0);$ids['agreements'][]=$amendment;
    [$status]=httpBook($writerClient,'/books/'.$slug);checkBook($status===200,'prior accepted version remains valid during negotiation');
    [$status]=httpBook($writerClient,'/writer/book-offer/decision',['_token'=>$writerToken,'agreement_id'=>$amendment,'decision'=>'declined']);checkBook($status===302,'author may decline amendment');
    [$status]=httpBook($writerClient,'/books/'.$slug);checkBook($status===200,'declined amendment preserves prior publication');
    [$status]=httpBook($adminClient,'/admin/books/agreements/offer',$offer);checkBook($status===302,'admin sends revised amendment');
    $amendment2=(int)(Database::one('SELECT id FROM book_agreements WHERE request_id=? ORDER BY version DESC LIMIT 1',[$request])['id']??0);$ids['agreements'][]=$amendment2;
    [$status]=httpBook($writerClient,'/writer/book-offer/decision',['_token'=>$writerToken,'agreement_id'=>$amendment2,'decision'=>'accepted']);checkBook($status===302,'author accepts revised terms');
    [$status]=httpBook($writerClient,'/books/'.$slug);checkBook($status===404,'old linked version is hidden after amendment acceptance');
    [$status]=httpBook($adminClient,'/admin/books/status',['_token'=>$adminToken,'id'=>$book,'status'=>'unpublished']);checkBook($status===302,'admin unpublishes');
    [$status]=httpBook($writerClient,'/books/'.$slug);checkBook($status===404,'unpublished detail is private');
    echo "$passed HTTP book checks passed.\n";
} finally {
    foreach($ids['books'] as $id) Database::exec('DELETE FROM books WHERE id=?',[$id]);
    foreach($ids['agreements'] as $id) Database::exec('DELETE FROM book_agreements WHERE id=?',[$id]);
    foreach($ids['requests'] as $id) Database::exec('DELETE FROM book_service_requests WHERE id=?',[$id]);
    foreach($ids['media'] as $id){$m=Database::one('SELECT path FROM media WHERE id=?',[$id]);Database::exec('DELETE FROM media WHERE id=?',[$id]);$file=BASE_PATH.(string)($m['path']??'');$root=realpath(BASE_PATH.'/uploads');$actual=realpath($file);if($root&&$actual&&str_starts_with($actual,$root.DIRECTORY_SEPARATOR))@unlink($actual);}
    foreach($ids['users'] as $id){Database::exec('DELETE FROM notifications WHERE user_id=?',[$id]);Database::exec('DELETE FROM audit_logs WHERE user_id=?',[$id]);Database::exec('DELETE FROM users WHERE id=?',[$id]);}
    @unlink($coverTemp);curl_close($writerClient);curl_close($adminClient);curl_close($pendingClient);
}
