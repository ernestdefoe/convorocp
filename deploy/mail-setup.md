# ConvoroCP mail stack (Postfix + Dovecot)

Provisions virtual maildir mail on the node. Webmail (MailController) reads via
IMAP on 127.0.0.1:143 and sends via Postfix on 127.0.0.1:25. Mailboxes are
created/removed by the agent (`mail.account_create` / `mail.account_delete`),
which manages `/etc/dovecot/users` and Postfix `virtual_mailbox_domains`.

## Install (once, as root)
```
DEBIAN_FRONTEND=noninteractive
echo "postfix postfix/main_mailer_type select Internet Site" | debconf-set-selections
echo "postfix postfix/mailname string mail.<host>" | debconf-set-selections
apt-get install -y postfix dovecot-imapd dovecot-lmtpd
groupadd -g 5000 vmail; useradd -g vmail -u 5000 vmail -d /var/vmail -m -s /usr/sbin/nologin
mkdir -p /var/vmail && chown -R vmail:vmail /var/vmail
```

## Dovecot
Copy `dovecot-local.conf` to `/etc/dovecot/local.conf`. IMAP is bound to
loopback only (no plaintext IMAP exposed to the internet); webmail connects
from PHP on the same host. `touch /etc/dovecot/users && chown root:dovecot
/etc/dovecot/users && chmod 640 /etc/dovecot/users`.

## Postfix
```
postconf -e "virtual_mailbox_domains = <mail-domain>"
postconf -e "virtual_transport = lmtp:unix:private/dovecot-lmtp"
postconf -e "inet_interfaces = loopback-only"
postconf -e "smtpd_recipient_restrictions = permit_mynetworks, reject_unauth_destination"
systemctl restart dovecot postfix
```

The agent appends additional domains to `virtual_mailbox_domains` automatically
as mailboxes are created. `/etc/dovecot/users` entries are
`user@domain:{PLAIN}password`.
