<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DaemonController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DnsController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\PhpController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\SiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/signup', [SignupController::class, 'show'])->name('signup');
    Route::post('/signup', [SignupController::class, 'store']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Public auto-deploy webhook (git host posts here on push; token-gated, CSRF-exempt).
Route::post('/deploy-hook/{site}/{token}', [SiteController::class, 'webhook'])->name('sites.webhook');

Route::middleware('auth')->get('/', function (Request $request) {
    if ($request->user()->isOperator()) {
        $clients = \App\Models\User::where('role', 'client')->with('plan')->withCount('sites')->get();
        $mrr = $clients->sum(fn ($u) => $u->plan?->price_cents ?? 0) / 100;
        $initials = fn ($n) => collect(explode(' ', $n))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');

        return Inertia::render('OperatorDashboard', [
            'metrics' => [
                ['label' => 'MRR', 'value' => '$'.number_format($mrr, 0), 'sub' => 'recurring', 'tone' => 'grn'],
                ['label' => 'Active customers', 'value' => (string) $clients->count(), 'sub' => 'subscribed', 'tone' => 'grn'],
                ['label' => 'Nodes', 'value' => '1', 'sub' => 'all healthy', 'tone' => 'mut'],
                ['label' => 'Sites', 'value' => (string) \App\Models\Site::count(), 'sub' => 'hosted', 'tone' => 'mut'],
            ],
            'customers' => $clients->sortByDesc('created_at')->take(5)->map(fn ($u) => [
                'name' => $u->name,
                'initials' => $initials($u->name),
                'plan' => $u->plan?->name ?? '—',
                'sites' => $u->sites_count,
                'node' => 'web-01',
                'mrr' => '$'.number_format(($u->plan?->price_cents ?? 0) / 100, 0),
            ])->values(),
            'node' => \App\Support\ServerMetrics::snapshot(),
        ]);
    }

    return Inertia::render('ClientDashboard', [
        'plan' => ['name' => 'Pro plan', 'price' => '$45/mo', 'renews' => 'Jul 1, 2026'],
        'usage' => [
            ['label' => 'Disk', 'used' => '12', 'total' => '50 GB', 'pct' => 24, 'tone' => 'ind'],
            ['label' => 'Bandwidth', 'used' => '84', 'total' => '500 GB', 'pct' => 17, 'tone' => 'cy'],
            ['label' => 'Email', 'used' => '4', 'total' => '25', 'pct' => 16, 'tone' => 'vio'],
        ],
        'sites' => \App\Models\Site::where('user_id', $request->user()->id)->pluck('domain'),
        'invoice' => ['amount' => '$45.00', 'due' => 'Jul 1, 2026', 'card' => 'Visa ending 6411'],
    ]);
})->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
    Route::patch('/sites/{site}/php', [SiteController::class, 'setPhp'])->name('sites.php');
    Route::patch('/sites/{site}/php-settings', [SiteController::class, 'setPhpSettings'])->name('sites.php-settings');
    Route::patch('/sites/{site}/repo', [SiteController::class, 'updateRepo'])->name('sites.repo');
    Route::post('/sites/{site}/deploy', [SiteController::class, 'deploy'])->name('sites.deploy');
    Route::get('/sites/{site}/files', [FileController::class, 'index'])->name('sites.files');
    Route::post('/sites/{site}/files/save', [FileController::class, 'save'])->name('sites.files.save');
    Route::post('/sites/{site}/files/upload', [FileController::class, 'upload'])->name('sites.files.upload');
    Route::post('/sites/{site}/files/mkdir', [FileController::class, 'mkdir'])->name('sites.files.mkdir');
    Route::post('/sites/{site}/files/chmod', [FileController::class, 'chmod'])->name('sites.files.chmod');
    Route::delete('/sites/{site}/files', [FileController::class, 'destroy'])->name('sites.files.destroy');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');

    Route::get('/databases', [DatabaseController::class, 'index'])->name('databases.index');
    Route::post('/databases', [DatabaseController::class, 'store'])->name('databases.store');
    Route::delete('/databases/{database}', [DatabaseController::class, 'destroy'])->name('databases.destroy');

    Route::get('/dns', [DnsController::class, 'index'])->name('dns.index');
    Route::post('/dns', [DnsController::class, 'store'])->name('dns.store');
    Route::delete('/dns/{record}', [DnsController::class, 'destroy'])->name('dns.destroy');

    Route::get('/scheduler', [SchedulerController::class, 'index'])->name('scheduler.index');
    Route::post('/scheduler', [SchedulerController::class, 'store'])->name('scheduler.store');
    Route::patch('/scheduler/{task}/toggle', [SchedulerController::class, 'toggle'])->name('scheduler.toggle');
    Route::post('/scheduler/{task}/run', [SchedulerController::class, 'run'])->name('scheduler.run');
    Route::delete('/scheduler/{task}', [SchedulerController::class, 'destroy'])->name('scheduler.destroy');

    Route::get('/daemons', [DaemonController::class, 'index'])->name('daemons.index');
    Route::post('/daemons', [DaemonController::class, 'store'])->name('daemons.store');
    Route::post('/daemons/{daemon}/{action}', [DaemonController::class, 'action'])->name('daemons.action');
    Route::delete('/daemons/{daemon}', [DaemonController::class, 'destroy'])->name('daemons.destroy');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{user}', [CustomerController::class, 'show'])->name('customers.show');

    Route::get('/docker/search', [ContainerController::class, 'search'])->name('containers.search');
    Route::get('/containers', [ContainerController::class, 'index'])->name('containers.index');
    Route::post('/containers', [ContainerController::class, 'store'])->name('containers.store');
    Route::post('/containers/{container}/{action}', [ContainerController::class, 'action'])->name('containers.action');
    Route::delete('/containers/{container}', [ContainerController::class, 'destroy'])->name('containers.destroy');

    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
    Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');

    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/services/control', [ServiceController::class, 'control'])->name('services.control');

    Route::get('/php', [PhpController::class, 'index'])->name('php.index');
    Route::post('/php/{runtime}/install', [PhpController::class, 'install'])->name('php.install');
    Route::post('/php/{runtime}/uninstall', [PhpController::class, 'uninstall'])->name('php.uninstall');

    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::patch('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
});
