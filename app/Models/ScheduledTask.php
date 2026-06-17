<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTask extends Model
{
    protected $fillable = ['user_id', 'name', 'command', 'cron', 'enabled', 'last_status'];

    protected $casts = ['enabled' => 'boolean'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
