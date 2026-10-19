# Migration status (not production-ready)

## Implemented
- Next.js application deployed to Vercel from GitHub main.
- Original public CSS preserved at `app/original-site.css`.
- Basic homepage and locale index routes (not pixel-perfect).
- Read-only article list and article details backed by optional MySQL `DATABASE_URL`.
- Readiness endpoint: `/api/health`.

## Not implemented
- Original PHP template parity, rich article HTML rendering, original assets and images.
- Authentication, Google OAuth, 2FA, session management, role-based authorization.
- Writer dashboard, editorial workflows, admin and admin-v2 panels.
- Book, podcast, forum, comments, newsletters, translations workflow, uploads.
- Complete schema provisioning and Vercel-managed database.
- Automated visual and functional acceptance tests.

## Safety
The PHP source remains intact. Do not replace the original production domain. A Vercel READY status only proves that a build deployed, not that PHP features were ported.

Configure `DATABASE_URL` through Vercel's protected environment variables, never in Git. No database is currently provisioned by this migration.
