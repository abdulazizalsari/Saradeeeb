<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\DatabaseBackup;

$oldSlug = 'behind-saradeeb';
$newSlug = 'من-خلف-سراديب';
$page = Database::one(
    "SELECT p.id, pt.id AS translation_id, pt.slug
     FROM pages p
     JOIN page_translations pt ON pt.page_id = p.id AND pt.locale = 'ar'
     WHERE p.deleted_at IS NULL AND (pt.slug = ? OR pt.slug = ?)
     ORDER BY p.id
     LIMIT 1",
    [$oldSlug, $newSlug]
);

if (!$page) {
    throw new RuntimeException('The managed Behind Saradeeeb page was not found.');
}

$backup = DatabaseBackup::saveSnapshot('before-behind-saradeeb-slug');

Database::transaction(static function () use ($page, $oldSlug, $newSlug): void {
    $conflict = Database::one(
        'SELECT id FROM page_translations WHERE locale = ? AND slug = ? AND page_id <> ? LIMIT 1',
        ['ar', $newSlug, (int) $page['id']]
    );
    if ($conflict) {
        throw new RuntimeException('The requested Arabic page slug is already in use.');
    }

    Database::exec(
        'UPDATE page_translations SET slug = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
        [$newSlug, (int) $page['translation_id']]
    );
    Database::exec(
        'INSERT INTO redirects(source_path,target_url,http_code) VALUES(?,?,301) ON DUPLICATE KEY UPDATE target_url=VALUES(target_url),http_code=VALUES(http_code)',
        ['/page/' . $oldSlug, '/page/' . rawurlencode($newSlug)]
    );
    Database::exec(
        'INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=CURRENT_TIMESTAMP',
        ['footer_behind_url', '/page/' . rawurlencode($newSlug)]
    );
});

echo json_encode([
    'page_id' => (int) $page['id'],
    'old_slug' => $oldSlug,
    'new_slug' => $newSlug,
    'backup_created' => basename($backup),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
