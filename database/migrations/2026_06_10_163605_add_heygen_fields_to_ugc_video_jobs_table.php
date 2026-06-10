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
        Schema::table('ugc_video_jobs', function (Blueprint $table) {
            $table->string('heygen_video_id')->nullable()->after('status');
            $table->string('avatar_video_path')->nullable()->after('heygen_video_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ugc_video_jobs', function (Blueprint $table) {
            $table->dropColumn(['heygen_video_id', 'avatar_video_path']);
        });
    }
};
