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
        ]);
    }

    /** The actual fpm php.ini for each installed PHP, read straight off disk. */
    private function iniFiles(): array
    {
        return collect(glob('/etc/php/*/fpm/php.ini') ?: [])
            ->map(function (string $path) {
                preg_match('#/etc/php/([\d.]+)/#', $path, $m);

                return [
                    'version' => $m[1] ?? '?',
                    'path' => $path,
                    'content' => is_readable($path) ? (string) file_get_contents($path) : null,
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
        ]);

        Agent::dispatch('php.ini.write', ['version' => $data['version'], 'content' => $data['content']]);

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
