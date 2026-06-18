<?php

return [
    /*
     * The installed ConvoroCP version. Compared against the latest GitHub
     * release/tag on the Updates page; the panel.update agent op pulls and
     * applies a newer tag. Bump this on every release tag.
     */
    'version' => '1.0.0',

    /*
     * Self-update source. `repo` is owner/name on GitHub; private repos need a
     * token (set in the panel, stored encrypted). The updater downloads the tag
     * tarball, syncs it in, then runs composer install + migrations.
     */
    'update' => [
        'repo' => env('CONVOROCP_UPDATE_REPO', 'ernestdefoe/convorocp'),
    ],

    /*
     * PHP versions installed on a node and offered per-site. The agent only ever
     * points a site's FPM pool at one of these — it never installs at request time.
     */
    'php_versions' => ['8.5', '8.4', '8.3', '8.2', '8.1'],

    /*
     * Database engines ConvoroCP can provision. `db.*` agent ops dispatch to the
     * matching driver behind a common interface.
     */
    'db_engines' => [
        'mariadb' => 'MariaDB',
        'mysql' => 'MySQL',
        'pgsql' => 'PostgreSQL',
        'sqlite' => 'SQLite',
    ],

    /*
     * The agent worker. DRY-RUN by default: it drains the operation queue and
     * records what it WOULD do without touching the OS. Real handlers (and live
     * mode) only ever run on a dedicated, ConvoroCP-owned node — never enable on
     * a shared/production box.
     */
    'agent' => [
        'dry_run' => env('CONVOROCP_AGENT_DRY_RUN', true),
        'poll_seconds' => 2,
        'cert_staging' => env('CONVOROCP_CERT_STAGING', true),
        'cert_email' => env('CONVOROCP_CERT_EMAIL', 'admin@convorocp.test'),
    ],
];
