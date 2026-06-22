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
    private const PER_PAGE = 30;

    private function scoped(Request $request)
    {
        return $request->user()->isOperator()
            ? MailAccount::query()
            : MailAccount::where('user_id', $request->user()->id);
    }

    /** The mailbox the viewer is allowed to READ — owner-only (privacy). */
    private function readable(Request $request, ?int $id): ?MailAccount
    {
        if (! $id) {
            return null;
        }
        $account = MailAccount::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        return $account && $account->status === 'active' ? $account : null;
    }

    private function client(MailAccount $account)
    {
        $client = (new ClientManager)->make([
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
        $me = (int) $request->user()->id;
        $isOperator = $request->user()->isOperator();

        $accounts = $this->scoped($request)->with('user:id,name')->orderBy('email')
            ->get(['id', 'email', 'domain', 'status', 'user_id'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'email' => $a->email,
                'domain' => $a->domain,
                'status' => $a->status,
                'mine' => (int) $a->user_id === $me,
                'owner' => $isOperator ? $a->user?->name : null,
            ])->values();

        $selected = $request->integer('account')
            ?: ($accounts->firstWhere('mine', true)['id'] ?? $accounts->first()['id'] ?? null);

        // PRIVACY: only the mailbox owner may read its contents.
        $account = $this->readable($request, $selected);
        $canRead = (bool) $account;

        $folders = [];
        $messages = [];
        $open = null;
        $error = null;
        $page = max(1, $request->integer('page') ?: 1);
        $hasMore = false;
        $search = trim((string) $request->query('q', ''));
        $folderName = (string) ($request->query('folder') ?: 'INBOX');

        if ($account) {
            try {
                $client = $this->client($account);
                $folders = $this->listFolders($client);
                // Fall back to INBOX if the requested folder doesn't exist.
                if (! collect($folders)->firstWhere('path', $folderName)) {
                    $folderName = 'INBOX';
                }
                $folder = $client->getFolderByPath($folderName) ?? $client->getFolder('INBOX');

                $q = $folder->query()->setFetchOrder('desc');
                $search !== '' ? $q->text($search) : $q->all();
                $list = $q->limit(self::PER_PAGE, $page)->get();
                $hasMore = $list->count() >= self::PER_PAGE;

                foreach ($list as $m) {
                    $messages[] = [
                        'uid' => (int) $m->getUid(),
                        'subject' => (string) $m->getSubject() ?: '(no subject)',
                        'from' => $this->addr($m->getFrom()),
                        'date' => optional($m->getDate()?->first())->diffForHumans(),
                        'seen' => $m->getFlags()->has('Seen'),
                        'attachments' => $m->getAttachments()->count(),
                    ];
                }

                if ($uid = $request->integer('uid')) {
                    $msg = $folder->query()->getMessageByUid($uid);
                    if ($msg) {
                        $msg->setFlag('Seen');
                        $open = [
                            'uid' => (int) $uid,
                            'folder' => $folderName,
                            'subject' => (string) $msg->getSubject(),
                            'from' => $this->addr($msg->getFrom()),
                            'fromRaw' => $this->rawAddr($msg->getReplyTo() ?: $msg->getFrom()),
                            'to' => $this->addr($msg->getTo()),
                            'cc' => $this->addr($msg->getCc()),
                            'date' => optional($msg->getDate()?->first())->toDayDateTimeString(),
                            'messageId' => (string) $msg->getMessageId(),
                            'html' => $msg->hasHTMLBody() ? $msg->getHTMLBody() : null,
                            'text' => $msg->getTextBody(),
                            'attachments' => $msg->getAttachments()->values()->map(fn ($att, $i) => [
                                'index' => $i,
                                'name' => $att->getName() ?: 'attachment-'.$i,
                                'size' => $att->getSize(),
                                'mime' => $att->getMimeType(),
                            ])->all(),
                        ];
                    }
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
            'canRead' => $canRead,
            'folder' => $folderName,
            'folders' => $folders,
            'messages' => $messages,
            'open' => $open,
            'error' => $error,
            'search' => $search,
            'page' => $page,
            'hasMore' => $hasMore,
        ]);
    }

    /** All folders with their unread counts, for the sidebar. */
    private function listFolders($client): array
    {
        $out = [];
        foreach ($client->getFolders(false) as $folder) {
            $unread = 0;
            try {
                $status = $folder->examine();
                $unread = (int) ($status['unseen'] ?? 0);
            } catch (\Throwable) {
            }
            $path = $folder->path ?? $folder->name;
            $out[] = [
                'path' => $path,
                'name' => $this->folderLabel($path),
                'unread' => $unread,
            ];
        }

        // Sort the well-known folders first, then the rest alphabetically.
        $rank = ['INBOX' => 0, 'Drafts' => 1, 'Sent' => 2, 'Junk' => 3, 'Trash' => 4];
        usort($out, fn ($a, $b) => [$rank[$a['path']] ?? 9, $a['name']] <=> [$rank[$b['path']] ?? 9, $b['name']]);

        return $out;
    }

    private function folderLabel(string $path): string
    {
        if (strtoupper($path) === 'INBOX') {
            return 'Inbox';
        }
        $parts = preg_split('/[\/.]/', $path);

        return ucfirst((string) end($parts));
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
        // Owner-only — never let an operator send as someone else's mailbox.
        abort_unless((int) $account->user_id === (int) $request->user()->id, 403);
        $data = $request->validate([
            'to' => ['required', 'string', 'max:2000'],
            'cc' => ['nullable', 'string', 'max:2000'],
            'bcc' => ['nullable', 'string', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'html' => ['nullable', 'boolean'],
            'in_reply_to' => ['nullable', 'string', 'max:512'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:25600'], // 25 MB each
        ]);

        $transport = new EsmtpTransport('127.0.0.1', 25);
        $transport->setAutoTls(false);
        $mailer = new Mailer($transport);

        $email = (new Email)
            ->from($account->email)
            ->subject($data['subject'] ?? '(no subject)');

        foreach ($this->parseAddrs($data['to']) as $addr) {
            $email->addTo($addr);
        }
        foreach ($this->parseAddrs($data['cc'] ?? '') as $addr) {
            $email->addCc($addr);
        }
        foreach ($this->parseAddrs($data['bcc'] ?? '') as $addr) {
            $email->addBcc($addr);
        }
        abort_if(empty($email->getTo()), 422, 'At least one valid recipient is required.');

        $request->boolean('html')
            ? $email->html($data['body'])->text(strip_tags($data['body']))
            : $email->text($data['body']);

        if (! empty($data['in_reply_to'])) {
            $email->getHeaders()->addTextHeader('In-Reply-To', $data['in_reply_to']);
            $email->getHeaders()->addTextHeader('References', $data['in_reply_to']);
        }

        foreach ((array) $request->file('attachments', []) as $file) {
            $email->attachFromPath($file->getRealPath(), $file->getClientOriginalName(), $file->getClientMimeType());
        }

        $mailer->send($email);

        return back();
    }

    /** Stream one attachment from a message (owner-only). */
    public function attachment(Request $request, MailAccount $account)
    {
        abort_unless((int) $account->user_id === (int) $request->user()->id, 403);
        $folderName = (string) ($request->query('folder') ?: 'INBOX');
        $uid = $request->integer('uid');
        $index = $request->integer('index');

        try {
            $client = $this->client($account);
            $folder = $client->getFolderByPath($folderName) ?? $client->getFolder('INBOX');
            $msg = $folder->query()->getMessageByUid($uid);
            $att = $msg?->getAttachments()->values()->get($index);
            abort_unless($att, 404);

            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $att->getName() ?: 'attachment') ?: 'attachment';
            $content = $att->getContent();
            $client->disconnect();

            return response($content, 200, [
                'Content-Type' => $att->getMimeType() ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.$name.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $e) {
            report($e);
            abort(404);
        }
    }

    /** Mark seen/unseen, delete (→ Trash), or move a message (owner-only). */
    public function message(Request $request, MailAccount $account)
    {
        abort_unless((int) $account->user_id === (int) $request->user()->id, 403);
        $data = $request->validate([
            'uid' => ['required', 'integer'],
            'folder' => ['required', 'string', 'max:191'],
            'action' => ['required', 'in:seen,unseen,delete,move'],
            'target' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $client = $this->client($account);
            $folder = $client->getFolderByPath($data['folder']) ?? $client->getFolder('INBOX');
            $msg = $folder->query()->getMessageByUid($data['uid']);
            if ($msg) {
                match ($data['action']) {
                    'seen' => $msg->setFlag('Seen'),
                    'unseen' => $msg->unsetFlag('Seen'),
                    'delete' => strtoupper($data['folder']) === 'TRASH' ? $msg->delete() : $msg->move('Trash'),
                    'move' => $data['target'] ? $msg->move($data['target']) : null,
                };
            }
            $client->disconnect();
        } catch (\Throwable $e) {
            report($e);
        }

        return back();
    }

    /** @return string[] */
    private function parseAddrs(string $raw): array
    {
        return collect(preg_split('/[,;\s]+/', $raw))
            ->map(fn ($a) => trim($a))
            ->filter(fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))
            ->unique()->values()->all();
    }

    private function addr($collection): string
    {
        $a = is_iterable($collection) ? collect($collection)->first() : null;
        if (! $a) {
            return '';
        }

        return trim(($a->personal ?: '').' <'.$a->mail.'>');
    }

    private function rawAddr($collection): string
    {
        $a = is_iterable($collection) ? collect($collection)->first() : null;

        return $a ? (string) $a->mail : '';
    }
}
