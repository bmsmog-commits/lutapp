<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sort_order', 'name', 'abbreviation', 'testament', 'chapters_count'])]
class BibleBook extends Model
{
    use HasFactory;

    public function verses(): HasMany
    {
        return $this->hasMany(BibleVerse::class);
    }
}
