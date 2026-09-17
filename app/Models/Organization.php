<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id',
    'name',
    'slug',
    'type',
    'logo_path',
    'description',
    'email',
    'phone',
    'website',
    'address',
    'country',
    'state',
    'city',
    'postal_code',
    'latitude',
    'longitude',
    'social_links',
    'visibility',
    'status',
])]
class Organization extends Model
{
    use HasFactory;

    public const TYPES = ['church', 'company', 'ngo', 'ministry', 'community', 'other'];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot(['department_id', 'job_title', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }
}
