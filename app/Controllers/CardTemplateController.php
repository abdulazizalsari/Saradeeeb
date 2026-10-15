<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\CardTemplateService;

final class CardTemplateController {
    private function admin(): array { Auth::requireRole(['admin']); return Auth::user()??[]; }
    private function template(int $id): array { $row=Database::one('SELECT * FROM card_templates WHERE id=?',[$id]); if(!$row) throw new \RuntimeException('القالب غير موجود.'); return $row; }
    public function index(): void { $this->admin(); $templates=Database::all('SELECT ct.*,u.name uploaded_by_name FROM card_templates ct LEFT JOIN users u ON u.id=ct.uploaded_by ORDER BY ct.type,ct.is_active DESC,ct.created_at DESC,ct.id DESC'); View::render('admin/card-templates',compact('templates')+['types'=>CardTemplateService::types(),'title'=>'قوالب البطاقات'],'dashboard'); }
    public function upload(): void { $admin=$this->admin(); Csrf::enforce(); try{$data=(new CardTemplateService())->upload($_FILES['template_html']??[],(string)($_POST['type']??'')); $id=Database::insert('INSERT INTO card_templates(type,name,version,path,is_active,uploaded_by) VALUES(?,?,?,?,0,?)',[$data['type'],$data['name'],$data['version'],$data['path'],(int)$admin['id']]); audit_log('card_template_upload','card_template',$id,$data['type'].'@'.$data['version']); flash('success','تم رفع القالب وفحصه. النسخة غير فعّالة حتى تفعيلها.');}catch(\Throwable $e){flash('error','تعذر رفع القالب: '.$e->getMessage());} redirect('/admin/card-templates'); }
    public function preview(int $id): void { $this->admin(); $template=$this->template($id); $html=@file_get_contents(BASE_PATH.'/storage/card-templates/'.$template['path']); if(!is_string($html)){http_response_code(404);exit('ملف القالب غير موجود.');} try{$card=(new CardTemplateService())->render($html,['name'=>'اسم الكاتب التجريبي','username'=>'writer_demo','reason'=>'تقديرًا لمساهماته المتميزة وإثرائه المحتوى الأصيل في سراديب.','profile_url'=>rtrim(url('/'),'/').'/writer/writer_demo']);}catch(\Throwable $e){http_response_code(422);exit('تعذر عرض القالب: '.e($e->getMessage()));} View::render('admin/card-template-preview',compact('template','card')+['title'=>'معاينة قالب بطاقة'],'dashboard'); }
    public function activate(): void { $this->admin(); Csrf::enforce(); try{$template=$this->template((int)($_POST['id']??0)); Database::transaction(function()use($template){Database::exec('UPDATE card_templates SET is_active=0 WHERE type=?',[$template['type']]); Database::exec('UPDATE card_templates SET is_active=1 WHERE id=?',[$template['id']]);}); audit_log('card_template_activate','card_template',(int)$template['id'],$template['type']);flash('success','تم تفعيل القالب.');}catch(\Throwable){flash('error','تعذر تفعيل القالب.');} redirect('/admin/card-templates'); }
    public function delete(): void { $this->admin(); Csrf::enforce(); try{$template=$this->template((int)($_POST['id']??0)); Database::exec('DELETE FROM card_templates WHERE id=?',[$template['id']]); (new CardTemplateService())->deleteStored((string)$template['path']); audit_log('card_template_delete','card_template',(int)$template['id'],$template['type']);flash('success',$template['is_active']?'تمت إزالة القالب الفعّال والعودة للتصميم الافتراضي.':'تمت إزالة القالب.');}catch(\Throwable){flash('error','تعذر إزالة القالب.');} redirect('/admin/card-templates'); }
}
