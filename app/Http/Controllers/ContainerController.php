<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Support\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContainerController extends Controller
{
    private function scoped(Request $request)
    {
        return $request->user()->isOperator()
            ? Container::query()
            : Container::where('user_id', $request->user()->id);
    }

    private function authorizeContainer(Request $request, Container $container): void
    {
        abort_unless($request->user()->isOperator() || $container->user_id === $request->user()->id, 403);
    }

    public function index(Request $request)
    {
        $containers = $this->scoped($request)->with('owner')->latest()->get()->map(fn (Container $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'image' => $c->image,
            'host_port' => $c->host_port,
            'container_port' => $c->container_port,
            'domain' => $c->domain,
            'status' => $c->status,
            'owner' => $c->owner?->name,
        ]);

        return Inertia::render('Containers/Index', [
            'containers' => $containers,
            'dockerInstalled' => self::dockerInstalled(),
        ]);
    }

    /** Is the Docker engine present on this node? */
    private static function dockerInstalled(): bool
    {
        return is_executable('/usr/bin/docker') || is_executable('/usr/local/bin/docker');
    }

    /** Install the Docker engine (operator only). */
    public function installEngine(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);
        if (! self::dockerInstalled()) {
            Agent::dispatch('docker.install', []);
        }

        return back()->with('status', 'Installing Docker — runs in the background; refresh in a minute.');
    }

    /** Live Docker Hub image search (proxied server-side to avoid CORS). */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(8)
                ->get('https://hub.docker.com/v2/search/repositories/', ['query' => $q, 'page_size' => 8]);

            $results = collect($res->json('results', []))
                ->map(fn ($r) => [
                    'name' => $r['repo_name'] ?? ($r['name'] ?? ''),
                    'description' => \Illuminate\Support\Str::limit((string) ($r['short_description'] ?? ''), 70),
                    'stars' => (int) ($r['star_count'] ?? 0),
                    'official' => (bool) ($r['is_official'] ?? false),
                ])
                ->filter(fn ($r) => $r['name'] !== '')
                ->values();

            return response()->json(['results' => $results]);
        } catch (\Throwable) {
            return response()->json(['results' => []]);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:48', 'unique:containers,name', 'regex:/^[a-z0-9_-]+$/i'],
            'image' => ['required', 'string', 'max:160', 'regex:#^[a-z0-9][a-z0-9._/-]*(:[a-z0-9._-]+)?$#i'],
            'container_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'domain' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'restart_policy' => ['required', 'in:no,always,on-failure,unless-stopped'],
        ]);

        $hostPort = ((int) Container::max('host_port') ?: 8999) + 1;

        $container = Container::create($data + [
            'user_id' => $request->user()->id,
            'host_port' => $hostPort,
            'domain' => $data['domain'] ?? null,
            'status' => 'running',
        ]);

        Agent::dispatch('container.run', [
            'name' => $container->name,
            'image' => $container->image,
            'container_port' => $container->container_port,
            'host_port' => $hostPort,
            'domain' => $container->domain,
            'restart' => $container->restart_policy,
        ]);
        if ($container->domain) {
            Agent::dispatch('cert.issue', ['domain' => $container->domain]);
        }

        return redirect('/containers');
    }

    public function action(Request $request, Container $container, string $action)
    {
        $this->authorizeContainer($request, $container);
        abort_unless(in_array($action, ['start', 'stop'], true), 404);

        $container->update(['status' => $action === 'stop' ? 'stopped' : 'running']);
        Agent::dispatch("container.{$action}", ['name' => $container->name]);

        return back();
    }

    public function destroy(Request $request, Container $container)
    {
        $this->authorizeContainer($request, $container);
        Agent::dispatch('container.remove', ['name' => $container->name, 'domain' => $container->domain]);
        $container->delete();

        return redirect('/containers');
    }
}
