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
        $daemons = $this->scoped($request)->latest()->get()->map(fn (Daemon $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'command' => $d->command,
            'status' => $d->status,
            'autostart' => $d->autostart,
            'restart_policy' => $d->restart_policy,
        ]);

        return Inertia::render('Daemons/Index', ['daemons' => $daemons]);
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
        Agent::dispatch("daemon.{$action}", ['id' => $daemon->id]);

        return back();
    }

    public function destroy(Request $request, Daemon $daemon)
    {
        $this->authorizeDaemon($request, $daemon);
        Agent::dispatch('daemon.delete', ['id' => $daemon->id]);
        $daemon->delete();

        return redirect('/daemons');
    }
}
