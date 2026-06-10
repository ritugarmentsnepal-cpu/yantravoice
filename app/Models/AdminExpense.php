<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminExpense extends Model
{
    protected $fillable = [
        'admin_id', 'category', 'amount', 'currency',
        'description', 'expense_date', 'voiceover_log_id', 'is_auto',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:4',
            'expense_date' => 'date',
            'is_auto'      => 'boolean',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
