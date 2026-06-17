<?php

namespace App\Http\Controllers;

use App\Models\PhpRuntime;
use App\Support\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhpController extends Controller
{
    private function ensureOperator(Request $request): void
    {
        abort_unless($request->user()->isOperator(), 403);
    }

    public function index(Request $request)
    {
        $this->ensureOperator($request);

        return Inertia::render('Php/Index', [
            'runtimes' => PhpRuntime::orderByDesc('version')->get(['id', 'version', 'status']),
        ]);
    }

    public function install(Request $request, PhpRuntime $runtime)
    {
        $this->ensureOperator($request);
        if ($runtime->status === 'available') {
            $runtime->update(['status' => 'installing']);
            Agent::dispatch('php.install', ['version' => $runtime->version]);
        }

        return back();
    }

    public function uninstall(Request $request, PhpRuntime $runtime)
    {
        $this->ensureOperator($request);
        if ($runtime->status === 'installed') {
            $runtime->update(['status' => 'removing']);
            Agent::dispatch('php.uninstall', ['version' => $runtime->version]);
        }

        return back();
    }
}
