# Data gaps — verified 2026-10-20

## Production Neon counts
- Articles: **21**; article translations: **19**; users: **1**.
- **13** translations have empty content and **13** use temporary slugs matching `archive-%`.
- The current 1-user placeholder archive account is not a migration of original account identities.

## Original source
- A local SQL restore file was previously prepared from the uploaded backup, but the complete original records have **not** been applied to Neon. Original source data includes more than article titles and must be migrated without exposing personal records.
- Original MySQL schema contains approximately 89 tables; Neon has not been proven equivalent.
- Original image URLs and actual asset existence are not comprehensively audited.

## Next action
- Restore full original translations and canonical slugs through a private idempotent migration, using explicit checks and no blind deletion. Run `node nextjs/scripts/audit-data.mjs` with `DATABASE_URL` to identify gaps.
- Do not publish an unverified '100% restored' claim.
