<?php

namespace App\Http\Controllers;

use App\Models\FirewallRule;
use App\Support\Agent;
use App\Support\Setting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class SecurityController extends Controller
{
    private function ensureOperator(Request $request): void
    {
        abort_unless($request->user()->isOperator(), 403);
    }

    public function index(Request $request)
    {
        $isOperator = $request->user()->isOperator();

        return Inertia::render('Security/Index', [
            'isOperator' => $isOperator,
            'twoFactor' => $this->twoFactorData($request),
            // Server-level controls are operator-only.
            'rules' => $isOperator ? FirewallRule::orderBy('port')->get(['id', 'port', 'proto', 'action', 'note']) : [],
            'enabled' => $isOperator ? (bool) Setting::get('firewall.enabled', false) : false,
            'fail2ban' => $isOperator ? $this->fail2banStatus() : ['installed' => false, 'jails' => []],
        ]);
    }

    /** Personal 2FA state for the current user (manage here, in the Security area). */
    private function twoFactorData(Request $request): array
    {
        $user = $request->user();
        $pending = ! $user->hasTwoFactorEnabled() && ! empty($user->two_factor_secret);

        $qr = null;
        $secret = null;
        if ($pending) {
            $secret = $user->two_factor_secret;
            $url = (new Google2FA)->getQRCodeUrl(config('app.name', 'ConvoroCP'), $user->email, $secret);
            $renderer = new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd);
            $qr = 'data:image/svg+xml;base64,'.base64_encode((new Writer($renderer))->writeString($url));
        }

        return [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $pending,
            'qr' => $qr,
            'secret' => $secret,
            'recoveryCodes' => $request->session()->get('recovery_codes'),
        ];
    }

    /**
     * Live fail2ban state read by the web tier. Status reads run via a scoped,
     * read-only sudoers rule (see fail2ban.install); never assumes root here.
     */
    private function fail2banStatus(): array
    {
        $active = trim(Process::run(['systemctl', 'is-active', 'fail2ban'])->output()) === 'active';
        if (! $active) {
            return ['installed' => false, 'jails' => []];
        }

        $jails = [];
        $top = Process::run(['sudo', '-n', 'fail2ban-client', 'status']);
        if (preg_match('/Jail list:\s*(.*)/', $top->output(), $m)) {
            foreach (array_filter(array_map('trim', explode(',', $m[1]))) as $name) {
                if (! preg_match('/^[\w.-]+$/', $name)) {
                    continue;
                }
                $out = Process::run(['sudo', '-n', 'fail2ban-client', 'status', $name])->output();
                $banned = [];
                if (preg_match('/Banned IP list:\s*(.*)/', $out, $bm)) {
                    $banned = array_values(array_filter(array_map('trim', explode(' ', $bm[1]))));
                }
                preg_match('/Total banned:\s*(\d+)/', $out, $tm);
                $jails[] = ['name' => $name, 'banned' => $banned, 'total' => (int) ($tm[1] ?? count($banned))];
            }
        }

        return ['installed' => true, 'jails' => $jails];
    }

    public function installFail2ban(Request $request)
    {
        $this->ensureOperator($request);
        Agent::dispatch('fail2ban.install', []);

        return back();
    }

    public function fail2banAction(Request $request, string $action)
    {
        $this->ensureOperator($request);
        abort_unless(in_array($action, ['ban', 'unban'], true), 404);
        $data = $request->validate([
            'jail' => ['required', 'string', 'max:40', 'regex:/^[\w.-]+$/'],
            'ip' => ['required', 'ip'],
        ]);
        Agent::dispatch('fail2ban.'.$action, ['jail' => $data['jail'], 'ip' => $data['ip']]);

        return back();
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
