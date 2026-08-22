# Progress

## Current

- **Feature**: classic-key-manager
- **Task**: 3.1 (Duplicate-name suffix resolution)
- **Branch**: feature-classic-key-manager
- **Started**: 2026-08-22
- **Status**: Phase 2 (tasks 2.1–2.4) complete, starting Phase 3

### Notes

- Feature has 4 plan files, 14 tasks total, 8 complete (Phase 1 + 2 done).
- `OutlineService::deleteAllKeys()` reuses `fetchAccessKeys()`, deletes
  sequentially, catches `OutlineRequestException` per key (never aborts the
  loop), returns `{deleted, failed, results[]}`.
- Full suite: 27/27 passing.
- Task 3.1 needs a pure `resolveUniqueName(string $requested, array
$existingNames, array $reservedInBatch): string` — no I/O, `_2`/`_3`...
  suffixes (underscore, not hyphen).
- Task 3.2 (migrate) depends on 3.1 + `createKey()`/`listKeys()` (already
  done). Key behaviors: check destination reachable before any writes,
  continue past per-key failures, support `onlyNames` for retry.

## Up Next

- 3.2: Migrate batch endpoint
- Phase 4 (04-ui.md): Classic Manager view

## Blockers

- None
