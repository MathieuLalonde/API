# API Deployment

Production: `https://api.mathieulalonde.com` on SiteGround shared hosting.

## Config model

- **`.env`** (not committed) — environment values and secrets. Defaults for
  `PG_PORT` / `PG_SSLMODE` also live in `PdoFactory` if a key is missing.
- **`.env.example`** — committed template of those keys.
- **Deploy `deploy_env`** — writes the prod `.env` from step env + GitHub secrets.

## Deploying

Manual, via GitHub Actions: run the **Build and deploy** workflow
(`gh workflow run deploy.yaml --ref main`), or from the GitHub UI
(*Actions → Build and deploy → Run workflow*).

Inputs:

- **Commit SHA/tag** (optional) — defaults to the ref you dispatch from.
- **deploy_env** (default off) — when enabled, writes `.env` on the server from
  GitHub secrets (`PG_DB` / `PG_USER` / `PG_PASSWORD`) plus `APP_ENV`,
  `PG_HOST`, `PG_PORT`, `PG_SSLMODE`. Enable it when secrets change or on first
  setup; normal deploys leave the existing `.env` untouched.
- **run_migrations** (default off) — syncs `db/migration/` + `bin/migrate.php`
  to the server and runs `php bin/migrate.php` over SSH (PDO to local
  Postgres). SiteGround blocks SSH TCP forwarding, so Flyway-on-the-runner
  via tunnel does not work; local Docker still uses Flyway.

The workflow rsyncs `vendor/`, `src/`, `composer.json` to
`~/www/api.mathieulalonde.com/` and `public/` to `public_html/`, then moves the
`staging` tag to the deployed commit. Pushing to `main` does **not** deploy.

## Required GitHub secrets

| Secret | Set from |
|---|---|
| `SSH_USER`, `SSH_HOST`, `SSH_PRIVATE_KEY` | SiteGround SSH credentials (port 18765) |
| `PG_DB`, `PG_USER`, `PG_PASSWORD` | Site Tools → PostgreSQL Manager |

Non-secret DB settings (`APP_ENV=production`, `PG_HOST=localhost`, port, sslmode)
live on the Ensure server .env step's `env:` block. If `localhost` refuses
connections, change `PG_HOST` there to the site IP.

`PG_DB`, `PG_USER` and `PG_PASSWORD` are read when **deploy_env** or
**run_migrations** is on.

## Database

PostgreSQL runs on SiteGround itself (Site Tools → PostgreSQL Manager).

Schema is applied from `db/migration/` (`VyyyyMMdd_HHmm__*.sql`):

- **Local:** Flyway via Docker (`make flyway` / `make migrate`).
- **Production:** `php bin/migrate.php` over SSH when **run_migrations** is on
  (same `localhost` Postgres path as the app). Tracks applied versions in
  `public.schema_migrations`.

Direct remote access (psql/Flyway from your laptop to the site IP) still needs
your IP in PostgreSQL Manager → Remote. An SSH tunnel from CI to Postgres is
not viable on shared hosting (TCP forwarding disabled / connection reset).

The `.env` on the server sits next to `composer.json`, one level above the web
root, and is not web-accessible.

## Troubleshooting

- **404 on `/`** — expected; the API redirects `/` to mathieulalonde.com.
- **`PG_HOST is not set`** — `.env` missing on server: run a deploy with
  **deploy_env** enabled.
- **Stale responses after deploy** — verify `Cache-Control: no-store` headers
  (added by `NoCacheMiddleware`); cache-bust with `?v=$RANDOM` if needed.
- **Remote connection refused** — for laptop access, whitelist your IP in
  PostgreSQL Manager → Remote. CI migrations use the SSH tunnel instead.
- **Flyway / migrate fails in CI** — prod uses `php bin/migrate.php` over SSH,
  not a DB tunnel. Check that step’s log; confirm `.env` exists on the server
  and `pdo_pgsql` is enabled in Site Tools → PHP.
