<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'language', 'theme', 'date_format', 'timezone', 'notifications_enabled', 'notification_preferences',
    'privacy_preferences', 'bible_preferences', 'audio_preferences', 'dashboard_preferences',
])]
class UserPreference extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'notification_preferences' => 'array',
            'privacy_preferences' => 'array',
            'bible_preferences' => 'array',
            'audio_preferences' => 'array',
            'dashboard_preferences' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
