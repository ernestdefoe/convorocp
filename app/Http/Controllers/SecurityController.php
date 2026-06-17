<?php

namespace App\Http\Controllers;

use App\Models\FirewallRule;
use App\Support\Agent;
use App\Support\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SecurityController extends Controller
{
    private function ensureOperator(Request $request): void
    {
        abort_unless($request->user()->isOperator(), 403);
    }

    public function index(Request $request)
    {
        $this->ensureOperator($request);

        return Inertia::render('Security/Index', [
            'rules' => FirewallRule::orderBy('port')->get(['id', 'port', 'proto', 'action', 'note']),
            'enabled' => (bool) Setting::get('firewall.enabled', false),
        ]);
    }

    public function addRule(Request $request)
    {
        $this->ensureOperator($request);
        $data = $request->validate([
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'proto' => ['required', 'in:tcp,udp'],
            'action' => ['required', 'in:allow,deny'],
            'note' => ['nullable', 'string', 'max:60'],
        ]);
        FirewallRule::create($data);
        Agent::dispatch('firewall.allow', ['port' => $data['port'], 'proto' => $data['proto'], 'action' => $data['action']]);

        return back();
    }

    public function removeRule(Request $request, FirewallRule $rule)
    {
        $this->ensureOperator($request);
        Agent::dispatch('firewall.remove', ['port' => $rule->port, 'proto' => $rule->proto, 'action' => $rule->action]);
        $rule->delete();

        return back();
    }

    public function toggle(Request $request)
    {
        $this->ensureOperator($request);
        $enabled = ! Setting::get('firewall.enabled', false);
        Setting::set('firewall.enabled', $enabled);
        Agent::dispatch($enabled ? 'firewall.enable' : 'firewall.disable', []);

        return back();
    }
}
