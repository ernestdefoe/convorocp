<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class SignupController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Signup', [
            'plans' => Plan::where('is_public', true)->orderBy('position')->get()->map(fn (Plan $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->priceLabel(),
                'sites' => $p->sites_limit,
                'databases' => $p->db_limit,
                'disk' => round($p->disk_mb / 1024, 0).' GB',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        // Billing (Stripe) is wired in a later step — signup provisions the
        // account + subscription immediately for now.
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'role' => 'client',
            'plan_id' => $data['plan_id'],
            'subscribed_at' => now(),
        ]);

        Auth::login($user);

        return redirect('/');
    }
}
