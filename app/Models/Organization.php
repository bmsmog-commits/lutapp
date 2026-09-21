<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
    'logo_media_id',
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

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'logo_media_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrganizationEvent::class);
    }

    public function audioResources(): HasMany
    {
        return $this->hasMany(AudioResource::class);
    }

    public function audioCollections(): HasMany
    {
        return $this->hasMany(AudioCollection::class);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    // The Phase 14 directory listing/profile surface — visibility here is the
    // *only* gate (Phase 10's existing visibility system), not a second one.
    // Private organizations never match this scope, full stop, regardless of
    // who is asking; per-viewer exceptions (a member viewing their own private
    // org) stay in OrganizationPolicy::view for the single-record route, not here.
    public function scopeInPublicDirectory(Builder $query): Builder
    {
        return $query->where('visibility', 'public')->where('status', 'active');
    }

    // A cheap, index-friendly bounding-box pre-filter — not the final radius
    // check. SQL-level trig (acos/radians) isn't portable (the SQLite
    // connection the test suite runs on has no math functions at all), so the
    // exact Haversine distance is computed in PHP afterward via
    // distanceInKmFrom(); this just keeps that PHP-side pass from having to
    // scan every organization in the table first.
    public function scopeNearby(Builder $query, float $latitude, float $longitude, float $radiusKm): Builder
    {
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * max(cos(deg2rad($latitude)), 0.01));

        return $query->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta]);
    }

    public function distanceInKmFrom(float $latitude, float $longitude): ?float
    {
        if (! $this->hasLocation()) {
            return null;
        }

        $earthRadiusKm = 6371;
        $latDelta = deg2rad($latitude - (float) $this->latitude);
        $lngDelta = deg2rad($longitude - (float) $this->longitude);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad((float) $this->latitude)) * cos(deg2rad($latitude)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
