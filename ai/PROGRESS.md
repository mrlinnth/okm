# Progress

## Current

- **Feature**: classic-key-manager
- **Task**: 1.4 (Classic controller skeleton and routes)
- **Branch**: feature-classic-key-manager
- **Started**: 2026-08-22
- **Status**: Tasks 1.1–1.3 done, implementing 1.4

### Notes

- Feature has 4 plan files, 14 tasks total, 3 complete.
- Dev/verification environment: `docker-compose.yml` + `Dockerfile` using
  `serversideup/php:8.5-cli` / `8.5-fpm-nginx`, with `intl` and `pcov`
  extensions added. Run via `docker compose exec cli <command>` (CLI/tests)
  or `docker compose up -d web` + `http://localhost:8080` (browser checks).
- Root cause found and fixed (user edited local `.env`, not committed):
  `CI_ENVIRONMENT` was commented out, so CI4 defaulted to `production`, which
  put BladeOne in `MODE_FAST` — it never compiles views, only reads an
  already-compiled `.bladec` file, so every page 500'd with "Failed to open
  stream" on the cache file. Set `CI_ENVIRONMENT = development` locally.
- Task 1.3 verified via `curl` (page loads 200, pinned `htmx@2.0.10` and
  `alpinejs@3.16.2` script tags present in `<head>`) — the Chrome extension
  browser tool was disconnected on the user's end, so the devtools
  `window.Alpine`/`window.htmx` console check from the plan wasn't run live.

## Up Next

- Phase 2 (02-key-operations.md): list/create/delete/delete-all key endpoints
- Phase 3 (03-migrate.md): migrate batch endpoint
- Phase 4 (04-ui.md): Classic Manager view

## Blockers

- None
