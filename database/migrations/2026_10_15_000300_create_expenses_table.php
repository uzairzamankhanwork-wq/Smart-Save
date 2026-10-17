<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('category', 30);
            $table->date('expense_date');
            $table->string('note', 500)->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expense_date']);
            $table->unique(['user_id', 'idempotency_key']);
        });

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('ALTER TABLE expenses ADD CONSTRAINT chk_expense_amount_positive CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
