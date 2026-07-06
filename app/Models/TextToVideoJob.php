<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TextToVideoJob extends Model
{
    use HasFactory;

    // ── Valid options (mirrors Veo 3.1 capabilities) ─────────

    const MODEL_VARIANTS = ['veo-3.1', 'veo-3.1-fast', 'veo-3.1-lite'];
    const ASPECT_RATIOS  = ['16:9', '9:16'];
    const RESOLUTIONS    = ['720p', '1080p'];
    const DURATIONS      = [4, 6, 8];
    const MODES          = ['text_to_video', 'image_to_video'];

    const STATUSES = ['pending', 'generating', 'polling', 'completed', 'failed'];

    // ── Mass assignment ─────────────────────────────────────

    protected $fillable = [
        'user_id',
        'prompt',
        'negative_prompt',
        'model_variant',
        'aspect_ratio',
        'resolution',
        'duration',
        'generation_mode',
        'first_frame_path',
        'last_frame_path',
        'openrouter_job_id',
        'status',
        'video_url',
        'output_path',
        'credits_charged',
        'error_message',
        'metadata',
    ];

    // ── Casts ───────────────────────────────────────────────

    protected $casts = [
        'metadata'        => 'array',
        'duration'        => 'integer',
        'credits_charged' => 'decimal:4',
    ];

    // ── Relationships ───────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ─────────────────────────────────────────────

    /**
     * Map model_variant to the full OpenRouter model ID.
     */
    public function getOpenRouterModelId(): string
    {
        return match ($this->model_variant) {
            'veo-3.1-fast' => 'google/veo-3.1-fast',
            'veo-3.1-lite' => 'google/veo-3.1-lite',
            default        => 'google/veo-3.1',
        };
    }

    /**
     * Check if this is an image-to-video job.
     */
    public function isImageToVideo(): bool
    {
        return $this->generation_mode === 'image_to_video';
    }
}
