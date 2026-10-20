# Saradeeeb / سراديب

This repository contains the original PHP site and a Next.js migration in `nextjs/`. **Restoration is in progress, not complete.** See [RESTORE_LOG.md](RESTORE_LOG.md), [DATA-GAPS.md](DATA-GAPS.md) and [FINAL-REPORT.md](FINAL-REPORT.md).

## Local development

Requirements: Node.js 20+ and PostgreSQL (Neon is used for the hosted database).

```bash
cd nextjs
npm install
npm run dev
```

Open http://localhost:3000. For database-backed articles, categories and pages, configure `DATABASE_URL` in `nextjs/.env.local`. For the existing web account endpoints, configure a random `AUTH_SECRET` of at least 32 characters. Do not commit secrets or customer data.

## Database migrations

The original PHP MySQL schema is `database/schema.sql` (89 table declarations). Incremental PostgreSQL migrations are in `nextjs/migrations/`. Apply each file in filename order using a privileged Neon/PostgreSQL connection. These migrations are additive and do **not** restore missing data automatically. The production Neon database currently has a partial schema. Import original backups only after verifying their provenance and reviewing personal data handling.

## Production

Next.js is deployed from `main` to Vercel at https://saradeeeb-preview.vercel.app. Set `DATABASE_URL`, `AUTH_SECRET` and `NEXT_PUBLIC_SITE_URL` in Vercel environment settings. Media upload features may additionally need `BLOB_READ_WRITE_TOKEN`. `PHP_ADMIN_ORIGIN` is reserved for a separately hosted PHP administration service; the admin is not yet ready.

Keep original PHP source files unchanged unless migrating functionality deliberately. Vercel READY is a deployment state, not proof of complete functional restoration.

## Current limits

The original media files, many schema tables, original account migration, writer workflows, community features, and PHP administration are not fully restored. Visual parity and complete browser/build acceptance have not been verified. Per the owner's current instruction, tests are deferred until restoration is complete.
