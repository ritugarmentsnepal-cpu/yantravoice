<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPurchase extends Model
{
    protected $fillable = [
        'user_id', 'package_amount', 'bonus_amount', 'total_credits',
        'payment_screenshot', 'status', 'admin_note', 'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the available credit packages with bonuses.
     */
    public static function packages(): array
    {
        return [
            ['amount' => 100,   'bonus_pct' => 0,  'bonus' => 0,    'total' => 100],
            ['amount' => 500,   'bonus_pct' => 2,  'bonus' => 10,   'total' => 510],
            ['amount' => 1000,  'bonus_pct' => 4,  'bonus' => 40,   'total' => 1040],
            ['amount' => 5000,  'bonus_pct' => 6,  'bonus' => 300,  'total' => 5300],
            ['amount' => 10000, 'bonus_pct' => 10, 'bonus' => 1000, 'total' => 11000],
        ];
    }
}
