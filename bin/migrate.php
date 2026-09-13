#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Apply versioned SQL migrations from db/migration/ (Flyway V__ naming).
 *
 * Used in production via SSH from GitHub Actions — SiteGround blocks SSH TCP
 * forwarding, so Flyway on the runner cannot reach Postgres through a tunnel.
 * Local Docker continues to use Flyway; this script is the prod path.
 *
 * Usage: php bin/migrate.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Config\AppConfig;
use App\Infrastructure\Database\PdoFactory;

AppConfig::load();

$migrationsDir = realpath(__DIR__ . '/../db/migration');
if ($migrationsDir === false || !is_dir($migrationsDir)) {
    fwrite(STDERR, "Error: db/migration directory not found\n");
    exit(1);
}

$pdo = PdoFactory::create();
$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.schema_migrations (
    version     TEXT PRIMARY KEY,
    script      TEXT NOT NULL,
    checksum    TEXT NOT NULL,
    applied_at  TIMESTAMPTZ NOT NULL DEFAULT now()
)
SQL);

$applied = [];
foreach ($pdo->query('SELECT version, checksum FROM public.schema_migrations') as $row) {
    $applied[$row['version']] = $row['checksum'];
}

$files = glob($migrationsDir . '/V*.sql') ?: [];
sort($files, SORT_STRING);

$pending = 0;
foreach ($files as $path) {
    $script = basename($path);
    if (!preg_match('/^V([0-9]{8}_[0-9]{4})__(.+)\.sql$/', $script, $m)) {
        fwrite(STDERR, "Skipping unrecognized migration name: {$script}\n");
        continue;
    }
    $version = $m[1];
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Error: cannot read {$script}\n");
        exit(1);
    }
    $checksum = hash('sha256', str_replace("\r\n", "\n", $sql));

    if (isset($applied[$version])) {
        if ($applied[$version] !== $checksum) {
            fwrite(STDERR, "ERROR: checksum mismatch for {$script} (version {$version}).\n");
            fwrite(STDERR, "Migration was altered after apply. Add a new V__ file instead.\n");
            exit(1);
        }
        echo "OK already applied: {$script}\n";
        continue;
    }

    echo "Migrating: {$script} ... ";
    $started = hrtime(true);
    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $stmt = $pdo->prepare(
            'INSERT INTO public.schema_migrations (version, script, checksum) VALUES (?, ?, ?)'
        );
        $stmt->execute([$version, $script, $checksum]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "FAILED\n{$e->getMessage()}\n");
        exit(1);
    }
    $ms = (int) round((hrtime(true) - $started) / 1e6);
    echo "done ({$ms} ms)\n";
    $pending++;
}

if ($pending === 0) {
    echo "No pending migrations.\n";
} else {
    echo "Applied {$pending} migration(s).\n";
}
