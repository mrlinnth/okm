# Progress

## Current

- **Feature**: classic-key-manager
- **Task**: 3.2 (Migrate batch endpoint)
- **Branch**: feature-classic-key-manager
- **Started**: 2026-08-22
- **Status**: Task 3.1 done, implementing 3.2 (last backend task before Phase 4 UI)

### Notes

- Feature has 4 plan files, 14 tasks total, 9 complete.
- `OutlineService::resolveUniqueName()` is pure (no I/O): `_2`/`_3`...
  suffixes, checks both `$existingNames` and `$reservedInBatch`.
- Full suite: 31/31 passing.
- Task 3.2 is the last backend task before Phase 4 (UI). Key points from the
  plan: check destination reachable via `listKeys()` before any writes (throw
  immediately if unreachable), fetch destination names once then process
  sequentially reserving names as it goes, continue past per-key failures,
  support `onlyNames` for retry (Classic::migrate() takes `sourceKeys` array,
  `destApiUrl` string, optional `onlyNames` array).

## Up Next

- Phase 4 (04-ui.md): Classic Manager view — two-panel workspace, key list,
  create/delete/migrate modals, results panels

## Blockers

- None
