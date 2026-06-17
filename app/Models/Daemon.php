<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Daemon extends Model
{
    protected $fillable = ['user_id', 'name', 'command', 'status', 'autostart', 'restart_policy'];

    protected $casts = ['autostart' => 'boolean'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
