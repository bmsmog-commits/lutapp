<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'content', 'category', 'published_date', 'is_active'])]
class DailyPrayer extends Model
{
    use HasFactory;

    protected $casts = [
        'published_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('published_date', $date);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
