<?php

namespace App\Http\Controllers;

use App\Models\PhpRuntime;
use App\Support\Agent;
use App\Support\AgentHandlers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Inertia\Inertia;

class ServiceController extends Controller
{
    private function ensureOperator(Request $request): void
    {
        abort_unless($request->user()->isOperator(), 403);
    }

    public function index(Request $request)
    {
        $this->ensureOperator($request);

        $list = [['nginx', 'Nginx']];
        foreach (PhpRuntime::installed() as $v) {
            $list[] = ["php{$v}-fpm", "PHP {$v} FPM"];
        }
        foreach (['mariadb' => 'MariaDB', 'postgresql' => 'PostgreSQL', 'docker' => 'Docker', 'fail2ban' => 'fail2ban'] as $name => $label) {
            $list[] = [$name, $label];
        }

        $installable = AgentHandlers::serviceInstallPackages();
        $services = collect($list)->map(fn ($s) => [
            'name' => $s[0],
            'label' => $s[1],
            'status' => trim(Process::timeout(5)->run(['systemctl', 'is-active', $s[0]])->output()) ?: 'unknown',
            'installed' => trim(Process::timeout(5)->run(['systemctl', 'list-unit-files', '--no-legend', $s[0].'.service'])->output()) !== '',
            'installable' => array_key_exists($s[0], $installable),
        ]);

        return Inertia::render('Services/Index', ['services' => $services]);
    }

    public function control(Request $request)
    {
        $this->ensureOperator($request);
        $data = $request->validate([
            'service' => ['required', 'string'],
            'action' => ['required', 'in:restart,start,stop,reload'],
        ]);
        abort_unless(AgentHandlers::serviceControllable($data['service']), 422);

        Agent::dispatch('service.control', ['service' => $data['service'], 'action' => $data['action']]);

        return back();
    }

    public function install(Request $request)
    {
        $this->ensureOperator($request);
        $data = $request->validate(['service' => ['required', 'string']]);
        abort_unless(AgentHandlers::serviceInstallable($data['service']), 422);

        Agent::dispatch('service.install', ['service' => $data['service']]);

        return back();
    }
}
