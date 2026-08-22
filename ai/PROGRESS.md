# Progress

## Current

- **Feature**: classic-key-manager
- **Task**: 4.1 (Classic Manager view scaffold) — not yet started
- **Branch**: feature-classic-key-manager
- **Started**: 2026-08-22
- **Status**: Backend complete (Phases 1-3, tasks 1.1-3.2). Paused before
  Phase 4 (UI) to check in with the user — see Notes.

### Notes

- Feature has 4 plan files, 14 tasks total, 10 complete. Full suite: 37/37
  passing (`docker compose exec cli vendor/bin/phpunit`).
- `OutlineService::migrateKeys()` does the reachability check via `listKeys()`
  (dual purpose: confirms destination is up + gets existing names in one
  call), resolves collisions via `resolveUniqueName()`, continues past
  per-key failures, supports `onlyNames` for retry. `Classic::migrate()`
  wires it up with the same requireString/errorResponse validation pattern.
- Phase 4 (4.1-4.4) is frontend-only: one large `app/Views/classic/index.blade.php`
  rewrite implementing the two-panel workspace from `ai/prototype/index.html`
  with Alpine `x-data`. Its own verification steps are manual/visual
  ("paste a real Outline server JSON, confirm...") — no real or mock Outline
  server is available in this environment to test end-to-end against.
- Dev environment: `docker-compose.yml` + `Dockerfile` (serversideup/php 8.5,
  intl + pcov). `docker compose exec cli vendor/bin/phpunit` for tests,
  `docker compose up -d web` + `http://localhost:8080` for browser checks.
  Local `.env` needs `CI_ENVIRONMENT = development` (not committed).

## Up Next

- 4.1: Classic Manager view scaffold (two-panel layout, connect handlers)
- 4.2: Key list, copy, and create
- 4.3: Delete, delete-all, and results
- 4.4: Migrate, retry, and start over

## Blockers

- Phase 4 verification needs a real or mock Outline server to test against
  end-to-end — none available in this environment.
