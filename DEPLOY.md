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
- **run_migrations** (default off) — when enabled, opens an SSH tunnel to the
  SiteGround account and runs Flyway (`db/migration/` only) against Postgres on
  the server’s `localhost:5432`. Use when schema changed or on first setup.

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

Schema is applied with Flyway (`db/migration/`):

- **Local:** Docker (`make flyway` / `make migrate`).
- **Production:** GitHub Actions with **run_migrations** — SSH tunnel from the
  runner to the account shell, then Flyway → `127.0.0.1:5432` on the server
  (same local path the PHP app uses). No PostgreSQL Remote whitelist needed for
  that path.

Direct remote access (psql/Flyway from your laptop to the site IP) still needs
your IP in PostgreSQL Manager → Remote.

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
- **Flyway fails in CI** — check the Run Flyway step log; confirm SSH still
  works and `PG_*` secrets match Site Tools.
