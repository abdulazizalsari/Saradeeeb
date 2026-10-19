# Decisions — Vercel parity work

- 2026-10-20: Vercel and `main` remain the only production target; no PHP files are removed.
- 2026-10-20: Preserve the existing homepage at `/` while the `/ar` locale is aligned; do not redirect `/` until equivalent localized layout and link behavior are verified, to avoid breaking the currently working production homepage.
- 2026-10-20: Never call an untested or placeholder route complete. Keep legacy PHP as the source of truth.
- 2026-10-20: Do not remove existing Neon rows or migrate user password material automatically. Data migration must be non-destructive, reversible, and validated before production changes.
- 2026-10-20: PHP admin on Vercel requires a separate compatible PHP runtime, not a rewrite rule to a PHP file.
