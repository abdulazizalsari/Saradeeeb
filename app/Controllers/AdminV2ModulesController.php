<?php
namespace App\Controllers;

use App\Core\Database;

/** Admin V2 entry point for legacy-backed modules. It exposes real counts and
 * keeps the existing, tested mutation endpoints as the source of truth. */
final class AdminV2ModulesController extends AdminV2BaseController
{
    private const MODULES = [
        'translations'=>['label'=>'الترجمة واللغات','description'=>'التقدم، الأعمدة اللغوية، الاستيراد والتصدير والمراجعة.','url'=>'/admin/languages','icon'=>'文'],
        'media'=>['label'=>'مكتبة الوسائط','description'=>'إدارة الصور والملفات المستخدمة في المقالات والصفحات.','url'=>'/admin/media','icon'=>'▧'],
        'messages'=>['label'=>'الرسائل والتواصل','description'=>'رسائل التواصل ومراسلات الكتّاب والمنتدى.','url'=>'/admin/messages','icon'=>'✉'],
        'comments'=>['label'=>'التعليقات والمنتدى','description'=>'المراجعة، الحذف، التصنيف وإدارة المنتدى.','url'=>'/admin/comments','icon'=>'◌'],
        'categories'=>['label'=>'التصنيفات والوسوم','description'=>'تصنيفات المقالات والخواطر والوسوم.','url'=>'/admin/categories','icon'=>'#'],
        'podcasts'=>['label'=>'البودكاست','description'=>'الحلقات، الترجمات وحالة النشر.','url'=>'/admin/podcast','icon'=>'◉'],
        'ads'=>['label'=>'الإعلانات والمواضع','description'=>'الإعلانات والبانرات ومواضع العرض.','url'=>'/admin/ads','icon'=>'▣'],
        'system'=>['label'=>'إعدادات النظام','description'=>'الإعدادات، النسخ الاحتياطي، الروابط، التكاملات وسلامة النظام.','url'=>'/admin/settings','icon'=>'⚙'],
    ];

    public function index(): void
    {
        $u=$this->admin();
        $counts=$this->counts();
        $this->render('modules-v2',compact('counts','u')+['modules'=>self::MODULES],'وحدات الإدارة');
    }

    public function section(string $module): void
    {
        $u=$this->admin();
        if(!isset(self::MODULES[$module])){http_response_code(404);exit('الوحدة غير موجودة.');}
        $counts=$this->counts(); $item=self::MODULES[$module];
        $this->render('module-v2',compact('counts','u','module','item')+['modules'=>self::MODULES],'وحدة '.$item['label']);
    }

    private function counts(): array
    {
        $queries=[
            'translations'=>'SELECT COUNT(*) c FROM languages WHERE enabled=1',
            'media'=>'SELECT COUNT(*) c FROM media',
            'messages'=>'SELECT COUNT(*) c FROM contact_messages WHERE status IN (\'new\',\'read\')',
            'comments'=>'SELECT COUNT(*) c FROM comments WHERE status IN (\'pending\',\'visible\')',
            'categories'=>'SELECT COUNT(*) c FROM categories WHERE active=1',
            'podcasts'=>"SELECT COUNT(*) c FROM podcast_episodes WHERE status='published'",
            'ads'=>'SELECT COUNT(*) c FROM advertisements WHERE active=1',
            'system'=>'SELECT COUNT(*) c FROM audit_logs',
        ];
        $out=[]; foreach($queries as $key=>$sql){try{$out[$key]=(int)(Database::one($sql)['c']??0);}catch(\Throwable){$out[$key]=null;}}
        return $out;
    }
}
