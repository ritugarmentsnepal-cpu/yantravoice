<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ApiSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Credits per 1 NPR — now 1:1 */
    const CREDITS_PER_NPR = 1;

    /**
     * Get a setting value by key, with caching.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember("api_setting_{$key}", 300, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value by key, clearing cache.
     */
    public static function setValue(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("api_setting_{$key}");
    }

    /**
     * Get the admin's OpenRouter API key.
     */
    public static function getApiKey(): ?string
    {
        $key = static::getValue('openrouter_api_key');
        return $key && strlen($key) > 5 ? $key : null;
    }

    /**
     * Get the credit cost for voiceover generation.
     */
    public static function getCreditCost(): float
    {
        return (float) static::getValue('credit_cost_per_generation', 2);
    }

    /**
     * Convert credits to NPR (1:1 ratio).
     */
    public static function creditsToNpr(float $credits): float
    {
        return round($credits / self::CREDITS_PER_NPR, 2);
    }

    /**
     * Convert NPR to credits (1:1 ratio).
     */
    public static function nprToCredits(float $npr): float
    {
        return round($npr * self::CREDITS_PER_NPR, 2);
    }

    /**
     * Get the admin's base cost per generation in USD (actual OpenRouter cost).
     */
    public static function getAdminCostUsd(): float
    {
        return (float) static::getValue('admin_cost_per_generation_usd', 0.005);
    }

    /**
     * Get the credit cost for an Ad Video based on duration in seconds.
     */
    public static function getVideoRenderCost(): float
    {
        return (float) static::getValue('video_render_cost', 5);
    }

    /**
     * Get the payment QR image path.
     */
    public static function getPaymentQrPath(): ?string
    {
        $path = static::getValue('payment_qr_path');
        return $path ? $path : null;
    }

    /**
     * Get the app logo image path.
     */
    public static function getLogoPath(): ?string
    {
        $path = static::getValue('app_logo_path');
        return $path ? $path : null;
    }

    /**
     * Get the app logo URL (or null).
     */
    public static function getLogoUrl(): ?string
    {
        $path = static::getLogoPath();
        return $path ? asset('storage/' . $path) : null;
    }
}
