<?php

return [
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
];
