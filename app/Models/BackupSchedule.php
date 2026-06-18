<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSchedule extends Model
{
    protected $fillable = ['user_id', 'kind', 'target', 'engine', 'frequency', 'retention', 'enabled', 'last_run_at'];

    protected $casts = [
        'enabled' => 'boolean',
        'retention' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function isDue(): bool
    {
        if (! $this->enabled) {
            return false;
        }
        if (! $this->last_run_at) {
            return true;
        }

        return $this->frequency === 'weekly'
            ? $this->last_run_at->lt(now()->subWeek())
            : $this->last_run_at->lt(now()->subDay());
    }
}
