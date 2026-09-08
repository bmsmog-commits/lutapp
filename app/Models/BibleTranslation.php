<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'language', 'description', 'license', 'is_active'])]
class BibleTranslation extends Model
{
    use HasFactory;

    public function verses(): HasMany
    {
        return $this->hasMany(BibleVerse::class, 'translation_id');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(BibleBookmark::class, 'translation_id');
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(BibleHighlight::class, 'translation_id');
    }

    public function readingHistory(): HasMany
    {
        return $this->hasMany(BibleReadingHistory::class, 'translation_id');
    }
}
