<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailAccount extends Model
{
    protected $fillable = ['user_id', 'email', 'domain', 'secret', 'quota_mb', 'status'];

    protected $hidden = ['secret'];

    protected $casts = [
        'secret' => 'encrypted',
        'quota_mb' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
