<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
use App\Services\ManagedPages;
if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev')throw new RuntimeException('Local development database only.');
$settings=Database::all('SELECT `key`,value FROM settings');$pages=[];$translations=[];$ids=[];$prefix='footercheck-'.bin2hex(random_bytes(4));$passed=0;$failures=[];
$http=curl_init();curl_setopt($http,CURLOPT_COOKIEFILE,'');
function request(string $path,?array $post=null):array{global $http;curl_setopt_array($http,[CURLOPT_URL=>'http://127.0.0.1:8001'.$path,CURLOPT_HTTPGET=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>45]);if($post!==null)curl_setopt_array($http,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);$body=curl_exec($http);if($body===false)throw new RuntimeException(curl_error($http));return [(int)curl_getinfo($http,CURLINFO_RESPONSE_CODE),$body];}
function expect(bool $ok,string $label):void{global $passed,$failures;if($ok)$passed++;else{$failures[]=$label;echo "FAIL: $label\n";}}
function token(string $html):string{preg_match('/name="_token" value="([^"]+)"/',$html,$m);return $m[1]??'';}
function formData(string $html):array{$dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);$pairs=[];foreach($xp->query('//form[@id="footerStudioForm"]//*[self::input or self::textarea or self::select]') as $field){$name=$field->getAttribute('name');if(!$name||$field->hasAttribute('disabled'))continue;$type=$field->getAttribute('type');if(in_array($type,['checkbox','radio'],true)&&!$field->hasAttribute('checked'))continue;if($field->tagName==='textarea')$value=$field->textContent;elseif($field->tagName==='select'){$opts=$xp->query('.//option[@selected]',$field);if(!$opts->length)$opts=$xp->query('.//option',$field);$value=$opts->item(0)?->getAttribute('value')??'';}else$value=$field->getAttribute('value');$pairs[]=urlencode($name).'='.urlencode($value);}parse_str(implode('&',$pairs),$values);return $values;}
try{
    $access=file_get_contents(BASE_PATH.'/storage/dev-access.txt');preg_match('/Email: (.+)/',$access,$email);preg_match('/Password: (.+)/',$access,$pw);[$status,$html]=request('/login');request('/login',['_token'=>token($html),'email'=>trim($email[1]),'password'=>trim($pw[1])]);
    [$status,$html]=request('/admin/footer');$post=formData($html);expect($status===200,'Footer editor renders');
    $post['footer_title']=$prefix.' brand';$post['footer_description']=$prefix.' description';$post['footer_menu_title']=$prefix.' column';$post['footer_cta_title']=$prefix.' connect';
    $post['footer_copy']['en']['status']='published';$post['footer_copy']['en']['title']=$prefix.' English brand';$post['footer_copy']['en']['menu_title']=$prefix.' English column';
    $storyId=(int)ManagedPages::ids()['story'];$newsId=(int)ManagedPages::ids()['newsletter'];
    $post['footer_items']=array_values(array_unique(array_merge($post['footer_items']??[],['story','write','explore','community','behind'])));
    $post['footer_page_placement'][$storyId]['labels']['ar']=$prefix.' story label';$post['footer_page_placement'][$storyId]['labels']['en']=$prefix.' English story label';$post['footer_page_placement'][$storyId]['column']='menu';$post['footer_page_placement'][$storyId]['order']=-9;
    $post['footer_page_placement'][$newsId]['column']='brand';$post['footer_show_newsletter']=1;
    $post['footer_custom'][1]=['label'=>$prefix.' custom ar','url'=>'/send-story','column'=>'connect','labels'=>['en'=>$prefix.' custom en'],'show'=>1,'order'=>2];
    [$status,$body]=request('/admin/footer/save',$post);expect($status===302,'Footer form saves all existing and new fields');
    foreach(['ar'=>'/','en'=>'/en/'] as $locale=>$path){[$status,$html]=request($path);expect($status===200&&!preg_match('/Warning:|Fatal error:|Notice:/',$html),"$locale updated footer no errors");$dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
        expect($xp->query('//section[contains(@class,"footer-pro-brand")]//*[@id="site-newsletter"]')->length===1,"$locale subscription moved to brand with original form");
        expect($xp->query('//footer//div[@id="site-newsletter"]')->length===1,"$locale subscription rendered once");
        $label=$locale==='ar'?$prefix.' story label':$prefix.' English story label';expect($xp->query('//footer//section[contains(@class,"footer-pro-column")]//a[contains(.,"'.$label.'")]')->length===1,"$locale story action moved to menu");
        $expectedBrand=$locale==='ar'?$prefix.' brand':$prefix.' English brand';expect(str_contains($html,$expectedBrand),"$locale saved brand copy renders");
        $custom=$locale==='ar'?$prefix.' custom ar':$prefix.' custom en';expect($xp->query('//section[contains(@class,"footer-v45-connect")]//a[contains(.,"'.$custom.'")]')->length===1,"$locale custom link translated and moved");
    }
    $post['footer_copy']['en']['status']='draft';request('/admin/footer/save',$post);[$status,$html]=request('/en/');expect(!str_contains($html,$prefix.' English brand')&&!str_contains($html,$prefix.' description'),'Draft foreign footer copy never falls back to Arabic');
    // A local custom page URL resolves to the requested translation, never the source slug.
    $id=Database::insert("INSERT INTO pages(status) VALUES('published')");$ids[]=$id;
    foreach(['ar','en'] as $locale)Database::insert("INSERT INTO page_translations(page_id,locale,title,slug,content,status,review_state) VALUES(?,?,?,?,?,'published','approved')",[$id,$locale,$prefix,$prefix.'-'.$locale,'<p>Original '.$locale.'</p>']);
    expect(str_ends_with(ManagedPages::navigationUrl('/page/'.$prefix.'-ar','en')??'','/en/page/'.$prefix.'-en'),'Custom internal page URL resolves exact English translation');
    expect(str_ends_with(ManagedPages::navigationUrl(url('/page/'.$prefix.'-ar'),'en')??'','/en/page/'.$prefix.'-en'),'Absolute internal page URL obeys translation availability');
    expect(ManagedPages::navigationUrl('/page/'.$prefix.'-ar','ru')===null,'Custom internal link without Russian translation hidden');
    $other=Database::insert("INSERT INTO pages(status) VALUES('published')");$ids[]=$other;
    Database::insert("INSERT INTO page_translations(page_id,locale,title,slug,content,status,review_state) VALUES(?,'ar',?,?,?,'published','approved')",[$other,$prefix.' other',$prefix.'-en','<p>Other Arabic page</p>']);
    expect(str_ends_with(ManagedPages::navigationUrl('/en/page/'.$prefix.'-en','ar')??'','/page/'.$prefix.'-ar'),'Explicit source locale wins when different pages share a slug across languages');
    $post['footer_custom'][1]['url']='/page/'.$prefix.'-ar';$post['footer_custom'][1]['labels']['ru']=$prefix.' custom ru';request('/admin/footer/save',$post);[$status,$html]=request('/ru/');expect(!str_contains($html,$prefix.' custom ru'),'Translated custom label cannot expose untranslated page');
    Database::exec('INSERT INTO redirects(source_path,target_url,http_code) VALUES(?,?,301)',['/page/'.$prefix.'-old','/page/'.$prefix.'-middle']);Database::exec('INSERT INTO redirects(source_path,target_url,http_code) VALUES(?,?,301)',['/page/'.$prefix.'-middle','/page/'.$prefix.'-ar']);[$status]=request('/page/'.$prefix.'-old');expect($status===301,'Multiple historical slugs resolve to live source');
    Database::exec("UPDATE page_translations SET status='draft' WHERE page_id=? AND locale='ar'",[$id]);[$status]=request('/page/'.$prefix.'-old');expect($status===404,'Historical URL never redirects to a draft');[$status]=request('/en/page/'.$prefix.'-en');expect($status===404,'Foreign page requires published Arabic mother');
    [$status,$html]=request('/admin/pages/new');$csrf=token($html);
    request('/admin/pages/toggle',['_token'=>$csrf,'id'=>$id]);request('/admin/pages/toggle',['_token'=>$csrf,'id'=>$id]);[$status]=request('/page/'.$prefix.'-ar');expect($status===200,'Page publication action publishes source explicitly');
    // Default interface locale must not become the source language of page exports.
    Database::exec("UPDATE settings SET value='en' WHERE `key`='default_locale'");$rows=\App\Services\TranslationExchange::rows(['page'],['en']);foreach($rows as $row)if((int)$row['entity_id']===$id)expect($row['source_locale']==='ar','Page exchange preserves Arabic source when interface default is English');
    [$status,$xml]=request('/sitemap.xml');expect(str_contains($xml,'hreflang="x-default"')&&str_contains($xml,$prefix.'-ar'),'Sitemap keeps Arabic source under English site default');
}catch(Throwable $e){$failures[]=$e->getMessage();echo 'ERROR: '.$e->getMessage()."\n";}
finally{
    $keys=array_column($settings,'key');foreach(Database::all('SELECT `key` FROM settings') as $row)if(!in_array($row['key'],$keys,true))Database::exec('DELETE FROM settings WHERE `key`=?',[$row['key']]);foreach($settings as $row)Database::exec('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$row['key'],$row['value']]);
    foreach($ids as $id){Database::exec('DELETE FROM page_translations WHERE page_id=?',[$id]);Database::exec('DELETE FROM pages WHERE id=?',[$id]);}Database::exec('DELETE FROM redirects WHERE source_path LIKE ?',['%'.$prefix.'%']);curl_close($http);
}
$result=['passed'=>$passed,'failed'=>count($failures),'failures'=>$failures];file_put_contents(BASE_PATH.'/storage/footer-settings-tests.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";exit($failures?1:0);
