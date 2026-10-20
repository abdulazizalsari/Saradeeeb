# Admin hybrid design — Vercel public app + PHP admin

**Status: ARCHITECTURE ONLY; no PHP admin service is deployed.**

Public website remains Next.js on Vercel. A separate HTTPS PHP 8.2+ runtime can host the existing admin PHP routes at a dedicated `admin.` subdomain without changing the public site's domain. `PHP_ADMIN_ORIGIN` controls the guarded Next.js `/admin` redirect; if unset, it displays an unavailable notice instead of a fake admin.

The PHP admin must use the same Neon PostgreSQL database through `pdo_pgsql` with TLS, a dedicated least-privilege DB role, secure secrets in environment variables, CSRF, secure cookies, and explicit authorization. Do not share raw PHP session cookies with Next.js without an authenticated SSO design.

`app/Core/Database.php` now includes a PostgreSQL PDO connection option alongside MySQL and SQLite. Adding PDO pgsql DSN alone is insufficient: every MySQL-specific query (`ON DUPLICATE KEY`, `INSERT IGNORE`, `LAST_INSERT_ID`, backticks, `LIMIT` differences, `ENUM`, `JSON` operators, `DATE_FORMAT`) must be audited and adapted. Keep MySQL support for the original installation.

**Blocking dependencies:** compatible full PostgreSQL schema, private data migration, PHP runtime and admin subdomain, verified query parity and permissions. No production admin credentials should be tested against incomplete schema.

## Implementation update
`app/Core/Database.php` now supports `driver=pgsql` using a TLS-enabled PDO DSN and keeps the MySQL default unchanged. This is **connection support only**, not a verified administrative SQL compatibility layer; all MySQL-specific queries and schema differences remain blockers.
