<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'campaign_id', 'organization_id', 'user_id', 'donor_name', 'donor_email',
    'amount', 'currency', 'reference', 'status',
])]
class Donation extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'successful', 'failed', 'cancelled'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GivingCampaign::class, 'campaign_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GivingTransaction::class);
    }

    public function donorDisplayName(): string
    {
        return $this->donor?->name ?? $this->donor_name ?? 'Anonymous';
    }

    public function isFulfilled(): bool
    {
        return $this->status === 'successful';
    }

    // A donation may only gain a new payment attempt while it hasn't already
    // succeeded — once successful, that outcome is terminal.
    public function acceptsNewAttempt(): bool
    {
        return in_array($this->status, ['pending', 'failed'], true);
    }
}
