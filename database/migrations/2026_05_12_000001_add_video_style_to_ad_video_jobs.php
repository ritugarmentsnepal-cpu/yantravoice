<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_video_jobs', function (Blueprint $table) {
            $table->string('video_style')->default('cinematic')->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('ad_video_jobs', function (Blueprint $table) {
            $table->dropColumn('video_style');
        });
    }
};
