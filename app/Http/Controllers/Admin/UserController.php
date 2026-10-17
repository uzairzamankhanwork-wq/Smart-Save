<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Account management only. Admins see names, emails, roles and status,
 * never balances, goals, transactions or expenses of other users.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', [
            'users' => User::orderBy('id')->get(['id', 'name', 'email', 'role', 'is_active', 'must_change_password', 'last_login_at', 'created_at']),
            'maxUsers' => config('smartsave.max_users'),
            'log' => ActivityLog::with('user:id,name')
                ->where(fn ($q) => $q->where('action', 'like', 'admin.%')->orWhere('action', 'like', 'auth.%'))
                ->latest('id')->limit(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([User::ROLE_USER, User::ROLE_ADMIN])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($data) {
            // Lock the table's rows so two simultaneous creates can't exceed the cap.
            $count = User::lockForUpdate()->count();
            if ($count >= config('smartsave.max_users')) {
                return null;
            }
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
            ]);
            $user->role = $data['role'];
            $user->must_change_password = true; // temporary password
            $user->save();

            return $user;
        });

        if (! $user) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'SmartSave allows at most '.config('smartsave.max_users').' accounts.');
        }

        ActivityLog::record('admin.user_created', $user, ['email' => $user->email, 'role' => $user->role]);

        return back()->with('success', "Account created for {$user->name}. They'll choose a new password at first sign-in.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_USER, User::ROLE_ADMIN])],
            'is_active' => ['required', 'boolean'],
        ]);

        $isSelf = $user->is($request->user());
        if ($isSelf && ($data['role'] !== User::ROLE_ADMIN || ! $data['is_active'])) {
            return back()->with('error', "You can't remove your own admin access or deactivate yourself.");
        }

        $user->role = $data['role'];
        $user->is_active = (bool) $data['is_active'];
        $user->save();

        ActivityLog::record('admin.user_updated', $user, ['role' => $user->role, 'is_active' => $user->is_active]);

        return back()->with('success', "{$user->name}'s account was updated.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);

        $user->password = $data['password'];
        $user->must_change_password = true;
        $user->setRememberToken(null);
        $user->save();

        // Sign the account out everywhere.
        DB::table('sessions')->where('user_id', $user->id)->delete();
        ActivityLog::record('admin.password_reset', $user, []);

        return back()->with('success', "Temporary password set for {$user->name}.");
    }
}
