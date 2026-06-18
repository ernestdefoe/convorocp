<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Stripe\StripeClient;

/**
 * Wraps Stripe configured with the OPERATOR's keys (stored in settings, secrets
 * encrypted). Lets a panel owner connect their own Stripe account to sell hosting
 * without any env/redeploy.
 */
class StripeGateway
{
    public static function secret(): ?string
    {
        return self::decrypt(Setting::get('stripe.secret'));
    }

    public static function publishable(): ?string
    {
        return Setting::get('stripe.publishable') ?: null;
    }

    public static function webhookSecret(): ?string
    {
        return self::decrypt(Setting::get('stripe.webhook_secret'));
    }

    public static function configured(): bool
    {
        return ! empty(self::secret());
    }

    public static function client(): ?StripeClient
    {
        $secret = self::secret();

        return $secret ? new StripeClient($secret) : null;
    }

    private static function decrypt(?string $enc): ?string
    {
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
