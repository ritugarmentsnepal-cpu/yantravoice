<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_video_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Workflow inputs
            $table->string('media_path')->nullable(); // Uploaded image/video path
            $table->text('user_highlights')->nullable(); // Text provided by user
            $table->integer('target_duration')->default(16); // 8, 16, 32, 45, 60
            $table->string('language')->default('English');
            $table->string('voice_model')->nullable();
            
            // AI Outputs (Script & Audio)
            $table->text('generated_script')->nullable(); // AI generated script
            $table->string('tts_audio_path')->nullable(); // Final generated audio
            
            // Output & Status
            $table->string('status')->default('pending'); // pending, generating_script, pending_approval, processing_video, completed, failed
            $table->string('output_video_path')->nullable();
            $table->text('error_message')->nullable();
            
            // Billing
            $table->decimal('credits_charged', 10, 2)->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_video_jobs');
    }
};
