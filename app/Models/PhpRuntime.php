<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhpRuntime extends Model
{
    protected $fillable = ['version', 'status'];

    public function isInstalled(): bool
    {
        return $this->status === 'installed';
    }

    /** Installed versions, newest first — the set sites can choose from. */
    public static function installed(): array
    {
        return static::where('status', 'installed')->orderByDesc('version')->pluck('version')->all();
    }
}
