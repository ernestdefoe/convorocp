<?php

namespace App\Console\Commands;

use App\Models\AgentOperation;
use App\Support\AgentHandlers;
use Illuminate\Console\Command;

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

            if ($this->option('once')) {
                $this->info('Processed '.$ops->count().' operation(s).');

                return self::SUCCESS;
            }

            sleep((int) config('convorocp.agent.poll_seconds', 2));
        } while (true);
    }
}
