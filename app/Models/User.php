<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'credits', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'credits'           => 'decimal:4',
            'is_active'         => 'boolean',
        ];
    }

    // ── Helpers ──────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasCredits(float $amount = 0.0001): bool
    {
        return $this->credits >= $amount;
    }

    public function deductCredits(float $amount): void
    {
        $this->decrement('credits', $amount);
    }

    public function addCredits(float $amount): void
    {
        $this->increment('credits', $amount);
    }

    // ── Relationships ───────────────────────────────────────

    public function voiceoverLogs()
    {
        return $this->hasMany(VoiceoverLog::class);
    }

    public function creditTransactions()
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
