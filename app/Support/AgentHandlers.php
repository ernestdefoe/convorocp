<?php

namespace App\Support;

/**
 * Maps an allowlisted operation to the work the root agent performs. Today every
 * handler is DRY-RUN: it returns a description of what it *would* do, touching
 * nothing. The real implementation (templated nginx/php/dns/systemd writes, certbot,
 * db drivers) lands per-op and only runs in live mode on a dedicated node.
 */
class AgentHandlers
{
    /** @return array<string,mixed> the operation result */
    public static function apply(string $op, array $args, bool $dryRun = true): array
    {
        if (! in_array($op, Agent::ALLOWED, true)) {
            throw new \InvalidArgumentException("Refusing unknown operation [{$op}].");
        }

        if (! $dryRun) {
            // Safety interlock: no live OS handlers are implemented yet, so live
            // mode is a hard stop rather than an accidental no-op.
            throw new \RuntimeException("Live handler for [{$op}] is not implemented yet.");
        }

        return [
            'dry_run' => true,
            'op' => $op,
            'would' => self::describe($op, $args),
        ];
    }

    private static function describe(string $op, array $args): string
    {
        $d = $args['domain'] ?? $args['name'] ?? ($args['id'] ?? '');

        return match (true) {
            $op === 'site.create' => "render nginx vhost + PHP-FPM pool for {$d}, reload nginx",
            $op === 'site.delete' => "remove vhost + pool + webroot for {$d}",
            $op === 'site.set_php_version' => "point {$d} FPM pool at PHP ".($args['php'] ?? '?').", reload",
            str_starts_with($op, 'cert.') => "ACME ".substr($op, 5)." certificate for {$d}",
            str_starts_with($op, 'db.') => "{$op} on the ".($args['engine'] ?? '?')." engine ({$d})",
            str_starts_with($op, 'dns.') => "write + reload the zone for {$d}",
            str_starts_with($op, 'cron.') => "write systemd timer for task {$d}",
            str_starts_with($op, 'daemon.') => "write/control systemd unit for daemon {$d}",
            str_starts_with($op, 'php.') => "manage PHP-FPM pool ({$op})",
            default => "apply {$op}",
        };
    }
}
