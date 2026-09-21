<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'donation_id', 'campaign_id', 'organization_id', 'provider', 'reference',
    'provider_reference', 'provider_transaction_id', 'amount', 'currency',
    'status', 'payment_method', 'provider_metadata', 'verified_at', 'completed_at',
])]
class GivingTransaction extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'successful', 'failed', 'cancelled', 'reversed', 'refunded'];

    protected function casts(): array
    {
        return [
            'provider_metadata' => 'array',
            'verified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(GivingCampaign::class, 'campaign_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
