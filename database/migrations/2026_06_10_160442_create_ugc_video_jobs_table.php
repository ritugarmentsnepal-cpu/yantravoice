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
        Schema::create('ugc_video_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('prompt');
            $table->json('video_blueprint')->nullable();
            $table->string('status')->default('generating'); // generating, completed, failed
            $table->string('output_video_path')->nullable();
            $table->integer('credits_charged')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ugc_video_jobs');
    }
};
