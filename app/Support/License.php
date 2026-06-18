<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * ConvoroCP licensing: a 30-day trial, then a license key validated daily
 * against the Convoro store. State lives in the Setting KV store:
 *
 *   license.installed_at    first boot (trial start)
 *   license.key             the operator-entered key
 *   license.state           last check result: active | invalid | unknown
 *   license.checked_at      when we last called the store
 *   license.last_ok_at      when validation last succeeded (drives the grace window)
 */
class License
{
    /** Trial start — set lazily on first read so existing installs start a trial now. */
    public static function installedAt(): Carbon
    {
        $ts = Setting::get('license.installed_at');
        if (! $ts) {
            $ts = now()->toIso8601String();
            Setting::set('license.installed_at', $ts);
        }

        return Carbon::parse($ts);
    }

    public static function trialEndsAt(): Carbon
    {
        return self::installedAt()->copy()->addDays((int) config('convorocp.license.trial_days', 30));
    }

    public static function inTrial(): bool
    {
        return now()->lt(self::trialEndsAt());
    }

    public static function trialDaysLeft(): int
    {
        return max(0, (int) ceil(now()->floatDiffInDays(self::trialEndsAt(), false)));
    }

    public static function key(): ?string
    {
        $k = Setting::get('license.key');

        return $k ? trim($k) : null;
    }

    /** True while validation succeeded recently enough to ride out a network blip. */
    private static function withinGrace(): bool
    {
        $ok = Setting::get('license.last_ok_at');
        if (! $ok) {
            return false;
        }

        return Carbon::parse($ok)->gt(now()->subDays((int) config('convorocp.license.grace_days', 7)));
    }

    /** Is a paid license currently valid (active at last check, or inside the grace window)? */
    public static function isLicensed(): bool
    {
        return Setting::get('license.state') === 'active' || self::withinGrace();
    }

    /** May the panel be used right now? (trial OR a valid license) */
    public static function isUnlocked(): bool
    {
        return self::inTrial() || self::isLicensed();
    }

    /** A summary for the License page. */
    public static function summary(): array
    {
        $key = self::key();

        return [
            'state' => self::isLicensed() ? 'licensed' : (self::inTrial() ? 'trial' : 'expired'),
            'licensed' => self::isLicensed(),
            'in_trial' => self::inTrial(),
            'trial_days_left' => self::trialDaysLeft(),
            'trial_ends_at' => self::trialEndsAt()->toDateString(),
            'has_key' => (bool) $key,
            'key_masked' => $key ? substr($key, 0, 9).str_repeat('•', max(0, strlen($key) - 9)) : null,
            'last_check' => Setting::get('license.checked_at'),
            'last_result' => Setting::get('license.state'),
        ];
    }

    /**
     * Validate the stored key against the Convoro store and cache the result.
     * Network/HTTP errors leave the prior state untouched (grace handles them).
     *
     * @return array{ok:bool, message:string}
     */
    public static function check(): array
    {
        $key = self::key();
        if (! $key) {
            Setting::set('license.state', 'invalid');
            Setting::set('license.checked_at', now()->toIso8601String());

            return ['ok' => false, 'message' => 'No license key on file.'];
        }

        $url = rtrim((string) config('convorocp.license.server'), '/').'/api/licenses/validate';
        try {
            $res = Http::timeout(15)->acceptJson()->asForm()->post($url, [
                'key' => $key,
                'package' => config('convorocp.license.package'),
            ]);
        } catch (\Throwable $e) {
            // Transient failure — keep the last known state; grace covers it.
            Setting::set('license.checked_at', now()->toIso8601String());

            return ['ok' => self::isLicensed(), 'message' => 'Could not reach the license server: '.$e->getMessage()];
        }

        Setting::set('license.checked_at', now()->toIso8601String());

        if ($res->successful() && $res->json('valid') === true) {
            Setting::set('license.state', 'active');
            Setting::set('license.last_ok_at', now()->toIso8601String());

            return ['ok' => true, 'message' => 'License is active.'];
        }

        // A definitive negative answer from the server (invalid/canceled key).
        if ($res->status() === 404 || $res->status() === 422 || $res->json('valid') === false) {
            Setting::set('license.state', 'invalid');

            return ['ok' => false, 'message' => (string) ($res->json('message') ?? 'License is invalid or inactive.')];
        }

        // 5xx / unexpected — don't flip state; let grace decide.
        return ['ok' => self::isLicensed(), 'message' => 'License server returned an unexpected response.'];
    }
}
