<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'price_cents', 'sites_limit', 'db_limit',
        'email_limit', 'disk_mb', 'is_public', 'position',
    ];

    protected $casts = ['is_public' => 'boolean'];

    public function priceLabel(): string
    {
        return $this->price_cents === 0 ? 'Free' : '$'.number_format($this->price_cents / 100, 0).'/mo';
    }
}
