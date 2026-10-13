<?php
$fullAdmin=$fullAdmin??is_admin();
$roleLabels=[
    'admin'=>'مدير كامل',
    'editor'=>'مساعد المدير',
    'trusted_writer'=>'كاتب',
    'writer'=>'كاتب',
];
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <span class="panel-kicker"><?=$fullAdmin?'الحسابات والصلاحيات':'إدارة الكتّاب'?></span>
            <h2><?=$fullAdmin?'المديرون والكتّاب':'حسابات الكتّاب'?></h2>
        </div>
        <a class="btn btn-ghost" href="/admin/writer-applications">طلبات الكتّاب</a>
    </div>

    <details class="user-admin-card" open style="margin-bottom:18px">
        <summary>
            <div><strong><?=$fullAdmin?'إنشاء حساب جديد':'إضافة كاتب جديد'?></strong><span><?=$fullAdmin?'يمكنك إنشاء مساعد مدير أو كاتب.':'صلاحيتك تسمح بإضافة وإدارة الكتّاب فقط.'?></span></div>
        </summary>
        <form method="post" action="/admin/users/save" class="form-grid user-edit-form">
            <?=csrf_field()?>
            <input type="hidden" name="id" value="0">
            <label>الاسم<input name="name" required></label>
            <label>اسم المستخدم<input name="username" pattern="[A-Za-z][A-Za-z0-9_-]{2,29}" required><small>إنجليزي فقط، 3–30 محرفًا.</small></label>
            <label>البريد<input type="email" name="email" required></label>
            <label>الهاتف<input name="phone"></label>
            <label>الدور
                <select name="role">
                    <option value="writer">كاتب</option>
                    <option value="trusted_writer">كاتب</option>
                    <?php if($fullAdmin):?>
                        <option value="editor">مساعد المدير — محتوى وكتّاب فقط</option>
                        <option value="admin">مدير كامل</option>
                    <?php endif;?>
                </select>
            </label>
            <label>الحالة
                <select name="status">
                    <option value="active">مفعل</option>
                    <option value="pending">معلق</option>
                    <option value="blocked">محظور</option>
                </select>
            </label>
            <label class="span-2">نبذة<textarea name="bio" rows="3"></textarea></label>
            <label class="span-2">كلمة المرور الأولية<input type="password" name="new_password" minlength="12"><small>للكاتب يمكنك تركها فارغة ليصله رابط تأكيد البريد وإنشاء كلمة المرور بنفسه. لحسابات الإدارة حدد كلمة مرور من 12 محرفًا على الأقل.</small></label>
            <button class="btn btn-primary span-2"><?=$fullAdmin?'إنشاء الحساب':'إنشاء حساب الكاتب'?></button>
        </form>
    </details>

    <div class="user-admin-grid">
        <?php foreach($users as $x):?>
            <details class="user-admin-card">
                <summary>
                    <div><strong><?=e($x['name'])?></strong><span>@<?=e($x['username'])?> · <?=e($x['email'])?></span></div>
                    <div class="user-role-badges"><span class="status"><?=e($roleLabels[$x['role']]??$x['role'])?></span><span class="status s-<?=e($x['status'])?>"><?=e($x['status'])?></span></div>
                </summary>
                <?php if(in_array($x['role'],['writer','trusted_writer'],true)):?><div class="writer-card-actions"><a class="btn btn-ghost" href="/admin/writers/<?=$x['id']?>">ملف الكاتب والنشاط</a><a class="btn btn-ghost" target="_blank" href="<?=e(writer_url($x['username'],default_locale()))?>">عرض الملف العام</a><?php if($fullAdmin && $x['status']==='active'):?><form method="post" action="/admin/users/impersonate" onsubmit="return confirm('الدخول إلى لوحة هذا الكاتب؟')"><?=csrf_field()?><input type="hidden" name="user_id" value="<?=$x['id']?>"><button class="btn btn-primary">الدخول ككاتب</button></form><?php endif;?></div><?php endif;?>
                <form method="post" action="/admin/users/save" class="form-grid user-edit-form">
                    <?=csrf_field()?>
                    <input type="hidden" name="id" value="<?=$x['id']?>">
                    <label>الاسم<input name="name" value="<?=e($x['name'])?>" required></label>
                    <label>اسم المستخدم<input name="username" pattern="[A-Za-z][A-Za-z0-9_-]{2,29}" value="<?=e($x['username'])?>" required><small><?=e(writer_url($x['username']))?></small></label>
                    <label>البريد<input type="email" name="email" value="<?=e($x['email'])?>" required></label>
                    <label>الهاتف<input name="phone" value="<?=e($x['phone']??'')?>"></label>
                    <label>الدور
                        <select name="role">
                            <option value="member" <?=$x['role']==='member'?'selected':''?>>عضو منتدى</option>
                            <option value="writer" <?=$x['role']==='writer'?'selected':''?>>كاتب</option>
                            <option value="trusted_writer" <?=$x['role']==='trusted_writer'?'selected':''?>>كاتب</option>
                            <?php if($fullAdmin):?>
                                <option value="editor" <?=$x['role']==='editor'?'selected':''?>>مساعد المدير — صلاحيات محدودة</option>
                                <option value="admin" <?=$x['role']==='admin'?'selected':''?>>مدير كامل</option>
                            <?php endif;?>
                        </select>
                    </label>
                    <label>الحالة<select name="status"><option value="active" <?=$x['status']==='active'?'selected':''?>>مفعل</option><option value="pending" <?=$x['status']==='pending'?'selected':''?>>معلق</option><option value="blocked" <?=$x['status']==='blocked'?'selected':''?>>محظور</option></select></label>
                    <label class="span-2">نبذة الكاتب<textarea name="bio" rows="4"><?=e($x['bio']??'')?></textarea></label><?php if($fullAdmin && in_array($x['role'],['writer','trusted_writer','editor','admin'],true)):?><div class="span-2 writer-admin-badges"><label class="check-line"><input type="checkbox" name="writer_verified" value="1" <?=!empty($x['writer_verified'])?'checked':''?>> ✓ كاتب موثّق</label><label class="check-line"><input type="checkbox" name="writer_honored" value="1" <?=!empty($x['writer_honored'])?'checked':''?>> <span class="green-dot"></span> كاتب مميّز / مكرّم</label></div><?php endif;?>
                    <label class="span-2">تعيين كلمة مرور جديدة<input type="password" name="new_password" minlength="12" placeholder="اتركها فارغة إذا لا تريد تغييرها"><small>الإدارة تستطيع إعادة التعيين، والكاتب يستطيع تغييرها بنفسه من حسابه أو استعادتها عبر البريد.</small></label>
                    <button class="btn btn-primary span-2">حفظ بيانات الحساب</button>
                </form>
            </details>
        <?php endforeach;?>
    </div>
</section>
