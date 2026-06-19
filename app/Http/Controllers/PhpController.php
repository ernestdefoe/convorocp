<?php

namespace App\Http\Controllers;

use App\Models\PhpRuntime;
use App\Support\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhpController extends Controller
{
    private function ensureOperator(Request $request): void
    {
        abort_unless($request->user()->isOperator(), 403);
    }

    public function index(Request $request)
    {
        $this->ensureOperator($request);

        return Inertia::render('Php/Index', [
            'runtimes' => PhpRuntime::orderByDesc('version')->get(['id', 'version', 'status']),
            'inis' => $this->iniFiles(),
            'systemVersion' => $this->systemVersion(),
        ]);
    }

    /**
     * The PHP version the panel itself runs on. Its global php.ini is shared by
     * EVERY pool of that version — the panel and any adopted/live site co-located
     * on the box (e.g. a forum). Editing it is a system-wide change.
     */
    private function systemVersion(): string
    {
        return implode('.', array_slice(explode('.', PHP_VERSION), 0, 2));
    }

    /** Versions whose global php.ini is shared with the panel or an adopted site. */
    private function protectedVersions(): array
    {
        $protected = [$this->systemVersion()];
        // Adopted sites are served from this box's PHP — protect their version(s) too.
        $protected = array_merge(
            $protected,
            \App\Models\Site::where('adopted', true)->whereNotNull('php_version')->pluck('php_version')->all()
        );

        return array_values(array_unique($protected));
    }

    /** The actual fpm php.ini for each installed PHP, read straight off disk. */
    private function iniFiles(): array
    {
        $protected = $this->protectedVersions();

        return collect(glob('/etc/php/*/fpm/php.ini') ?: [])
            ->map(function (string $path) use ($protected) {
                preg_match('#/etc/php/([\d.]+)/#', $path, $m);
                $version = $m[1] ?? '?';

                return [
                    'version' => $version,
                    'path' => $path,
                    'content' => is_readable($path) ? (string) file_get_contents($path) : null,
                    'protected' => in_array($version, $protected, true),
                ];
            })
            ->filter(fn ($x) => $x['content'] !== null)
            ->sortByDesc('version')
            ->values()
            ->all();
    }

    /** Write a php.ini (operator only) — validated + reloaded by the agent. */
    public function saveIni(Request $request)
    {
        $this->ensureOperator($request);
        $data = $request->validate([
            'version' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'content' => ['required', 'string', 'max:200000'],
            'confirm' => ['boolean'],
        ]);

        // Editing the shared/system php.ini affects every pool of that version —
        // including any live forum on the box. Require an explicit confirmation.
        if (in_array($data['version'], $this->protectedVersions(), true) && ! $request->boolean('confirm')) {
            return back()->withErrors(['content' => "PHP {$data['version']} is the system version — its php.ini is shared with the panel and live sites. Tick the confirmation to change it, or use a site's PHP settings to change just one site."]);
        }

        Agent::dispatch('php.ini.write', [
            'version' => $data['version'],
            'content' => $data['content'],
            'confirm' => $request->boolean('confirm'),
        ]);

        return back()->with('status', "php.ini for PHP {$data['version']} queued — the agent will validate + reload FPM.");
    }

    public function install(Request $request, PhpRuntime $runtime)
    {
        $this->ensureOperator($request);
        if ($runtime->status === 'available') {
            $runtime->update(['status' => 'installing']);
            Agent::dispatch('php.install', ['version' => $runtime->version]);
        }

        return back();
    }

    public function uninstall(Request $request, PhpRuntime $runtime)
    {
        $this->ensureOperator($request);
        if ($runtime->status === 'installed') {
            $runtime->update(['status' => 'removing']);
            Agent::dispatch('php.uninstall', ['version' => $runtime->version]);
        }

        return back();
    }
}
