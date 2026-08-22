# Progress

## Current

- **Feature**: classic-key-manager
- **Task**: 2.1 (List keys, merged with transfer metrics)
- **Branch**: feature-classic-key-manager
- **Started**: 2026-08-22
- **Status**: Phase 1 (tasks 1.1–1.4) complete, starting Phase 2

### Notes

- Feature has 4 plan files, 14 tasks total, 4 complete (Phase 1 done).
- Dev/verification environment: `docker-compose.yml` + `Dockerfile` using
  `serversideup/php:8.5-cli` / `8.5-fpm-nginx`, `intl` + `pcov` extensions.
  `docker compose exec cli <command>` for CLI/tests, `http://localhost:8080`
  for browser checks (needs `docker compose up -d web`).
- Local `.env` needed `CI_ENVIRONMENT = development` uncommented — see task
  1.3 notes in git history for why (BladeOne MODE_FAST issue).
- `Classic` controller skeleton done: `GET /classic` renders a placeholder
  view, `POST /classic/keys/{list,create,delete,delete-all,migrate}` all
  stub to `[]` JSON. Full suite: 10/10 passing.
- Task 2.1 needs `OutlineService::listKeys()` (merges Access Keys +
  transfer-metrics endpoints) and a `formatBytes()` helper, then wires
  `Classic::listKeys()` for real. Verification plan wants a feature test
  with a faked `OutlineService` injected via `Services::injectMock`.

## Up Next

- 2.2: Create key
- 2.3: Delete key
- 2.4: Delete all keys

## Blockers

- None
