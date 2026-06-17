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
        'site.create', 'site.delete', 'site.set_php_version', 'site.set_php_settings',
        'site.deploy', 'vhost.render',
        'php.fpm.pool.write', 'php.fpm.reload', 'php.install', 'php.uninstall',
        'cert.issue', 'cert.renew',
        'db.create', 'db.drop', 'db.user.create', 'db.user.grant',
        'dns.zone.read', 'dns.zone.write', 'dns.reload',
        'cron.write', 'cron.delete', 'cron.run_now',
        'daemon.create', 'daemon.delete', 'daemon.start', 'daemon.stop', 'daemon.restart',
        'container.run', 'container.start', 'container.stop', 'container.remove',
        'backup.run', 'backup.delete',
        'service.control',
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
