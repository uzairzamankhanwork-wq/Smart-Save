<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            // Users are deactivated, never hard-deleted, so financial history is kept.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('category', 30);
            $table->string('icon', 30)->default('piggy-bank');
            $table->string('color', 20)->default('emerald');
            $table->decimal('target_amount', 15, 2);
            $table->date('target_date')->nullable();
            $table->string('priority', 10)->default('medium');   // low | medium | high
            $table->string('status', 10)->default('active');     // active | paused | completed | archived
            $table->decimal('planned_contribution', 15, 2)->nullable();
            $table->string('contribution_frequency', 10)->default('monthly'); // weekly | biweekly | monthly
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('ALTER TABLE savings_goals ADD CONSTRAINT chk_goal_target_positive CHECK (target_amount > 0)');
            DB::statement('ALTER TABLE savings_goals ADD CONSTRAINT chk_goal_planned_nonneg CHECK (planned_contribution IS NULL OR planned_contribution >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goals');
    }
};
