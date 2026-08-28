# Progress

## Current

- **Feature**: key-sync-reconciliation
- **Task**: 6.1 (Unresolved-diff indicator and Sync now modal)
- **Branch**: feature-key-sync-reconciliation
- **Started**: 2026-08-28
- **Status**: Phases 1–5 (all backend) complete; starting Phase 6 (UI)

### Notes

- Phase 1: `SavedServersService::diffServer()`,
  `SubscriptionsService::createFromOutlineKey()`.
- Phase 2: `SubscriptionsService::importAllFromServer()` wired into
  `Servers::store()`; response carries an `import` summary.
- Phase 3: `Servers::sync` / `syncImport` / `syncRemove` +
  `SubscriptionsService::resolveFoundOnServer()` / `removeRecord()` +
  routes `servers/(:segment)/sync[/import|/remove]`.
- Phase 4: `SavedServersService::migrate()` (server-level validation, lazy
  SubscriptionsService accessor to avoid a construction cycle) delegating
  to `SubscriptionsService::migrateAllToServer()` (create-before-destroy
  per item, collision suffixing, inactive repoint). `Servers::migrate` +
  route.
- Phase 5: `app/Commands/SyncServers.php` → `php spark servers:sync`
  (crontab note: `10 0 * * *`). NOTE: a smoke-test run of this command on
  2026-08-28 created 57 real subscription records in live Cockpit
  (pre-existing key drift) — user chose to keep them, 1-month expiry, will
  edit manually.
- Verification: full suite 165 tests green.

## Up Next

- 6.1: Sync now modal + amber unresolved-diff dot (manual verification)
- 6.2: Migrate modal + results panel (manual verification)
- 6.3: Add Server success panel import summary (manual verification)

## Blockers

- None.
