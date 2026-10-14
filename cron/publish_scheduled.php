<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';
echo "Scheduled publishing checked at ".date('c')."; published ".\App\Services\ScheduledPublisher::run().PHP_EOL;
