<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

final class Books {
    public static function publicWhere(string $book='b', string $agreement='a', string $writer='u'): string {
        return "$book.status='published' AND $book.published_at IS NOT NULL AND $book.published_at<=CURRENT_TIMESTAMP
            AND $agreement.id=$book.agreement_id AND $agreement.status='accepted'
            AND ($agreement.payment_status='verified' OR ($agreement.payment_status='not_required' AND $agreement.fee=0)) AND $agreement.accepted_at IS NOT NULL
            AND $agreement.accepted_snapshot IS NOT NULL
            AND $agreement.allow_directory=1 AND $agreement.allow_profile=1
            AND NOT EXISTS (SELECT 1 FROM book_service_requests cancelled_request WHERE cancelled_request.id=$agreement.request_id AND cancelled_request.status='cancelled')
            AND ($agreement.service_start IS NULL OR $agreement.service_start<=CURRENT_DATE)
            AND ($agreement.service_end IS NULL OR $agreement.service_end>=CURRENT_DATE OR $agreement.listing_after_expiry=1)
            AND $writer.id=$book.writer_id AND $writer.status='active'
            AND $writer.role IN ('writer','trusted_writer') AND $writer.writer_verified=1
            AND $book.front_media_id IS NOT NULL AND $book.back_media_id IS NOT NULL
            AND $book.description IS NOT NULL AND $book.description<>''
            AND $book.purchase_url LIKE 'https://%'";
    }

    public static function publicBooks(?int $writerId=null, ?string $slug=null, string $search='', string $genre='', int $limit=60, ?int $directoryAuthorId=null, int $offset=0): array {
        try {
            $where=self::publicWhere(); $params=[];
            if($writerId!==null){$where.=' AND b.writer_id=? AND b.promo_profile=1 AND a.allow_profile=1';$params[]=$writerId;}
            elseif($slug===null){$where.=' AND b.promo_directory=1 AND a.allow_directory=1';}
            if($slug!==null){$where.=' AND b.slug=?';$params[]=$slug;}
            if($directoryAuthorId!==null && $writerId===null){$where.=' AND b.writer_id=?';$params[]=$directoryAuthorId;}
            if($search!==''){$where.=' AND (b.title LIKE ? OR u.name LIKE ?)';$params[]='%'.$search.'%';$params[]='%'.$search.'%';}
            if($genre!==''){$where.=' AND b.genre=?';$params[]=$genre;}
            return Database::all("SELECT b.*,u.name author_name,u.username,f.path front_cover,k.path back_cover
                FROM books b JOIN book_agreements a ON a.id=b.agreement_id JOIN users u ON u.id=b.writer_id
                JOIN media f ON f.id=b.front_media_id AND f.deleted_at IS NULL AND f.mime LIKE 'image/%'
                JOIN media k ON k.id=b.back_media_id AND k.deleted_at IS NULL AND k.mime LIKE 'image/%'
                WHERE $where ORDER BY (CASE WHEN a.service_end IS NULL OR a.service_end>=CURRENT_DATE THEN b.is_featured ELSE 0 END) DESC,b.published_at DESC,b.id DESC LIMIT ".max(1,min(100,$limit)).' OFFSET '.max(0,$offset), $params);
        } catch(\Throwable) { return []; }
    }

    public static function hasPublicBooks(): bool {
        try {
            return (bool)Database::one("SELECT b.id FROM books b JOIN book_agreements a ON a.id=b.agreement_id JOIN users u ON u.id=b.writer_id JOIN media f ON f.id=b.front_media_id AND f.deleted_at IS NULL AND f.mime LIKE 'image/%' JOIN media k ON k.id=b.back_media_id AND k.deleted_at IS NULL AND k.mime LIKE 'image/%' WHERE ".self::publicWhere()." AND b.promo_directory=1 AND a.allow_directory=1 LIMIT 1");
        } catch(\Throwable) { return false; }
    }

    public static function homePromotions(): array {
        try {
            return Database::all("SELECT b.id,b.title,b.slug,b.genre,u.name author_name,f.path front_cover FROM books b JOIN book_agreements a ON a.id=b.agreement_id JOIN users u ON u.id=b.writer_id JOIN media f ON f.id=b.front_media_id AND f.deleted_at IS NULL AND f.mime LIKE 'image/%' JOIN media k ON k.id=b.back_media_id AND k.deleted_at IS NULL AND k.mime LIKE 'image/%' WHERE ".self::publicWhere()." AND b.promo_home=1 AND a.allow_home=1 AND (a.service_end IS NULL OR a.service_end>=CURRENT_DATE) ORDER BY b.is_featured DESC,b.published_at DESC LIMIT 4");
        } catch(\Throwable) { return []; }
    }

    public static function publicSitemap(): array {
        try {
            return Database::all("SELECT b.slug,b.updated_at FROM books b JOIN book_agreements a ON a.id=b.agreement_id JOIN users u ON u.id=b.writer_id JOIN media f ON f.id=b.front_media_id AND f.deleted_at IS NULL AND f.mime LIKE 'image/%' JOIN media k ON k.id=b.back_media_id AND k.deleted_at IS NULL AND k.mime LIKE 'image/%' WHERE ".self::publicWhere()." AND b.promo_directory=1 ORDER BY b.id");
        } catch(\Throwable) { return []; }
    }

    public static function notify(int $userId,string $title,string $message,string $link): void {
        Database::insert('INSERT INTO notifications(user_id,type,title,message,link,is_read,created_at) VALUES(?,?,?,?,?,0,CURRENT_TIMESTAMP)',[$userId,'book_service',$title,$message,$link]);
    }

    public static function notifyAdmins(string $title,string $message,string $link): void {
        foreach(Database::all("SELECT id FROM users WHERE role='admin' AND status='active'") as $admin){self::notify((int)$admin['id'],$title,$message,$link);}
    }

    public static function notifyExpiringAgreements(int $adminId): void {
        try {
            $rows=Database::all("SELECT id,request_id,service_end FROM book_agreements WHERE status='accepted' AND service_end BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE,INTERVAL 7 DAY)");
            foreach($rows as $a){
                $title='اقتراب انتهاء اتفاق كتاب #'.(int)$a['id'];$link='/admin/books/agreements/'.(int)$a['id'];
                if(!Database::one('SELECT id FROM notifications WHERE user_id=? AND type=? AND title=? LIMIT 1',[$adminId,'book_service',$title])) self::notify($adminId,$title,'تنتهي فترة الاتفاق في '.(string)$a['service_end'].'. راجع شروط استمرار عرض الكتاب والترويج.',$link);
            }
        } catch(\Throwable) {}
    }
}
