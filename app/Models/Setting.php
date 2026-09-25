<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A simple key/value settings store, cached forever (invalidated on every write) so reading a
 * setting on the hot path — e.g. EscrowService checking the return window — costs nothing beyond
 * the first request. Values are stored as plain strings; callers cast as needed (see get()'s
 * $default parameter for the expected shape).
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'setting.';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key, $default) {
            $value = self::query()->where('key', $key)->value('value');

            return $value ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * The single VAT rate (%) applied to every order platform-wide — admin-configurable from
     * Filament\Pages\Settings, no longer something an individual vendor can set (see
     * App\Models\Vendor::calculateVat(), the only caller that matters for real order math).
     */
    public static function vatRate(): float
    {
        return (float) self::get('default_vat_rate', 7.50);
    }
}
