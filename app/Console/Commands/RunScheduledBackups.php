<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\Database;
use App\Models\Site;
use App\Support\Agent;
use App\Support\Offsite;
use Illuminate\Console\Command;

class RunScheduledBackups extends Command
{
    protected $signature = 'backups:scheduled';

    protected $description = 'Run any due backup schedules and prune old backups beyond retention.';

    public function handle(): int
    {
        foreach (BackupSchedule::all() as $schedule) {
            if (! $schedule->isDue()) {
                continue;
            }

            foreach ($this->resolveTargets($schedule) as [$target, $engine]) {
                $backup = Backup::create([
                    'user_id' => $schedule->user_id,
                    'kind' => $schedule->kind,
                    'target' => $target,
                    'engine' => $engine,
                    'status' => 'pending',
                ]);
                Agent::dispatch('backup.run', [
                    'backup_id' => $backup->id,
                    'kind' => $schedule->kind,
                    'target' => $target,
                    'engine' => $engine,
                ]);
                $this->pruneRetention($schedule->kind, $target, $schedule->retention);
            }

            $schedule->update(['last_run_at' => now()]);
            $this->info("Ran schedule #{$schedule->id} ({$schedule->kind}/{$schedule->target}).");
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{0:string,1:?string}> [target, engine] pairs */
    private function resolveTargets(BackupSchedule $s): array
    {
        if ($s->target !== '*') {
            return [[$s->target, $s->engine]];
        }
        if ($s->kind === 'database') {
            return Database::all(['name', 'engine'])->map(fn ($d) => [$d->name, $d->engine])->all();
        }

        return Site::pluck('domain')->map(fn ($d) => [$d, null])->all();
    }

    /** Keep the newest N completed backups for a target; delete older ones (row + local file + offsite). */
    private function pruneRetention(string $kind, string $target, int $retention): void
    {
        $old = Backup::where('kind', $kind)->where('target', $target)
            ->where('status', 'done')
            ->orderByDesc('id')
            ->skip(max($retention, 1))
            ->take(100)
            ->get();

        foreach ($old as $b) {
            if ($b->filename) {
                @unlink('/var/backups/convorocp/'.basename($b->filename));
                if ($b->offsite) {
                    try {
                        Offsite::delete($b->filename);
                    } catch (\Throwable) {
                    }
                }
            }
            $b->delete();
        }
    }
}
