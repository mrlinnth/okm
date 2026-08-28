# Phase 1: Automated Expiry Job

Depends on: Subscription ledger (`SubscriptionsService`, `subscriptions`
Cockpit collection), Saved servers registry (`SavedServersService`), Classic
key manager (`app/Libraries/OutlineService.php`,
`app/Libraries/OutlineRequestException.php`).

### Task [1.1]: Grace-period config, eligibility scan, and expiry processing [DONE]

#### Subtasks

- [ ] Create `app/Config/Expiry.php` (`BaseConfig`) with `int $gracePeriodDays
= 3`.
- [ ] Extend `app/Libraries/OutlineRequestException.php` (shared with
      Classic key manager / Subscription ledger) with a `notFound` flag —
      e.g. a constructor parameter `bool $notFound = false` plus an
      `isNotFound(): bool` getter. In `OutlineService::deleteKey()`, throw
      with `notFound: true` specifically when the key-list re-fetch finds no
      key matching the target (already gone), and `notFound: false` for any
      other transport/HTTP failure. This lets callers distinguish "already
      deleted" from a genuine failure without parsing error message text.
- [ ] Add `SubscriptionsService::findExpirable(): array` — from the cached
      `subscriptions` list, filter to `status === 'active'` AND `today >
  expiryDate + gracePeriodDays` (using `Config\Expiry::$gracePeriodDays`).
- [ ] Add `SubscriptionsService::processExpiry(array $subscription): array`
      — resolves the subscription's server via `SavedServersService`, calls
      `OutlineService::deleteKey($apiUrl, $subscription['keyName'])`: - No exception, or `OutlineRequestException` with `isNotFound() ===
    true` → update the Cockpit record to `status = 'expired'`, return
      `['id' => ..., 'outcome' => 'expired']`. - `OutlineRequestException` with `isNotFound() === false` (genuine
      failure) → leave the record untouched, return `['id' => ...,
    'outcome' => 'failed', 'error' => <message>]`.

#### Key Files

- `app/Config/Expiry.php` (new)
- `app/Libraries/OutlineRequestException.php`
- `app/Libraries/OutlineService.php`
- `app/Libraries/SubscriptionsService.php`

#### Verification

`vendor/bin/phpunit --filter=SubscriptionsServiceTest` — unit tests with a
faked `OutlineService`/`SavedServersService`:
`findExpirable()` correctly includes/excludes records at the grace-period
boundary (exactly on the boundary is NOT eligible; one day past is);
`processExpiry()` marks `expired` on success and on a simulated not-found
delete, and leaves the record untouched with a `failed` outcome on a
simulated genuine failure.

---

### Task [1.2]: Spark CLI command

#### Subtasks

- [ ] Create `app/Commands/ExpireSubscriptions.php` extending CI4's
      `BaseCommand`, registered as `subscriptions:expire`
      (`php spark subscriptions:expire`).
- [ ] Calls `SubscriptionsService::findExpirable()`, then
      `processExpiry()` for each record. On a `failed` outcome, log the
      error via CI4's `log_message('error', ...)`. Continues through the
      full list even if individual records fail.
- [ ] At the end of the run, print a summary line to stdout (e.g. "Expired:
      N, Failed: M").
- [ ] Document the suggested crontab entry as a comment/note in this task
      (e.g. `5 0 * * * cd /path/to/app && php spark subscriptions:expire`,
      daily at 00:05 UTC, matching the old app's schedule) — this is a
      deploy-time note for the developer, not something the app installs
      itself.

#### Key Files

- `app/Commands/ExpireSubscriptions.php` (new)

#### Verification

Run `php spark subscriptions:expire` against test data (via
`vendor/bin/phpunit --filter=ExpireSubscriptionsTest` using CI4's command
test helpers, or a manual run against a sandboxed Cockpit/Outline setup):
confirm eligible active subscriptions past grace period get their key
deleted and `status` set to `expired`; confirm subscriptions within the
grace period, already-`disabled`, and already-`expired` are all left
untouched; confirm a simulated failure is logged and does not stop
processing of the remaining records.

---

### Task [1.3]: Recipient page — handle explicit expired status

#### Subtasks

- [ ] In `app/Libraries/SubscriptionsService.php`, update
      `resolveRecipientState()` (from `ai/plans/recipient-public-page/`) to
      add a `status === 'expired'` branch returning `'expired'` — identical
      outcome to the existing derived branch (`status === 'active'` AND
      `expiryDate < today`). Both an overdue-but-not-yet-processed
      subscription and one this job has already marked `expired` must
      render the same way on `/s/:token`.

#### Key Files

- `app/Libraries/SubscriptionsService.php`

#### Verification

`vendor/bin/phpunit --filter=SubscriptionsServiceTest` — extend
`resolveRecipientState()`'s test cases to cover `status === 'expired'`
explicitly, confirming it returns the same `'expired'` result as the
derived-from-date case.
