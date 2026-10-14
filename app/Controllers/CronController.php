<?php
namespace App\Controllers;

use App\Services\ScheduledPublisher;

final class CronController {
    public function publishScheduled(): void {
        $secret = (string)(getenv('CRON_SECRET') ?: '');
        $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if ($secret === '' || !hash_equals('Bearer ' . $secret, $authorization)) {
            http_response_code(401); header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'unauthorized']); return;
        }
        try {
            $published = ScheduledPublisher::run();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'published' => $published, 'checked_at' => gmdate('c')]);
        } catch (\Throwable $error) {
            error_log('Saradeeeb protected cron failed: ' . $error->getMessage());
            http_response_code(500); header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'scheduled_publication_failed']);
        }
    }
}
