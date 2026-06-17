<?php

namespace App\Support;

use App\Models\AgentOperation;

/**
 * Client for the privileged agent (see docs/agent-protocol.md).
 *
 * The control plane never runs privileged commands — it enqueues an allowlisted
 * operation here as DESIRED state. The root agent daemon drains the queue and
 * converges the machine. Until that daemon ships, dispatch() simply records the
 * intent (status `pending`), which is exactly what the agent will consume.
 */
class Agent
{
    /** Operations the agent will accept. Anything else is rejected here. */
    public const ALLOWED = [
        'site.create', 'site.delete', 'site.set_php_version', 'vhost.render',
        'php.fpm.pool.write', 'php.fpm.reload', 'cert.issue', 'cert.renew',
        'db.create', 'db.user.create', 'cron.write', 'daemon.create',
        'daemon.start', 'daemon.stop', 'daemon.restart',
    ];

    public static function dispatch(string $op, array $args): AgentOperation
    {
        if (! in_array($op, self::ALLOWED, true)) {
            throw new \InvalidArgumentException("Operation [{$op}] is not allowlisted.");
        }

        return AgentOperation::create([
            'op' => $op,
            'args' => $args,
            'status' => 'pending',
        ]);
    }
}
