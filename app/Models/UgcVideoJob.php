<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UgcVideoJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'avatar_id',
        'prompt',
        'style_preset',
        'video_blueprint',
        'status',
        'heygen_video_id',
        'avatar_video_path',
        'output_video_path',
        'credits_charged',
        'error_message'
    ];

    protected $casts = [
        'video_blueprint' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function avatar()
    {
        return $this->belongsTo(Avatar::class);
    }
}
