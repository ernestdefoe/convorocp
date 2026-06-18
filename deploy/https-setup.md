# ConvoroCP HTTPS / port setup

The panel runs on a dedicated port (**:8000**) over HTTPS, separate from hosted
sites on :80/:443.

- `nginx-panel.conf` → `/etc/nginx/sites-available/convorocp`: the panel vhost.
  `listen 8000 ssl default_server` with the Let's Encrypt cert; web-terminal
  auth_request loopback uses `https://127.0.0.1:8000` (proxy_ssl_verify off).
- `nginx-acme.conf` → `/etc/nginx/sites-available/convorocp-acme` (symlinked into
  sites-enabled): a :80 server for the panel domain that serves the ACME webroot
  challenge and 301-redirects everything else to `https://<domain>:8000`.

## Issue / renew the cert
```
mkdir -p /var/www/letsencrypt/.well-known/acme-challenge
certbot certonly --webroot -w /var/www/letsencrypt -d convorocp.convoro.co \
  --non-interactive --agree-tos -m ernestdefoe@gmail.com
```
Auto-renewal: certbot's systemd timer runs `certbot renew`; a deploy hook at
`/etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh` reloads nginx after renewal.

## Required .env (on the box; not in the repo)
```
APP_URL=https://convorocp.convoro.co:8000
SESSION_COOKIE=convorocp_session
SESSION_SECURE_COOKIE=true
```
