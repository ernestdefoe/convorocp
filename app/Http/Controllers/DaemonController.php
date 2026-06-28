<?php

namespace App\Http\Controllers;

use App\Models\Daemon;
use App\Support\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DaemonController extends Controller
{
    private function scoped(Request $request)
    {
        return $request->user()->isOperator()
            ? Daemon::query()
            : Daemon::where('user_id', $request->user()->id);
    }

    private function authorizeDaemon(Request $request, Daemon $daemon): void
    {
        abort_unless($request->user()->isOperator() || $daemon->user_id === $request->user()->id, 403);
    }

    public function index(Request $request)
    {
        $live = $this->liveStatuses();
        if ($request->user()->isOperator()) {
            $this->discover($live, $request->user()->id);
        }

        $daemons = $this->scoped($request)->latest()->get()->map(fn (Daemon $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'command' => $d->command,
            'status' => $live[$d->unitName()] ?? $d->status,
            'autostart' => $d->autostart,
            'restart_policy' => $d->restart_policy,
            'adopted' => $d->adopted,
            'unit' => $d->unitName(),
        ]);

        return Inertia::render('Daemons/Index', ['daemons' => $daemons]);
    }

    /** Live systemd state for every site daemon + the agent (unit => active|inactive|...). */
    private function liveStatuses(): array
    {
        $r = \Illuminate\Support\Facades\Process::timeout(8)->run([
            'systemctl', 'list-units', '--type=service', '--all', '--no-legend', '--plain',
            '*-ssr.service', '*-reverb.service', '*-horizon.service', '*-worker.service', 'convorocp-agent.service',
        ]);
        $out = [];
        foreach (preg_split('/?
/', trim($r->output())) as $line) {
            if ($line === '') { continue; }
            $c = preg_split('/\s+/', trim($line));
            $unit = $c[0] ?? '';
            if (str_ends_with($unit, '.service')) {
                $out[$unit] = $c[2] ?? 'unknown';
            }
        }
        return $out;
    }

    /** Auto-adopt any site daemon (another forum's ssr/reverb/horizon/worker, etc.)
     *  ConvoroCP is not tracking yet, so every daemon on the node shows. */
    private function discover(array $live, int $ownerId): void
    {
        $known = Daemon::query()->whereNotNull('unit')->pluck('unit')->all();
        foreach (array_keys($live) as $unit) {
            if (in_array($unit, $known, true)) {
                continue;
            }
            Daemon::create([
                'user_id' => $ownerId,
                'name' => ucwords(str_replace(['-', '.service'], [' ', ''], $unit)),
                'command' => '(external unit)',
                'status' => $live[$unit] ?? 'running',
                'autostart' => true,
                'restart_policy' => 'always',
                'adopted' => true,
                'unit' => $unit,
            ]);
        }
    }

    /**
     * Adopt an existing systemd unit ConvoroCP didn't create (operator only).
     * The panel controls/monitors the real unit; it never writes a new one.
     */
    public function adopt(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'unit' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9@._-]+(\.service)?$/i'],
            'command' => ['nullable', 'string', 'max:500'],
        ]);

        $unit = str_ends_with($data['unit'], '.service') ? $data['unit'] : $data['unit'].'.service';
        Daemon::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'command' => $data['command'] ?: '(external unit)',
            'status' => 'running',
            'autostart' => true,
            'restart_policy' => 'always',
            'adopted' => true,
            'unit' => $unit,
        ]);

        return redirect('/daemons');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'command' => ['required', 'string', 'max:500'],
            'restart_policy' => ['required', 'in:always,on-failure,never'],
        ]);

        $daemon = Daemon::create($data + [
            'user_id' => $request->user()->id,
            'status' => 'running',
            'autostart' => true,
        ]);
        Agent::dispatch('daemon.create', ['id' => $daemon->id, 'command' => $daemon->command, 'restart' => $daemon->restart_policy]);

        return redirect('/daemons');
    }

    public function action(Request $request, Daemon $daemon, string $action)
    {
        $this->authorizeDaemon($request, $daemon);
        abort_unless(in_array($action, ['start', 'stop', 'restart'], true), 404);

        $daemon->update(['status' => $action === 'stop' ? 'stopped' : 'running']);
        Agent::dispatch("daemon.{$action}", ['id' => $daemon->id, 'unit' => $daemon->unitName()]);

        return back();
    }

    public function destroy(Request $request, Daemon $daemon)
    {
        $this->authorizeDaemon($request, $daemon);
        // Adopted daemons are only detached (forgotten by the panel) — the real unit is left running.
        if (! $daemon->adopted) {
            Agent::dispatch('daemon.delete', ['id' => $daemon->id]);
        }
        $daemon->delete();

        return redirect('/daemons');
    }
}
