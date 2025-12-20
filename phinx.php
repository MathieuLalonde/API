<?php
return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'pgsql',
            'host' => getenv('PG_HOST') ?: 'localhost',
            'port' => getenv('PG_PORT') ?: '5432',
            'user' => getenv('PG_USER') ?: 'postgres',
            'pass' => getenv('PG_PASSWORD') ?: '',
            'db' => getenv('PG_DB') ?: 'api_dev',
            'ssl_mode' => getenv('PG_SSLMODE') ?: 'prefer',
        ],
        'production' => [
            'adapter' => 'pgsql',
            'host' => getenv('PG_HOST'),
            'port' => getenv('PG_PORT') ?: '5432',
            'user' => getenv('PG_USER'),
            'pass' => getenv('PG_PASSWORD'),
            'db' => getenv('PG_DB'),
            'ssl_mode' => getenv('PG_SSLMODE') ?: 'require',
        ],
    ],
    'version_order' => 'creation'
];
