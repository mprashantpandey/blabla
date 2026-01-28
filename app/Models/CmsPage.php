<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CmsPage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'content',
        'is_active',
        'show_in_footer',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_footer' => 'boolean',
    ];

    /**
     * Generate slug from title.
     */
    public static function generateSlug(string $title): string
    {
        $slug = Str::slug($title);
        $count = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = Str::slug($title) . '-' . $count;
            $count++;
        }
        return $slug;
    }

    /**
     * Scope to get active pages.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get footer pages.
     */
    public function scopeFooter($query)
    {
        return $query->where('show_in_footer', true)->where('is_active', true);
    }
}
