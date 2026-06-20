# Troubleshooting

## Windows Docker Desktop Pipe Error

If Docker commands fail with a pipe permission error on Windows, confirm Docker Desktop is running and the Linux engine is available.

Example symptom:

- `permission denied while trying to connect to the docker API at npipe:////./pipe/dockerDesktopLinuxEngine`

## Redis Password Mismatch

If Redis commands return `NOAUTH`, verify the password from `.env` or `backend/.env.example`.

Example:

```bash
docker compose exec -T redis sh -lc 'redis-cli -a "$REDIS_PASSWORD" ping'
```

## Testing DB Repair

If only the testing database needs repair, use the testing environment flag:

```bash
docker compose exec -T backend php artisan db:wipe --env=testing --force
docker compose exec -T backend php artisan migrate:fresh --env=testing --force
```

Never repair the default development database unless you explicitly intend to reset it.

## Angular / Vite Spawn EPERM

On Windows hosts, Angular or Vite tooling can fail with `spawn EPERM` even when the production build is otherwise healthy.

Typical response:

- verify Docker/Desktop process permissions
- prefer containerized execution for validation
- use targeted frontend commands instead of broad rebuild loops

## API Docs Blank Behind CSP

If `/docs/api?lang=en` or `/docs/api?lang=uk` opens but the Swagger/Stoplight UI stays blank in local Docker, check the browser console for CSP errors against `https://unpkg.com`.

The docs UI is rendered by Scramble and loads Stoplight Elements from that CDN in local/dev mode. Production should remain strict and rely on bundled or self-hosted assets instead of widening the global CSP.

## Vue Admin 401 After Login

If the Vue admin shell loads but protected API requests such as `/api/v1/me`, `/api/v1/bootstrap`, or `/api/v1/stats` return `401`, verify that the SPA dev origins are listed in `SANCTUM_STATEFUL_DOMAINS`.

For local Docker development, the list should include the Vue and Angular ports, for example:

- `localhost:5173`
- `127.0.0.1:5173`
- `localhost:4200`
- `127.0.0.1:4200`

If those origins are missing, Sanctum treats the requests as non-stateful and the browser session cookie will not authenticate the API calls.

If `/api/v1/auth/session/me` returns `200` but other `auth:sanctum` endpoints such as `/api/v1/stats`, `/api/v1/meta/bootstrap`, `/api/v1/notifications/unread-count`, or `/api/v1/chat/conversations` still return `401`, check nginx FastCGI header forwarding.

Same-origin browser `GET` requests usually do not include an `Origin` header. This project uses `EnsureFirstPartyApiRequestsAreStateful` to keep Sanctum session auth working for configured first-party hosts while preserving Sanctum's normal `Origin` / `Referer` checks for cross-origin requests.

The local nginx config should also forward the host and origin-related FastCGI headers so Laravel receives the same request context the browser sent:

```nginx
map $http_origin $sanctum_origin {
    default $http_origin;
    "" "$scheme://$http_host";
}

fastcgi_param HTTP_REFERER $http_referer;
fastcgi_param HTTP_ORIGIN $sanctum_origin;
fastcgi_param HTTP_HOST $http_host;
fastcgi_param HTTP_X_FORWARDED_HOST $http_host;
fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
```

After changing nginx config, restart only nginx:

```bash
docker compose restart nginx
```

## Line Ending Warnings

Git may warn about LF/CRLF normalization on Windows.

That warning is usually informational and does not indicate a code problem.

## Horizon Status

If Horizon reports inactive, confirm whether the `horizon` profile is actually enabled.

In the default setup, `queue-worker` is the active processor and Horizon is optional.

## Reverb Timing

If realtime startup looks inconsistent, check the `reverb` container logs and confirm the backend container is healthy before retrying.
