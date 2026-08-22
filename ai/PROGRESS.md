# Progress

## Current

- **Feature**: classic-key-manager
- **Task**: 1.2 (SSRF-safe Outline HTTP client service)
- **Branch**: feature-classic-key-manager
- **Started**: 2026-08-22
- **Status**: Task 1.1 done, implementing 1.2

### Notes

- Feature has 4 plan files, 14 tasks total, 1 complete.
- Set up local dev/verification environment: `docker-compose.yml` + `Dockerfile`
  using `serversideup/php:8.5-cli` / `8.5-fpm-nginx` (with `intl` extension
  added), since this machine has no PHP and the project had no prior Docker
  setup. Run commands via `docker compose exec cli <command>`.
  `writable/cache` had to be created and `writable/` chowned to `www-data`
  for spark to boot.
- Task 1.2 builds `app/Libraries/OutlineService.php` (SSRF-safe transport
  layer only — no list/create/delete methods yet, those land in Phase 2) and
  `app/Libraries/OutlineRequestException.php`.

## Up Next

- 1.3: Wire Alpine.js and htmx into the layout
- 1.4: Classic controller skeleton and routes

## Blockers

- None
