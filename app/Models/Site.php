<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Site extends Model
{
    protected $fillable = [
        'user_id', 'domain', 'runtime', 'php_version', 'php_settings', 'status',
        'ssl_status', 'repo', 'branch', 'auto_deploy',
    ];

    protected $casts = ['auto_deploy' => 'boolean', 'php_settings' => 'array'];

    /** Functions an operator/client may toggle off for a site. */
    public const DISABLEABLE_FUNCTIONS = [
        'exec', 'passthru', 'shell_exec', 'system', 'proc_open',
        'popen', 'curl_multi_exec', 'show_source', 'symlink', 'dl',
    ];

    public static function defaultPhpSettings(): array
    {
        return [
            'memory_limit' => '256M',
            'upload_max_filesize' => '64M',
            'post_max_size' => '64M',
            'max_execution_time' => 30,
            'display_errors' => false,
            'disable_functions' => [],
        ];
    }

    /** Stored settings merged over the defaults. */
    public function phpSettings(): array
    {
        return array_merge(self::defaultPhpSettings(), $this->php_settings ?? []);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
