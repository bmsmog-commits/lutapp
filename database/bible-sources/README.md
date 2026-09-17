# Bible Source Datasets

This directory intentionally contains **no bulk Bible text**. Per Phase 5 policy,
large third-party content is never bundled into the repository — it is fetched
on demand by a dedicated import command and validated before it touches the
database.

## KJV (imported)

- **Source**: [aruljohn/Bible-kjv](https://github.com/aruljohn/Bible-kjv) — 66 JSON
  files, one per book, structured as `{book, chapters: [{chapter, verses: [{verse, text}]}]}`.
- **Repository license**: MIT (covers the JSON structuring/compilation).
- **Underlying text**: the King James Version itself is independently public
  domain worldwide. The one documented exception is a Crown printing patent
  restricting *commercial printing* of the KJV within the United Kingdom
  specifically — this does not affect Lutapp's use.
- **Verified structural facts** (Phase 5 audit, checked against the downloaded
  files before import): 66 books, 1,189 chapters, 31,102 verses — these are the
  well-known canonical KJV totals, and every book's chapter count matched
  Lutapp's existing `bible_books.chapters_count` exactly.
- **How to reproduce**: `php artisan bible:import-kjv --dry-run` (validate only)
  then `php artisan bible:import-kjv` (import). The command fetches directly
  from the source above; nothing is stored in this repository.

## Other translations (NOT imported)

NIV, ESV, NASB, French (Segond 21), and German (Lutherbibel) are proprietary or
have unverified licensing — see `BibleTranslationSeeder` for the documented
status of each. Yoruba (YOR) licensing could not be verified from any source
available during this phase and remains `redistributable = false`. Do not
import text for any of these translations until licensing is independently
confirmed and documented here.
