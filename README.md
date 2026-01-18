# API (Slim 4, PostgreSQL-ready)

Minimal Slim 4 API with layered structure (controller/service/repository/DTO) and PostgreSQL via PDO. Deploys to SiteGround using GitHub Actions + rsync.

## Prerequisites
- PHP 8.0+ with `pdo_pgsql`
- Composer
- PostgreSQL reachable (local for dev; remote for prod)
- Make (for script) - install via `choco install make`

## Setup
```bash
composer install
# copy env template and edit
copy .env.example .env   # Windows
# set PG_* values if you want DB calls to work locally
# set DISCOGS_USERNAME for Discogs LP collection import
```

## Run locally
```bash
php -S localhost:8000 -t public
# Health (no DB required)
curl http://localhost:8000/health
# DB health (requires PG_* and reachable Postgres)
curl http://localhost:8000/health/db
```

## Run locally with Docker (recommended)
```bash
# build and start services (php-fpm, nginx, postgres)
docker compose up -d --build

# install PHP dependencies inside the app container
docker compose exec app composer install

# check health endpoints
curl http://localhost:8080/health
curl http://localhost:8080/health/db

# stop containers
docker compose down → Container stops & is deleted, but pgdata volume persists. Data stays
docker compose down -v → Deletes the containers AND the pgdata volume. Data is gone
```

## Migrations (Flyway)
- Keep Flyway config (`flyway.conf` or env vars) with your `jdbc:postgresql://` URL and credentials.
- Common commands (adjust to your setup):
```bash
# Run migrations
# flyway migrate
docker compose run --rm flyway

# Any other command can be run by adding the command at the end of the docker command:
# ex: flyway info -> docker compose run --rm flyway info

# Flyway information
flyway info

# Validate migrations
flyway validate

# Baseline an existing DB (if needed)
flyway baseline
```

### Common approaches to prune/squash safely:

- Dev-only (easy, destructive):
  1. Create a single baseline migration that reflects current schema, e.g. db/migration/V1__baseline.sql (generate with pg_dump -s).
  2. Drop the DB / run flyway clean, then migrate from the single file.
```bash
# generate baseline (example)
pg_dump -s -U $PG_USER -d $PG_DB > db/migration/V1__baseline.sql

# destructive reset (dev only)
docker compose run --rm flyway clean
docker compose run --rm flyway migrate
```

- Production (non-destructive, recommended):
  1. Create V1__baseline.sql representing the current schema and commit it (keep an archive of old migrations).
  2. Run Flyway baseline to mark the DB at that version (no schema changes).
```bash
docker compose run --rm flyway baseline -baselineVersion=1 -baselineDescription="squash before pruning"
```
  3. Remove old migration files from the repo (keep backups or an archive branch).

#### Notes and tips

Always test the procedure in staging first and take DB backups.
- Keep a copy/archive of removed migrations (git branch/tag or a zip) for auditing/rollbacks.
- Use flyway repair only to fix schema history after failed migrations — it does not replace careful pruning.
- Flyway Teams/Pro features (undo, migrate callbacks) may offer extra options but are not required for squashing.


## Import DVD Profiler Data

Import DVD Profiler XML export files into the database:

### Using Make (recommended, with Docker)
```bash
# Place XML files in manual_imports/ directory (at project root)
# Import a specific file by filename
make import SampleCollection.xml
```

**Note**: The `manual_imports/` directory is mounted at `/var/www/manual_imports/` in the container. Place your XML files there and specify a filename to import.

### Using CLI Script Directly
```bash
# With Docker
docker compose exec php php bin/import_dvdprofiler.php samples/SampleCollection.xml

# Without Docker (requires local PHP and DB connection)
php bin/import_dvdprofiler.php samples/SampleCollection.xml
```

### Using HTTP API
```bash
# POST to /collections/import with multipart/form-data containing 'file' field
curl -X POST http://localhost:8080/collections/import \
  -F "file=@samples/SampleCollection.xml"
```

The import process:
- Parses DVD Profiler XML export
- Creates/updates films and editions
- Syncs relationships (genres, studios, countries, crew, regions, subtitles, features)
- Removes orphaned editions and films not in the import

## Import Discogs LP Collection

Import your Discogs LP collection directly from the Discogs API:

### Prerequisites

Set `DISCOGS_USERNAME` in your `.env` file:
```bash
DISCOGS_USERNAME=kirkenshrir
```

### Using CLI Script

```bash
# With Docker
docker compose exec app php bin/import_discogs.php

# Without Docker (requires local PHP and DB connection)
php bin/import_discogs.php
```

The import process:
- Fetches collection from Discogs API (`/users/{username}/collection/folders/0/releases`)
- Handles pagination automatically
- Creates/updates albums (grouped by `master_id`) and releases
- Syncs relationships (artists, labels, genres, styles, formats)
- Stores media condition and sleeve condition
- Removes orphaned releases and albums not in the import

The script uses `discogs_instance_id` to detect changes in releases.

## Common Composer tasks
```bash
composer install          # install deps
composer dump-autoload -o # rebuild autoloader
```

## Project structure (key parts)
```
public/            # document root
  index.php        # boots Slim with DI
src/
  Config/          # env/config loader
  Bootstrap/       # DI container
  Controller/      # HTTP controllers
  Service/         # business logic
  Domain/          # repository interfaces
  Infrastructure/  # PDO factory, repo impl
  DTO/, Mapper/    # data shapes and mapping
  Routes/          # route registration
```

## Deployment (GitHub Actions → SiteGround)
- Push to `main` runs workflow
- Deploys `vendor/` + `composer.json` to `~/www/<REMOTE_PATH>/`
- Deploys `public/` to `~/www/<REMOTE_PATH>/public_html/`
- Ensure `pdo_pgsql` enabled on SiteGround and `PG_*` env vars set on the server

## Notes
- App starts without a DB; DB endpoints fail only when contacted.
- Keep `.env` out of git; set `PG_*` on the server for production.

## Update PHP version (per subdomain)
- Change PHP version via Site Tools -> Devs -> PHP Manager

### Checking actual version running (and other info)

To access the info screen, create a PHP file (ex: systeminfo.php) in the public_html folder:

`<?php phpinfo(); ?>`

Then open the file in a browser:
http://yourdomain.com/systeminfo.php
