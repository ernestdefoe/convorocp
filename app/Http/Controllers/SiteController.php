<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiteController extends Controller
{
    /** Operators see every site; clients see only their own. */
    private function scoped(Request $request)
    {
        return $request->user()->isOperator()
            ? Site::query()
            : Site::where('user_id', $request->user()->id);
    }

    private function authorizeSite(Request $request, Site $site): void
    {
        abort_unless($request->user()->isOperator() || $site->user_id === $request->user()->id, 403);
    }

    public function index(Request $request)
    {
        $sites = $this->scoped($request)->with('owner')->latest()->get()->map(fn (Site $s) => [
            'id' => $s->id,
            'domain' => $s->domain,
            'runtime' => $s->runtime,
            'php_version' => $s->php_version,
            'status' => $s->status,
            'ssl_status' => $s->ssl_status,
            'owner' => $s->owner?->name,
        ]);

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
            'phpVersions' => \App\Models\PhpRuntime::installed(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:191', 'unique:sites,domain', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'runtime' => ['required', 'in:php,node,static'],
            'php_version' => ['nullable', 'in:'.implode(',', \App\Models\PhpRuntime::installed())],
        ]);

        $user = $request->user();
        if ($user->isClient() && $user->plan) {
            $count = Site::where('user_id', $user->id)->count();
            if ($count >= $user->plan->sites_limit) {
                return back()->withErrors(['domain' => "Your {$user->plan->name} plan allows {$user->plan->sites_limit} site(s). Upgrade to add more."]);
            }
        }

        $site = Site::create([
            'user_id' => $request->user()->id,
            'domain' => strtolower($data['domain']),
            'runtime' => $data['runtime'],
            'php_version' => $data['runtime'] === 'php' ? ($data['php_version'] ?? \App\Models\PhpRuntime::installed()[0]) : null,
            'status' => 'active',
            'ssl_status' => 'pending',
        ]);

        Agent::dispatch('site.create', ['domain' => $site->domain, 'runtime' => $site->runtime, 'php' => $site->php_version]);
        Agent::dispatch('cert.issue', ['domain' => $site->domain]);

        return redirect('/sites/'.$site->id);
    }

    public function show(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);

        return Inertia::render('Sites/Show', [
            'site' => [
                'id' => $site->id,
                'domain' => $site->domain,
                'runtime' => $site->runtime,
                'php_version' => $site->php_version,
                'status' => $site->status,
                'ssl_status' => $site->ssl_status,
                'repo' => $site->repo,
                'branch' => $site->branch,
                'auto_deploy' => $site->auto_deploy,
            ],
            'phpVersions' => \App\Models\PhpRuntime::installed(),
        ]);
    }

    public function setPhp(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        $data = $request->validate([
            'php_version' => ['required', 'in:'.implode(',', \App\Models\PhpRuntime::installed())],
        ]);

        $site->update(['php_version' => $data['php_version']]);
        Agent::dispatch('site.set_php_version', ['domain' => $site->domain, 'php' => $data['php_version']]);

        return back();
    }

    public function destroy(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        Agent::dispatch('site.delete', ['domain' => $site->domain]);
        $site->delete();

        return redirect('/sites');
    }
}
