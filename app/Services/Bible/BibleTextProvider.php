<?php

namespace App\Services\Bible;

use App\Models\BibleTranslation;

/**
 * Provider-agnostic seam for sourcing Bible verse text (Phase 27.5).
 *
 * NOTHING in this phase implements a remote provider. This interface exists so
 * that a future, properly licensed integration (API.Bible, a rights-cleared
 * publisher feed, or an offline public-domain dataset) can be plugged in behind
 * BibleImportService without touching the reader UI.
 *
 * DEFERRED, explicitly out of scope for Phase 27.5:
 *  - Any live HTTP call to a Bible text provider.
 *  - Any credential handling (no API keys are read, stored, or referenced).
 *  - Any import of copyrighted translations. BibleImportService still refuses
 *    to import into a translation whose redistributable flag is false, and that
 *    guard was not weakened.
 *
 * Phase 27.8 note: API.Bible is integrated as a DISPLAY-TIME source
 * (App\Services\Bible\ApiBible\ApiBibleClient), deliberately NOT as an
 * implementation of this import interface — API.Bible's terms forbid bulk
 * storage (cache < 500 consecutive verses, cleared within 14 days), so its
 * text must never flow into BibleImportService / bible_verses.
 *
 * Implementations MUST return only text they are licensed to redistribute, and
 * MUST NOT fabricate, paraphrase, or machine-generate verse text.
 */
interface BibleTextProvider
{
    /**
     * A stable identifier for this provider, e.g. 'local-file', 'api.bible'.
     */
    public function name(): string;

    /**
     * Whether this provider can legally supply text for the given translation.
     * Implementations should consult the translation's licensing metadata
     * (public_domain / redistributable / license) rather than assuming.
     */
    public function supports(BibleTranslation $translation): bool;

    /**
     * Yield rows shaped for BibleImportService::import().
     *
     * @return iterable<array{book: string, chapter: int, verse: int, text: string}>
     */
    public function fetch(BibleTranslation $translation): iterable;
}
