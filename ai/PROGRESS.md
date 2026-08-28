# Progress

## Current

- **Feature**: automated-expiry-job
- **Task**: 1.2 (Spark CLI command)
- **Branch**: feature-automated-expiry-job
- **Started**: 2026-08-28
- **Status**: Task 1.1 complete; implementing the `subscriptions:expire` command next

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
- Verification: `phpunit --filter=SubscriptionsServiceTest` (41 tests) and
  full suite (135 tests) green.

## Up Next

- 1.2: Spark command `subscriptions:expire` (app/Commands/ExpireSubscriptions.php)
- 1.3: `resolveRecipientState()` explicit `status === 'expired'` branch

## Blockers

- None.
