<div class="editorial-shell editorial-pro editorial-v640">
  <aside class="editorial-list panel">
    <div class="panel-head"><div><span class="panel-kicker">سراديب</span><h2><?=$staff?'مراسلات الكتّاب':'مراسلات فريق التحرير'?></h2><p><?=$staff?'مساحة مستقلة للمراسلات، بعيدة عن إدارة المقالات.':'تواصل مباشرة مع فريق سراديب.'?></p></div></div>

    <?php if($staff):?>
      <form class="editorial-filters" method="get">
        <input type="search" name="q" value="<?=e($q)?>" placeholder="ابحث باسم الكاتب أو الموضوع...">
        <select name="writer"><option value="0">كل الكتّاب</option><?php foreach($writers as $w):?><option value="<?=$w['id']?>" <?=$writerFilter===$w['id']?'selected':''?>><?=e($w['name'])?> · @<?=e($w['username'])?></option><?php endforeach;?></select>
        <div class="editorial-filter-tabs"><button name="state" value="open" class="<?=$state==='open'?'active':''?>">النشطة</button><button name="state" value="unread" class="<?=$state==='unread'?'active':''?>">غير المقروءة</button><button name="state" value="archived" class="<?=$state==='archived'?'active':''?>">الأرشيف</button></div>
      </form>
      <details class="new-thread writer-new-message"><summary class="btn btn-primary">＋ مراسلة كاتب</summary><form method="post" action="/admin/editorial-messages/create" class="form-grid compact"><?=csrf_field()?><label class="span-2">الكاتب<select name="writer_id" required><option value="">اختر الكاتب</option><?php foreach($writers as $w):?><option value="<?=$w['id']?>" <?=$writerFilter===$w['id']?'selected':''?>><?=e($w['name'])?> · @<?=e($w['username'])?></option><?php endforeach;?></select></label><label class="span-2">القصة المرتبطة (اختياري)<select name="story_submission_id"><option value="0">بدون ربط بقصة</option><?php foreach($writerSubmissions as $submission):?><option value="<?=$submission['id']?>"><?=e($submission['title'])?> · <?=e($submission['status'])?></option><?php endforeach;?></select></label><label class="span-2">موضوع الرسالة<input name="subject" maxlength="190" placeholder="مثال: متابعة حساب الكاتب" required></label><button class="btn btn-primary span-2">بدء المحادثة</button></form></details>
    <?php else:?>
      <details class="new-thread writer-new-message"><summary class="btn btn-primary">＋ رسالة جديدة لفريق التحرير</summary>
        <form method="post" action="/writer/editorial-messages/start" enctype="multipart/form-data" class="form-grid compact rich-message-form"><?=csrf_field()?>
          <label class="span-2">القصة المرتبطة (اختياري)<select name="story_submission_id"><option value="0">بدون ربط بقصة</option><?php foreach($writerSubmissions as $submission):?><option value="<?=$submission['id']?>"><?=e($submission['title'])?> · <?=e($submission['status'])?></option><?php endforeach;?></select></label><label class="span-2">الموضوع<input name="subject" maxlength="190" placeholder="اكتب موضوعًا مختصرًا" required></label>
          <div class="span-2 rich-message-editor" data-rich-wrap><div class="rich-toolbar" role="toolbar"><button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="underline"><u>U</u></button><button type="button" data-cmd="strikeThrough"><s>S</s></button><button type="button" data-cmd="insertUnorderedList">• قائمة</button><button type="button" data-cmd="insertOrderedList">1. قائمة</button><button type="button" data-cmd="formatBlock" data-value="blockquote">❝ اقتباس</button><button type="button" data-link>🔗 رابط</button></div><div class="rich-surface" contenteditable="true" data-rich-editor data-placeholder="اكتب رسالتك لفريق التحرير..."></div><textarea name="body" hidden data-rich-input></textarea></div>
          <label class="span-2 editorial-attach">🖼 صور أو 📄 PDF<input type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp,image/avif,image/gif,application/pdf" multiple data-attachment-input><small>حتى 6 ملفات، 10MB لكل ملف.</small></label><div class="span-2 attachment-preview" data-attachment-preview></div><button class="btn btn-primary span-2">إرسال الرسالة</button>
        </form>
      </details>
    <?php endif;?>

    <div class="thread-list">
      <?php foreach($threads as $t):?><a class="thread-item <?=($thread&&$thread['id']==$t['id'])?'active':''?>" href="<?=e($base)?>/editorial-messages?thread=<?=$t['id']?><?= $staff?'&state='.rawurlencode($state):'' ?>"><div class="thread-writer-row"><?php if(!empty($t['writer_avatar'])):?><img src="<?=e($t['writer_avatar'])?>" alt=""><?php else:?><span><?=e(mb_substr($t['writer_name'],0,1,'UTF-8'))?></span><?php endif;?><strong><?=e($staff?$t['writer_name']:'فريق سراديب')?></strong></div><b class="thread-subject"><?=e($t['subject'])?></b><small><?=e(mb_substr(trim(strip_tags((string)$t['last_message'])),0,75))?></small><time><?=e($t['last_message_at']?:$t['updated_at'])?></time><?php if((int)$t['unread']):?><b class="unread-badge"><?=$t['unread']?></b><?php endif;?></a><?php endforeach;?>
      <?php if(!$threads):?><div class="empty-state">لا توجد مراسلات في هذا القسم.</div><?php endif;?>
    </div>
  </aside>

  <section class="editorial-chat panel">
    <?php if($thread):?>
      <header class="chat-head">
        <div class="chat-head-main"><div><?php if($staff):?><a class="panel-kicker" href="/admin/writers/<?=$thread['writer_id']?>">ملف الكاتب</a><?php else:?><span class="panel-kicker">فريق سراديب</span><?php endif;?><h2><?=e($thread['subject'])?></h2><p><?=$staff?e($thread['writer_name'].' · @'.$thread['username']):'قناة التواصل الخاصة بك مع فريق التحرير'?></p></div>
        <?php if($staff):?><div class="actions"><a class="btn btn-ghost" href="/admin/writers/<?=$thread['writer_id']?>">فتح ملف الكاتب</a><form method="post" action="/admin/editorial-messages/archive"><?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$thread['id']?>"><input type="hidden" name="action" value="<?=$thread['status']==='archived'?'open':'archive'?>"><button class="btn btn-ghost"><?=$thread['status']==='archived'?'إعادة للمراسلات':'أرشفة'?></button></form></div><?php endif;?></div>
        <?php if(!empty($thread['story_submission_id'])):?><div class="editorial-context-card"><span>القصة المرتبطة</span><b><?=e($thread['story_submission_title']?:'مشاركة الكاتب')?></b><a href="/admin/story-submissions">فتح المشاركات</a></div><?php endif;?><?php if(!empty($thread['article_id'])):?><div class="editorial-context-card"><span>مرجع قديم للمحادثة</span><b><?=e($thread['article_title']?:'مقال في سراديب')?></b><a href="/admin/articles/<?=$thread['article_id']?>">فتح المقال منفصلًا ↗</a></div><?php endif;?>
      </header>
      <div class="chat-stream" id="editorialChatStream">
        <?php foreach($messages as $m): $mine=((int)$m['sender_id']===(int)$u['id']); ?>
          <article class="chat-message <?=$mine?'mine':'theirs'?>">
            <div class="chat-message-head"><?php if(!empty($m['sender_avatar'])):?><img src="<?=e($m['sender_avatar'])?>" alt=""><?php else:?><span><?=e(mb_substr($m['sender_name'],0,1,'UTF-8'))?></span><?php endif;?><div><b><?=e($m['sender_name'])?></b><small><?=in_array($m['sender_role'],['admin','editor'],true)?'فريق سراديب':'كاتب'?></small></div></div>
            <?php if(trim((string)$m['body'])!==''):?><div class="chat-rich-body"><?=$m['body']?></div><?php endif;?>
            <?php if(!empty($attachments[(int)$m['id']])):?><div class="chat-attachments"><?php foreach($attachments[(int)$m['id']] as $a):?><?php if(str_starts_with((string)$a['mime_type'],'image/')):?><a class="chat-image-attachment" href="<?=e($a['path'])?>" target="_blank" rel="noopener"><img src="<?=e($a['path'])?>" alt="<?=e($a['original_name'])?>"><span><?=e($a['original_name'])?></span></a><?php else:?><a class="chat-file-attachment" href="<?=e($a['path'])?>" target="_blank" rel="noopener">📄 <span><?=e($a['original_name'])?></span><small><?=number_format(((int)$a['size_bytes'])/1024,0)?> KB</small></a><?php endif;?><?php endforeach;?></div><?php endif;?>
            <small class="chat-time"><?=e($m['created_at'])?><?=$mine?($m['read_at']?' · مقروء ✓✓':' · مرسل ✓'):''?></small>
          </article>
        <?php endforeach;?>
      </div>

      <?php if($thread['status']!=='archived' || !$staff):?><form class="chat-compose rich-message-form" method="post" enctype="multipart/form-data" action="<?=e($base)?>/editorial-messages/send">
        <?=csrf_field()?><input type="hidden" name="thread_id" value="<?=$thread['id']?>">
        <div class="rich-message-editor" data-rich-wrap><div class="rich-toolbar"><button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="underline"><u>U</u></button><button type="button" data-cmd="strikeThrough"><s>S</s></button><button type="button" data-cmd="insertUnorderedList">• قائمة</button><button type="button" data-cmd="insertOrderedList">1. قائمة</button><button type="button" data-cmd="formatBlock" data-value="blockquote">❝ اقتباس</button><button type="button" data-link>🔗 رابط</button></div><div class="rich-surface" contenteditable="true" data-rich-editor data-placeholder="اكتب رسالتك..."></div><textarea name="body" hidden data-rich-input></textarea></div>
        <div class="attachment-preview" data-attachment-preview></div><div class="chat-compose-actions"><label class="editorial-attach mini">📎 صورة / PDF<input type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp,image/avif,image/gif,application/pdf" multiple data-attachment-input></label><button class="btn btn-primary">إرسال</button></div>
      </form><?php elseif($staff):?><div class="archived-thread-note">هذه المحادثة مؤرشفة. أعد فتحها للرد.</div><?php endif;?>
    <?php else:?><div class="empty-state big"><strong>اختر محادثة</strong><span>المراسلات مستقلة عن قسم المقالات ويمكن إدارتها من هنا بالكامل.</span></div><?php endif;?>
  </section>
</div>
<script>
(()=>{
 const init=(wrap)=>{const ed=wrap.querySelector('[data-rich-editor]'),input=wrap.querySelector('[data-rich-input]');if(!ed||!input)return;wrap.querySelectorAll('[data-cmd]').forEach(b=>b.addEventListener('click',()=>{ed.focus();document.execCommand(b.dataset.cmd,false,b.dataset.value||null)}));const link=wrap.querySelector('[data-link]');if(link)link.addEventListener('click',()=>{const u=prompt('ضع الرابط الكامل https://');if(!u)return;if(!/^(https?:\/\/|\/|mailto:)/i.test(u)){alert('الرابط غير صالح.');return}ed.focus();document.execCommand('createLink',false,u)});const form=wrap.closest('form');if(form)form.addEventListener('submit',()=>input.value=ed.innerHTML.trim())};document.querySelectorAll('[data-rich-wrap]').forEach(init);
 document.querySelectorAll('[data-attachment-input]').forEach(input=>input.addEventListener('change',()=>{const box=input.closest('form')?.querySelector('[data-attachment-preview]');if(!box)return;box.innerHTML='';[...(input.files||[])].slice(0,6).forEach(f=>{const x=document.createElement('div');x.className='attachment-preview-item';if(f.type.startsWith('image/')){const img=document.createElement('img');img.src=URL.createObjectURL(f);x.appendChild(img)}else{x.textContent='📄'}const s=document.createElement('span');s.textContent=f.name;x.appendChild(s);box.appendChild(x)})}));
 const stream=document.getElementById('editorialChatStream');if(stream)stream.scrollTop=stream.scrollHeight;
 const activeThread=new URLSearchParams(location.search).get('thread');if(activeThread){let lastActivity=Date.now();document.querySelectorAll('.chat-compose input,.chat-compose textarea,.chat-compose [contenteditable]').forEach(el=>el.addEventListener('input',()=>lastActivity=Date.now()));setInterval(()=>{if(document.visibilityState==='visible'&&Date.now()-lastActivity>5000)location.reload()},20000)}
})();
</script>
