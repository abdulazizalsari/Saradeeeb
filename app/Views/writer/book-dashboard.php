<?php if(empty($u['writer_vip']) && in_array((string)($u['role']??''),['writer','trusted_writer'],true) && !empty($u['writer_verified']) && !\App\Core\Auth::isImpersonating()): ?>
<section class="panel book-service-cta" aria-label="خدمة نشر الكتب">
<?php if(empty($bookRequest)): ?><form method="post" action="/writer/book-service/request" onsubmit="return confirm('هل تريد إرسال طلب نشر وتسويق كتابك إلى إدارة سراديب؟')"><?=csrf_field()?><button type="submit" class="btn btn-primary">بيع كتابي عبر المنصة</button></form>
<?php else: ?><?php $bookStatusLabels=['pending'=>'تم الاستلام','reviewing'=>'قيد المراجعة','negotiating'=>'قيد التفاوض','offer_sent'=>'بانتظار موافقتك على الاتفاق','agreement_accepted'=>'تم قبول الاتفاق','agreement_declined'=>'رُفض العرض']; ?><p>طلب نشر كتابك لدى الإدارة. الحالة: <strong><?=e($bookStatusLabels[$bookRequest['status']]??(string)$bookRequest['status'])?></strong></p><?php if($bookOfferId):?><a class="btn btn-primary" href="/writer/book-offer/<?=$bookOfferId?>">مراجعة الاتفاق</a><?php endif; ?><?php endif; ?>
</section>
<?php endif; ?>
<?php require BASE_PATH.'/app/Views/writer/'.(!empty($u['writer_vip'])?'vip-dashboard.php':'dashboard.php'); ?>
