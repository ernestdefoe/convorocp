<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DaemonController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DnsController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SchedulerController;
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

Route::middleware('auth')->get('/', function (Request $request) {
    if ($request->user()->isOperator()) {
        return Inertia::render('OperatorDashboard', [
            'metrics' => [
                ['label' => 'MRR', 'value' => '$8,420', 'sub' => '+9% MoM', 'tone' => 'grn'],
                ['label' => 'Active customers', 'value' => '142', 'sub' => '+6 this week', 'tone' => 'grn'],
                ['label' => 'Nodes', 'value' => '6', 'sub' => 'all healthy', 'tone' => 'mut'],
                ['label' => 'Open tickets', 'value' => '3', 'sub' => '1 overdue', 'tone' => 'amb'],
            ],
            'customers' => [
                ['name' => 'Maya Rodriguez', 'initials' => 'MR', 'plan' => 'Business', 'sites' => 7, 'node' => 'web-01', 'mrr' => '$120'],
                ['name' => 'Daniel Okafor', 'initials' => 'DO', 'plan' => 'Pro', 'sites' => 3, 'node' => 'web-02', 'mrr' => '$45'],
                ['name' => 'Sara Lindqvist', 'initials' => 'SL', 'plan' => 'Pro', 'sites' => 2, 'node' => 'web-01', 'mrr' => '$45'],
                ['name' => 'Tomás Núñez', 'initials' => 'TN', 'plan' => 'Starter', 'sites' => 1, 'node' => 'web-03', 'mrr' => '$12'],
            ],
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

    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::patch('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
});
