# ConvoroCP — MVP launch plan

Goal: a control panel worth **$10/mo**, on par with or better than cPanel/Plesk where it counts, that someone can run to host their own sites or resell hosting.

## 1. Positioning (the important scope decision)

Two different products hide under "control panel":

- **Shared-hosting panel** (cPanel/Plesk classic): runs ON the box, does the *entire* stack — sites, **email + webmail + spam**, authoritative **DNS**, FTP, stats, backups, reseller accounts. Email + DNS hosting are enormous, thankless, security-heavy surfaces.
- **Server/app panel** (RunCloud, Ploi, GridPane, ServerPilot — all ~$8–25/mo, the actual competitors at our price): a SaaS control plane + an agent on *your* VPS. Focus: deploy sites, multi-PHP, SSL, databases, queues/daemons, backups, security. Most **deliberately skip email** and lean on Cloudflare/external DNS.

**Decision for v1: ship the server/app panel first.** It's a real, proven $10/mo market, it matches the architecture we already built, and it's achievable. Chase full cPanel parity (email/webmail, authoritative DNS, reseller tiers) in v1.1+ — not at launch. Trying to do cPanel's whole surface for v1 is how this never ships.

## 2. v1 scope — IN

Everything below must **actually work on a real server**, not just queue an op:

- Connect a server (provision the agent), server health/metrics (real).
- Sites: create/delete, nginx vhost, **multi-PHP** (FPM pools), static & Node apps.
- **SSL** via Let's Encrypt (auto-issue + renew).
- Git deploys (pull + deploy script + auto-deploy webhook).
- Databases: **MySQL/MariaDB + PostgreSQL** provisioning + users.
- Scheduler (cron → systemd timers) and daemons (supervised processes).
- **Backups**: scheduled site + DB backups to an offsite target (S3-compatible), restore.
- **Security**: firewall rules, fail2ban, SSH key mgmt, panel 2FA.
- File manager + in-browser terminal.
- Multi-tenant: operator + client roles (done), plan limits (done).
- **Billing**: Stripe subscriptions on signup; suspend on non-payment.
- Email/transactional + activity log + basic monitoring/alerts.

## 3. v1 scope — OUT (deferred to v1.1+)

- Email hosting + webmail + spam filtering (the from-scratch webmail stays a render for now).
- Authoritative DNS hosting (v1 offers DNS *records* that write to a provider API / external; not a nameserver).
- One-click app marketplace beyond a starter set.
- White-label / custom branding per operator.
- Reseller-of-resellers, usage-based billing, multi-region.

## 4. What's already built (control plane)

UI + desired-state + agent **queue** for: sites, multi-PHP, DNS records, multi-engine DB, scheduler, daemons; auth + operator/client roles; plans + signup + limits; customers; light/dark. The agent worker drains the queue but is **dry-run** — it applies nothing yet. Estimate: the control plane is ~25–30% of v1; the agent + real features + hardening is the rest.

## 5. Critical path — build order to MVP

1. **M1 — Real agent (the gate).** Dedicated test VPS. Implement the socket + auth from `agent-protocol.md`, then real handlers for `site.create`, `php.*`, `cert.issue`. First milestone: create a site in the UI → a real nginx vhost + FPM pool + Let's Encrypt cert serving HTTPS. *Nothing else matters until this works.*
2. **M2 — Core hosting real.** Databases (MySQL/MariaDB/PG), git deploys, file manager, terminal (PTY).
3. **M3 — Trust/ops.** Backups (offsite + restore), security (firewall/fail2ban/2FA), scheduler + daemons real, monitoring/alerts.
4. **M4 — Business.** Stripe subscriptions, suspend/unsuspend on payment state, onboarding, activity log, transactional email.
5. **M5 — Polish + launch.** Docs, ToS/privacy, status page, error handling, a closed beta on real customer servers, then public launch.

## 6. Operational table stakes (non-negotiable to charge money)

- **Security:** the agent is the crown jewels — signed allowlisted ops only (done in design), least privilege, audit log, no arbitrary shell. Panel 2FA. Regular dependency + CVE review.
- **Backups & restore tested** — a backup you've never restored isn't a backup.
- **Reliability:** the SaaS control plane needs its own monitoring, backups, and an agent that fails safe (a panel outage must never take customer sites down — the agent keeps the box running independently).
- **Support + SLA expectations**, billing dunning, refund policy.

## 7. Infrastructure to run the business

- **Control plane** (this app) on its own small server — NOT a customer node, NOT the existing prod VPS.
- **Agent** runs on each customer's server.
- A **dedicated test node** for development of M1–M3 (throwaway VPS).

## 8. Open decisions for Ernest

- Confirm v1 = server/app panel (email/DNS-hosting deferred)? 
- Target customer for v1: developers/agencies managing their own VPS, or end-clients of a host you run?
- Billing: per-server, per-site, or flat $10/mo per account? (Competitors charge per-server.)
- Where do M1 test node + the control-plane host live?
