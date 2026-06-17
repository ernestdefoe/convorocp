<?php

namespace App\Support;

/**
 * Live node metrics, read directly from the OS (the panel is co-located with the
 * node). CPU is load-average based; memory from /proc/meminfo; disk from the root
 * filesystem. Cached briefly so dashboard refreshes don't hammer the FS.
 */
class ServerMetrics
{
    public static function snapshot(): array
    {
        return cache()->remember('cp.metrics', 5, fn () => [
            'cpu' => self::cpu(),
            'memory' => self::memory(),
            'disk' => self::disk(),
            'uptime' => self::uptime(),
        ]);
    }

    private static function cores(): int
    {
        $n = @substr_count((string) @file_get_contents('/proc/cpuinfo'), 'processor');

        return max(1, $n);
    }

    private static function cpu(): array
    {
        $load = function_exists('sys_getloadavg') ? (sys_getloadavg()[0] ?? 0) : 0;
        $cores = self::cores();

        return ['load' => round($load, 2), 'cores' => $cores, 'pct' => min(100, (int) round($load / $cores * 100))];
    }

    private static function memory(): array
    {
        $info = [];
        foreach (explode("\n", (string) @file_get_contents('/proc/meminfo')) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $info[$m[1]] = (int) $m[2]; // kB
            }
        }
        $total = ($info['MemTotal'] ?? 0) / 1024;
        $avail = ($info['MemAvailable'] ?? 0) / 1024;
        $used = max(0, $total - $avail);

        return [
            'used_mb' => (int) round($used),
            'total_mb' => (int) round($total),
            'pct' => $total > 0 ? (int) round($used / $total * 100) : 0,
        ];
    }

    private static function disk(): array
    {
        $total = (float) @disk_total_space('/');
        $free = (float) @disk_free_space('/');
        $used = max(0, $total - $free);

        return [
            'used_gb' => round($used / 1073741824, 1),
            'total_gb' => round($total / 1073741824, 1),
            'pct' => $total > 0 ? (int) round($used / $total * 100) : 0,
        ];
    }

    private static function uptime(): string
    {
        $secs = (int) (float) strtok((string) @file_get_contents('/proc/uptime'), ' ');
        $d = intdiv($secs, 86400);
        $h = intdiv($secs % 86400, 3600);

        return $d > 0 ? "{$d}d {$h}h" : "{$h}h";
    }
}
