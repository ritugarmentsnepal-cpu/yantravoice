<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceoverLog extends Model
{
    protected $fillable = [
        'user_id', 'language', 'voice_model', 'emotion',
        'input_text', 'formatted_prompt', 'file_path',
        'status', 'credits_charged', 'api_cost', 'char_count',
    ];

    protected function casts(): array
    {
        return [
            'credits_charged' => 'decimal:4',
            'api_cost'        => 'decimal:6',
            'char_count'      => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creditTransaction()
    {
        return $this->hasOne(CreditTransaction::class, 'voiceover_log_id');
    }
}
