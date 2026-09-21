<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

#[Fillable(['type', 'direct_key'])]
class Conversation extends Model
{
    use HasFactory;

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('participants', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    public function participantFor(User $user): ?ConversationParticipant
    {
        return $this->participants->firstWhere('user_id', $user->id);
    }

    public function otherParticipant(User $user): ?ConversationParticipant
    {
        return $this->participants->firstWhere('user_id', '!=', $user->id);
    }

    /**
     * Race-safe find-or-create for a direct conversation between two users,
     * relying on the unique `direct_key` index rather than a plain
     * SELECT-then-INSERT check (which two simultaneous requests could both
     * pass before either commits).
     */
    public static function findOrCreateDirect(User $a, User $b): self
    {
        $key = self::directKeyFor($a->id, $b->id);

        $existing = self::where('direct_key', $key)->first();

        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($key, $a, $b) {
                $conversation = self::create(['type' => 'direct', 'direct_key' => $key]);
                $conversation->participants()->createMany([
                    ['user_id' => $a->id],
                    ['user_id' => $b->id],
                ]);

                return $conversation;
            });
        } catch (QueryException $e) {
            // Lost the race to a concurrent request — the unique index rejected
            // our insert, so the row that won is the one to return.
            return self::where('direct_key', $key)->firstOrFail();
        }
    }

    public static function directKeyFor(int $userIdA, int $userIdB): string
    {
        $ids = [$userIdA, $userIdB];
        sort($ids);

        return implode('-', $ids);
    }
}
