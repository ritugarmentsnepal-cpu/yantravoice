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
            $table->foreignId('avatar_id')->nullable()->after('user_id')->constrained('avatars')->nullOnDelete();
            $table->string('style_preset')->nullable()->after('prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ugc_video_jobs', function (Blueprint $table) {
            $table->dropForeign(['avatar_id']);
            $table->dropColumn(['avatar_id', 'style_preset']);
        });
    }
};
