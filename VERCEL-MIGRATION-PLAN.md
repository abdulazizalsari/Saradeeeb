# Saradeeeb → Vercel: deployment readiness and migration plan

Status: **NOT READY FOR PRODUCTION DEPLOYMENT**. This is a migration plan, not a claim that the PHP application runs on Vercel.

## Verified architecture
- `index.php` dispatches public, writer, authentication, and admin routes via `App\Core\Router`.
- `app/bootstrap.php` loads PHP classes, starts native PHP sessions, initializes PDO, and optionally runs schema migrations.
- `app/Core/Database.php` supports MySQL and local SQLite.
- `config/app.php` points uploads to a local `uploads/` directory.
- `router.php` and `.htaccess` enforce PHP-server-specific protections for internal files.
- The project has server-rendered PHP views, Google OAuth endpoints, CSRF-protected POST routes, role-based administration, and multilingual articles.

## Blocking incompatibilities
1. Vercel does not natively host this full traditional PHP application with its existing PHP router and sessions. Do **not** add a static `vercel.json` rewrite to `index.php` and assume it works.
2. Local PHP session files and uploaded files are not durable across serverless invocations.
3. MySQL is external to Vercel and must be provisioned, backed up, and reached via TLS using securely managed environment variables.
4. PHP-based auth, CSRF, Google OAuth callback, role enforcement, uploads, and administrative workflows must be ported and regression-tested.
5. Never expose `config/`, `database/`, `app/`, `tools/`, installer scripts, SQL files, or internal logs as public assets.
6. Never enable automatic production schema migrations on page requests.

## Target architecture
- Vercel-hosted **Next.js (TypeScript)** application for public pages, localized article routes, admin, writer workspace, and API handlers.
- Managed MySQL-compatible database **outside Vercel**, with TLS, least-privilege credentials, backups, and migration process; retain the current relational schema initially to reduce risk.
- External object storage (S3-compatible or Vercel Blob) for uploads, with authorization, MIME validation, file-size limits, and signed access where required.
- Database-backed or signed-cookie sessions with secure, HttpOnly, SameSite cookies, CSRF protection for mutating endpoints, OAuth redirect URI updated to the new domain.
- Server-side RBAC for admin/editor/trusted_writer/writer and audit trails.

## Required implementation phases
1. Inventory all `index.php` routes and map each to a Next.js page or route handler; enumerate tables, indexes, foreign keys, and migrations.
2. Establish a staging database from a **sanitized** copy, with separate credentials and no real user data in preview deployments.
3. Implement data-access layer and migration runner; verify Arabic and other Unicode collations and slug uniqueness.
4. Port public pages and localized article URLs; preserve canonical links, SEO metadata, sitemap, feeds, and redirects.
5. Port login, password verification/rehashing, Google OAuth, MFA, CSRF, sessions, and RBAC.
6. Port author/editor/admin workflows, uploads, editorial publishing, and notifications.
7. Add tests for authorization, XSS, CSRF, uploads, migrations, language switching, and route parity.
8. Deploy to Vercel **preview** first, with staging secrets; validate against the parity tracker.
9. Back up production DB/uploads, freeze writes briefly, perform a rehearsed migration, verify data counts, then switch DNS only after acceptance tests and rollback plan.

## Environment variables (names only; never commit values)
- `DATABASE_URL` or `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSL`
- `APP_BASE_URL`, `AUTH_SECRET`
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- Storage provider credentials, bucket, and endpoint as required

## Acceptance gates
- Every published article URL resolves with the correct language and SEO tags.
- Login/logout, role restrictions, CSRF, and Google callback work across multiple requests.
- Writers can save, submit, and edit; editors can review; admins can manage users and settings.
- Uploads remain available across deployments and cannot leak private files.
- Database backups, migrations, monitoring, and rollback are proven in staging.
- No PHP source or SQL dump is publicly accessible.

**Current decision:** do not create a production Vercel deployment until the application runtime has been migrated and these checks pass. Hostinger remains the safer production host for the current PHP implementation.
