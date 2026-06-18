<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppInstall extends Model
{
    protected $fillable = ['user_id', 'domain', 'app', 'status', 'info'];
}
