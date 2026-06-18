<?php

namespace App\Http\Controllers;

use App\Models\AppInstall;
use App\Models\Site;
use App\Support\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppController extends Controller
{
    /** One-click app catalog. */
    public const CATALOG = [
        'wordpress' => ['name' => 'WordPress', 'desc' => "The world's most popular CMS & blog platform.", 'icon' => 'ti-brand-wordpress', 'db' => true],
        'convoro' => ['name' => 'Convoro Forums', 'desc' => 'Modern community forum platform.', 'icon' => 'ti-messages', 'db' => true, 'featured' => true],
        'flarum' => ['name' => 'Flarum', 'desc' => 'Delightfully simple, fast forum software.', 'icon' => 'ti-message-circle', 'db' => true],
        'phpmyadmin' => ['name' => 'phpMyAdmin', 'desc' => 'Web-based MySQL / MariaDB administration.', 'icon' => 'ti-database-cog', 'db' => false],
        'static' => ['name' => 'Static site', 'desc' => 'A blank starter page to build from.', 'icon' => 'ti-file-code', 'db' => false],
    ];

    private function sites(Request $request)
    {
        return ($request->user()->isOperator() ? Site::query() : Site::where('user_id', $request->user()->id))->pluck('domain');
    }

    public function index(Request $request)
    {
        $catalog = collect(self::CATALOG)->map(fn ($a, $key) => ['key' => $key, 'featured' => $a['featured'] ?? false] + $a)
            ->sortByDesc('featured')->values();

        $installs = ($request->user()->isOperator() ? AppInstall::query() : AppInstall::where('user_id', $request->user()->id))
            ->latest()->take(25)->get()->map(fn (AppInstall $i) => [
                'id' => $i->id,
                'app' => self::CATALOG[$i->app]['name'] ?? $i->app,
                'domain' => $i->domain,
                'status' => $i->status,
                'info' => $i->info,
                'created' => $i->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Apps/Index', [
            'catalog' => $catalog,
            'sites' => $this->sites($request),
            'installs' => $installs,
        ]);
    }

    public function install(Request $request)
    {
        $data = $request->validate([
            'app' => ['required', 'string'],
            'domain' => ['required', 'string', 'max:191'],
        ]);
        abort_unless(array_key_exists($data['app'], self::CATALOG), 422, 'Unknown app.');
        abort_unless($this->sites($request)->contains($data['domain']), 422, 'You don’t own that site.');

        $install = AppInstall::create([
            'user_id' => $request->user()->id,
            'domain' => $data['domain'],
            'app' => $data['app'],
            'status' => 'pending',
        ]);

        Agent::dispatch('app.install', [
            'install_id' => $install->id,
            'app' => $data['app'],
            'domain' => $data['domain'],
        ]);

        return redirect('/apps');
    }

    public function destroy(Request $request, AppInstall $app)
    {
        abort_unless($request->user()->isOperator() || $app->user_id === $request->user()->id, 403);
        $app->delete();

        return back();
    }
}
