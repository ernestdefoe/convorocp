# ConvoroCP

A modern web hosting control panel — sites, email, DNS, databases, scheduled jobs and long-running daemons — in one fast, keyboard-first, genuinely beautiful surface. Built by **Convoro LLC**. Aiming to be the best hosting control panel on the market, not the cheapest clone of the worst ones.

This repo is the **control plane**: a Laravel + Inertia/Vue application that renders the UI and records desired state. It does **not** run as root and does not touch system services directly (see the architecture note below).

## Stack

- Laravel 13 (PHP 8.5)
- Inertia.js + Vue 3 (SFCs)
- Tailwind v4 + Vite 8
- Inter typeface, Tabler icons
- Design system: dark, indigo `#5B5BD6` / violet `#8B8BF0`, near-black `#0E1020` chrome, 0.5px borders, flat surfaces. Tokens live in `resources/css/app.css` as `--cp-*` variables.

## Architecture (the important part)

ConvoroCP is split into two processes so the public-facing web app never holds root:

```
Control plane (this app)            Privileged agent (root daemon)
Laravel + Inertia/Vue        ──▶    applies changes to the OS:
runs as `convorocp` user     ◀──    nginx · php-fpm · certbot · DNS
stores DESIRED state                postfix/dovecot · systemd · cron · firewall
        (signed JSON commands over a local unix socket)
```

- The web app writes **desired state** (a site exists, this cron runs, this daemon is supervised) to its DB.
- A separate **privileged agent** daemon owns the machine. It exposes a small, **allowlisted** set of operations over a local socket — never a generic "run this shell command." Every operation is templated and validated; there is no path for the web tier to execute arbitrary root commands.
- This is the opposite of cPanel/Plesk, which run enormous privileged surfaces. A compromised UI here cannot escalate beyond the agent's fixed operation set.

**Deployment rule:** ConvoroCP runs on a **dedicated server it fully owns** — never co-installed on a box already running other production services. Control panels take over nginx, PHP, DNS, mail and the firewall; sharing a machine with hand-managed sites will break them.

## Roadmap (phases)

- **P0 — Shell (done):** local scaffold, design system, dashboard reading mock data.
- **P1 — Identity & servers:** login, teams, a server record + the privileged agent contract (socket protocol, operation allowlist).
- **P2 — Sites:** create/list sites, nginx vhost templating, PHP-FPM pools, Let's Encrypt via the agent, git deploys.
- **P3 — DNS & databases:** zone editor → agent, MariaDB/PostgreSQL provisioning + users, query console.
- **P4 — Scheduler & daemons:** visual cron builder → systemd timers; supervised processes (autostart, restart policy, memory caps, log streaming).
- **P5 — Webmail & mail admin:** mailbox/alias/quota management; the from-scratch Convoro-styled webmail client.
- **P6 — Backups, security, monitoring, ⌘K palette, multi-tenant billing.**

## Develop

```bash
composer install
npm install
npm run dev          # Vite
php artisan serve    # http://127.0.0.1:8000
```

The dashboard is at `/`.

## License

Proprietary — © Convoro LLC. All rights reserved.
