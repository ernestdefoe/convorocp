<?php

namespace App\Http\Controllers;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private function g2fa(): Google2FA
    {
        return new Google2FA;
    }

    // ---- Account page (manage own 2FA) ---------------------------------

    public function account(Request $request)
    {
        $user = $request->user();
        $pending = ! $user->hasTwoFactorEnabled() && ! empty($user->two_factor_secret);

        $qr = null;
        $secret = null;
        if ($pending) {
            $secret = $user->two_factor_secret;
            $url = $this->g2fa()->getQRCodeUrl(config('app.name', 'ConvoroCP'), $user->email, $secret);
            $qr = $this->qrDataUri($url);
        }

        return Inertia::render('Account/Index', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $pending,
            'qr' => $qr,
            'secret' => $secret,
            'recoveryCodes' => $request->session()->get('recovery_codes'),
        ]);
    }

    public function enable(Request $request)
    {
        $user = $request->user();
        abort_if($user->hasTwoFactorEnabled(), 422);

        $user->forceFill([
            'two_factor_secret' => $this->g2fa()->generateSecretKey(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    public function confirm(Request $request)
    {
        $user = $request->user();
        $request->validate(['code' => ['required', 'string']]);
        abort_if(empty($user->two_factor_secret), 422);

        if (! $this->g2fa()->verifyKey($user->two_factor_secret, preg_replace('/\s+/', '', $request->code))) {
            return back()->withErrors(['code' => 'That code is not valid. Try again.']);
        }

        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();

        return back()->with('recovery_codes', $codes);
    }

    public function disable(Request $request)
    {
        $user = $request->user();
        $request->validate(['password' => ['required']]);
        if (! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    // ---- Login challenge ------------------------------------------------

    public function challenge(Request $request)
    {
        if (! $request->session()->has('2fa.id')) {
            return redirect('/login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function challengeVerify(Request $request)
    {
        $id = $request->session()->get('2fa.id');
        if (! $id) {
            return redirect('/login');
        }
        $user = User::find($id);
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['2fa.id', '2fa.remember']);

            return redirect('/login');
        }

        $request->validate(['code' => ['nullable', 'string'], 'recovery_code' => ['nullable', 'string']]);
        $ok = false;

        if ($recovery = $request->input('recovery_code')) {
            $codes = $user->two_factor_recovery_codes ?? [];
            $recovery = Str::lower(trim($recovery));
            if (in_array($recovery, $codes, true)) {
                $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$recovery]))])->save();
                $ok = true;
            }
        } elseif ($code = $request->input('code')) {
            $ok = $this->g2fa()->verifyKey($user->two_factor_secret, preg_replace('/\s+/', '', $code));
        }

        if (! $ok) {
            return back()->withErrors(['code' => 'That code is not valid.']);
        }

        $remember = (bool) $request->session()->get('2fa.remember');
        $request->session()->forget(['2fa.id', '2fa.remember']);
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    private function qrDataUri(string $url): string
    {
        $renderer = new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
