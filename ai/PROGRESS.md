# Progress

## Current

- **Feature**: automated-expiry-job
- **Task**: Complete
- **Branch**: feature-automated-expiry-job
- **Started**: 2026-08-28
- **Status**: Automated expiry job feature complete (all 3 tasks)

### Notes

- Task 1.1: `Config\Expiry` (gracePeriodDays = 3), `OutlineRequestException`
  carries a `notFound` flag (`isNotFound()`), `OutlineService::deleteKey()`
  throws `notFound: true` when the key is already gone, and
  `SubscriptionsService::findExpirable()` / `processExpiry()` scan and
  process eligible records. `processExpiry()` resolves the server via a new
  `findServerById()` helper (no active check — deactivated servers still
  hold live keys); a not-found delete counts as success, a genuine failure
  leaves the record untouched.
- Task 1.2: `app/Commands/ExpireSubscriptions.php` → `php spark
subscriptions:expire`. Iterates `findExpirable()` → `processExpiry()`,
  logs failed outcomes, continues past failures, prints `Expired: N,
Failed: M`. Cron note (00:05 UTC daily) in the command docblock.
- Task 1.3: `resolveRecipientState()` now has an explicit
  `status === 'expired'` branch matching the derived-from-date case.
- Verification: full suite 140 tests green.

## Up Next

- key-sync-reconciliation (0/13) — the remaining planned feature.

## Blockers

- None.
