<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'language', 'language_id', 'description', 'license',
    'license_type', 'copyright_holder', 'availability_status',
    'public_domain', 'redistributable', 'license_url', 'source_name', 'source_url',
    'attribution', 'is_active', 'api_bible_id',
])]
class BibleTranslation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'public_domain' => 'boolean',
            'redistributable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // A translation may only be safely imported/displayed once this is true —
    // see Phase 4 licensing audit. "license" being non-null is not sufficient proof.
    public function isLegallyRedistributable(): bool
    {
        return $this->redistributable === true;
    }

    // Named to avoid colliding with the legacy free-text "language" column —
    // accessing $translation->language must keep returning that string, not this relation.
    public function languageRecord(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'language_id');
    }

    /**
     * Phase 27.5 — honest, computed availability.
     *
     * A translation is "available" only when real verse text for it already
     * exists in this installation's database. Nothing here infers availability
     * from licensing flags, and no text is ever synthesised for a translation
     * that has not been legitimately imported.
     */
    public function hasLocalText(): bool
    {
        return $this->verses()->exists();
    }

    /**
     * Phase 27.6 — the richer catalog states. `available` is still never
     * asserted from metadata: only real verse rows can produce it. The stored
     * `availability_status` only ever explains *why* something is unavailable.
     */
    public const AVAILABILITY_STATES = [
        'available',            // real verse rows exist locally
        'remote',               // licensed + a configured remote provider could serve it
        'license_required',     // copyrighted; needs a signed licence first
        'coming_soon',          // cleared, import scheduled, not yet loaded
        'not_available',        // no route to this text at all
        // Phase 27.9
        'not_in_account',       // verified absent from this API.Bible key's catalog
        'temporarily_unavailable', // runtime only (network/429) — never stored
        // legacy Phase 27.5 states, still produced when no metadata is stored
        'not_imported',
        'licensed_unavailable',
    ];

    /**
     * @return 'available'|'remote'|'license_required'|'coming_soon'|'not_available'|'not_in_account'|'not_imported'|'licensed_unavailable'
     */
    public function availabilityState(?bool $hasText = null): string
    {
        $hasText ??= $this->hasLocalText();

        if ($hasText) {
            return 'available';
        }

        // Phase 27.8 — a verified API.Bible ID plus configured credentials.
        // This is still not 'available': the reader only shows text once a
        // real chapter request to API.Bible has actually succeeded.
        if ($this->canBeServedRemotely()) {
            return 'remote';
        }

        if ($this->isPendingVerification()) {
            return 'license_required';
        }

        $declared = $this->availability_status;

        // 'available' can never be claimed by metadata alone, and neither can
        // 'remote' (needs a real API.Bible link + licence gate) or
        // 'temporarily_unavailable' (a runtime condition, never persisted).
        if (is_string($declared)
            && ! in_array($declared, ['available', 'remote', 'temporarily_unavailable'], true)
            && in_array($declared, self::AVAILABILITY_STATES, true)) {
            return $declared;
        }

        return $this->isLegallyRedistributable() ? 'not_imported' : 'licensed_unavailable';
    }

    /**
     * Phase 27.8 — whether this translation may be requested from API.Bible.
     *
     * Requires a verified API.Bible Bible ID and configured credentials.
     * Copyrighted texts additionally require API_BIBLE_ALLOW_COPYRIGHTED,
     * because API.Bible's Starter plan licenses non-commercial use only.
     */
    public function canBeServedRemotely(): bool
    {
        return filled($this->api_bible_id)
            && filled(config('services.api_bible.key'))
            && filled(config('services.api_bible.base_url'))
            && ! $this->isPendingVerification()
            && ($this->public_domain === true || config('services.api_bible.allow_copyrighted') === true);
    }

    /**
     * Phase 27.10 — linked to API.Bible, but an operator has not yet cleared
     * it for this deployment (API_BIBLE_PENDING_VERIFICATION).
     */
    public function isPendingVerification(): bool
    {
        return filled($this->api_bible_id)
            && in_array(strtoupper((string) $this->code), (array) config('services.api_bible.pending_verification', []), true);
    }

    /** Whether this translation's text can legitimately be read right now. */
    public function isReadable(?bool $hasText = null): bool
    {
        return $this->availabilityState($hasText) === 'available';
    }

    public function verses(): HasMany
    {
        return $this->hasMany(BibleVerse::class, 'translation_id');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(BibleBookmark::class, 'translation_id');
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(BibleHighlight::class, 'translation_id');
    }

    public function readingHistory(): HasMany
    {
        return $this->hasMany(BibleReadingHistory::class, 'translation_id');
    }
}
