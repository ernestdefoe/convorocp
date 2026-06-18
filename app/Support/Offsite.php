<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Optional offsite backup storage on any S3-compatible bucket (AWS, Backblaze,
 * Wasabi, MinIO…). The operator drops in credentials; backups are streamed up
 * after they're created. No keys → offsite is simply skipped.
 */
class Offsite
{
    private const PREFIX = 'convorocp/';

    public static function configured(): bool
    {
        return Setting::get('backup.s3.bucket') && Setting::get('backup.s3.key') && self::secret();
    }

    public static function disk(): ?Filesystem
    {
        if (! self::configured()) {
            return null;
        }
        $cfg = [
            'driver' => 's3',
            'key' => Setting::get('backup.s3.key'),
            'secret' => self::secret(),
            'region' => Setting::get('backup.s3.region') ?: 'us-east-1',
            'bucket' => Setting::get('backup.s3.bucket'),
            'throw' => true,
        ];
        if ($ep = Setting::get('backup.s3.endpoint')) {
            $cfg['endpoint'] = $ep;
            $cfg['use_path_style_endpoint'] = true;
        }

        return Storage::build($cfg);
    }

    /** Stream a local backup file up to the bucket. */
    public static function put(string $localPath): bool
    {
        $disk = self::disk();
        if (! $disk || ! is_file($localPath)) {
            return false;
        }
        $stream = fopen($localPath, 'r');
        $disk->put(self::PREFIX.basename($localPath), $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return true;
    }

    public static function delete(string $filename): void
    {
        $disk = self::disk();
        $disk?->delete(self::PREFIX.basename($filename));
    }

    private static function secret(): ?string
    {
        $enc = Setting::get('backup.s3.secret');
        if (! $enc) {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
    }
}
