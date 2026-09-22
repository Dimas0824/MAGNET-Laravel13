# Docker / FrankenPHP

MAGNET runs on **FrankenPHP** (PHP 8.3 + Caddy built in) with a dedicated
**Redis queue worker** and **MySQL**.

## Stack (`compose.yaml`)

| Service   | Image / build        | Purpose                                  |
|-----------|----------------------|------------------------------------------|
| `app`     | `magnet-app:local`   | FrankenPHP web server (`:8001` -> `:80`) |
| `worker`  | `magnet-app:local`   | `php artisan queue:work` (Redis)         |
| `migrate` | `magnet-app:local`   | one-shot `migrate --force` (profile `setup`) |
| `redis`   | `redis:7-alpine`     | cache + queue                            |
| `db`      | `mysql:8.0`          | database (`:3307` -> `:3306`)            |

## Build

```powershell
podman build --format docker -t magnet-app:local .
```

3 stages: composer vendor → vite frontend → FrankenPHP runtime (non-root `www-data`).

## Run

```powershell
# create .env from the container example (set APP_KEY)
copy .env.container.example .env

podman-compose up -d            # start app, worker, redis, db
podman-compose run --rm migrate # run migrations once (profile: setup)
# or: podman exec magnet_app php artisan migrate --force
```

App: http://localhost:8001  ·  Health: http://localhost:8001/up

## Verify

```powershell
podman ps                                      # all 4 Up
(Invoke-WebRequest http://127.0.0.1:8001/up).StatusCode   # 200
podman logs magnet_app | Select-String "FrankenPHP started"
podman exec magnet_app php artisan about
```

## Safety notes

- Runs as **non-root** (`www-data`, uid 33); `/data` + `/config` + `storage` are writable by it.
- `APP_DEBUG=false`, `APP_ENV=production`; **no `.env` baked into the image**.
- `opcache.validate_timestamps=0` + JIT (`docker/php.ini`) -> rebuild image to ship code changes.
- No application-level static/singleton state, so worker reuse is safe.
- PII uploads (CV/transcript/portfolio) live on the **private disk** (`storage/app/private`),
  served only via the authorized `berkas.download` route — never via `public/`.

## Optimizations applied

- Layer caching via `--mount=type=cache` for composer + npm.
- `composer install` split from source copy => dependency layer cached.
- `--classmap-authoritative` autoloader; `config/route/view/event:cache` warmed at boot.
- `.dockerignore` excludes `vendor`, `node_modules`, `public/build`, `tests`, `docs`, env files.
