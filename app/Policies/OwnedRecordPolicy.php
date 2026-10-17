<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Financial records belong to exactly one user. There is deliberately no
 * admin bypass: being an administrator grants no access to anyone else's money.
 * Denials are reported as 404 so record ids cannot be probed.
 */
abstract class OwnedRecordPolicy
{
    protected function owns(User $user, Model $record): Response
    {
        return (int) $record->user_id === (int) $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, Model $record): Response
    {
        return $this->owns($user, $record);
    }

    public function update(User $user, Model $record): Response
    {
        return $this->owns($user, $record);
    }

    public function delete(User $user, Model $record): Response
    {
        return $this->owns($user, $record);
    }
}
