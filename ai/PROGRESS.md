# Progress

## Current

- **Feature**: classic-key-manager
- **Task**: 1.3 (Wire Alpine.js and htmx into the layout)
- **Branch**: feature-classic-key-manager
- **Started**: 2026-08-22
- **Status**: Tasks 1.1–1.2 done, implementing 1.3

### Notes

- Feature has 4 plan files, 14 tasks total, 2 complete.
- Dev/verification environment: `docker-compose.yml` + `Dockerfile` using
  `serversideup/php:8.5-cli` / `8.5-fpm-nginx`, with `intl` and `pcov`
  extensions added. Run via `docker compose exec cli <command>`.
  `writable/`, `.phpunit.result.cache`, and `build/logs/` needed chown to
  `www-data` (container user) — host mount is owned by uid 1000.
- Task 1.2: `OutlineService::request()` is protected; transport execution is
  isolated in `executeCurl()` so tests can override it with a fake response
  instead of hitting the network (see `tests/unit/OutlineServiceTest.php`'s
  `TestableOutlineService`). Full suite: 8/8 passing.

## Up Next

- 1.4: Classic controller skeleton and routes
- Phase 2 (02-key-operations.md): list/create/delete/delete-all key endpoints

## Blockers

- None
