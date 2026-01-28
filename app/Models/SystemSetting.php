<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            if (!$setting) {
                return $default;
            }

            return match ($setting->type) {
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $setting->value,
                'json' => json_decode($setting->value, true),
                default => $setting->value,
            };
        });
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, $value, string $type = 'string', string $group = 'general', ?string $description = null): self
    {
        $setting = self::firstOrNew(['key' => $key]);
        $setting->value = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;
        $setting->type = $type;
        $setting->group = $group;
        if ($description) {
            $setting->description = $description;
        }
        $setting->save();

        Cache::forget("setting.{$key}");

        return $setting;
    }

    /**
     * Clear all settings cache.
     */
    public static function clearCache(): void
    {
        Cache::flush();
    }
}
