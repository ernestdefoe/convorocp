<?php

namespace App\Http\Controllers;

use App\Models\ScheduledTask;
use App\Support\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchedulerController extends Controller
{
    private function scoped(Request $request)
    {
        return $request->user()->isOperator()
            ? ScheduledTask::query()
            : ScheduledTask::where('user_id', $request->user()->id);
    }

    private function authorizeTask(Request $request, ScheduledTask $task): void
    {
        abort_unless($request->user()->isOperator() || $task->user_id === $request->user()->id, 403);
    }

    public function index(Request $request)
    {
        $tasks = $this->scoped($request)->latest()->get()->map(fn (ScheduledTask $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'command' => $t->command,
            'cron' => $t->cron,
            'enabled' => $t->enabled,
            'last_status' => $t->last_status,
            'adopted' => $t->adopted,
        ]);

        return Inertia::render('Scheduler/Index', ['tasks' => $tasks]);
    }

    /**
     * Adopt an existing /etc/cron.d entry ConvoroCP didn't create (operator only).
     * Shown + runnable in the panel; the panel never rewrites the real cron file.
     */
    public function adopt(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'command' => ['required', 'string', 'max:500'],
            'cron' => ['required', 'string', 'max:120', 'regex:/^[\d\*\/,\-\s]+$/'],
            'cron_file' => ['required', 'string', 'max:255'],
        ]);

        ScheduledTask::create($data + ['user_id' => $request->user()->id, 'enabled' => true, 'adopted' => true]);

        return redirect('/scheduler');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'command' => ['required', 'string', 'max:500'],
            'cron' => ['required', 'string', 'max:120', 'regex:/^[\d\*\/,\-\s]+$/'],
        ]);

        $task = ScheduledTask::create($data + ['user_id' => $request->user()->id, 'enabled' => true]);
        Agent::dispatch('cron.write', ['id' => $task->id, 'command' => $task->command, 'cron' => $task->cron]);

        return redirect('/scheduler');
    }

    public function toggle(Request $request, ScheduledTask $task)
    {
        $this->authorizeTask($request, $task);
        // Adopted crons are managed by their own file — the panel doesn't rewrite them.
        abort_if($task->adopted, 422, 'This cron is adopted (managed externally) — enable/disable it at its source.');
        $task->update(['enabled' => ! $task->enabled]);
        Agent::dispatch('cron.write', ['id' => $task->id, 'enabled' => $task->enabled]);

        return back();
    }

    public function run(Request $request, ScheduledTask $task)
    {
        $this->authorizeTask($request, $task);
        Agent::dispatch('cron.run_now', ['id' => $task->id, 'command' => $task->command]);

        return back();
    }

    public function destroy(Request $request, ScheduledTask $task)
    {
        $this->authorizeTask($request, $task);
        // Adopted crons are only detached (forgotten) — the real cron file is left in place.
        if (! $task->adopted) {
            Agent::dispatch('cron.delete', ['id' => $task->id]);
        }
        $task->delete();

        return redirect('/scheduler');
    }
}
