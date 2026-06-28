<?php

namespace App\Console\Commands;

use App\Models\AgentOperation;
use App\Support\AgentHandlers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * The ConvoroCP agent worker. Drains the operation queue and applies each op via
 * AgentHandlers. DRY-RUN by default (see config/convorocp.php) — records intent
 * without touching the OS. In production this runs as the privileged agent on a
 * dedicated node; here it's a safe, local loop.
 */
class AgentRun extends Command
{
    protected $signature = 'agent:run {--once : Drain the queue once and exit}';

    protected $description = 'Drain the ConvoroCP agent operation queue';

    public function handle(): int
    {
        $dry = (bool) config('convorocp.agent.dry_run', true);
        $this->info('ConvoroCP agent — '.($dry ? 'DRY RUN (no OS changes)' : 'LIVE').' mode');

        do {
            $ops = AgentOperation::where('status', 'pending')->orderBy('id')->limit(25)->get();

            foreach ($ops as $op) {
                $op->update(['status' => 'running']);
                try {
                    $result = AgentHandlers::apply($op->op, $op->args, $dry);
                    $op->update(['status' => 'done', 'result' => $result]);
                    $this->line("  <fg=green>✓</> {$op->op}");
                } catch (\Throwable $e) {
                    $op->update(['status' => 'error', 'result' => ['error' => $e->getMessage()]]);
                    $this->line("  <fg=red>✗</> {$op->op} — {$e->getMessage()}");
                }
            }

            $this->snapshotDocker();

            if ($this->option('once')) {
                $this->info('Processed '.$ops->count().' operation(s).');

                return self::SUCCESS;
            }

            sleep((int) config('convorocp.agent.poll_seconds', 2));
        } while (true);
    }

    private function snapshotDocker(): void
    {
        try {
            $r = Process::timeout(10)->run("docker ps -a --format '{{json .}}'");
            if ($r->successful()) {
                $p = storage_path('app/docker-ps.json');
                file_put_contents($p, $r->output());
                @chmod($p, 0644);
            }
        } catch (\Throwable) {
        }

        $this->snapshotCounts();
    }

    /** Throttled real resource counts (DBs/containers/mailboxes) for the Overview. */
    private function snapshotCounts(): void
    {
        try {
            $f = storage_path('app/node-counts.json');
            if (is_file($f) && (time() - (int) filemtime($f)) < 30) { return; }

            $containers = 0;
            $out = trim(Process::timeout(10)->run(['docker', 'ps', '-aq'])->output());
            if ($out !== '') { $containers = count(explode("
", $out)); }

            $databases = 0;
            $db = trim(Process::timeout(10)->run(['mysql', '-N', '-e', "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE schema_name NOT IN ('information_schema','mysql','performance_schema','sys')"])->output());
            if (is_numeric($db)) { $databases = (int) $db; }

            $mailboxes = 0;
            foreach (['/etc/dovecot/users', '/etc/dovecot/passwd'] as $uf) {
                if (is_file($uf)) {
                    foreach (file($uf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ln) {
                        if ($ln !== '' && $ln[0] !== '#') { $mailboxes++; }
                    }
                    break;
                }
            }

            file_put_contents($f, json_encode(compact('databases', 'containers', 'mailboxes')));
            @chmod($f, 0644);
        } catch (\Throwable) {
        }
    }
}
