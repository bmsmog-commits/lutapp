<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function todoItems(): HasMany
    {
        return $this->hasMany(TodoItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function hymns(): HasMany
    {
        return $this->hasMany(Hymn::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function securityQuestions(): HasMany
    {
        return $this->hasMany(SecurityQuestion::class);
    }

    public function bibleBookmarks(): HasMany
    {
        return $this->hasMany(BibleBookmark::class);
    }

    public function bibleHighlights(): HasMany
    {
        return $this->hasMany(BibleHighlight::class);
    }

    public function bibleReadingHistory(): HasMany
    {
        return $this->hasMany(BibleReadingHistory::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
