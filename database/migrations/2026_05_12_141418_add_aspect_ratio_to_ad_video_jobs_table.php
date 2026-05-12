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
        Schema::table('ad_video_jobs', function (Blueprint $table) {
            $table->string('aspect_ratio')->default('original')->after('target_duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_video_jobs', function (Blueprint $table) {
            $table->dropColumn('aspect_ratio');
        });
    }
};
