<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'organization_id', 'disk', 'path', 'original_name',
    'mime_type', 'extension', 'size', 'visibility', 'category', 'checksum',
])]
class MediaFile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function isPersonal(): bool
    {
        return $this->user_id !== null;
    }

    public function isOrganizationOwned(): bool
    {
        return $this->organization_id !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
