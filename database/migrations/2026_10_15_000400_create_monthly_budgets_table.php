<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's monthly plan. A row applies to its month and every later month
 * until a newer row exists, so past months keep the targets they had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('month'); // always the 1st of the month
            $table->decimal('savings_target', 15, 2)->nullable();
            $table->decimal('expense_budget', 15, 2)->nullable();
            $table->string('savings_frequency', 10)->default('monthly');
            $table->timestamps();

            $table->unique(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_budgets');
    }
};
