<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin read-only client for the local Beszel monitoring hub. Lets ConvoroCP
 * render live, history-backed node metrics server-side (e.g. the Overview
 * node-health panel) without anyone signing into the Beszel UI. Every call is
 * best-effort: any failure returns null so callers fall back to the local
 * /proc snapshot.
 */
class Beszel
{
    protected function base(): ?string
    {
        return rtrim((string) config('convorocp.monitoring.api_url'), '/') ?: null;
    }

    protected function token(): ?string
    {
        $base = $this->base();
        $email = config('convorocp.monitoring.api_email');
        $pass = config('convorocp.monitoring.api_password');
        if (! $base || ! $email || ! $pass) {
            return null;
        }

        return Cache::remember('beszel.token', 600, function () use ($base, $email, $pass) {
            try {
                $r = Http::timeout(4)->post("$base/api/collections/_superusers/auth-with-password", [
                    'identity' => $email,
                    'password' => $pass,
                ]);

                return $r->ok() ? $r->json('token') : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * The primary system's live metrics in NodeInfo's `metrics` shape (so it can
     * be overlaid onto the existing panel), or null when the hub is unreachable.
     */
    public function metrics(): ?array
    {
        $base = $this->base();
        $token = $this->token();
        if (! $base || ! $token) {
            return null;
        }

        try {
            $headers = ['Authorization' => $token];
            $sys = Http::timeout(4)->withHeaders($headers)
                ->get("$base/api/collections/systems/records", ['perPage' => 1, 'sort' => 'updated'])
                ->json('items.0');
            if (! $sys) {
                return null;
            }

            $info = $sys['info'] ?? [];
            $stats = Http::timeout(4)->withHeaders($headers)
                ->get("$base/api/collections/system_stats/records", [
                    'perPage' => 1,
                    'sort' => '-created',
                    'filter' => "system='".$sys['id']."'",
                ])->json('items.0.stats') ?? [];

            $la = $stats['la'] ?? ($info['la'] ?? [0, 0, 0]);
            $memTotalGb = (float) ($stats['m'] ?? 0);
            $memUsedGb = (float) ($stats['mu'] ?? 0);

            return [
                'status' => $sys['status'] ?? 'unknown',
                'uptime' => $this->humanUptime((int) ($info['u'] ?? 0)),
                'load' => array_map(fn ($x) => round((float) $x, 2), array_slice((array) $la, 0, 3)),
                'cpu' => [
                    'pct' => round((float) ($stats['cpu'] ?? $info['cpu'] ?? 0), 1),
                    'load' => round((float) ($la[0] ?? 0), 2),
                ],
                'memory' => [
                    'pct' => round((float) ($stats['mp'] ?? $info['mp'] ?? 0), 1),
                    'used_mb' => (int) round($memUsedGb * 1024),
                    'total_mb' => (int) round($memTotalGb * 1024),
                ],
                'disk' => [
                    'pct' => round((float) ($stats['dp'] ?? $info['dp'] ?? 0), 1),
                    'used_gb' => round((float) ($stats['du'] ?? 0), 1),
                    'total_gb' => round((float) ($stats['d'] ?? 0), 1),
                ],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function humanUptime(int $s): string
    {
        if ($s <= 0) {
            return '—';
        }
        $d = intdiv($s, 86400);
        $h = intdiv($s % 86400, 3600);
        $m = intdiv($s % 3600, 60);
        if ($d > 0) {
            return $d.'d '.$h.'h';
        }
        if ($h > 0) {
            return $h.'h '.$m.'m';
        }

        return $m.'m';
    }
}
