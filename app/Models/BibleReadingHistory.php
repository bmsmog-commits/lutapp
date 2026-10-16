<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'bible_book_id', 'chapter', 'translation_id', 'last_read_at', 'expires_at'])]
class BibleReadingHistory extends Model
{
    use HasFactory;

    /**
     * Phase 27.6 — reading history is a 24-hour convenience trail, not an
     * archive. Bookmarks (BibleBookmark) and highlights (BibleHighlight) are
     * separate tables with no expiry at all and are never touched by this.
     */
    public const RETENTION_HOURS = 24;

    protected $casts = [
        'last_read_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * The canonical write path — keeps expires_at exactly RETENTION_HOURS after
     * the moment of reading, so expiry is never recomputed inconsistently.
     */
    public static function record(int $userId, int $bookId, int $chapter, int $translationId): self
    {
        $readAt = now();

        return static::updateOrCreate(
            [
                'user_id' => $userId,
                'bible_book_id' => $bookId,
                'chapter' => $chapter,
                'translation_id' => $translationId,
            ],
            [
                'last_read_at' => $readAt,
                'expires_at' => $readAt->copy()->addHours(self::RETENTION_HOURS),
            ]
        );
    }

    /** Rows still inside the 24-hour window (a null expiry is treated as live). */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo(now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(BibleBook::class, 'bible_book_id');
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(BibleTranslation::class, 'translation_id');
    }
}
