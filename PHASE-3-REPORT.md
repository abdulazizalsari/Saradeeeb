# Phase 3 — Public routes and SEO

**Status: PARTIAL / NOT ACCEPTED.**

Completed: dynamic category detail pages backed by restored category translations and article relationships; homepage category navigation; live sitemap and RSS, robots public indexing, llms.txt, partial route map.

Outstanding: complete 143 public routes and views, other content types, llms-full.txt, ad click redirects, full legacy URL redirects and browser route sweep.

Tests: source-level changes and direct Neon category count verification only. HTTP route sweep not run.

Added a dynamic permanent redirect for legacy root-level article slugs after matching them against published Neon translations. This is partial legacy compatibility, not a complete redirect map.

2026-10-20: Search now queries all published article text in Neon instead of only the first 50 rows; added locale-aware search direction and live llms-full.txt article index. Homepage browse-by-category panel now links to restored original categories. Search SQL was validated directly against Neon with a known article title. End-to-end HTTP tests still pending.

Additional verified source changes: `/[locale]/categories` now renders real Neon categories; detail breadcrumbs point to the directory and detail metadata uses the original category title and description. Production Vercel deployment for commit 63f8d606 was READY; subsequent changes remain unverified until READY. The HTTP retrieval tool did not provide access to the production URL, so browser and HTTP acceptance remain outstanding.
