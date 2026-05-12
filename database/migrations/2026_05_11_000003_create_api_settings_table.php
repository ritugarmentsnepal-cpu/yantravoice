<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();
        });

        // Seed default settings
        DB::table('api_settings')->insert([
            ['key' => 'openrouter_api_key', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'cost_per_generation', 'value' => '0.005', 'created_at' => now(), 'updated_at' => now()], // $0.005 default
            ['key' => 'markup_multiplier', 'value' => '10', 'created_at' => now(), 'updated_at' => now()],     // 10x markup
            ['key' => 'signup_bonus_credits', 'value' => '5', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('api_settings');
    }
};
