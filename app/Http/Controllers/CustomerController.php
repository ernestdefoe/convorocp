<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    private function ensureOperator(Request $request): void
    {
        abort_unless($request->user()->isOperator(), 403);
    }

    public function index(Request $request)
    {
        $this->ensureOperator($request);

        $customers = User::where('role', 'client')
            ->with('plan')->withCount(['sites', 'databases'])
            ->orderBy('name')->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'initials' => self::initials($u->name),
                'plan' => $u->plan?->name ?? '—',
                'mrr' => $u->plan ? '$'.number_format($u->plan->price_cents / 100, 0) : '$0',
                'sites' => $u->sites_count,
                'databases' => $u->databases_count,
                'since' => $u->created_at?->format('M Y'),
            ]);

        return Inertia::render('Customers/Index', ['customers' => $customers]);
    }

    public function show(Request $request, User $user)
    {
        $this->ensureOperator($request);
        abort_unless($user->isClient(), 404);

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'initials' => self::initials($user->name),
                'plan' => $user->plan?->name ?? '—',
                'since' => $user->created_at?->format('M j, Y'),
            ],
            'sites' => $user->sites()->get(['id', 'domain', 'runtime', 'php_version', 'status']),
            'databases' => $user->databases()->get(['id', 'name', 'engine']),
        ]);
    }

    private static function initials(string $name): string
    {
        return collect(explode(' ', $name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    }
}
