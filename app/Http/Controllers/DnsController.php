<?php

namespace App\Http\Controllers;

use App\Models\DnsRecord;
use App\Support\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DnsController extends Controller
{
    private function scoped(Request $request)
    {
        return $request->user()->isOperator()
            ? DnsRecord::query()
            : DnsRecord::where('user_id', $request->user()->id);
    }

    public function index(Request $request)
    {
        $records = $this->scoped($request)->orderBy('domain')->orderBy('type')->get()->map(fn (DnsRecord $r) => [
            'id' => $r->id,
            'domain' => $r->domain,
            'type' => $r->type,
            'name' => $r->name,
            'value' => $r->value,
            'ttl' => $r->ttl,
        ]);

        return Inertia::render('Dns/Index', [
            'records' => $records,
            'types' => ['A', 'AAAA', 'CNAME', 'MX', 'TXT'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:191'],
            'type' => ['required', 'in:A,AAAA,CNAME,MX,TXT'],
            'name' => ['required', 'string', 'max:191'],
            'value' => ['required', 'string', 'max:1000'],
            'ttl' => ['required', 'integer', 'min:60', 'max:86400'],
        ]);

        DnsRecord::create($data + ['user_id' => $request->user()->id]);
        Agent::dispatch('dns.zone.write', ['domain' => $data['domain']]);
        Agent::dispatch('dns.reload', ['domain' => $data['domain']]);

        return redirect('/dns');
    }

    public function destroy(Request $request, DnsRecord $record)
    {
        abort_unless($request->user()->isOperator() || $record->user_id === $request->user()->id, 403);
        $domain = $record->domain;
        $record->delete();
        Agent::dispatch('dns.zone.write', ['domain' => $domain]);
        Agent::dispatch('dns.reload', ['domain' => $domain]);

        return redirect('/dns');
    }
}
