<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 4); // +/- credits (decimal for fractional costs)
            $table->enum('type', ['admin_grant', 'generation_debit', 'refund', 'bonus', 'signup_bonus']);
            $table->string('description');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('voiceover_log_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
