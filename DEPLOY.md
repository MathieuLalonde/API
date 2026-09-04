# API Deployment

Production: `https://api.mathieulalonde.com` on SiteGround shared hosting.

## Deploying

Manual, via GitHub Actions: run the **Build and deploy** workflow
(`gh workflow run deploy.yaml --ref main`), or from the GitHub UI
(*Actions → Build and deploy → Run workflow*).

Inputs:

- **Commit SHA/tag** (optional) — defaults to the ref you dispatch from.
- **deploy_env** (default off) — when enabled, writes `.env` on the server from
  GitHub secrets (`PG_*`, `APP_ENV=production`). Enable it when secrets change
  or on first setup; normal deploys leave the existing `.env` untouched.

The workflow rsyncs `vendor/`, `src/`, `composer.json` to
`~/www/api.mathieulalonde.com/` and `public/` to `public_html/`, then moves the
`staging` tag to the deployed commit. Pushing to `main` does **not** deploy.

## Required GitHub secrets

| Secret | Set from |
|---|---|
| `SSH_USER`, `SSH_HOST`, `SSH_PRIVATE_KEY` | SiteGround SSH credentials (port 18765) |
| `PG_HOST` | `localhost` for the on-server app; site IP for remote (your PC) |
| `PG_PORT` | `5432` |
| `PG_DB`, `PG_USER`, `PG_PASSWORD` | Site Tools → PostgreSQL Manager |
| `PG_SSLMODE` | `prefer` |

`PG_*` secrets are only read when the **deploy_env** toggle is on.

## Database

PostgreSQL runs on SiteGround itself (Site Tools → PostgreSQL Manager). Schema
is applied with Flyway — locally via Docker (`make flyway`), pointed at the
remote DB by temporarily setting `FLYWAY_URL` to the remote host. Your IP must
be whitelisted in PostgreSQL Manager → Remote for any off-server connection.

The `.env` on the server sits next to `composer.json`, one level above the web
root, and is not web-accessible.

## Troubleshooting

- **404 on `/`** — expected; the API redirects `/` to mathieulalonde.com.
- **`PG_HOST is not set`** — `.env` missing on server: run a deploy with
  **deploy_env** enabled.
- **Stale responses after deploy** — verify `Cache-Control: no-store` headers
  (added by `NoCacheMiddleware`); cache-bust with `?v=$RANDOM` if needed.
- **Remote connection refused** — whitelist your IP in PostgreSQL Manager →
  Remote, or tunnel over SSH (port 18765).
