<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The savings ledger. Amounts are always positive; the sign comes from `type`:
 *   + initial, deposit, transfer_in
 *   - withdrawal, transfer_out
 * A transfer is two rows (transfer_out + transfer_in) sharing `transfer_group`,
 * so it nets to zero in the user's total savings.
 * Rows are never deleted: corrections are edits (audited) or voids (voided_at set).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Goals with transactions cannot be deleted (only archived), so restrict.
            $table->foreignId('goal_id')->nullable()->constrained('savings_goals')->restrictOnDelete();
            $table->string('type', 15);
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('note', 500)->nullable();
            $table->uuid('transfer_group')->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['goal_id', 'voided_at']);
            $table->index('transfer_group');
            // Blocks double-submits of the same form (see App\Support\Idempotency).
            $table->unique(['user_id', 'idempotency_key']);
        });

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('ALTER TABLE savings_transactions ADD CONSTRAINT chk_tx_amount_positive CHECK (amount > 0)');
            DB::statement("ALTER TABLE savings_transactions ADD CONSTRAINT chk_tx_type CHECK (type IN ('initial','deposit','withdrawal','transfer_in','transfer_out'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_transactions');
    }
};
