<?php

namespace App\Http\Requests;

use App\Models\SavingsTransaction;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Used for creating and editing single (non-transfer) ledger entries. */
class TransactionRequest extends FormRequest
{
    /** Returns the policy response so a foreign entry is a 404, not a 403. */
    public function authorize(): Response|bool
    {
        $tx = $this->route('transaction');

        return $tx ? Gate::inspect('update', $tx) : true;
    }

    public function rules(): array
    {
        $editing = $this->route('transaction') !== null;
        $isTransfer = $editing && $this->route('transaction')->isTransfer();

        return [
            // Type is fixed after creation; switching a deposit into a withdrawal is a void + new entry.
            'type' => $editing ? ['prohibited'] : ['required', Rule::in([SavingsTransaction::INITIAL, SavingsTransaction::DEPOSIT, SavingsTransaction::WITHDRAWAL])],
            // Goal ownership is enforced again inside SavingsLedger.
            'goal_id' => $isTransfer ? ['prohibited'] : ['nullable', 'integer', Rule::exists('savings_goals', 'id')->where('user_id', $this->user()->id)],
            'amount' => $isTransfer ? ['prohibited'] : AmountRules::required(),
            'transaction_date' => AmountRules::pastDate(),
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => $editing ? ['prohibited'] : ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'goal_id.exists' => 'The selected goal is invalid.',
            'transaction_date.before_or_equal' => 'Savings entries cannot be dated in the future.',
        ];
    }
}
