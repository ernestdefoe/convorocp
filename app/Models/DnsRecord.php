<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnsRecord extends Model
{
    protected $fillable = ['user_id', 'domain', 'type', 'name', 'value', 'ttl'];

    protected $casts = ['ttl' => 'integer'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
