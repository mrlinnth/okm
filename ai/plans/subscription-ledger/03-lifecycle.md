# Phase 3: Extend, Expiry Edit, Enable/Disable

Depends on: Phase 2 (`SubscriptionsService` create/read, `Subscriptions`
controller).

### Task [3.1]: Extend [DONE]

#### Subtasks

- [ ] Add `SubscriptionsService::extend(string $id): array` — new
      `expiryDate` = `addMonthsClamped(max(today, current expiryDate), 1)`
      (the "later of now and current expiry" rule from requirements).
      Update the Cockpit record, return it.
- [ ] Implement `Subscriptions::extend(string $id)`: call `extend()`,
      return the updated record.

#### Key Files

- `app/Libraries/SubscriptionsService.php`
- `app/Controllers/Subscriptions.php`

#### Verification

Feature test: extending a subscription with a future expiry adds one month
to that expiry (not to today); extending an already-expired one adds one
month from today; a month-end case clamps correctly.

---

### Task [3.2]: Inline expiry edit [DONE]

#### Subtasks

- [ ] Add `SubscriptionsService::setExpiry(string $id, \DateTimeImmutable
    $date): array` — validates the date is today or later, updates
      `expiryDate` in Cockpit, returns the record.
- [ ] Implement `Subscriptions::setExpiry(string $id)`: accept an explicit
      `date` (Y-m-d) from the POST body, call `setExpiry()`, return the
      updated record; 422 if the date is in the past.

#### Key Files

- `app/Libraries/SubscriptionsService.php`
- `app/Controllers/Subscriptions.php`

#### Verification

Feature test: a valid future date updates `expiryDate` exactly; a
past-date request is rejected with 422.

---

### Task [3.3]: Enable / Disable

#### Subtasks

- [ ] Add `SubscriptionsService::disable(string $id): array` — deletes the
      Outline key (`OutlineService::deleteKey`, resolved by `outlineKeyId`
      or by re-listing and matching `keyName` — reuse whichever pattern
      `OutlineService` already exposes from Classic key manager Phase 2),
      sets `status = 'disabled'`, updates Cockpit.
- [ ] Add `SubscriptionsService::enable(string $id): array` — creates a
      _replacement_ Outline key with the subscription's `keyName` on its
      `serverId`'s server (`OutlineService::createKey`), updates
      `outlineKeyId`/`accessUrl`, sets `status = 'active'`. Does not touch
      `expiryDate` — an expired subscription being enabled keeps its (past)
      expiry unless separately extended, matching the current app's
      "expired subscriptions can also be enabled" note without implying an
      automatic extension.
- [ ] Implement `Subscriptions::disable(string $id)` and
      `Subscriptions::enable(string $id)`, each returning the updated
      record.

#### Key Files

- `app/Libraries/SubscriptionsService.php`
- `app/Controllers/Subscriptions.php`

#### Verification

Feature tests: disable removes the Outline key and sets `disabled`; enable
on a disabled or expired subscription creates a new key with a different
`outlineKeyId`/`accessUrl` than before and sets `active`, leaving
`expiryDate` unchanged.
