<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Upload;
use App\Core\View;
use App\Services\Books;

final class BooksController {
    private function admin(): array { Auth::requireRole(['admin']); return Auth::user(); }
    private function writer(): array {
        Auth::requireRole(['writer','trusted_writer']);
        $u=Auth::user();
        if(Auth::isImpersonating() || ($u['status']??'')!=='active' || empty($u['writer_verified'])){http_response_code(403);exit('خدمة الكتب متاحة للكتّاب المعتمدين فقط.');}
        return $u;
    }
    private function fail(string $message,string $target='/admin/books'): never { flash('error',$message); redirect($target); }

    public function request(): void {
        $u=$this->writer(); Csrf::enforce();
        $recent=Database::one('SELECT created_at FROM book_service_requests WHERE writer_id=? ORDER BY id DESC LIMIT 1',[(int)$u['id']]);
        if($recent && strtotime((string)$recent['created_at'])>time()-1800 && !Database::one('SELECT id FROM book_service_requests WHERE writer_id=? AND active_slot=1',[(int)$u['id']]))$this->fail('يمكن إعادة إرسال الطلب بعد مرور 30 دقيقة.','/writer');
        try {
            $created=Database::transaction(function() use($u): int {
                // Unique (writer_id, active_slot) is the final concurrency guard.
                $existing=Database::one('SELECT id FROM book_service_requests WHERE writer_id=? AND active_slot=1',[(int)$u['id']]);
                if($existing) return 0;
                return Database::insert("INSERT INTO book_service_requests(writer_id,status,active_slot) VALUES(?,'pending',1)",[(int)$u['id']]);
            });
        } catch(\PDOException $e) {
            if(($e->errorInfo[1]??0)===1062) $created=0; else throw $e;
        }
        if($created){Books::notifyAdmins('طلب نشر كتاب جديد','أرسل الكاتب '.(string)$u['name'].' طلب نشر وتسويق كتاب.','/admin/books/requests'); Books::notify((int)$u['id'],'استلام طلبك','استلمنا طلب نشر كتابك، وستراجعه الإدارة.','/writer'); audit_log('book_request_create','book_service_request',$created);flash('success','استلمنا طلب نشر كتابك. ستتابع الإدارة الطلب معك.');}
        else flash('error','لديك طلب نشط بالفعل؛ لا حاجة إلى إرساله مرة أخرى.');
        redirect('/writer');
    }

    public function offer(string $id): void {
        $u=$this->writer(); $agreement=Database::one('SELECT a.*,r.status request_status FROM book_agreements a JOIN book_service_requests r ON r.id=a.request_id WHERE a.id=? AND a.writer_id=?',[(int)$id,(int)$u['id']]);
        if(!$agreement){http_response_code(404);View::render('errors/404',['title'=>'404'],'public');return;}
        View::render('writer/book-offer',compact('u','agreement')+['title'=>'عرض نشر الكتاب'],'dashboard');
    }
    public function decide(): void {
        $u=$this->writer();Csrf::enforce();$id=(int)($_POST['agreement_id']??0);$decision=(string)($_POST['decision']??'');
        if(!in_array($decision,['accepted','declined'],true)) $this->fail('قرار غير صالح.','/writer');
        $changed=Database::transaction(function()use($id,$u,$decision):?array{
            $a=Database::one("SELECT a.* FROM book_agreements a JOIN book_service_requests r ON r.id=a.request_id WHERE a.id=? AND a.writer_id=? AND r.active_slot=1 AND a.status='offered' FOR UPDATE",[$id,(int)$u['id']]);
            if(!$a)return null;
            $snapshot=$decision==='accepted'?implode("\n\n",[(string)$a['terms'],'الرسوم: '.$a['fee'].' '.$a['currency'],'شروط الدفع: '.$a['payment_terms'],'نطاق الترويج: '.$a['promo_scope'],'حقوق الكاتب: '.$a['rights_terms'],'الإلغاء: '.$a['cancellation_terms'],'الفترة: '.$a['service_start'].' — '.$a['service_end'],'مواضع العرض: الدليل وصفحة الكاتب'.(!empty($a['allow_home'])?' والصفحة الرئيسية':''),'استمرار العرض بعد انتهاء المدة: '.(!empty($a['listing_after_expiry'])?'نعم':'لا')]):null;
            Database::exec('UPDATE book_agreements SET status=?,accepted_snapshot=?,accepted_at=? WHERE id=?',[$decision,$snapshot,$decision==='accepted'?date('Y-m-d H:i:s'):null,$id]);
            if($decision==='accepted') Database::exec("UPDATE book_agreements SET status='superseded' WHERE request_id=? AND id<>? AND status='accepted'",[(int)$a['request_id'],$id]);
            Database::exec('UPDATE book_service_requests SET status=? WHERE id=?',[$decision==='accepted'?'agreement_accepted':'agreement_declined',(int)$a['request_id']]);
            return $a;
        });
        if(!$changed)$this->fail('انتهت صلاحية هذا العرض أو سُجل قرارك سابقًا.','/writer');
        audit_log('book_agreement_'.$decision,'book_agreement',$id,'version '.(int)$changed['version']);
        Books::notifyAdmins('قرار الكاتب بشأن اتفاق الكتاب',($decision==='accepted'?'وافق':'رفض').' الكاتب '.(string)$u['name'].' على العرض رقم '.$id.'.','/admin/books/requests/'.(int)$changed['request_id']);
        flash('success',$decision==='accepted'?'تم تسجيل موافقتك على هذه النسخة من الاتفاق.':'تم تسجيل رفضك للعرض.');redirect('/writer/book-offer/'.$id);
    }

    public function requests(): void {
        $this->admin();
        $rows=Database::all('SELECT r.*,u.name writer_name,u.username,(SELECT COUNT(*) FROM book_agreements a WHERE a.request_id=r.id) agreement_count FROM book_service_requests r JOIN users u ON u.id=r.writer_id ORDER BY r.created_at DESC LIMIT 200');
        $expiring=Database::all("SELECT a.id,a.service_end,u.name writer_name FROM book_agreements a JOIN users u ON u.id=a.writer_id WHERE a.status='accepted' AND a.service_end BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE,INTERVAL 7 DAY) ORDER BY a.service_end");
        View::render('admin/book-requests',compact('rows','expiring')+['title'=>'طلبات نشر الكتب'],'dashboard');
    }
    public function requestDetail(string $id): void {
        $this->admin();$request=Database::one('SELECT r.*,u.name writer_name,u.username FROM book_service_requests r JOIN users u ON u.id=r.writer_id WHERE r.id=?',[(int)$id]);
        if(!$request)$this->fail('الطلب غير موجود.','/admin/books/requests');
        $agreements=Database::all('SELECT * FROM book_agreements WHERE request_id=? ORDER BY version DESC',[(int)$id]);
        View::render('admin/book-request',compact('request','agreements')+['title'=>'مراجعة طلب كتاب'],'dashboard');
    }
    public function adminAgreement(string $id): void {
        $this->admin();$agreement=Database::one('SELECT a.*,u.name writer_name FROM book_agreements a JOIN users u ON u.id=a.writer_id WHERE a.id=?',[(int)$id]);
        if(!$agreement)$this->fail('الاتفاق غير موجود.','/admin/books/requests');
        View::render('admin/book-agreement',compact('agreement')+['title'=>'تفاصيل اتفاق الكتاب'],'dashboard');
    }
    public function updateRequest(): void {
        $this->admin();Csrf::enforce();$id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');
        if(!in_array($status,['pending','reviewing','negotiating','cancelled','completed'],true))$this->fail('حالة غير صالحة.','/admin/books/requests/'.$id);
        $r=Database::one('SELECT * FROM book_service_requests WHERE id=?',[$id]);if(!$r)$this->fail('الطلب غير موجود.');
        $note=mb_substr(trim((string)($_POST['admin_note']??'')),0,10000);
        Database::exec('UPDATE book_service_requests SET status=?,active_slot=?,admin_note=? WHERE id=?',[$status,in_array($status,['cancelled','completed'],true)?null:1,$note,$id]);
        audit_log('book_request_update','book_service_request',$id,$status);
        Books::notify((int)$r['writer_id'],'تحديث طلب نشر كتابك','أصبحت حالة طلبك: '.$status.'.','/writer');
        flash('success','تم تحديث الطلب.');redirect('/admin/books/requests/'.$id);
    }
    public function sendOffer(): void {
        $this->admin();Csrf::enforce();$id=(int)($_POST['request_id']??0);$terms=trim((string)($_POST['terms']??''));
        $fee=filter_var($_POST['fee']??null,FILTER_VALIDATE_FLOAT);$currency=strtoupper(trim((string)($_POST['currency']??'USD')));
        $start=trim((string)($_POST['service_start']??''));$end=trim((string)($_POST['service_end']??''));
        if(mb_strlen($terms)<50 || $fee===false || $fee<0 || !preg_match('/^[A-Z]{3}$/',$currency))$this->fail('اكتب اتفاقًا واضحًا لا يقل عن 50 حرفًا، ورسومًا وعملة صحيحتين.','/admin/books/requests/'.$id);
        if($start===''||$end===''||!self::dateValid($start)||!self::dateValid($end)||$end<$start)$this->fail('حدد تاريخ بداية الخدمة ونهايتها بصورة صحيحة.','/admin/books/requests/'.$id);
        $fields=['payment_terms','promo_scope','rights_terms','cancellation_terms'];$extra=[];foreach($fields as $field){$value=mb_substr(trim((string)($_POST[$field]??'')),0,10000);if($value==='')$this->fail('أكمل شروط الدفع والترويج والحقوق والإلغاء قبل إرسال العرض.','/admin/books/requests/'.$id);$extra[]=$value;}
        $offer=Database::transaction(function()use($id,$terms,$fee,$currency,$start,$end,$extra):?array{
            $r=Database::one('SELECT * FROM book_service_requests WHERE id=? AND active_slot=1 FOR UPDATE',[$id]);if(!$r)return null;
            $v=(int)(Database::one('SELECT MAX(version) v FROM book_agreements WHERE request_id=?',[$id])['v']??0)+1;
            Database::exec("UPDATE book_agreements SET status='superseded' WHERE request_id=? AND status='offered'",[$id]);
            $aid=Database::insert('INSERT INTO book_agreements(request_id,writer_id,version,terms,fee,currency,payment_terms,promo_scope,rights_terms,cancellation_terms,service_start,service_end,listing_after_expiry,allow_directory,allow_profile,allow_home) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$r['writer_id'],$v,$terms,$fee,$currency,...$extra,$start?:null,$end?:null,isset($_POST['listing_after_expiry'])?1:0,1,1,isset($_POST['allow_home'])?1:0]);
            Database::exec("UPDATE book_service_requests SET status='offer_sent' WHERE id=?",[$id]);return ['id'=>$aid,'writer_id'=>(int)$r['writer_id'],'version'=>$v];
        });
        if(!$offer)$this->fail('الطلب مغلق؛ افتح طلبًا نشطًا قبل إرسال العرض.','/admin/books/requests/'.$id);
        Books::notify($offer['writer_id'],'عرض نشر وتسويق كتابك','أرسلت الإدارة نسخة جديدة من الاتفاق لمراجعتها وقبولها أو رفضها.','/writer/book-offer/'.$offer['id']);
        audit_log('book_agreement_offer','book_agreement',$offer['id'],'version '.$offer['version']);flash('success','أُرسل العرض إلى الكاتب.');redirect('/admin/books/requests/'.$id);
    }
    public function payment(): void {
        $this->admin();Csrf::enforce();$id=(int)($_POST['agreement_id']??0);$status=(string)($_POST['payment_status']??'');
        if(!in_array($status,['pending','verified','not_required'],true))$this->fail('حالة دفع غير صالحة.','/admin/books/requests');
        $a=Database::one('SELECT id,request_id,writer_id,status FROM book_agreements WHERE id=?',[$id]);if(!$a || $a['status']!=='accepted')$this->fail('لا يمكن تأكيد دفع اتفاق غير مقبول.','/admin/books/requests');
        $reference=mb_substr(trim((string)($_POST['payment_reference']??'')),0,255);
        if($status==='verified' && $reference==='')$this->fail('اكتب مرجع التحقق من الدفعة قبل تأكيدها.','/admin/books/requests/'.(int)$a['request_id']);
        Database::exec('UPDATE book_agreements SET payment_status=?,payment_reference=? WHERE id=?',[$status,$reference?:null,$id]);audit_log('book_payment_status','book_agreement',$id,$status);
        Books::notify((int)$a['writer_id'],'تحديث شروط نشر كتابك',$status==='verified'?'أكدت الإدارة استلام الدفعة.':'حُدثت حالة الدفعة في اتفاق كتابك.','/writer/book-offer/'.$id);
        flash('success','تم تسجيل حالة الدفعة.');redirect('/admin/books/requests/'.(int)$a['request_id']);
    }

    public function adminBooks(): void {
        $this->admin();$rows=Database::all('SELECT b.*,u.name author_name FROM books b JOIN users u ON u.id=b.writer_id ORDER BY b.updated_at DESC LIMIT 200');
        View::render('admin/books',compact('rows')+['title'=>'إدارة الكتب والإصدارات'],'dashboard');
    }
    public function editBook(?string $id=null): void {
        $this->admin();$book=$id?Database::one('SELECT * FROM books WHERE id=?',[(int)$id]):null;
        if($id && !$book)$this->fail('الكتاب غير موجود.');
        $linkedAgreement=$book?Database::one('SELECT status,payment_status FROM book_agreements WHERE id=?',[(int)$book['agreement_id']]):null;
        $agreements=Database::all("SELECT a.id,a.request_id,a.writer_id,a.version,a.status,a.payment_status,u.name writer_name FROM book_agreements a JOIN users u ON u.id=a.writer_id WHERE a.status='accepted' ORDER BY a.created_at DESC LIMIT 200");
        $media=Database::all("SELECT id,path,title FROM media WHERE deleted_at IS NULL AND mime LIKE 'image/%' ORDER BY id DESC LIMIT 300");
        View::render('admin/book-edit',compact('book','agreements','media','linkedAgreement')+['title'=>$book?'تعديل الكتاب':'إضافة كتاب'],'dashboard');
    }
    public function previewBook(string $id): void {
        $this->admin();$book=Database::one('SELECT b.*,u.name author_name,f.path front_cover,k.path back_cover FROM books b JOIN users u ON u.id=b.writer_id LEFT JOIN media f ON f.id=b.front_media_id LEFT JOIN media k ON k.id=b.back_media_id WHERE b.id=?',[(int)$id]);
        if(!$book)$this->fail('الكتاب غير موجود.');
        View::render('admin/book-preview',compact('book')+['title'=>'معاينة الكتاب'],'dashboard');
    }
    public function saveBook(): void {
        $admin=$this->admin();Csrf::enforce();$id=(int)($_POST['id']??0);$book=$id?Database::one('SELECT * FROM books WHERE id=?',[$id]):null;
        if($id&&!$book)$this->fail('الكتاب غير موجود.');
        $title=mb_substr(trim((string)($_POST['title']??'')),0,240);$slug=mb_substr(trim((string)($_POST['slug']??'')),0,260);
        if($slug==='')$slug=slugify($title);if($slug==='')$slug='book-'.bin2hex(random_bytes(4));
        if(Database::one('SELECT id FROM books WHERE slug=? AND id<>? LIMIT 1',[$slug,$id]))$this->fail('الرابط المختصر مستخدم؛ اختر رابطًا مختلفًا.',$id?'/admin/books/'.$id:'/admin/books/new');
        $agreementId=(int)($_POST['agreement_id']??0);$agreement=Database::one("SELECT a.*,r.active_slot,u.role,u.status writer_status FROM book_agreements a JOIN book_service_requests r ON r.id=a.request_id JOIN users u ON u.id=a.writer_id WHERE a.id=?",[$agreementId]);
        if($title===''||!$agreement||$agreement['status']!=='accepted'||!$agreement['active_slot']||!in_array($agreement['role'],['writer','trusted_writer'],true)||$agreement['writer_status']!=='active')$this->fail('اختر اتفاقًا مقبولًا لكاتب معتمد واكتب عنوان الكتاب.',$id?'/admin/books/'.$id:'/admin/books/new');
        $url=trim((string)($_POST['purchase_url']??''));if($url!==''&&!self::httpsUrl($url))$this->fail('استخدم رابط شراء HTTPS صالحًا فقط.',$id?'/admin/books/'.$id:'/admin/books/new');
        $front=(int)($_POST['front_media_id']??0);$back=(int)($_POST['back_media_id']??0);
        try {
            $front=$this->uploadCover('front_cover_file',(int)$admin['id'])?:$front;
            $back=$this->uploadCover('back_cover_file',(int)$admin['id'])?:$back;
        } catch(\Throwable $e){$this->fail('تعذر رفع الغلاف: '.$e->getMessage(),$id?'/admin/books/'.$id:'/admin/books/new');}
        foreach([$front,$back] as $mediaId){if($mediaId && !Database::one("SELECT id FROM media WHERE id=? AND deleted_at IS NULL AND mime LIKE 'image/%'",[$mediaId]))$this->fail('صورة الغلاف غير موجودة في مكتبة الوسائط.',$id?'/admin/books/'.$id:'/admin/books/new');}
        $data=['writer_id'=>(int)$agreement['writer_id'],'agreement_id'=>$agreementId,'title'=>$title,'slug'=>$slug,'subtitle'=>mb_substr(trim((string)($_POST['subtitle']??'')),0,240),'genre'=>mb_substr(trim((string)($_POST['genre']??'')),0,120),'publisher'=>mb_substr(trim((string)($_POST['publisher']??'')),0,160),'publication_year'=>(int)($_POST['publication_year']??0)?:null,'isbn'=>mb_substr(trim((string)($_POST['isbn']??'')),0,32),'book_language'=>mb_substr(trim((string)($_POST['book_language']??'')),0,40),'page_count'=>(int)($_POST['page_count']??0)?:null,'book_format'=>mb_substr(trim((string)($_POST['book_format']??'')),0,80),'description'=>trim((string)($_POST['description']??'')),'front_media_id'=>$front?:null,'back_media_id'=>$back?:null,'purchase_url'=>$url?:null,'purchase_label'=>mb_substr(trim((string)($_POST['purchase_label']??'')),0,120),'is_featured'=>isset($_POST['is_featured'])?1:0,'promo_directory'=>isset($_POST['promo_directory'])?1:0,'promo_profile'=>isset($_POST['promo_profile'])?1:0,'promo_home'=>isset($_POST['promo_home'])?1:0];
        if($book && $book['status']==='published' && !self::publishable($data,$agreement))$this->fail('احفظ شروط النشر المطلوبة قبل تعديل كتاب منشور.','/admin/books/'.$id);
        try {
            if($id){$set=implode(',',array_map(static fn($k)=>"`$k`=?",array_keys($data)));Database::exec("UPDATE books SET $set WHERE id=?",[...array_values($data),$id]);}
            else {$keys=array_keys($data);$id=Database::insert('INSERT INTO books(`'.implode('`,`',$keys).'`) VALUES('.implode(',',array_fill(0,count($keys),'?')).')',array_values($data));}
        } catch(\PDOException $e){if(($e->errorInfo[1]??0)===1062)$this->fail('الرابط المختصر مستخدم؛ اختر رابطًا مختلفًا.',$book?'/admin/books/'.$id:'/admin/books/new');throw $e;}
        audit_log('book_save','book',$id);flash('success','حُفظ الكتاب.');redirect('/admin/books/'.$id);
    }
    private function uploadCover(string $field,int $adminId): ?int {
        $file=$_FILES[$field]??null;if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
        $info=Upload::imageDetailed($file,false);if(!$info)return null;
        $id=Database::insert('INSERT INTO media(user_id,path,storage_key,mime,kind,title,alt_text,original_name,width,height,size_bytes) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$adminId,$info['path'],$info['path'],$info['mime'],'image',pathinfo((string)$info['original_name'],PATHINFO_FILENAME),'غلاف كتاب',(string)$info['original_name'],$info['width'],$info['height'],$info['size_bytes']]);
        audit_log('book_cover_upload','media',$id,$field);return $id;
    }
    public function statusBook(): void {
        $this->admin();Csrf::enforce();$id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');
        if(!in_array($status,['published','unpublished','archived'],true))$this->fail('إجراء غير صالح.');
        $book=Database::one('SELECT * FROM books WHERE id=?',[$id]);if(!$book)$this->fail('الكتاب غير موجود.');
        if($book['status']===$status){flash('success','حالة الكتاب محدّثة بالفعل.');redirect('/admin/books/'.$id);}
        if($status==='published'){
            $a=Database::one('SELECT a.*,r.active_slot FROM book_agreements a JOIN book_service_requests r ON r.id=a.request_id WHERE a.id=?',[(int)$book['agreement_id']]);
            if(!$a||!self::publishable($book,$a))$this->fail('النشر يتطلب موافقة الكاتب، تأكيد الدفعة، فترة صالحة، غلافين ووصفًا ورابط شراء آمنًا.','/admin/books/'.$id);
        }
        Database::exec('UPDATE books SET status=?,published_at=CASE WHEN ?=\'published\' THEN CURRENT_TIMESTAMP ELSE published_at END WHERE id=?',[$status,$status,$id]);
        audit_log('book_'.$status,'book',$id);
        Books::notify((int)$book['writer_id'],'تحديث كتابك',$status==='published'?'نُشر كتابك في سراديب.':'تغيرت حالة عرض كتابك على الموقع.','/writer');
        flash('success','حُدثت حالة الكتاب.');redirect('/admin/books/'.$id);
    }
    private static function publishable(array $b,array $a): bool {
        $paymentReady=$a['payment_status']==='verified'||($a['payment_status']==='not_required' && (float)$a['fee']===0.0);
        if($a['status']!=='accepted'||!$paymentReady||empty($a['accepted_at'])||empty($a['accepted_snapshot'])||empty($a['active_slot'])||(int)$a['writer_id']!==(int)$b['writer_id'])return false;
        if(empty($a['allow_directory'])||empty($a['allow_profile']))return false;
        if(empty($b['promo_directory'])||empty($b['promo_profile']))return false;
        if(!empty($b['promo_home'])&&empty($a['allow_home']))return false;
        if(!empty($a['service_start'])&&$a['service_start']>date('Y-m-d'))return false;
        if(!empty($a['service_end'])&&$a['service_end']<date('Y-m-d')&&empty($a['listing_after_expiry']))return false;
        if(mb_strlen(trim((string)($b['description']??'')))<40||empty($b['front_media_id'])||empty($b['back_media_id'])||!self::httpsUrl((string)($b['purchase_url']??''))||trim((string)($b['purchase_label']??''))==='')return false;
        foreach([$b['front_media_id'],$b['back_media_id']] as $id){if(!Database::one("SELECT id FROM media WHERE id=? AND deleted_at IS NULL AND mime LIKE 'image/%'",[(int)$id]))return false;}
        return true;
    }
    private static function httpsUrl(string $url): bool { $p=parse_url($url);return strlen($url)<=1000 && filter_var($url,FILTER_VALIDATE_URL)!==false && strtolower((string)($p['scheme']??''))==='https' && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']); }
    private static function dateValid(string $v): bool { $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);return $d!==false&&$d->format('Y-m-d')===$v; }

    public function directory(): void {
        $locale=default_locale();$search=mb_substr(trim((string)($_GET['q']??'')),0,100);$genre=mb_substr(trim((string)($_GET['genre']??'')),0,120);$authorId=(int)($_GET['author']??0);$page=max(1,min(1000,(int)($_GET['page']??1)));
        if(!Books::hasPublicBooks()){$this->notFound($locale);return;}
        $books=Books::publicBooks(null,null,$search,$genre,25,$authorId?:null,($page-1)*24);$hasMore=count($books)>24;if($hasMore)array_pop($books);$genres=Database::all("SELECT DISTINCT b.genre FROM books b JOIN book_agreements a ON a.id=b.agreement_id JOIN users u ON u.id=b.writer_id WHERE ".Books::publicWhere()." AND b.promo_directory=1 AND a.allow_directory=1 AND b.genre<>'' ORDER BY b.genre");
        $authors=Database::all("SELECT DISTINCT u.id,u.name FROM books b JOIN book_agreements a ON a.id=b.agreement_id JOIN users u ON u.id=b.writer_id WHERE ".Books::publicWhere()." AND b.promo_directory=1 ORDER BY u.name");
        View::render('public/books',compact('locale','books','search','genre','genres','authors','authorId','page','hasMore')+['title'=>'إصدارات سراديب','description'=>'كتب وإصدارات كتّاب سراديب'],'public');
    }
    public function show(string $slug): void {
        $locale=default_locale();$book=Books::publicBooks(null,$slug,limit:1)[0]??null;
        if(!$book){$this->notFound($locale);return;}
        $canonical=url('/books/'.$book['slug']);$description=mb_substr(strip_tags((string)$book['description']),0,220);
        View::render('public/book',compact('locale','book','canonical','description')+['title'=>$book['title']],'public');
    }
    private function notFound(string $locale): void {http_response_code(404);View::render('errors/404',compact('locale')+['title'=>'404'],'public');}
}
