<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'user_id', 'status'])]
class EventRsvp extends Model
{
    use HasFactory;

    public const STATUSES = ['attending', 'cancelled'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(OrganizationEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
