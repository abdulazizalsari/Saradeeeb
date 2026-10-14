<?php
namespace App\Core;

/**
 * Shared PHP session storage for stateless/serverless runtimes.
 * Enable only with APP_SESSION_DRIVER=database after app_sessions exists.
 */
final class DatabaseSessionHandler implements \SessionHandlerInterface {
    public function __construct(private readonly int $lifetime) {}

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string {
        $row = Database::one('SELECT payload FROM app_sessions WHERE id=? AND expires_at>CURRENT_TIMESTAMP', [$id]);
        return (string)($row['payload'] ?? '');
    }

    public function write(string $id, string $data): bool {
        // Calculate expiry with the database clock. PHP and managed MySQL may
        // use different time zones, which would otherwise expire sessions early.
        Database::exec('INSERT INTO app_sessions(id,payload,expires_at,updated_at) VALUES(?,?,DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ? SECOND),CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at),updated_at=CURRENT_TIMESTAMP', [$id, $data, $this->lifetime]);
        return true;
    }

    public function destroy(string $id): bool {
        Database::exec('DELETE FROM app_sessions WHERE id=?', [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false {
        return Database::exec('DELETE FROM app_sessions WHERE expires_at<=CURRENT_TIMESTAMP');
    }
}
