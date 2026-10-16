<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'body', 'color', 'passcode_hash', 'is_pinned',
    'bible_reference', 'bible_book_id', 'bible_chapter', 'bible_translation_id',
])]
class Note extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recoveryAttempts(): HasMany
    {
        return $this->hasMany(NotePasscodeRecoveryAttempt::class);
    }

    // Phase 27.6 — the optional Bible reference a note was written against,
    // set from the Bible Study split-screen workspace. Nullable everywhere;
    // a note created anywhere else simply has none.
    public function bibleBook(): BelongsTo
    {
        return $this->belongsTo(BibleBook::class, 'bible_book_id');
    }

    public function bibleTranslation(): BelongsTo
    {
        return $this->belongsTo(BibleTranslation::class, 'bible_translation_id');
    }

    public function hasBibleReference(): bool
    {
        return filled($this->bible_reference);
    }

    public function isLocked(): bool
    {
        return filled($this->passcode_hash);
    }
}
