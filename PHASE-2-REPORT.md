# Phase 2 — Data and media

**Status: PARTIAL / NOT ACCEPTED.**

## Completed
- Imported original **20 article records and 18 full article translations** into production Neon via six idempotent SQL transactions.
- Preserved source slugs, original article text and source image references.
- Retained the separately authored article `جن الصحراء`.
- Confirmed **21 articles, 19 translations, 0 empty translations, 0 temporary archive slugs** in production Neon.
- Added read-only `nextjs/scripts/audit-data.mjs`.

## Outstanding
- Complete MySQL-to-PostgreSQL schema conversion for ~89 tables.
- Original accounts, comments, categories, pages, settings and community data.
- Real image uploads and HTTP verification, source-to-target reconciliation for all tables.
- Do not expose or commit private user backup data.

## Tests
- Direct Neon production aggregate query **passed** for nonempty article translations and absence of temporary slugs.
- Image checks, full source-to-target table reconciliation and functional acceptance **not run**.
