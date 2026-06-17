# ConvoroCP privileged-agent protocol (draft)

The control plane (the Laravel/Inertia app) runs as the unprivileged `convorocp`
user and **never** executes privileged operations directly. All changes to the
machine go through the **agent** — a small root daemon with a deliberately tiny,
allowlisted surface. This document is the contract between them.

## Why this shape

cPanel/Plesk run enormous privileged surfaces and a web UI close to root. ConvoroCP
inverts that: the web tier can only ask the agent to perform a **fixed set of
named operations**, each with a validated argument schema and a server-side
template. There is no `exec(arbitrary string)`. A fully compromised web tier can
do nothing the agent's operation set doesn't already allow.

## Transport & auth

- **Transport:** a Unix domain socket at `/run/convorocp/agent.sock`, owned
  `root:convorocp`, mode `0660`. No network listener.
- **Auth:** every request carries `ts`, a random `nonce`, and an HMAC-SHA256
  `sig` over the canonical body using a shared key from `/etc/convorocp/agent.key`
  (root-only, readable by the web user via a setgid helper). The agent rejects
  stale (`|now - ts| > 30s`) or replayed (seen-nonce) requests.
- **Audit:** every accepted op is appended to `/var/log/convorocp/agent.audit`
  with actor, op, args digest, result.

## Message format

Request (one JSON object per frame):

```json
{ "id": "req_01H…", "op": "site.create", "args": { "domain": "acme.test", "php": "8.3" },
  "ts": 1718600000, "nonce": "…", "sig": "…" }
```

Response:

```json
{ "id": "req_01H…", "ok": true, "result": { "site_id": 42 }, "events": [] }
```

Errors are structured, never raw stderr leaked to clients:

```json
{ "id": "req_01H…", "ok": false, "error": { "code": "validation", "message": "domain already exists" } }
```

Long-running / streaming ops (log tails, the terminal PTY) keep the frame open
and emit `event` frames (`{ "id", "event": "stdout", "data": "…" }`) until a
terminal `done` frame.

## Operation allowlist (v1 surface)

Every op has a fixed JSON-schema for `args`; the agent renders from templates in
`/etc/convorocp/templates`. Unknown ops are rejected.

| Domain | Operations |
|--------|-----------|
| sites | `site.create`, `site.delete`, `site.set_php_version`, `vhost.render` |
| runtimes | `php.versions.list`, `php.fpm.pool.write`, `php.fpm.reload` |
| tls | `cert.issue`, `cert.renew`, `cert.list` (certbot/ACME) |
| dns | `dns.zone.read`, `dns.zone.write`, `dns.reload` |
| databases | `db.create`, `db.drop`, `db.user.create`, `db.user.grant` — `engine ∈ {mysql, mariadb, pgsql, sqlite}` |
| scheduler | `cron.list`, `cron.write`, `cron.run_now` (→ systemd timers) |
| daemons | `daemon.create`, `daemon.start`, `daemon.stop`, `daemon.restart`, `daemon.logs.tail` |
| mail | `mailbox.create`, `mailbox.quota`, `alias.write` |
| terminal | `pty.open`, `pty.write`, `pty.resize`, `pty.close` (scoped to a system user) |
| system | `service.status`, `metrics.snapshot` |

## Desired-state model

The web tier stores **desired state** (a site/cron/daemon should exist with these
settings). A `reconcile` pass diffs desired vs actual and issues the minimal ops
to converge — so a half-applied change or a manually-edited box self-heals on the
next reconcile rather than drifting.

## Multi-engine databases & multi-PHP

- PHP: multiple `phpX.Y-fpm` versions are installed side by side; `site.set_php_version`
  only ever points a site's pool at an already-installed version (allowlisted set),
  never installs arbitrary packages at request time.
- Databases: `db.*` ops dispatch to an engine driver (`mysql`/`mariadb` share one,
  `pgsql`, `sqlite`) behind a common interface, so the UI is engine-agnostic.

## Status

Draft for **P1**. Implementation order: socket + auth + `service.status`/`metrics.snapshot`
first (read-only, proves the channel), then `site.*` + `php.*` for P2.
