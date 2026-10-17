<?php

namespace Database\Factories;

use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SavingsGoal> */
class SavingsGoalFactory extends Factory
{
    protected $model = SavingsGoal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Emergency fund', 'New laptop', 'Umrah trip', 'Car down payment', 'Wedding']),
            'category' => 'other',
            'icon' => 'piggy-bank',
            'color' => 'emerald',
            'target_amount' => '100000.00',
            'target_date' => now()->addYear()->toDateString(),
            'priority' => 'medium',
            'contribution_frequency' => 'monthly',
            'status' => SavingsGoal::ACTIVE,
        ];
    }

    public function status(string $status): static
    {
        return $this->state(['status' => $status]);
    }
}
