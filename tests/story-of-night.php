<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\Database;
use App\Services\StoryOfNight;

if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev' || ($GLOBALS['db_config']['host']??'')!=='127.0.0.1') throw new RuntimeException('Tests require the isolated local development database.');
$passed=0;$failures=[];$pdo=Database::pdo();
function nightExpect(bool $ok,string $label):void{global $passed,$failures;if($ok)$passed++;else{$failures[]=$label;echo "FAIL: {$label}\n";}}

$pdo->beginTransaction();
try{
    $prefix='nighttest-'.bin2hex(random_bytes(4));
    $author=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status) VALUES(?,?,?,?,'writer','active')",['كاتب الاختبار',$prefix,$prefix.'@example.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
    $category=Database::insert('INSERT INTO categories(sort_order,active) VALUES(-998,1)');
    Database::insert("INSERT INTO category_translations(category_id,locale,name,slug,status) VALUES(?,?,'قصص الاختبار',?,'published')",[$category,'ar',$prefix]);
    $ids=[];
    foreach([1,2,3] as $number){
        $id=Database::insert("INSERT INTO articles(author_id,category_id,status,published_at,is_featured,featured_image,views) VALUES(?,?,'published',CURRENT_TIMESTAMP,0,?,0)",[$author,$category,$number===2?'':'/uploads/'.$prefix.'.jpg']);
        Database::insert("INSERT INTO article_translations(article_id,locale,title,slug,excerpt,content,status,review_state) VALUES(?,?,?,?,?,?,'published','approved')",[$id,'ar','قصة اختبار '.$number,$prefix.'-'.$number,'وصف موجز','<p>محتوى مكتمل صالح للنشر والقراءة.</p>']);
        $ids[]=$id;
    }
    $draft=Database::insert("INSERT INTO articles(author_id,category_id,status,published_at) VALUES(?,?,'draft',CURRENT_TIMESTAMP)",[$author,$category]);
    Database::insert("INSERT INTO article_translations(article_id,locale,title,slug,excerpt,content,status,review_state) VALUES(?,?,?,?,?,?,'published','approved')",[$draft,'ar','مسودة ممنوعة',$prefix.'-draft','','<p>مسودة</p>']);
    Database::exec("INSERT INTO settings(`key`,value) VALUES('story_of_night_enabled','1') ON DUPLICATE KEY UPDATE value='1'");
    Database::exec("INSERT INTO settings(`key`,value) VALUES('story_of_night_timezone','Europe/Istanbul') ON DUPLICATE KEY UPDATE value='Europe/Istanbul'");
    Database::exec("INSERT INTO settings(`key`,value) VALUES('story_of_night_categories',?) ON DUPLICATE KEY UPDATE value=VALUES(value)",[json_encode([$category])]);
    $service=new StoryOfNight();
    $day1=new DateTimeImmutable('2098-01-01 20:00:00',new DateTimeZone('Europe/Istanbul'));
    $one=$service->current('ar',$day1);$again=$service->current('ar',$day1);
    nightExpect($one!==null && in_array((int)$one['id'],$ids,true),'published eligible article selected');
    nightExpect((int)$one['id']===(int)$again['id'],'selection stays stable throughout the day');
    nightExpect((int)$one['id']!==$draft,'draft article is never selected');
    nightExpect(count(array_filter($service->eligibleArticles('ar'),static fn(array $row):bool=>trim((string)$row['featured_image'])===''))===1,'missing image remains eligible for graceful placeholder rendering');
    Database::exec("UPDATE articles SET status='archived' WHERE id=?",[(int)$one['id']]);
    $afterUnpublish=$service->current('ar',$day1);
    nightExpect($afterUnpublish!==null && (int)$afterUnpublish['id']!==(int)$one['id'],'unpublished current story is replaced safely');
    Database::exec("UPDATE articles SET status='published' WHERE id=?",[(int)$one['id']]);
    $day2=$service->current('ar',$day1->modify('+1 day'));
    nightExpect($day2!==null && (int)$day2['id']!==(int)$afterUnpublish['id'],'next day rotates away from recent story');
    Database::insert('INSERT INTO story_of_night_exclusions(article_id,reason) VALUES(?,?)',[(int)$day2['id'],'test']);
    $replaced=$service->current('ar',$day1->modify('+1 day'));
    nightExpect($replaced!==null && (int)$replaced['id']!==(int)$day2['id'],'exclusion immediately removes selected story');
    $manualChoices=array_values(array_filter(array_map('intval',array_column($service->eligibleArticles('ar'),'id')),static fn(int $id):bool=>$id!==(int)$replaced['id']));
    $manualId=(int)($manualChoices[0]??$replaced['id']);
    Database::insert("INSERT INTO story_of_night_overrides(article_id,starts_at,ends_at,active) VALUES(?,'2098-01-03 00:00:00','2098-01-03 23:59:59',1)",[$manualId]);
    $manual=$service->current('ar',new DateTimeImmutable('2098-01-03 12:00:00',new DateTimeZone('Europe/Istanbul')));
    nightExpect($manual!==null && (int)$manual['id']===$manualId && $manual['selection_mode']==='manual','manual override wins during its period');
    $expired=$service->current('ar',new DateTimeImmutable('2098-01-04 12:00:00',new DateTimeZone('Europe/Istanbul')));
    nightExpect($expired!==null && $expired['selection_mode']==='automatic','automatic selection resumes after override expires');
    Database::exec("UPDATE settings SET value='0' WHERE `key`='story_of_night_enabled'");
    nightExpect($service->current('ar',$day1)===null,'disabled feature returns no card');
    Database::exec("UPDATE settings SET value='1' WHERE `key`='story_of_night_enabled'");
    Database::exec("UPDATE settings SET value='[999999999]' WHERE `key`='story_of_night_categories'");
    nightExpect($service->current('ar',new DateTimeImmutable('2098-02-01',new DateTimeZone('Europe/Istanbul')))===null,'no eligible content is handled without fallback leakage');
}catch(Throwable $error){$failures[]=$error->getMessage();echo 'ERROR: '.$error->getMessage()."\n";}
finally{if($pdo->inTransaction())$pdo->rollBack();}
$report=['passed'=>$passed,'failed'=>count($failures),'failures'=>$failures,'executed_at'=>date(DATE_ATOM),'transaction_rolled_back'=>true];
file_put_contents(BASE_PATH.'/storage/story-of-night-tests.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
exit($failures?1:0);
