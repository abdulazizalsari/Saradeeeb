# Phase 1 — Original design parity

**Status: PARTIAL / NOT ACCEPTED.**

## Completed
- Reviewed original public layout, homepage, application JS, original migration plan, and current Next.js routes.
- Updated `/ar` to use the same full homepage component as `/`, replacing the minimal article-list fallback. Committed to `main`.
- Preserved original PHP and the currently imported original stylesheet.

## Outstanding
- Exact markup/class parity for header, footer, ticker, mobile menu, and homepage sections; full audit of footer partials and 66 KB of JS.
- Single original CSS source and asset copy audit; no conflicting CSS has been removed without evidence.
- RTL/LTR on all locale pages, actual interaction parity, and root-to-default-locale redirect remain pending.

## Verification
- Source-level verification only. PHP vs Next.js Playwright screenshots at 1440/390 and pixelmatch **not run**; no defensible visual-diff percentage exists. Target <1% **not met or demonstrated**.
