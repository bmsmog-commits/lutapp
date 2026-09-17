<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'language', 'language_id', 'description', 'license',
    'public_domain', 'redistributable', 'license_url', 'source_name', 'source_url',
    'attribution', 'is_active',
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
