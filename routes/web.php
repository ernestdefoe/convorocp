<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DaemonController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DnsController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\PhpController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UpdateController;
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

// Login 2FA challenge — reached mid-login (pending id in session), not yet authenticated.
Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
Route::post('/two-factor-challenge', [TwoFactorController::class, 'challengeVerify']);

// Public auto-deploy webhook (git host posts here on push; token-gated, CSRF-exempt).
Route::post('/deploy-hook/{site}/{token}', [SiteController::class, 'webhook'])->name('sites.webhook');

// Stripe webhook — signature-verified, CSRF-exempt, no auth.
Route::post('/billing/webhook', [BillingController::class, 'webhook'])->name('billing.webhook');

// nginx auth_request target gating the ttyd web-terminal proxy (operator-only).
// Kept outside the 'auth' group so it returns a bare 401/403, never a redirect.
Route::get('/terminal/check', [TerminalController::class, 'check'])->name('terminal.check');

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
            'customersTotal' => $clients->count(),
            'customers' => $clients->sortByDesc('created_at')->take(5)->map(fn ($u) => [
                'name' => $u->name,
                'initials' => $initials($u->name),
                'plan' => $u->plan?->name ?? '—',
                'sites' => $u->sites_count,
                'node' => 'web-01',
                'mrr' => '$'.number_format(($u->plan?->price_cents ?? 0) / 100, 0),
            ])->values(),
            'plans' => \App\Models\Plan::orderBy('price_cents')->get()->map(fn ($p) => [
                'name' => $p->name,
                'subs' => \App\Models\User::where('plan_id', $p->id)->count(),
                'price' => '$'.number_format($p->price_cents / 100, 0).'/mo',
            ]),
            'node' => \App\Support\NodeInfo::detail(),
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
    Route::post('/sites/adopt', [SiteController::class, 'adopt'])->name('sites.adopt');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
    Route::patch('/sites/{site}/php', [SiteController::class, 'setPhp'])->name('sites.php');
    Route::patch('/sites/{site}/php-settings', [SiteController::class, 'setPhpSettings'])->name('sites.php-settings');
    Route::patch('/sites/{site}/docroot', [SiteController::class, 'setDocroot'])->name('sites.docroot');
    Route::patch('/sites/{site}/repo', [SiteController::class, 'updateRepo'])->name('sites.repo');
    Route::post('/sites/{site}/nginx', [SiteController::class, 'saveNginx'])->name('sites.nginx');
    Route::post('/sites/{site}/deploy', [SiteController::class, 'deploy'])->name('sites.deploy');
    Route::get('/sites/{site}/files', [FileController::class, 'index'])->name('sites.files');
    Route::post('/sites/{site}/files/save', [FileController::class, 'save'])->name('sites.files.save');
    Route::post('/sites/{site}/files/upload', [FileController::class, 'upload'])->name('sites.files.upload');
    Route::post('/sites/{site}/files/mkdir', [FileController::class, 'mkdir'])->name('sites.files.mkdir');
    Route::post('/sites/{site}/files/chmod', [FileController::class, 'chmod'])->name('sites.files.chmod');
    Route::delete('/sites/{site}/files', [FileController::class, 'destroy'])->name('sites.files.destroy');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');

    Route::get('/apps', [AppController::class, 'index'])->name('apps.index');
    Route::post('/apps/install', [AppController::class, 'install'])->name('apps.install');
    Route::delete('/apps/{app}', [AppController::class, 'destroy'])->name('apps.destroy');

    Route::get('/databases', [DatabaseController::class, 'index'])->name('databases.index');
    Route::post('/databases', [DatabaseController::class, 'store'])->name('databases.store');
    Route::delete('/databases/{database}', [DatabaseController::class, 'destroy'])->name('databases.destroy');

    Route::get('/dns', [DnsController::class, 'index'])->name('dns.index');
    Route::post('/dns', [DnsController::class, 'store'])->name('dns.store');
    Route::delete('/dns/{record}', [DnsController::class, 'destroy'])->name('dns.destroy');

    Route::get('/scheduler', [SchedulerController::class, 'index'])->name('scheduler.index');
    Route::post('/scheduler', [SchedulerController::class, 'store'])->name('scheduler.store');
    Route::post('/scheduler/adopt', [SchedulerController::class, 'adopt'])->name('scheduler.adopt');
    Route::patch('/scheduler/{task}/toggle', [SchedulerController::class, 'toggle'])->name('scheduler.toggle');
    Route::post('/scheduler/{task}/run', [SchedulerController::class, 'run'])->name('scheduler.run');
    Route::delete('/scheduler/{task}', [SchedulerController::class, 'destroy'])->name('scheduler.destroy');

    Route::get('/daemons', [DaemonController::class, 'index'])->name('daemons.index');
    Route::post('/daemons', [DaemonController::class, 'store'])->name('daemons.store');
    Route::post('/daemons/adopt', [DaemonController::class, 'adopt'])->name('daemons.adopt');
    Route::post('/daemons/{daemon}/{action}', [DaemonController::class, 'action'])->name('daemons.action');
    Route::delete('/daemons/{daemon}', [DaemonController::class, 'destroy'])->name('daemons.destroy');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{user}', [CustomerController::class, 'show'])->name('customers.show');

    Route::post('/docker/install', [ContainerController::class, 'installEngine'])->name('containers.install-engine');
    Route::get('/docker/search', [ContainerController::class, 'search'])->name('containers.search');
    Route::get('/containers', [ContainerController::class, 'index'])->name('containers.index');
    Route::post('/containers', [ContainerController::class, 'store'])->name('containers.store');
    Route::post('/containers/{container}/{action}', [ContainerController::class, 'action'])->name('containers.action');
    Route::delete('/containers/{container}', [ContainerController::class, 'destroy'])->name('containers.destroy');

    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
    Route::post('/backups/schedules', [BackupController::class, 'storeSchedule'])->name('backups.schedules.store');
    Route::patch('/backups/schedules/{schedule}/toggle', [BackupController::class, 'toggleSchedule'])->name('backups.schedules.toggle');
    Route::delete('/backups/schedules/{schedule}', [BackupController::class, 'destroySchedule'])->name('backups.schedules.destroy');
    Route::post('/backups/offsite', [BackupController::class, 'saveOffsite'])->name('backups.offsite');
    Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');

    Route::get('/branding', [BrandingController::class, 'index'])->name('branding.index');
    Route::post('/branding', [BrandingController::class, 'save'])->name('branding.save');
    Route::post('/branding/logo', [BrandingController::class, 'uploadLogo'])->name('branding.logo');
    Route::delete('/branding/logo', [BrandingController::class, 'removeLogo'])->name('branding.logo.remove');

    Route::get('/updates', [UpdateController::class, 'index'])->name('updates.index');
    Route::post('/updates/check', [UpdateController::class, 'check'])->name('updates.check');
    Route::post('/updates/settings', [UpdateController::class, 'saveSettings'])->name('updates.settings');
    Route::post('/updates/apply', [UpdateController::class, 'apply'])->name('updates.apply');
    Route::post('/updates/system/check', [UpdateController::class, 'systemCheck'])->name('updates.system.check');
    Route::post('/updates/system/upgrade', [UpdateController::class, 'systemUpgrade'])->name('updates.system.upgrade');
    Route::post('/updates/system/reboot', [UpdateController::class, 'systemReboot'])->name('updates.system.reboot');

    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::post('/security/rules', [SecurityController::class, 'addRule'])->name('security.rules.add');
    Route::delete('/security/rules/{rule}', [SecurityController::class, 'removeRule'])->name('security.rules.remove');
    Route::post('/security/toggle', [SecurityController::class, 'toggle'])->name('security.toggle');
    Route::post('/security/fail2ban/install', [SecurityController::class, 'installFail2ban'])->name('security.fail2ban.install');
    Route::post('/security/fail2ban/{action}', [SecurityController::class, 'fail2banAction'])->name('security.fail2ban.action');

    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/services/control', [ServiceController::class, 'control'])->name('services.control');

    Route::get('/terminal', [TerminalController::class, 'index'])->name('terminal.index');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/keys', [BillingController::class, 'saveKeys'])->name('billing.keys');
    Route::post('/billing/test', [BillingController::class, 'testConnection'])->name('billing.test');
    Route::patch('/billing/plans/{plan}', [BillingController::class, 'savePlanPrice'])->name('billing.plan-price');
    Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.status');

    Route::post('/security/2fa/enable', [TwoFactorController::class, 'enable'])->name('security.2fa.enable');
    Route::post('/security/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('security.2fa.confirm');
    Route::delete('/security/2fa', [TwoFactorController::class, 'disable'])->name('security.2fa.disable');

    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

    Route::get('/license', [App\Http\Controllers\LicenseController::class, 'index'])->name('license.index');
    Route::post('/license', [App\Http\Controllers\LicenseController::class, 'save'])->name('license.save');
    Route::post('/license/recheck', [App\Http\Controllers\LicenseController::class, 'recheck'])->name('license.recheck');

    Route::get('/mail', [MailController::class, 'index'])->name('mail.index');
    Route::post('/mail', [MailController::class, 'store'])->name('mail.store');
    Route::post('/mail/{account}/send', [MailController::class, 'send'])->name('mail.send');
    Route::get('/mail/{account}/attachment', [MailController::class, 'attachment'])->name('mail.attachment');
    Route::post('/mail/{account}/message', [MailController::class, 'message'])->name('mail.message');
    Route::delete('/mail/{account}', [MailController::class, 'destroy'])->name('mail.destroy');

    Route::get('/php', [PhpController::class, 'index'])->name('php.index');
    Route::post('/php/save-ini', [PhpController::class, 'saveIni'])->name('php.save-ini');
    Route::post('/php/{runtime}/install', [PhpController::class, 'install'])->name('php.install');
    Route::post('/php/{runtime}/uninstall', [PhpController::class, 'uninstall'])->name('php.uninstall');

    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::patch('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
});
