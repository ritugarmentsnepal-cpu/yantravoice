<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('category', ['api_cost', 'server', 'domain', 'other']);
            $table->decimal('amount', 12, 4);
            $table->string('currency', 10)->default('USD');
            $table->string('description');
            $table->date('expense_date');
            $table->unsignedBigInteger('voiceover_log_id')->nullable(); // auto-linked for API costs
            $table->boolean('is_auto')->default(false); // true = auto-generated from TTS call
            $table->timestamps();

            $table->index('category');
            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_expenses');
    }
};
