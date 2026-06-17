<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class TerminalController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);

        return Inertia::render('Terminal/Index');
    }

    /**
     * Sub-request target for nginx auth_request. Returns 204 only for an
     * authenticated operator so the ttyd proxy stays operator-only. Returns a
     * bare 401/403 (never a redirect) so auth_request can interpret it.
     */
    public function check(Request $request)
    {
        $user = $request->user();
        abort_if(! $user, 401);
        abort_unless($user->isOperator(), 403);

        return response()->noContent();
    }
}
