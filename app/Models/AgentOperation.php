<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentOperation extends Model
{
    protected $fillable = ['op', 'args', 'status', 'result'];

    protected $casts = ['args' => 'array', 'result' => 'array'];
}
