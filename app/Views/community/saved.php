<?php $C=community_detail_ui($locale); ?>
<section class="community-page"><div class="container community-account-page"><header><a href="<?=e(locale_url($locale,'community'))?>">← <?=e($C['brand'])?></a><h1><?=e($C['saved_topics'])?></h1><p><?=e($C['saved_private'])?></p></header><div class="community-topic-list"><?php if(!$rows):?><div class="community-empty"><?=e($C['no_saved'])?></div><?php else:foreach($rows as $topic)require __DIR__.'/_topic-card.php';endif;?></div></div></section>

