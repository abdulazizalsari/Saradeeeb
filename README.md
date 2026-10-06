# Saradeeeb

Saradeeeb — PHP + MariaDB website project.

## Repository contents

This repository is the cleaned PHP-only source of the Saradeeeb project. The Laravel/Filament admin subsystem was removed; the project uses the native PHP admin under `app/Controllers` and `app/Views/admin`.

Runtime/generated data and private configuration are intentionally excluded from GitHub:
- `config/local.php`
- `.env`
- `uploads/`
- generated `storage/` data
- local logs/backups

Database schema: `database/schema.sql`

The project requires PHP 8+ and MariaDB/MySQL.
