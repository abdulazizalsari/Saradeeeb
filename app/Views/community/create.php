<?php $C=community_detail_ui($locale); ?>
<section class="community-page"><div class="container community-form-shell">
  <header><a href="<?=e(locale_url($locale,'community'))?>">← <?=e($C['back'])?></a><span><?=e($C['new_discussion'])?></span><h1><?=e($C['start_discussion'])?></h1><p><?=e($C['start_intro'])?></p></header>
  <?php if($x=flash('error')):?><div class="notice error"><?=e($x)?></div><?php endif;?>
  <form method="post" action="<?=e(locale_url($locale,'community/new'))?>" enctype="multipart/form-data" class="community-create-form"><?=csrf_field()?>
    <label><?=e($C['discussion_title'])?><input name="title" maxlength="190" required placeholder="<?=e($C['discussion_title_ph'])?>"></label>
    <label><?=e($C['section'])?><select name="category_id" required><option value=""><?=e($C['choose_section'])?></option><?php foreach($categories as $cat):?><option value="<?=$cat['id']?>"><?=e($cat['name'])?></option><?php endforeach;?></select></label>
    <div class="community-editor" data-community-editor><div class="community-editor-toolbar"><button type="button" data-editor-tag="strong"><b>B</b></button><button type="button" data-editor-list>• <?=e($C['list'])?></button><button type="button" data-editor-tag="blockquote">“ <?=e($C['quote'])?></button><button type="button" data-editor-link>↗ <?=e($C['link'])?></button><button type="button" data-editor-image>▧ <?=e($C['image'])?></button></div><label><?=e($C['content'])?><textarea name="content" rows="13" required placeholder="<?=e($C['content_ph'])?>"></textarea></label></div>
    <div class="community-form-grid"><label><?=e($C['optional_image'])?><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/avif"></label><label><?=e($C['optional_tags'])?><input name="tags" maxlength="500" placeholder="<?=e($C['tags_ph'])?>"></label></div>
    <button class="btn btn-primary community-submit" type="submit"><?=e($C['publish_discussion'])?></button>
  </form>
</div></section>

