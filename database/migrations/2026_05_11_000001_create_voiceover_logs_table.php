<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voiceover_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('language');
            $table->string('voice_model');
            $table->string('emotion');
            $table->text('input_text');
            $table->text('formatted_prompt');
            $table->string('file_path');
            $table->string('status'); // 'success' or 'failed'
            $table->decimal('credits_charged', 12, 4)->default(0);
            $table->decimal('api_cost', 12, 6)->default(0); // actual OpenRouter cost
            $table->integer('char_count')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voiceover_logs');
    }
};
