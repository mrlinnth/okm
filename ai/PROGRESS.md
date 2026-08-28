# Progress

## Current

- **Feature**: automated-expiry-job
- **Task**: 1.3 (Recipient page — explicit expired status)
- **Branch**: feature-automated-expiry-job
- **Started**: 2026-08-28
- **Status**: Tasks 1.1 and 1.2 complete; 1.3 (small resolver branch) next

### Notes

- Task 1.1 done: `Config\Expiry` (gracePeriodDays = 3),
  `OutlineRequestException` now carries a `notFound` flag (`isNotFound()`),
  `OutlineService::deleteKey()` throws `notFound: true` when the key is
  already gone, and `SubscriptionsService::findExpirable()` /
  `processExpiry()` scan and process eligible records.
- `processExpiry()` resolves the server via a new `findServerById()` helper
  (no active check — deactivated servers still hold live keys) and returns
  `['id', 'outcome' => 'expired'|'failed', 'error'?]`. A not-found delete
  counts as success; a genuine failure leaves the record untouched.
- Task 1.2 done: `app/Commands/ExpireSubscriptions.php` → `php spark
subscriptions:expire`. Iterates `findExpirable()` → `processExpiry()`,
  logs failed outcomes via `log_message('error', ...)`, continues past
  failures, prints `Expired: N, Failed: M`. Cron note (00:05 UTC daily) in
  the command docblock.
- Command tests use CI4's `command()` + `StreamFilterTrait`; log assertions
  via `TestLogger`/`assertLogged` (op_logs reset in setUp).
- Verification: full suite 138 tests green.

## Up Next

- 1.3: `resolveRecipientState()` explicit `status === 'expired'` branch

## Blockers

- None.
