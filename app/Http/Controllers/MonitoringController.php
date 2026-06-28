<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class MonitoringController extends Controller
{
    /**
     * Operator-only. Embeds the self-hosted Beszel monitoring dashboard, which
     * runs on its own operator-gated TLS vhost (nginx auth_request → /terminal/check).
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);

        $cfg = config('convorocp.monitoring');
        $url = $cfg['url'] ?: ('https://'.$request->getHost().':'.$cfg['port']);

        return Inertia::render('Monitoring/Index', [
            'enabled' => (bool) $cfg['enabled'],
            'url' => $url,
        ]);
    }
}
