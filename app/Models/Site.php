<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Site extends Model
{
    protected $fillable = [
        'user_id', 'domain', 'runtime', 'php_version', 'status',
        'ssl_status', 'repo', 'branch', 'auto_deploy',
    ];

    protected $casts = ['auto_deploy' => 'boolean'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
