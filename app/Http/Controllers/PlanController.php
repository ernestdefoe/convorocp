<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PlanController extends Controller
{
    private function ensureOperator(Request $request): void
    {
        abort_unless($request->user()->isOperator(), 403);
    }

    public function index(Request $request)
    {
        $this->ensureOperator($request);
        $counts = User::selectRaw('plan_id, count(*) as c')->groupBy('plan_id')->pluck('c', 'plan_id');

        return Inertia::render('Plans/Index', [
            'plans' => Plan::orderBy('position')->get()->map(fn (Plan $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price_cents' => $p->price_cents,
                'sites_limit' => $p->sites_limit,
                'db_limit' => $p->db_limit,
                'disk_mb' => $p->disk_mb,
                'is_public' => $p->is_public,
                'subscribers' => (int) ($counts[$p->id] ?? 0),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureOperator($request);
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);
        Plan::create($data);

        return redirect('/plans');
    }

    public function update(Request $request, Plan $plan)
    {
        $this->ensureOperator($request);
        $plan->update($this->validated($request));

        return redirect('/plans');
    }

    public function destroy(Request $request, Plan $plan)
    {
        $this->ensureOperator($request);
        $plan->delete();

        return redirect('/plans');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:9999999'],
            'sites_limit' => ['required', 'integer', 'min:1', 'max:10000'],
            'db_limit' => ['required', 'integer', 'min:0', 'max:10000'],
            'email_limit' => ['required', 'integer', 'min:0', 'max:10000'],
            'disk_mb' => ['required', 'integer', 'min:128', 'max:10485760'],
            'is_public' => ['boolean'],
        ]);
    }
}
