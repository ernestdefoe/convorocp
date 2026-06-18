<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketController extends Controller
{
    private function authorize(Request $request, Ticket $ticket): void
    {
        abort_unless($request->user()->isOperator() || $ticket->user_id === $request->user()->id, 403);
    }

    public function index(Request $request)
    {
        $query = $request->user()->isOperator()
            ? Ticket::query()->with('owner')
            : Ticket::where('user_id', $request->user()->id);

        $tickets = $query->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('last_reply_at')->orderByDesc('id')->get()
            ->map(fn (Ticket $t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'status' => $t->status,
                'priority' => $t->priority,
                'who' => $request->user()->isOperator() ? $t->owner?->name : null,
                'updated' => ($t->last_reply_at ?? $t->created_at)?->diffForHumans(),
            ]);

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'isOperator' => $request->user()->isOperator(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'priority' => ['required', 'in:low,normal,high'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'priority' => $data['priority'],
            'status' => 'open',
            'last_reply_at' => now(),
        ]);
        $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return redirect('/tickets/'.$ticket->id);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorize($request, $ticket);

        return Inertia::render('Tickets/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'who' => $ticket->owner?->name,
            ],
            'messages' => $ticket->messages()->with('author')->oldest()->get()->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'author' => $m->author?->name ?? 'User',
                'staff' => $m->author?->isOperator() ?? false,
                'mine' => $m->user_id === $request->user()->id,
                'at' => $m->created_at?->diffForHumans(),
            ]),
            'isOperator' => $request->user()->isOperator(),
        ]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $this->authorize($request, $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        // Operator reply → awaiting customer; customer reply (or reopen) → needs staff.
        $ticket->update([
            'status' => $request->user()->isOperator() ? 'pending' : 'open',
            'last_reply_at' => now(),
        ]);

        return back();
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $this->authorize($request, $ticket);
        $data = $request->validate(['status' => ['required', 'in:open,pending,closed']]);
        // Clients may only close (or reopen) their own ticket; operators set any status.
        abort_unless($request->user()->isOperator() || in_array($data['status'], ['open', 'closed'], true), 403);
        $ticket->update(['status' => $data['status']]);

        return back();
    }
}
