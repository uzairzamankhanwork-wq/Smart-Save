<?php

namespace App\Http\Requests;

use App\Models\SavingsGoal;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GoalRequest extends FormRequest
{
    /** Returns the policy response so a foreign goal is a 404, not a 403. */
    public function authorize(): Response|bool
    {
        $goal = $this->route('goal');

        return $goal ? Gate::inspect('update', $goal) : true;
    }

    public function rules(): array
    {
        $creating = $this->route('goal') === null;
        $original = $this->route('goal')?->target_date?->toDateString();

        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in(array_keys(config('smartsave.goal_categories')))],
            'icon' => ['required', Rule::in(config('smartsave.goal_icons'))],
            'color' => ['required', Rule::in(config('smartsave.goal_colors'))],
            'target_amount' => AmountRules::required(),
            // New deadlines must be in the future; an existing past deadline may be kept as-is.
            'target_date' => ['nullable', 'date_format:Y-m-d', 'before:2100-01-01',
                function ($attr, $value, $fail) use ($original) {
                    if ($value && $value !== $original && $value <= now()->toDateString()) {
                        $fail('The target date must be in the future.');
                    }
                }],
            'priority' => ['required', Rule::in(array_keys(SavingsGoal::PRIORITIES))],
            'planned_contribution' => AmountRules::optional(),
            'contribution_frequency' => ['required', Rule::in(array_keys(config('smartsave.frequencies')))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'starting_amount' => $creating ? AmountRules::optional() : ['prohibited'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    /** Only the model's own columns (excludes starting_amount / idempotency_key). */
    public function goalData(): array
    {
        return collect($this->validated())->except(['starting_amount', 'idempotency_key'])->all();
    }
}
