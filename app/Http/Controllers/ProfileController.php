<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'activity' => ActivityLog::where('user_id', $request->user()->id)->latest('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'currency' => ['required', Rule::in(array_keys(config('smartsave.currencies')))],
        ]);

        // Changing currency only relabels amounts; nothing is converted.
        $user->fill($data)->save();
        ActivityLog::record('profile.updated', $user, ['fields' => array_keys($user->getChanges())]);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $keys = array_keys(config('smartsave.notification_defaults'));
        $request->validate(['notifications' => ['array'], 'notifications.*' => ['boolean']]);

        $user = $request->user();
        $prefs = $user->preferences ?? [];
        $prefs['notifications'] = collect($keys)->mapWithKeys(fn ($k) => [$k => $request->boolean("notifications.$k")])->all();
        $user->preferences = $prefs;
        $user->save();

        return back()->with('success', 'Reminder preferences saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $this->changePassword($request);

        return back()->with('success', 'Password changed. Other sessions were signed out.');
    }

    /** Forced change for accounts created with a temporary password. */
    public function showForcedChange(): View
    {
        return view('auth.change-password');
    }

    public function forcedChange(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $this->changePassword($request);

        return redirect()->route('dashboard')->with('success', 'Password set. Welcome to SmartSave!');
    }

    private function changePassword(Request $request): void
    {
        $user = $request->user();
        $user->password = $request->input('password');
        $user->must_change_password = false;
        $user->save();

        // Invalidate any other device still signed in with the old password.
        Auth::logoutOtherDevices($request->input('password'));
        $request->session()->regenerate();
        ActivityLog::record('auth.password_changed', $user);
    }
}
