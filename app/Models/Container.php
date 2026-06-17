<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Container extends Model
{
    protected $fillable = [
        'user_id', 'name', 'image', 'container_port', 'host_port',
        'domain', 'restart_policy', 'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
