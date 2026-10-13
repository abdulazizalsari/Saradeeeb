<?php
namespace App\Controllers;

use App\Core\Database;

final class AdController {
    public function click(string $id): void {
        $ad=Database::one('SELECT id,target_url,active FROM ads WHERE id=?',[(int)$id]);
        if(!$ad || empty($ad['active']) || empty($ad['target_url'])) redirect('/');
        try{ Database::exec('UPDATE ads SET clicks=clicks+1 WHERE id=?',[(int)$ad['id']]); }catch(\Throwable){}
        $target=(string)$ad['target_url'];
        if(!preg_match('~^https?://~i',$target)) $target=url($target);
        header('Location: '.$target,true,302); exit;
    }
}
