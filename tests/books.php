<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
use App\Services\Books;

if(($GLOBALS['db_config']['database']??'')!=='saradeeeb_dev') throw new RuntimeException('Book integration tests require the isolated development database.');
foreach(['book_service_requests','book_agreements','books'] as $table) if(!schema_table_exists($table)) throw new RuntimeException("Missing table: $table");
$passed=0;
$expect=static function(bool $ok,string $label)use(&$passed):void {if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";$passed++;};
$pdo=Database::pdo();$pdo->beginTransaction();
try {
    $suffix=bin2hex(random_bytes(6));
    $writer=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status,writer_verified) VALUES(?,?,?,?, 'writer','active',1)",["Book test $suffix","booktest$suffix","booktest$suffix@example.invalid",password_hash($suffix,PASSWORD_DEFAULT)]);
    $request=Database::insert("INSERT INTO book_service_requests(writer_id,status,active_slot) VALUES(?,'pending',1)",[$writer]);
    $duplicate=false;try{Database::insert("INSERT INTO book_service_requests(writer_id,status,active_slot) VALUES(?,'pending',1)",[$writer]);}catch(PDOException $e){$duplicate=($e->errorInfo[1]??0)===1062;}
    $expect($duplicate,'duplicate active request rejected');
    $agreement=Database::insert("INSERT INTO book_agreements(request_id,writer_id,version,terms,fee,currency,status,payment_status,allow_directory,allow_profile,allow_home) VALUES(?,?,1,?,12,'USD','offered','pending',1,1,1)",[$request,$writer,'Test agreement with author rights, payment and promotional scope.']);
    $front=Database::insert("INSERT INTO media(user_id,path,mime) VALUES(?,'/assets/award-gold-medal.png','image/png')",[$writer]);
    $back=Database::insert("INSERT INTO media(user_id,path,mime) VALUES(?,'/assets/award-gold-medal.png','image/png')",[$writer]);
    $slug='book-test-'.$suffix;
    $book=Database::insert("INSERT INTO books(writer_id,agreement_id,title,slug,description,front_media_id,back_media_id,purchase_url,purchase_label,status,published_at,promo_directory,promo_profile,promo_home) VALUES(?,?,?,?,?,?,?,?,?,'draft',CURRENT_TIMESTAMP,1,1,1)",[$writer,$agreement,'Test book',$slug,str_repeat('Test description. ',4),$front,$back,'https://example.org/book','Buy']);
    $expect(Books::publicBooks(null,$slug)===[],'draft is private');
    Database::exec("UPDATE books SET status='published' WHERE id=?",[$book]);
    $expect(Books::publicBooks(null,$slug)===[],'publication without author acceptance is private');
    Database::exec("UPDATE book_agreements SET status='accepted',accepted_at=CURRENT_TIMESTAMP,accepted_snapshot='agreed' WHERE id=?",[$agreement]);
    $expect(Books::publicBooks(null,$slug)===[],'unverified payment is private');
    Database::exec("UPDATE book_agreements SET payment_status='verified' WHERE id=?",[$agreement]);
    $expect(count(Books::publicBooks(null,$slug))===1,'eligible book is public');
    $expect(count(Books::publicBooks($writer))===1,'eligible book appears for its author');
    $expect(count(Books::homePromotions())>=1,'authorized homepage promotion appears');
    Database::exec('UPDATE book_agreements SET allow_home=0 WHERE id=?',[$agreement]);
    $expect(!array_filter(Books::homePromotions(),static fn($b)=>$b['slug']===$slug),'homepage promotion requires agreement permission');
    Database::exec('UPDATE book_agreements SET allow_home=1,allow_directory=0 WHERE id=?',[$agreement]);
    $expect(Books::publicBooks(null,$slug)===[],'directory permission is required');
    Database::exec('UPDATE book_agreements SET allow_directory=1 WHERE id=?',[$agreement]);
    Database::exec("UPDATE book_agreements SET service_end=DATE_SUB(CURRENT_DATE,INTERVAL 1 DAY) WHERE id=?",[$agreement]);
    $expect(Books::publicBooks(null,$slug)===[],'expired agreement removes book');
    Database::exec('UPDATE book_agreements SET listing_after_expiry=1 WHERE id=?',[$agreement]);
    $expect(count(Books::publicBooks(null,$slug))===1,'contract may retain listing after expiry');
    $expect(!array_filter(Books::homePromotions(),static fn($b)=>$b['slug']===$slug),'expired promotion stops');
    Database::exec("UPDATE book_service_requests SET status='cancelled',active_slot=NULL WHERE id=?",[$request]);
    $expect(Books::publicBooks(null,$slug)===[],'cancelled service is private');
    echo "$passed book checks passed.\n";
} finally {if($pdo->inTransaction())$pdo->rollBack();}
