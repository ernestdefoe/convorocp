<?php

namespace App\Http\Controllers;

use App\Models\AgentOperation;
use App\Support\Agent;
use App\Support\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class UpdateController extends Controller
{
    private function ensureOperator(Request $request): void
    {
        abort_unless($request->user()->isOperator(), 403);
    }

    private function repo(): string
    {
        return Setting::get('update.repo') ?: config('convorocp.update.repo');
    }

    private function token(): ?string
    {
        $enc = Setting::get('update.token');
        if (! $enc) {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }

    public function index(Request $request)
    {
        $this->ensureOperator($request);

        return Inertia::render('Updates/Index', [
            'current' => config('convorocp.version'),
            'repo' => $this->repo(),
            'hasToken' => (bool) $this->token(),
            'latest' => Setting::get('update.latest'),
            'checkedAt' => Setting::get('update.checked_at'),
            'error' => Setting::get('update.error'),
            'updating' => AgentOperation::where('op', 'panel.update')->whereIn('status', ['pending', 'running'])->exists(),
            'system' => [
                'summary' => Setting::get('system.updates'),
                'checkedAt' => Setting::get('system.updates_checked_at'),
                'error' => Setting::get('system.updates_error'),
                'upgradedAt' => Setting::get('system.upgraded_at'),
                'rebootScheduledAt' => Setting::get('system.reboot_scheduled_at'),
                'checking' => AgentOperation::where('op', 'system.update_check')->whereIn('status', ['pending', 'running'])->exists(),
                'upgrading' => AgentOperation::where('op', 'system.upgrade')->whereIn('status', ['pending', 'running'])->exists(),
                'rebooting' => AgentOperation::where('op', 'system.reboot')->whereIn('status', ['pending', 'running'])->exists(),
            ],
        ]);
    }

    public function systemCheck(Request $request)
    {
        $this->ensureOperator($request);
        Agent::dispatch('system.update_check', []);

        return back();
    }

    public function systemUpgrade(Request $request)
    {
        $this->ensureOperator($request);
        $data = $request->validate([
            'mode' => ['nullable', 'in:all,security'],
        ]);
        Agent::dispatch('system.upgrade', ['mode' => $data['mode'] ?? 'all']);

        return back();
    }

    public function systemReboot(Request $request)
    {
        $this->ensureOperator($request);
        Agent::dispatch('system.reboot', []);

        return back();
    }

    public function check(Request $request)
    {
        $this->ensureOperator($request);
        Setting::set('update.error', null);

        $headers = ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'ConvoroCP'];
        if ($token = $this->token()) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        $repo = $this->repo();

        try {
            $tag = null;
            $name = $notes = $url = $date = null;

            $rel = Http::withHeaders($headers)->timeout(15)->get("https://api.github.com/repos/{$repo}/releases/latest");
            if ($rel->successful()) {
                $tag = $rel->json('tag_name');
                $name = $rel->json('name');
                $notes = $rel->json('body');
                $url = $rel->json('html_url');
                $date = $rel->json('published_at');
            } else {
                // No published releases — fall back to the newest tag.
                $tags = Http::withHeaders($headers)->timeout(15)->get("https://api.github.com/repos/{$repo}/tags");
                if ($tags->successful() && ! empty($tags->json())) {
                    $tag = $tags->json('0.name');
                } elseif ($tags->status() === 404 || $rel->status() === 404) {
                    Setting::set('update.error', 'Repository or releases not found. Check the repo name'.($this->token() ? '.' : ' or add a token for a private repo.'));

                    return back();
                }
            }

            if (! $tag) {
                Setting::set('update.error', 'No releases or tags found yet.');

                return back();
            }

            $current = config('convorocp.version');
            $clean = ltrim($tag, 'vV');
            Setting::set('update.latest', [
                'tag' => $tag,
                'name' => $name ?: $tag,
                'notes' => $notes,
                'url' => $url,
                'date' => $date,
                'newer' => version_compare($clean, $current, '>'),
            ]);
            Setting::set('update.checked_at', now()->toIso8601String());
        } catch (\Throwable $e) {
            Setting::set('update.error', 'Could not reach GitHub: '.$e->getMessage());
            report($e);
        }

        return back();
    }

    public function saveSettings(Request $request)
    {
        $this->ensureOperator($request);
        $data = $request->validate([
            'repo' => ['nullable', 'string', 'max:191', 'regex:#^[\w.-]+/[\w.-]+$#'],
            'token' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('repo', $data)) {
            Setting::set('update.repo', $data['repo'] ?: null);
        }
        if (! empty($data['token'])) {
            Setting::set('update.token', Crypt::encryptString($data['token']));
        } elseif ($request->boolean('clear_token')) {
            Setting::set('update.token', null);
        }

        return back();
    }

    public function apply(Request $request)
    {
        $this->ensureOperator($request);
        $latest = Setting::get('update.latest');
        abort_unless($latest && ! empty($latest['newer']), 422, 'No newer version available to install.');

        Agent::dispatch('panel.update', [
            'repo' => $this->repo(),
            'tag' => $latest['tag'],
            'token' => $this->token(),
        ]);

        return back();
    }
}
