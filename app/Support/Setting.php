<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Tiny persistent key/value settings store. */
class Setting
{
    public static function get(string $key, $default = null)
    {
        $v = DB::table('settings')->where('key', $key)->value('value');

        return $v === null ? $default : json_decode($v, true);
    }

    public static function set(string $key, $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
