<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Creates portable SQL dumps for downloads and rollback snapshots. */
final class DatabaseBackup
{
    public static function sql(): string
    {
        $pdo = Database::pdo();
        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        $sql = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $table = (string) $table;
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
            $definition = (string) ($create[1] ?? '');
            if ($definition === '') continue;

            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$definition};\n\n";
            $rows = $pdo->query("SELECT * FROM `{$table}`");
            while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $columns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
                $values = array_map(static fn (mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row));
                $sql .= 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
            }
            $sql .= "\n";
        }

        return $sql . "SET FOREIGN_KEY_CHECKS=1;\n";
    }

    /** Refuses a destructive restore unless a private rollback point is saved. */
    public static function saveSnapshot(string $reason = 'snapshot'): string
    {
        $directory = BASE_PATH . '/storage/backups';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the secure backup directory.');
        }

        $safeReason = preg_replace('/[^a-z0-9_-]+/i', '-', $reason) ?: 'snapshot';
        $destination = $directory . '/' . $safeReason . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sql';
        $temporary = tempnam($directory, 'backup-');
        if ($temporary === false) throw new \RuntimeException('Unable to prepare the rollback snapshot.');

        try {
            if (file_put_contents($temporary, self::sql(), LOCK_EX) === false || !rename($temporary, $destination)) {
                throw new \RuntimeException('Unable to save the rollback snapshot.');
            }
            @chmod($destination, 0600);
            return $destination;
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }
}
