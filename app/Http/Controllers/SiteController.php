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
            'adopted' => $s->adopted,
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
            'php_settings' => Site::defaultPhpSettings(),
            'status' => 'active',
            'ssl_status' => 'pending',
        ]);

        Agent::dispatch('site.create', ['domain' => $site->domain, 'runtime' => $site->runtime, 'php' => $site->php_version, 'settings' => $site->phpSettings()]);
        Agent::dispatch('cert.issue', ['domain' => $site->domain]);

        return redirect('/sites/'.$site->id);
    }

    public function show(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);

        // The raw nginx vhost is shown to operators only (read straight off disk).
        $nginx = null;
        if ($request->user()->isOperator()) {
            $path = "/etc/nginx/sites-available/{$site->domain}";
            $nginx = [
                'path' => $path,
                'conf' => is_readable($path) ? (string) file_get_contents($path) : null,
            ];
        }

        return Inertia::render('Sites/Show', [
            'site' => [
                'id' => $site->id,
                'domain' => $site->domain,
                'runtime' => $site->runtime,
                'docroot' => $site->docroot,
                'default_docroot' => $site->defaultDocroot(),
                'php_version' => $site->php_version,
                'status' => $site->status,
                'ssl_status' => $site->ssl_status,
                'repo' => $site->repo,
                'branch' => $site->branch,
                'auto_deploy' => $site->auto_deploy,
                'php_settings' => $site->phpSettings(),
                'adopted' => $site->adopted,
                'deploy_webhook' => url('/deploy-hook/'.$site->id.'/'.$site->deployToken()),
            ],
            'phpVersions' => \App\Models\PhpRuntime::installed(),
            'disableableFunctions' => Site::DISABLEABLE_FUNCTIONS,
            'nginx' => $nginx,
        ]);
    }

    /** Overwrite a site's nginx vhost (operator only) — agent tests + reloads, reverts on error. */
    public function saveNginx(Request $request, Site $site)
    {
        abort_unless($request->user()->isOperator(), 403);
        $data = $request->validate([
            'content' => ['required', 'string', 'max:100000'],
        ]);

        Agent::dispatch('nginx.write', ['domain' => $site->domain, 'content' => $data['content']]);

        return back()->with('status', 'nginx config queued — the agent will test it and reload (auto-reverts if invalid).');
    }

    public function setPhp(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        abort_if($site->adopted, 422, 'This site is adopted (managed externally) — PHP changes are disabled.');
        $data = $request->validate([
            'php_version' => ['required', 'in:'.implode(',', \App\Models\PhpRuntime::installed())],
        ]);

        $site->update(['php_version' => $data['php_version']]);
        Agent::dispatch('site.set_php_version', ['domain' => $site->domain, 'php' => $data['php_version'], 'settings' => $site->phpSettings()]);

        return back();
    }

    public function setPhpSettings(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        abort_if($site->adopted, 422, 'This site is adopted (managed externally) — PHP changes are disabled.');
        $data = $request->validate([
            'memory_limit' => ['required', 'regex:/^\d+[KMG]?$/i'],
            'upload_max_filesize' => ['required', 'regex:/^\d+[KMG]?$/i'],
            'post_max_size' => ['required', 'regex:/^\d+[KMG]?$/i'],
            'max_execution_time' => ['required', 'integer', 'min:0', 'max:3600'],
            'display_errors' => ['boolean'],
            'disable_functions' => ['array'],
            'disable_functions.*' => ['in:'.implode(',', Site::DISABLEABLE_FUNCTIONS)],
        ]);

        $settings = [
            'memory_limit' => strtoupper($data['memory_limit']),
            'upload_max_filesize' => strtoupper($data['upload_max_filesize']),
            'post_max_size' => strtoupper($data['post_max_size']),
            'max_execution_time' => (int) $data['max_execution_time'],
            'display_errors' => $request->boolean('display_errors'),
            'disable_functions' => array_values($data['disable_functions'] ?? []),
        ];
        $site->update(['php_settings' => $settings]);

        if ($site->runtime === 'php') {
            Agent::dispatch('site.set_php_settings', ['domain' => $site->domain, 'php' => $site->php_version, 'settings' => $settings]);
        }

        return back();
    }

    /** Set (or reset, when blank) the per-site nginx document root. */
    public function setDocroot(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        abort_if($site->adopted, 422, 'This site is adopted (managed externally) — its document root is set outside the panel.');
        $data = $request->validate([
            'docroot' => ['nullable', 'string', 'max:255'],
        ]);

        $docroot = trim((string) ($data['docroot'] ?? ''));
        if ($docroot !== '') {
            // Mirror the agent-side guard so bad input is rejected before queuing.
            $valid = (bool) preg_match('#^/[\w./-]+$#', $docroot) && ! str_contains($docroot, '..');
            $underRoot = collect(Site::DOCROOT_ROOTS)->contains(
                fn ($p) => $docroot === $p || str_starts_with($docroot, $p.'/')
            );
            if (! $valid || ! $underRoot) {
                return back()->withErrors(['docroot' => 'Document root must be an absolute path under /var/www, /home or /srv, with no "..".']);
            }
            $docroot = rtrim($docroot, '/');
        }

        $site->update(['docroot' => $docroot ?: null]);
        Agent::dispatch('site.set_docroot', [
            'domain' => $site->domain,
            'runtime' => $site->runtime,
            'docroot' => $site->effectiveDocroot(),
        ]);

        return back()->with('status', 'Document root update queued — the agent will re-point nginx and reload.');
    }

    public function updateRepo(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        $data = $request->validate([
            'repo' => ['nullable', 'regex:#^https://[\w./-]+$#i', 'max:255'],
            'branch' => ['required', 'string', 'max:120', 'regex:#^[\w./-]+$#'],
            'auto_deploy' => ['boolean'],
        ]);
        $site->update([
            'repo' => $data['repo'] ?: null,
            'branch' => $data['branch'],
            'auto_deploy' => $request->boolean('auto_deploy'),
        ]);

        return back();
    }

    public function deploy(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        if (! $site->repo) {
            return back()->withErrors(['repo' => 'Set a git repository first.']);
        }
        $site->update(['status' => 'deploying']);
        Agent::dispatch('site.deploy', ['domain' => $site->domain, 'repo' => $site->repo, 'branch' => $site->branch, 'docroot' => $site->effectiveDocroot()]);

        return back();
    }

    public function webhook(Request $request, Site $site, string $token)
    {
        abort_unless($site->deploy_token && hash_equals($site->deploy_token, $token), 404);
        if ($site->auto_deploy && $site->repo) {
            Agent::dispatch('site.deploy', ['domain' => $site->domain, 'repo' => $site->repo, 'branch' => $site->branch, 'docroot' => $site->effectiveDocroot()]);

            return response()->json(['ok' => true, 'queued' => true]);
        }

        return response()->json(['ok' => true, 'queued' => false]);
    }

    /**
     * Adopt an existing app/vhost ConvoroCP did NOT provision (operator only).
     * Records it + symlinks the path; never writes a vhost/pool or touches files.
     */
    public function adopt(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:191', 'unique:sites,domain', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'path' => ['required', 'string', 'max:255'],
        ]);

        $site = Site::create([
            'user_id' => $request->user()->id,
            'domain' => strtolower($data['domain']),
            'runtime' => 'php',
            'php_version' => \App\Models\PhpRuntime::installed()[0] ?? null,
            'php_settings' => Site::defaultPhpSettings(),
            'status' => 'active',
            'ssl_status' => 'active',
            'adopted' => true,
        ]);
        Agent::dispatch('site.adopt', ['domain' => $site->domain, 'path' => $data['path']]);

        return redirect('/sites/'.$site->id);
    }

    public function destroy(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        // Adopted sites are only ever detached (symlink removed) — never hard-deleted.
        Agent::dispatch($site->adopted ? 'site.detach' : 'site.delete', ['domain' => $site->domain]);
        $site->delete();

        return redirect('/sites');
    }
}
