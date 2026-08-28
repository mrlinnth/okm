# Progress

## Current

- **Feature**: key-sync-reconciliation
- **Task**: 2.1 (Auto-import existing keys when a server is added)
- **Branch**: feature-key-sync-reconciliation
- **Started**: 2026-08-28
- **Status**: Phase 1 (reconciliation core) complete; starting Phase 2 (import)

### Notes

- Task 1.1: `SavedServersService::diffServer(serverId)` → `{foundOnServer,
missingOnServer}`. Live keys via `OutlineService::listKeys()`,
  subscriptions via short-TTL (60s) filtered Cockpit collection. Diff never
  persisted. New private `findServer()` helper.
- Task 1.2: `SubscriptionsService::createFromOutlineKey(serverId,
outlineKey, expiryDate)` — single shared creator for Import / Sync-now
  import / cron. `recipientName = keyName = key name`, status active,
  generated token, caller supplies the date (no date math inside).
- Verification: full suite 145 tests green.

## Up Next

- 2.1: Import on Add Server (`Servers::store()` + `importAllFromServer()`)
- Phase 3: Sync now endpoints (3.1 diff, 3.2 resolve found, 3.3 resolve missing)
- Phase 4: Migrate (4.1 logic, 4.2 endpoint)
- Phase 5: `servers:sync` cron command (5.1)
- Phase 6: UI (6.1 sync modal + amber dot, 6.2 migrate modal, 6.3 import summary) — manual verification

## Blockers

- None.
