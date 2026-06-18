<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/**
 * Self-service account settings for the signed-in user (operator or client):
 * change display name + email, and change password. 2FA lives under Security.
 */
class AccountController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Account/Index', [
            'twoFactorEnabled' => $request->user()->hasTwoFactorEnabled(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($request->user()->id)],
        ]);

        $request->user()->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // 'password' is cast as hashed on the User model.
        $request->user()->update(['password' => $request->string('password')->value()]);

        return back()->with('status', 'Password changed.');
    }
}
