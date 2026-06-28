# Monitoring (Beszel) setup

ConvoroCP's **Monitoring** screen embeds a self-hosted [Beszel](https://beszel.dev)
dashboard — historical CPU / memory / disk / network graphs, per-container Docker
metrics, and alerts. The hub runs on loopback; nginx exposes it on a dedicated
**operator-gated** TLS port (reusing the panel's `/terminal/check`), and the panel
iframes it.

Requires Docker on the node.

## 1. Hub + agent (Docker)

```bash
mkdir -p /opt/beszel/data /opt/beszel/socket

# Hub — loopback only. Pick a free host port (here 8099 -> container 8090).
docker run -d --name beszel --restart unless-stopped \
  -v /opt/beszel/data:/beszel_data \
  -v /opt/beszel/socket:/beszel_socket \
  -p 127.0.0.1:8099:8090 henrygd/beszel:latest

# Dashboard admin (PocketBase superuser) + a dashboard user with the same login.
docker exec beszel /beszel superuser upsert you@example.com 'STRONG_PASSWORD'

# Agent — host network for real host metrics, docker.sock for container stats,
# local unix socket to the hub. KEY is the hub's PUBLIC key:
KEY=$(ssh-keygen -y -f /opt/beszel/data/id_ed25519)
docker run -d --name beszel-agent --restart unless-stopped \
  --network host \
  -v /opt/beszel/socket:/beszel_socket \
  -v /var/run/docker.sock:/var/run/docker.sock:ro \
  -e LISTEN=/beszel_socket/beszel.sock \
  -e KEY="$KEY" henrygd/beszel-agent:latest
```

Then create the dashboard **user** and register the node as a **system** over the
socket (or just do it in the hub UI → *Add system*, host `/beszel_socket/beszel.sock`).
Via the API (authenticated as the superuser): create a `users` record, then a
`systems` record with `host=/beszel_socket/beszel.sock` and `users=[that id]`.
Status should flip to `up` within ~20s.

## 2. Operator-gated TLS vhost

Add `deploy/nginx-beszel.conf` as a new server on a free TLS port (default **8443**),
reusing the panel cert and `/terminal/check` for `auth_request`. Key points:

- `location = /__beszelcheck` → `proxy_pass https://127.0.0.1:8000/terminal/check`
  (204 operator / 403 client / 401 unauth), forwarding `Cookie`.
- `location /` → `auth_request /__beszelcheck` then `proxy_pass http://127.0.0.1:8099`
  with websocket upgrade headers.
- Override CSP so the panel can frame it (different port = different origin):
  `add_header Content-Security-Policy "frame-ancestors https://<host>:8000 https://<host>:8443" always;`
  and `proxy_hide_header X-Frame-Options;`.

`nginx -t && systemctl reload nginx`. Confirm a no-cookie request returns **401**.

## 3. Enable in ConvoroCP

In `.env`:

```
CONVOROCP_MONITORING_ENABLED=true
CONVOROCP_MONITORING_PORT=8443
# CONVOROCP_MONITORING_URL=https://server.example.com:8443   # optional override

# Read-only API access so the Overview "node health" panel renders live Beszel
# metrics server-side (no Beszel login needed there). Use the dashboard account.
BESZEL_API_URL=http://127.0.0.1:8099
BESZEL_API_EMAIL=you@example.com
BESZEL_API_PASSWORD=the-dashboard-password
```

`php artisan config:clear`, reload the panel. The Monitoring nav item now shows the
live dashboard (operator logs into Beszel once with the dashboard user), and the
operator **Overview** node-health panel is fed by Beszel (`App\Support\Beszel`),
falling back to the local /proc snapshot if the hub is unreachable.
