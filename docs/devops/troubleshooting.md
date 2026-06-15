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

## Line Ending Warnings

Git may warn about LF/CRLF normalization on Windows.

That warning is usually informational and does not indicate a code problem.

## Horizon Status

If Horizon reports inactive, confirm whether the `horizon` profile is actually enabled.

In the default setup, `queue-worker` is the active processor and Horizon is optional.

## Reverb Timing

If realtime startup looks inconsistent, check the `reverb` container logs and confirm the backend container is healthy before retrying.
