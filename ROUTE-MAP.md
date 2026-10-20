# Route map — work in progress

**This is not a claim that all 143 public routes are implemented.** Full inventory must be generated from `index.php` and `router.php` before acceptance.

| Original public route | Next.js route | Status |
|---|---|---|
| `/` | `/` | Partial homepage |
| `/ar` | `/ar` | Full homepage component reused; visual parity unverified |
| `/{locale}` | `/[locale]` | Article-list fallback for non-Arabic locales |
| `/{locale}/article/{slug}` | `/[locale]/article/[slug]` | Partial; requires original slug/content import |
| `/{locale}/search` | `/[locale]/search` | Implemented article search; bounded to 50 recent rows |
| `/feed.xml` | `/feed.xml` | RSS added |
| `/sitemap.xml` | `/sitemap.xml` | Generated from published articles |
| `/robots.txt` | `/robots.txt` | Public indexing allowed |
| `/llms.txt` | `/llms.txt` | Basic informational text |
| `/admin` | `/admin` | Guarded configurable PHP admin redirect; no admin runtime |
| `/{locale}/category/{slug}` | `/[locale]/category/[slug]` | Implemented with original category translations and linked articles; visual parity pending |
| `/author/{slug}` | Not implemented | Pending |
| `/writers` | Not implemented | Pending |
| `/books`, `/book/{slug}` | Not implemented | Pending |
| `/podcast`, `/podcast/{slug}` | Not verified | Pending |
| `/khawatir` | Not verified | Pending |
| `/contact`, `/about`, `/privacy-policy` | Not verified | Pending |
| `/send-story`, `/story-status` | Not verified | Pending |
| `/ad/click/{id}` | Not implemented | Pending |

All missing routes require source-view matching and HTTP tests before acceptance.
