# Bible translation licensing & API.Bible compliance

Phase 27.9 audit, verified against the live API.Bible service on **2026-10-16**.
Re-verify with `php artisan bible:api-bible-catalog --language=eng --check-linked`.

## Translation availability

| Translation | Code | Source | API.Bible Bible ID | Real passage retrieval verified | Access for this key | Commercial use | Required attribution | State shown |
|---|---|---|---|---|---|---|---|---|
| King James Version | KJV | Local (`bible_verses`, 31,102 verses) | — (catalog has `de4e12af7f28f599-01`; not used) | Yes (local) | n/a | Yes, public domain (UK: Crown letters patent restrict *printing*) | None required | Available locally |
| World English Bible | WEB | Remote, API.Bible | `9879dbb7cfe39e4d-01` | Yes: John 3:16–17, John 3, Psalm 23 | Yes | Text is public domain; **API.Bible use is non-commercial on the Starter plan** | "PUBLIC DOMAIN" (from API) | Available remotely |
| Berean Standard Bible | BSB | Remote, API.Bible | `bba9f40183526463-01` | Yes: John 3:16–17, John 3, Psalm 117 | Yes | Text dedicated to public domain; API.Bible plan terms still apply | Full notice returned by API (shown verbatim) | Available remotely |
| New Living Translation | NLT | Linked, **not served** | `d6e14a625393b4da-01` | Yes: John 3:16–17, John 3 | Retrievable, but **gated off** | **Not established.** Copyright Tyndale House Foundation | "Holy Bible, New Living Translation, copyright © 1996, 2004, 2015 by Tyndale House Foundation…" | Licence verification required |
| New International Version | NIV | None | Not in catalog | No | Not available to this key | n/a | Biblica, Inc. | Not available to this account |
| English Standard Version | ESV | None | Not in catalog | No | Not available to this key | n/a | Crossway | Not available to this account |
| New American Standard Bible | NASB | None | Not in catalog | No | Not available to this key | n/a | The Lockman Foundation | Not available to this account |
| Christian Standard Bible | CSB | None | Not in catalog | No | Not available to this key | n/a | Holman Bible Publishers | Not available to this account |
| Bible Segond 21 | FR | None | Not in catalog (only J.N. Darby `a93a92589195411f-01` for French) | No | Not available to this key | n/a | Société Biblique de Genève | Not available to this account |
| Bibeli Mimo Inu (Yoruba) | YOR | None | Edition unverified. The catalog's Yoruba Bible (`b8d1feac6e94bd74-01`) is the **Biblica Open Yoruba Contemporary Bible 2017, © Biblica**, a different, copyrighted edition | No | n/a | n/a | — | Not available |
| Lutherbibel | DE | None | Edition unverified. Catalog has Luther **1912** (`926aa5efbc5e04e2-01`, "Public Domain due to copyright expiration"), which is not proven to be this row's edition | No | n/a | n/a | — | Not available |

| American Standard Version (1901) | ASV | Linked, **held** (Phase 27.10) | `06125adad2d5898a-01` (only ASV in catalog; dblId `06125adad2d5898a`) | Yes: John 3 (36 verses), Genesis 1 (31 verses), FUMS token issued | Retrievable; held by `API_BIBLE_PENDING_VERIFICATION=ASV` | Text: public domain (1901 edition, eBible.org `eng-asv`). Account: see below | "PUBLIC DOMAIN" (from API) | Licence verification required |

Also in the catalog but **not linked**: AMP (`a81b73293d3080c9-01`, © The Lockman Foundation, retrieval verified). It would need the same licence gate as NLT.

### Why ASV is held
Its translation licence is fully established (public domain, 1901 edition, attribution and FUMS verified). API.Bible's Starter plan includes "all open-access and public domain Bibles". The outstanding requirement is **account-level**: confirm in the API.Bible dashboard **which plan this key is on**, and confirm that Lutapp's use is **non-commercial** (Starter) or the plan allows commercial use. Then set `API_BIBLE_PENDING_VERIFICATION=` (empty). No code or data change is needed.

**The same account-level confirmation applies to WEB and BSB**, which are already served. If the use turns out to be commercial on a Starter key, all three must stop (`API_BIBLE_PENDING_VERIFICATION=WEB,BSB,ASV`) until the plan is upgraded.

### Rules the code enforces
- `available` comes only from local verse rows; `remote` only from a verified `api_bible_id` + configured key + (public domain **or** `API_BIBLE_ALLOW_COPYRIGHTED=true`). Stored metadata can never claim `available`, `remote` or `temporarily_unavailable`.
- `temporarily_unavailable` (network/5xx/429) and the 401/403 "not available to this account" are **per-request only**. Nothing is written to `bible_translations`.
- Local text always takes precedence. A failed remote chapter shows an explanation, never KJV text under the remote translation's name.

### Plans and commercial use (api.bible and docs.api.bible, checked 2026-10-16)

- **Starter:** free, **5,000 API calls/month**, **non-commercial use only**, "all open-access and public domain Bibles" plus "up to 3 licensed Bibles".
- **Pro:** 150,000 calls/month. **Enterprise:** negotiated.
- **Overage protection** (Pro/Enterprise only) costs $1 per 1,000 extra calls. What happens at the limit *without* it is **not documented**.
- **Commercial use** = "any use … in product or service that is monetized" (ads, fees, sponsorships, freemium).
- The API exposes no plan information, so **the plan for this key is unverified**.

## API usage and quota (Phase 27.10)

`ApiBibleUsage` keeps per-calendar-month (UTC) counters in the application cache:

- `upstream_success`, `upstream_error`, `rate_limited`: these reached API.Bible
- `network_error`: may or may not have reached it
- `cache_hit` and `suppressed` (skipped during a 429 cooldown): **not** upstream calls

Run `php artisan bible:api-usage [--month=YYYY-MM]` to see them (makes no API call).

- **Alerts:** once per month, a `warning` log entry at `API_BIBLE_WARNING_THRESHOLD × API_BIBLE_MONTHLY_LIMIT` (default 80% of 5,000) and an `error` entry at the limit. Entries contain counts only: no key, no Scripture.
- **Not a quota guarantee.** Nothing blocks requests. The counters miss other apps or scripts sharing the key, use calendar months (not API.Bible's billing cycle), reset if the cache is flushed, and can under-count on non-atomic stores (`file`). **The API.Bible dashboard is the authoritative usage record.**
- **Call avoidance:**
  - chapters, searches and copyright notices are cached (≤ 14 days);
  - search runs only on form submit (3–100 characters), never per keystroke;
  - site-wide search stays local-only;
  - failures are never cached and **never retried automatically**.
- **429 handling:** `Retry-After` (seconds or HTTP date) starts a cooldown, clamped to 1 minute–1 hour, during which no calls are made and remote translations show "Provider temporarily unavailable".
- **Logging:** connection-error logs drop the request URL, which could contain a user's search text.

## FUMS (Fair Use Management System) v3

Source: docs.api.bible/guides/fair-use (checked 2026-10-16 and re-reviewed in Phase 27.10, unchanged) plus live responses. The docs still do not cover token reuse, cached content, script failure or missing tokens, so the behaviour below is preserved unchanged.

| Requirement | Implementation | Verified |
|---|---|---|
| Request with `fums-version=3` | Sent on chapter and search requests | Tests assert the parameter |
| Token from `meta.fumsToken` | The only token used. Never synthesised; omitted if absent | Live: 248-char token per response. Test: no token means no script |
| Load `https://pkg.api.bible/fumsV3.min.js`, call `fums("trackView", token)` when Scripture is shown | `resources/views/bible/partials/fums.blade.php` (reader, study, search) | Tests |
| Device / session IDs | Managed by API.Bible's script | Per docs |
| Failure isolation | `try/catch`, async script, bounded retry; reader unaffected if blocked | Test asserts guard code |

**Cache hits (open question).** Live testing shows API.Bible issues a **new token on every request**. A cached chapter re-reports the token from the original fetch (`data-fums-source="cache"`). The docs say to report "each fumsToken you receive" once the Scripture is shown, but **do not address cached content or repeated reports of one token**. The current behaviour reports every real display and never invents a token. Whether duplicate reports are counted, deduplicated or discouraged is **unconfirmed; ask support@api.bible**. If they require one report per token, change the partial to report only when `fumsFromCache` is false.

## Caching

| Rule (API.Bible FAQ) | Implementation |
|---|---|
| Cache fewer than 500 consecutive verses | One chapter (max 176 verses, Psalm 119) or ≤ 50 search hits per entry |
| Clear the cache every 14 days or less | Default TTL 24h (`API_BIBLE_CACHE_TTL`), hard-capped at 14 days. Every read also checks `fetched_at` against the **current** TTL, so lowering the TTL evicts older entries even if the store ignores expiry |
| Licence metadata preserved | Copyright, reference, FUMS token and fetch time are stored with the verses |
| Failures not cached | Errors and empty responses throw before `Cache::put` |
| Keys | `api_bible:v2:chapter:{bibleId}:{BOOK}.{chapter}`, `api_bible:v2:search:{bibleId}:{sha1(query)}:{offset}:{limit}`, `api_bible:v2:copyright:{bibleId}` |

No translation-specific caching terms beyond API.Bible's were found for WEB or BSB (both public domain). NLT is not served, so its publisher terms are not exercised. **Do not raise the TTL** without explicit licence support.

## Search
- Local translations: `LIKE` search over `bible_verses`, labelled "Searching the text stored in Lutapp".
- Remote translations: `GET /bibles/{id}/search` (authorised for this key, verified live). 25 results per page, server-side, cached, run on form submit only (≥ 3 characters, ≤ 100). Reference queries ("John 3:16") return passage links.
- Gated or unlinked translations: an explicit "cannot be searched here" notice with a link to the same search in KJV.
- Empty results always carry a caveat that a passage may exist under different wording.
- The **global** site search (`BibleSearchProvider`) remains local-only by design: it runs across all content types and would spend API quota on every global query.
