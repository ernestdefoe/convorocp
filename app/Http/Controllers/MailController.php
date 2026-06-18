<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Support\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Webklex\PHPIMAP\ClientManager;

class MailController extends Controller
{
    private function scoped(Request $request)
    {
        return $request->user()->isOperator()
            ? MailAccount::query()
            : MailAccount::where('user_id', $request->user()->id);
    }

    private function client(MailAccount $account)
    {
        $cm = new ClientManager;

        $client = $cm->make([
            'host' => '127.0.0.1',
            'port' => 143,
            'encryption' => false,
            'validate_cert' => false,
            'username' => $account->email,
            'password' => $account->secret,
            'protocol' => 'imap',
            'authentication' => null,
        ]);
        $client->connect();

        return $client;
    }

    public function index(Request $request)
    {
        $accounts = $this->scoped($request)->orderBy('email')->get(['id', 'email', 'domain', 'status']);

        $selected = $request->integer('account') ?: $accounts->first()?->id;
        $folderName = in_array($request->query('folder'), ['INBOX', 'Sent', 'Drafts', 'Trash'], true) ? $request->query('folder') : 'INBOX';
        // Re-fetch the full model (with the encrypted secret) for server-side
        // IMAP; the $accounts list deliberately omits it so it never reaches the client.
        $account = $selected ? $this->scoped($request)->find($selected) : null;

        $messages = [];
        $open = null;
        $error = null;

        if ($account && $account->status === 'active') {
            try {
                $client = $this->client($account);
                $folder = $client->getFolder($folderName);
                $query = $folder->query()->all()->limit(40)->setFetchOrder('desc');

                if ($uid = $request->integer('uid')) {
                    $msg = $folder->query()->getMessageByUid($uid);
                    if ($msg) {
                        $msg->setFlag('Seen');
                        $open = [
                            'uid' => (int) $uid,
                            'subject' => (string) $msg->getSubject(),
                            'from' => $this->addr($msg->getFrom()),
                            'to' => $this->addr($msg->getTo()),
                            'date' => optional($msg->getDate()?->first())->toDayDateTimeString(),
                            'html' => $msg->hasHTMLBody() ? $msg->getHTMLBody() : null,
                            'text' => $msg->getTextBody(),
                        ];
                    }
                }

                foreach ($query->get() as $m) {
                    $messages[] = [
                        'uid' => (int) $m->getUid(),
                        'subject' => (string) $m->getSubject() ?: '(no subject)',
                        'from' => $this->addr($m->getFrom()),
                        'date' => optional($m->getDate()?->first())->diffForHumans(),
                        'seen' => $m->getFlags()->has('Seen'),
                    ];
                }
                $client->disconnect();
            } catch (\Throwable $e) {
                $error = 'Could not connect to the mailbox.';
                report($e);
            }
        }

        return Inertia::render('Mail/Index', [
            'accounts' => $accounts,
            'selected' => $account?->id,
            'folder' => $folderName,
            'folders' => ['INBOX', 'Sent', 'Drafts', 'Trash'],
            'messages' => $messages,
            'open' => $open,
            'error' => $error,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'local' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._%+-]+$/i'],
            'domain' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'password' => ['required', 'string', 'min:6', 'max:120', 'regex:/^[^:\s]+$/'],
        ]);
        $email = strtolower($data['local'].'@'.$data['domain']);
        abort_if(MailAccount::where('email', $email)->exists(), 422, 'Mailbox already exists.');

        $account = MailAccount::create([
            'user_id' => $request->user()->id,
            'email' => $email,
            'domain' => strtolower($data['domain']),
            'secret' => $data['password'],
            'status' => 'pending',
        ]);

        Agent::dispatch('mail.account_create', [
            'account_id' => $account->id,
            'email' => $email,
            'password' => $data['password'],
        ]);

        return redirect('/mail');
    }

    public function destroy(Request $request, MailAccount $account)
    {
        abort_unless($request->user()->isOperator() || $account->user_id === $request->user()->id, 403);
        Agent::dispatch('mail.account_delete', ['email' => $account->email]);
        $account->delete();

        return redirect('/mail');
    }

    public function send(Request $request, MailAccount $account)
    {
        abort_unless($request->user()->isOperator() || $account->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        // Loopback relay to Postfix — no auth, and disable opportunistic STARTTLS
        // (Postfix's self-signed cert CN won't match 127.0.0.1).
        $transport = new EsmtpTransport('127.0.0.1', 25);
        $transport->setAutoTls(false);
        $mailer = new Mailer($transport);
        $email = (new Email)
            ->from($account->email)
            ->to($data['to'])
            ->subject($data['subject'] ?? '(no subject)')
            ->text($data['body']);
        $mailer->send($email);

        return back();
    }

    private function addr($collection): string
    {
        $a = is_iterable($collection) ? collect($collection)->first() : null;
        if (! $a) {
            return '';
        }

        return trim(($a->personal ?: '').' <'.$a->mail.'>');
    }
}
