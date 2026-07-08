<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the potentially broken table from production
        Schema::dropIfExists('text_to_video_jobs');

        // Recreate it with the exact correct schema
        Schema::create('text_to_video_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Prompt & generation config
            $table->text('prompt');
            $table->text('negative_prompt')->nullable();
            $table->string('model_variant', 30)->default('veo-3.1');         // veo-3.1, veo-3.1-fast, veo-3.1-lite
            $table->string('aspect_ratio', 10)->default('16:9');              // 16:9, 9:16
            $table->string('resolution', 10)->default('720p');                // 720p, 1080p
            $table->unsignedTinyInteger('duration')->default(8);              // 4, 6, 8 seconds
            $table->string('generation_mode', 20)->default('text_to_video');  // text_to_video, image_to_video

            // Image-to-Video frame inputs
            $table->string('first_frame_path')->nullable();
            $table->string('last_frame_path')->nullable();

            // OpenRouter async job tracking
            $table->string('openrouter_job_id')->nullable();
            $table->string('status', 30)->default('pending'); // pending, generating, polling, completed, failed

            // Output
            $table->text('video_url')->nullable();
            $table->string('output_path')->nullable();

            // Billing
            $table->decimal('credits_charged', 10, 4)->default(0);

            // Error handling
            $table->text('error_message')->nullable();

            // Extra metadata from OpenRouter response
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('openrouter_job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('text_to_video_jobs');
    }
};
