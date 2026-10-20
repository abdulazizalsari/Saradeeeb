# Data gaps — verified 2026-10-20

## Production Neon after restoration
- **21 articles** (20 original, 1 new); **19 article translations** (18 original, 1 new).
- **0 empty article translations**; **0 temporary `archive-%` slugs**.
- **10 original categories**, **14 category translations**, **22 article-category links** (includes preexisting links).
- **1 placeholder archive author**; original accounts and password hashes have **not** been migrated.

## Completed
- Applied sanitized original public-content SQL through six idempotent Neon transactions, preserving original article bodies and slugs.
- Restored category names, locale translations and original article-category relationships from the October 13 source backup.
- Implemented category pages and connected real category navigation on homepage.

## Remaining
- The original MySQL schema contains approximately 89 tables, versus nine currently present in Neon.
- Original authors, user identities, comments, pages, books, podcasts, community, settings and admin data require private migration and compatibility work.
- Original image URLs are referenced but actual image availability and complete media transfer have not been validated.
- Legacy route parity and browser functional tests are outstanding.
