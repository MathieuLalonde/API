# API (Slim 4, PostgreSQL-ready)

Minimal Slim 4 API with layered structure (controller/service/repository/DTO) and PostgreSQL via PDO. Deploys to SiteGround using GitHub Actions + rsync.

## Prerequisites
- PHP 8.0+ with `pdo_pgsql`
- Composer
- PostgreSQL reachable (local for dev; remote for prod)

## Setup
```bash
composer install
# copy env template and edit
copy .env.example .env   # Windows
# set PG_* values if you want DB calls to work locally
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
flyway migrate

# Validate migrations
flyway validate

# Baseline an existing DB (if needed)
flyway baseline
```

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
