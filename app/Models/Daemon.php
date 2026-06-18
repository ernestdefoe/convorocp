<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Daemon extends Model
{
    protected $fillable = ['user_id', 'name', 'command', 'status', 'autostart', 'restart_policy', 'adopted', 'unit'];

    protected $casts = ['autostart' => 'boolean', 'adopted' => 'boolean'];

    /** systemd unit this row controls — the real one when adopted, else the cp-managed one. */
    public function unitName(): string
    {
        return $this->unit ?: "cp-daemon-{$this->id}.service";
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
