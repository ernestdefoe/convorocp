<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\Database;
use App\Models\Site;
use App\Support\Agent;
use App\Support\Offsite;
use App\Support\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    private function scoped(Request $request)
    {
        return $request->user()->isOperator()
            ? Backup::query()
            : Backup::where('user_id', $request->user()->id);
    }

    public function index(Request $request)
    {
        $sites = ($request->user()->isOperator() ? Site::query() : Site::where('user_id', $request->user()->id))->pluck('domain');
        $dbs = ($request->user()->isOperator() ? Database::query() : Database::where('user_id', $request->user()->id))->get(['name', 'engine']);

        return Inertia::render('Backups/Index', [
            'backups' => $this->scoped($request)->latest()->get()->map(fn (Backup $b) => [
                'id' => $b->id,
                'kind' => $b->kind,
                'target' => $b->target,
                'engine' => $b->engine,
                'size' => $b->size,
                'status' => $b->status,
                'offsite' => (bool) $b->offsite,
                'created' => $b->created_at?->diffForHumans(),
            ]),
            'sites' => $sites,
            'databases' => $dbs,
            'schedules' => ($request->user()->isOperator() ? BackupSchedule::query() : BackupSchedule::where('user_id', $request->user()->id))
                ->latest()->get()->map(fn (BackupSchedule $s) => [
                    'id' => $s->id,
                    'kind' => $s->kind,
                    'target' => $s->target,
                    'frequency' => $s->frequency,
                    'retention' => $s->retention,
                    'enabled' => $s->enabled,
                    'lastRun' => $s->last_run_at?->diffForHumans(),
                ]),
            'offsite' => [
                'configured' => Offsite::configured(),
                'bucket' => Setting::get('backup.s3.bucket'),
                'region' => Setting::get('backup.s3.region'),
                'endpoint' => Setting::get('backup.s3.endpoint'),
                'key' => Setting::get('backup.s3.key'),
                'hasSecret' => (bool) Setting::get('backup.s3.secret'),
            ],
        ]);
    }

    public function storeSchedule(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:site,database'],
            'target' => ['required', 'string', 'max:191'],
            'frequency' => ['required', 'in:daily,weekly'],
            'retention' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $engine = null;
        if ($data['target'] !== '*') {
            if ($data['kind'] === 'site') {
                abort_unless(($request->user()->isOperator() ? Site::query() : Site::where('user_id', $request->user()->id))->where('domain', $data['target'])->exists(), 422);
            } else {
                $db = ($request->user()->isOperator() ? Database::query() : Database::where('user_id', $request->user()->id))->where('name', $data['target'])->first();
                abort_unless($db, 422);
                $engine = $db->engine;
            }
        }

        BackupSchedule::create([
            'user_id' => $request->user()->id,
            'kind' => $data['kind'],
            'target' => $data['target'],
            'engine' => $engine,
            'frequency' => $data['frequency'],
            'retention' => $data['retention'],
            'enabled' => true,
        ]);

        return redirect('/backups');
    }

    public function toggleSchedule(Request $request, BackupSchedule $schedule)
    {
        abort_unless($request->user()->isOperator() || $schedule->user_id === $request->user()->id, 403);
        $schedule->update(['enabled' => ! $schedule->enabled]);

        return back();
    }

    public function destroySchedule(Request $request, BackupSchedule $schedule)
    {
        abort_unless($request->user()->isOperator() || $schedule->user_id === $request->user()->id, 403);
        $schedule->delete();

        return back();
    }

    public function saveOffsite(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);
        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:64'],
            'bucket' => ['nullable', 'string', 'max:191'],
            'endpoint' => ['nullable', 'string', 'max:255'],
        ]);
        foreach (['key', 'region', 'bucket', 'endpoint'] as $k) {
            if (array_key_exists($k, $data)) {
                Setting::set('backup.s3.'.$k, $data[$k] ?: null);
            }
        }
        if (! empty($data['secret'])) {
            Setting::set('backup.s3.secret', Crypt::encryptString($data['secret']));
        }

        return back();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:site,database'],
            'target' => ['required', 'string', 'max:191'],
        ]);

        $engine = null;
        if ($data['kind'] === 'site') {
            $owned = ($request->user()->isOperator() ? Site::query() : Site::where('user_id', $request->user()->id))->where('domain', $data['target'])->exists();
            abort_unless($owned, 422);
        } else {
            $db = ($request->user()->isOperator() ? Database::query() : Database::where('user_id', $request->user()->id))->where('name', $data['target'])->first();
            abort_unless($db, 422);
            $engine = $db->engine;
        }

        $backup = Backup::create([
            'user_id' => $request->user()->id,
            'kind' => $data['kind'],
            'target' => $data['target'],
            'engine' => $engine,
            'status' => 'pending',
        ]);

        Agent::dispatch('backup.run', [
            'backup_id' => $backup->id,
            'kind' => $backup->kind,
            'target' => $backup->target,
            'engine' => $engine,
        ]);

        return redirect('/backups');
    }

    public function download(Request $request, Backup $backup): BinaryFileResponse
    {
        abort_unless($request->user()->isOperator() || $backup->user_id === $request->user()->id, 403);
        abort_unless($backup->status === 'done' && $backup->filename, 404);
        $path = '/var/backups/convorocp/'.basename($backup->filename);
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }

    public function restore(Request $request, Backup $backup)
    {
        abort_unless($request->user()->isOperator() || $backup->user_id === $request->user()->id, 403);
        abort_unless($backup->status === 'done' && $backup->filename, 422);
        abort_unless(is_file('/var/backups/convorocp/'.basename($backup->filename)), 404);

        Agent::dispatch('backup.restore', [
            'kind' => $backup->kind,
            'target' => $backup->target,
            'engine' => $backup->engine,
            'filename' => basename($backup->filename),
        ]);

        return redirect('/backups');
    }

    public function destroy(Request $request, Backup $backup)
    {
        abort_unless($request->user()->isOperator() || $backup->user_id === $request->user()->id, 403);
        if ($backup->filename) {
            @unlink('/var/backups/convorocp/'.basename($backup->filename));
        }
        $backup->delete();

        return redirect('/backups');
    }
}
