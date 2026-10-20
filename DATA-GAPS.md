# Data gaps — verified 2026-10-20

## Production Neon after public-content restoration
- Articles: **21** (20 from original archive + 1 newly authored).
- Article translations: **19** (18 from original archive + 1 newly authored).
- Empty article translations: **0**.
- Temporary `archive-%` slugs: **0**.
- Minimum original translated article content length: **1603 characters**.
- Users: **1 placeholder archival author**; original user accounts have **not** been imported.

## What was imported
The previously generated private-data-free `saradeeeb_restore_public_content.sql` was applied to production Neon using idempotent UPSERT statements in six transactions. It restores the original 18 translated article bodies and source slugs, along with 20 article records. No password hashes, emails, 2FA secrets, or other private user data were included.

## Outstanding
- Original schema contains approximately 89 MySQL tables; PostgreSQL parity is not complete.
- Original category, page, author, settings, comments, writer and community records are not fully migrated.
- Original article image URLs were restored as references, but actual image availability and complete media migration remain unverified.
- Original user identities and authentication compatibility are not migrated.
- One original article record may lack a translation; reconcile source-to-target relationships.
