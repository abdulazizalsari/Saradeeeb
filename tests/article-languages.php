<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
use App\Core\HomeCategoryBrowser;
use App\Services\TranslationExchange;
use App\Services\DatabaseBackup;

if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev' || ($GLOBALS['db_config']['host']??'')!=='127.0.0.1') throw new RuntimeException('Tests require the isolated local development database.');
$base='http://127.0.0.1:8001';
$prefix='langtest-'.bin2hex(random_bytes(4));
$passed=0; $failures=[]; $ids=[]; $authorId=0; $categoryId=0;
$cookie=BASE_PATH.'/storage/'.$prefix.'.cookies';
$httpHandle=curl_init();
curl_setopt($httpHandle,CURLOPT_COOKIEFILE,'');
$originalDefault=Database::one("SELECT value FROM settings WHERE `key`='default_locale'");
$backup=DatabaseBackup::saveSnapshot('before-article-language-tests');
function expect(bool $ok,string $label): void { global $passed,$failures; if($ok){$passed++;}else{$failures[]=$label;echo "FAIL: $label\n";} }
function request(string $path,?array $post=null,bool $authenticated=false): array {
    global $base,$httpHandle;
    $handle=$httpHandle;
    curl_setopt_array($handle,[CURLOPT_URL=>$base.$path,CURLOPT_HTTPGET=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>45]);
    if($post!==null) curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $body=curl_exec($handle); $status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE); $error=curl_error($handle);
    if($body===false) throw new RuntimeException($error);
    return [$status,$body];
}
function token(string $html): string { if(!preg_match('/name="_token" value="([^"]+)"/',$html,$m))throw new RuntimeException('CSRF token missing');return $m[1]; }
function pathFor(string $locale,string $path=''): string { return ($locale==='ar'?'':'/'.$locale).'/'.ltrim($path,'/'); }
function sourceSnapshot(int $id): array { return Database::one("SELECT * FROM article_translations WHERE article_id=? AND locale='ar'",[$id])??[]; }
function addTranslation(int $id,string $locale,string $slug,string $status='published',string $review='approved'): void {
    global $prefix;
    $completeness=($locale==='ar'||$status!=='published'||$review!=='approved')?'complete_ready':'passed';
    $body=$locale==='ar'?'<p>هذا نص عربي أصلي مكتمل للاختبار.</p>':'<p>'.$prefix.' original body '.$locale.'</p>';
    Database::insert('INSERT INTO article_translations(article_id,locale,title,slug,excerpt,content,status,review_state,completeness_state) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$locale,$prefix.' '.$locale.' '.$slug,$slug,$prefix,$body,$status,$review,$completeness]);
}
try {
    $authorId=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status) VALUES(?,?,?,?,'writer','active')",[$prefix,$prefix,$prefix.'@example.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
    $categoryId=Database::insert('INSERT INTO categories(sort_order,active) VALUES(-999,1)');
    foreach(['ar','en','es','fr','ru'] as $locale) Database::insert("INSERT INTO category_translations(category_id,locale,name,slug,status) VALUES(?,?,?,?,'published')",[$categoryId,$locale,$prefix,$prefix.'-'.$locale]);
    $cases=['ar-only'=>['ar'],'ar-en'=>['ar','en'],'ar-ru'=>['ar','ru'],'en-draft'=>['ar'],'en-review'=>['ar'],'foreign-only'=>[]];
    foreach($cases as $name=>$published){
        $id=Database::insert("INSERT INTO articles(author_id,category_id,status,published_at,is_featured,views) VALUES(?,?,'published',CURRENT_TIMESTAMP,1,9999)",[$authorId,$categoryId]);
        $ids[$name]=$id;
        foreach($published as $locale) addTranslation($id,$locale,$prefix.'-'.$name.'-'.$locale);
        if($name==='en-draft')addTranslation($id,'en',$prefix.'-'.$name.'-en','draft','draft');
        if($name==='en-review')addTranslation($id,'en',$prefix.'-'.$name.'-en','published','review');
        if($name==='foreign-only')addTranslation($id,'en',$prefix.'-'.$name.'-en');
    }
    $baselineSource=sourceSnapshot($ids['ar-en']);
    $baselineParent=Database::one('SELECT * FROM articles WHERE id=?',[$ids['ar-en']]);
    if(!in_array('--mutations-only',$argv,true)){
    foreach(['ar','en','es','fr','ru'] as $locale){
        foreach(['home'=>'','category'=>'category/'.$prefix.'-'.$locale,'search'=>'search?q='.$prefix,'author'=>'writer/'.$prefix] as $area=>$path){
            [$status,$html]=request(pathFor($locale,$path));
            expect($status===200,"$area $locale HTTP 200");
            foreach($cases as $name=>$published){
                $allowed=in_array($locale,$published,true);
                $slug=$prefix.'-'.$name.'-'.$locale;
                expect(str_contains($html,'/article/'.$slug)===$allowed,"$area $locale $name language visibility");
                if($locale!=='ar')expect(!str_contains($html,'/article/'.$prefix.'-'.$name.'-ar'),"$area $locale $name no Arabic fallback");
            }
        }
        $data=HomeCategoryBrowser::load($locale);
        $visible=array_column($data['browserArticles'],'id');
        $featured=array_column($data['featured'],'id');
        $ticker=array_column(news_ticker_items($locale,20),'slug');
        foreach($cases as $name=>$published){
            $allowed=in_array($locale,$published,true);
            expect(in_array($ids[$name],$visible,true)===$allowed,"Home query $locale $name");
            expect(in_array($ids[$name],$featured,true)===$allowed,"Featured query $locale $name");
            expect(in_array($prefix.'-'.$name.'-'.$locale,$ticker,true)===$allowed,"Latest ticker $locale $name");
            [$status,$html]=request(pathFor($locale,'article/'.$prefix.'-'.$name.'-'.$locale));
            expect($status===($allowed?200:404),"Direct $locale $name exact HTTP status");
            if($allowed){
                expect(str_contains($html,'hreflang="ar"'),"hreflang $locale $name Arabic source");
                foreach(['en','es','fr','ru'] as $alternate) expect(str_contains($html,'hreflang="'.$alternate.'"')===in_array($alternate,$published,true),"hreflang $name $alternate published only");
                $dom=new DOMDocument(); @$dom->loadHTML($html); $xpath=new DOMXPath($dom);
                foreach($xpath->query('//section[contains(@class,"related-v42")]//a[contains(@href,"/article/")]') as $anchor){
                    $href=$anchor->getAttribute('href');
                    foreach($cases as $other=>$otherPublished) if(str_contains($href,$prefix.'-'.$other.'-'))expect(in_array($locale,$otherPublished,true)&&str_ends_with($href,'-'.$locale),"Related $locale $other has published translation");
                }
            }
        }
        if($locale!=='ar'){
            [$status]=request(pathFor($locale,'article/'.$prefix.'-ar-only-ar'));
            expect($status===404,"Arabic slug requested in $locale returns 404");
        }
    }
    [$status,$sitemap]=request('/sitemap.xml');expect($status===200,'Sitemap HTTP 200');
    foreach($cases as $name=>$published)foreach(['ar','en','es','fr','ru'] as $locale)expect(str_contains($sitemap,'/article/'.$prefix.'-'.$name.'-'.$locale.'</loc>')===in_array($locale,$published,true),"Sitemap $name $locale");
    expect(Database::one("SELECT review_state FROM article_translations WHERE article_id=? AND locale='en'",[$ids['en-review']])['review_state']==='review','Migrations do not approve a translation on page load');
    }

    // Exercise the real editor endpoint, not a copy of its persistence logic.
    $access=(string)file_get_contents(BASE_PATH.'/storage/dev-access.txt');
    preg_match('/Email: (.+)/',$access,$email);preg_match('/Password: (.+)/',$access,$password);
    [$status,$html]=request('/login',null,true);
    [$status]=request('/login',['_token'=>token($html),'email'=>trim($email[1]),'password'=>trim($password[1])],true);
    expect($status===302,'Local administrator login (HTTP '.$status.')');
    [$status,$html]=request('/writer/articles/'.$ids['ar-en'],null,true);$csrf=token($html);
    expect(str_contains($html,'حفظ هذه الترجمة فقط'),'Editor has independent translation action');
    $parentBefore=Database::one('SELECT * FROM articles WHERE id=?',[$ids['ar-en']]);
    $sourceBefore=sourceSnapshot($ids['ar-en']);
    $translationBefore=Database::one("SELECT * FROM article_translations WHERE article_id=? AND locale='en'",[$ids['ar-en']]);
    $payload=['_token'=>$csrf,'article_id'=>$ids['ar-en'],'translation_locale'=>'en','title'=>['en'=>$translationBefore['title'],'ar'=>'THIS MUST NOT OVERWRITE ARABIC'],'content'=>['en'=>'<p>'.$prefix.' edited English body</p>','ar'=>'<p>Must not overwrite Arabic</p>'],'slug'=>['en'=>$translationBefore['slug']],'translation_status'=>['en'=>'draft'],'review_state'=>['en'=>'draft']];
    [$status]=request('/writer/articles/save',$payload,true);expect($status===302,'Translation-only save accepted');
    expect(sourceSnapshot($ids['ar-en'])===$sourceBefore,'Translation edit preserves complete Arabic row including ID/slug/timestamp');
    expect(Database::one('SELECT * FROM articles WHERE id=?',[$ids['ar-en']])===$parentBefore,'Translation edit preserves mother metadata and identity');
    expect(Database::one("SELECT id FROM article_translations WHERE article_id=? AND locale='en'",[$ids['ar-en']])['id']===$translationBefore['id'],'Translation edit preserves translation ID');
    [$status]=request('/en/article/'.$translationBefore['slug']);expect($status===404,'Unpublished English translation returns 404');
    [$status]=request('/article/'.$sourceBefore['slug']);expect($status===200,'Arabic survives English unpublication');
    foreach(['','search?q='.$prefix,'category/'.$prefix.'-en','writer/'.$prefix] as $path){[$status,$html]=request('/en/'.ltrim($path,'/'));expect(!str_contains($html,'/article/'.$translationBefore['slug']),'Unpublication removes English from '.$path);}
    [$status,$sitemap]=request('/sitemap.xml');expect(!str_contains($sitemap,'/article/'.$translationBefore['slug'].'</loc>'),'Unpublished English removed from sitemap');
    [$status,$html]=request('/article/'.$sourceBefore['slug']);expect(!str_contains($html,'hreflang="en"'),'Unpublished English removed from hreflang');
    $payload['title']['en']='A complete independent English title';$payload['excerpt']['en']='A complete translated English summary.';$payload['content']['en']='<p>This is a fully translated English article body.</p>';$payload['translation_status']['en']='published';$payload['review_state']['en']='approved';
    [$status]=request('/writer/articles/save',$payload,true);expect($status===302,'Independent English republish');
    [$status]=request('/en/article/'.$translationBefore['slug']);expect($status===200,'Republished English visible');
    expect(sourceSnapshot($ids['ar-en'])===$sourceBefore,'Arabic unchanged by republishing');

    $count=(int)Database::one('SELECT COUNT(*) c FROM articles')['c'];
    [$status]=request('/writer/articles/submit',['_token'=>$csrf,'article_id'=>0,'title'=>['en'=>$prefix.' standalone'],'content'=>['en'=>'<p>Standalone foreign content</p>']],true);
    expect($status===422,'Foreign-only article creation rejected');
    expect((int)Database::one('SELECT COUNT(*) c FROM articles')['c']===$count,'Rejected creation leaves no parent article');
    [$status]=request('/writer/articles/submit',['_token'=>$csrf,'article_id'=>0,'category_id'=>$categoryId,'title'=>['ar'=>$prefix.' created Arabic','en'=>$prefix.' created English'],'content'=>['ar'=>'<p>Arabic original content</p>','en'=>'<p>English draft content</p>'],'slug'=>['ar'=>$prefix.'-created-ar','en'=>$prefix.'-created-en'],'translation_status'=>['ar'=>'draft','en'=>'draft']],true);
    expect($status===302,'Create and publish Arabic original through editor');
    $created=Database::one("SELECT article_id FROM article_translations WHERE locale='ar' AND slug=?",[$prefix.'-created-ar']);
    if(!$created)throw new RuntimeException('Created Arabic fixture missing');
    $ids['created']=(int)$created['article_id'];
    Database::exec('UPDATE articles SET author_id=? WHERE id=?',[$authorId,$ids['created']]);
    [$status]=request('/article/'.$prefix.'-created-ar');expect($status===200,'New Arabic article is published');
    [$status]=request('/en/article/'.$prefix.'-created-en');expect($status===404,'New complete English draft remains private');
    Database::pdo()->beginTransaction();
    try {
        Database::exec("DELETE FROM article_translations WHERE article_id=? AND locale='en'",[$ids['ar-en']]);
        expect(sourceSnapshot($ids['ar-en'])===$sourceBefore && Database::one('SELECT id FROM articles WHERE id=?',[$ids['ar-en']])!==null,'Deleting foreign translation keeps Arabic and mother article');
    } finally {Database::pdo()->rollBack();}
    Database::pdo()->beginTransaction();
    try {
        require BASE_PATH.'/cron/publish_scheduled.php';
        expect(Database::one("SELECT status FROM article_translations WHERE article_id=? AND locale='en'",[$ids['en-draft']])['status']==='draft','Scheduler does not publish foreign drafts');
    } finally {Database::pdo()->rollBack();}
    Database::exec("INSERT INTO settings(`key`,value) VALUES('default_locale','en') ON DUPLICATE KEY UPDATE value='en'");
    $export=TranslationExchange::rows(['article'],['en','ru']);
    foreach($export as $row)if((int)$row['entity_id']===$ids['ar-en'])expect($row['source_locale']==='ar'&&isset($row['values']['en']),'Translation exchange keeps Arabic source when UI default is English');
    [$status,$html]=request('/admin/articles?q='.$prefix,null,true);
    expect($status===200 && str_contains($html,$sourceBefore['title']),'Administration displays Arabic mother with English UI default');
    expect(str_contains($html,'غير مترجمة')&&str_contains($html,'مسودة')&&str_contains($html,'منشورة'),'Administration displays all three translation states');
} catch(Throwable $error){$failures[]=$error->getMessage();echo 'ERROR: '.$error->getMessage()."\n";}
finally {
    if(Database::pdo()->inTransaction())Database::pdo()->rollBack();
    if($originalDefault)Database::exec("UPDATE settings SET value=? WHERE `key`='default_locale'",[$originalDefault['value']]);else Database::exec("DELETE FROM settings WHERE `key`='default_locale'");
    // Remove only fixtures created by this run. Existing IDs and content are untouched.
    foreach($ids as $id)Database::exec('DELETE FROM articles WHERE id=? AND author_id=?',[$id,$authorId]);
    if($categoryId)Database::exec('DELETE FROM categories WHERE id=?',[$categoryId]);
    if($authorId)Database::exec('DELETE FROM users WHERE id=? AND username=?',[$authorId,$prefix]);
    if(is_file($cookie))unlink($cookie);
}
$report=['passed'=>$passed,'failed'=>count($failures),'failures'=>$failures,'backup'=>basename($backup),'executed_at'=>date(DATE_ATOM),'fixtures_removed'=>true];
$reportName=in_array('--mutations-only',$argv,true)?'article-language-mutations.json':'article-language-tests.json';
file_put_contents(BASE_PATH.'/storage/'.$reportName,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failures?1:0);
