<?php
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/app/Core/Database.php';
$cfg=require BASE_PATH.'/config/local.php';
App\Core\Database::init($cfg['db']);
$db=App\Core\Database::pdo();
foreach(['categories','category_translations','articles','article_translations'] as $table){
 echo $table,': ',json_encode($db->query('SHOW COLUMNS FROM '.$table)->fetchAll(PDO::FETCH_COLUMN),JSON_UNESCAPED_UNICODE),"\n";
}
echo json_encode($db->query("SELECT c.id,c.sort_order,ct.locale,ct.name,ct.slug FROM categories c JOIN category_translations ct ON ct.category_id=c.id ORDER BY c.id,ct.locale")->fetchAll(),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),"\n";
