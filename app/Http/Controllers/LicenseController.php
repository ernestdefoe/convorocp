<?php

namespace App\Http\Controllers;

use App\Support\License;
use App\Support\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LicenseController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('License/Index', [
            'license' => License::summary(),
            'isOperator' => $request->user()->isOperator(),
            'subscribeUrl' => rtrim((string) config('convorocp.license.server'), '/').'/convorocp#pricing',
        ]);
    }

    /** Save a license key and verify it immediately (operator only). */
    public function save(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);

        Setting::set('license.key', strtoupper(trim($data['key'])));
        $r = License::check();

        return back()->with($r['ok'] ? 'status' : 'error', $r['message']);
    }

    /** Re-run validation against the store now (operator only). */
    public function recheck(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);
        $r = License::check();

        return back()->with($r['ok'] ? 'status' : 'error', $r['message']);
    }
}
