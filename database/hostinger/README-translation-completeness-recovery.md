# Hostinger translation-completeness recovery

Use this only against the Saradeeeb production database after the complete PHP source is deployed.

1. In Hostinger phpMyAdmin, select the Saradeeeb database and use **Export → Quick → SQL**. Download and retain the resulting backup before running any SQL.
2. Confirm the selected database is the live Saradeeeb database; do not use another site's database.
3. Import `2026-10-14-translation-completeness-recovery.sql` through phpMyAdmin.
4. Confirm that the final query returns all seven `article_translations` columns.
5. Retry the public home page and `/admin-v2` over HTTPS. If either still returns 500, inspect the Hostinger PHP error log; do not re-run installation scripts or reset the database.

The SQL only adds missing columns/tables. It contains no credentials, no destructive statements, and can be safely re-run.
