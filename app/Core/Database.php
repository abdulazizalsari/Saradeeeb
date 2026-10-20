<?php
namespace App\Core;
use PDO;
use PDOException;

final class Database {
    private static ?PDO $pdo = null;

    public static function init(array $cfg): void {
        if (self::$pdo) return;
        $driver = $cfg['driver'] ?? 'mysql';
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . ($cfg['database'] ?? BASE_PATH . '/storage/dev.sqlite');
            self::$pdo = new PDO($dsn);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        } elseif ($driver === 'pgsql') {
            $host = $cfg['host'] ?? 'localhost';
            $port = (int)($cfg['port'] ?? 5432);
            $db = $cfg['database'] ?? '';
            $sslmode = $cfg['sslmode'] ?? 'require';
            if (!in_array($sslmode, ['require', 'verify-ca', 'verify-full'], true)) {
                throw new \InvalidArgumentException('Unsupported PostgreSQL TLS mode.');
            }
            $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$sslmode}";
            self::$pdo = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            $host = $cfg['host'] ?? 'localhost';
            $port = (int)($cfg['port'] ?? 3306);
            $db = $cfg['database'] ?? '';
            $charset = $cfg['charset'] ?? 'utf8mb4';
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
            self::$pdo = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
    }

    public static function pdo(): PDO {
        if (!self::$pdo) throw new \RuntimeException('Database is not initialized.');
        return self::$pdo;
    }

    public static function all(string $sql, array $params=[]): array {
        $s=self::pdo()->prepare($sql); $s->execute($params); return $s->fetchAll();
    }
    public static function one(string $sql, array $params=[]): ?array {
        $s=self::pdo()->prepare($sql); $s->execute($params); $r=$s->fetch(); return $r === false ? null : $r;
    }
    public static function exec(string $sql, array $params=[]): int {
        $s=self::pdo()->prepare($sql); $s->execute($params); return $s->rowCount();
    }
    public static function insert(string $sql, array $params=[]): int {
        self::exec($sql,$params); return (int)self::pdo()->lastInsertId();
    }
    public static function transaction(callable $cb): mixed {
        $pdo=self::pdo(); $pdo->beginTransaction();
        try { $r=$cb($pdo); $pdo->commit(); return $r; }
        catch (\Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
