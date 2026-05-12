<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdVideoJob extends Model
{
    protected $fillable = [
        'user_id',
        'media_path',
        'user_highlights',
        'target_duration',
        'language',
        'video_style',
        'aspect_ratio',
        'voice_model',
        'generated_script',
        'tts_audio_path',
        'status',
        'output_video_path',
        'error_message',
        'credits_charged',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Check if the job is ready for user approval (script generated).
     */
    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }
}
