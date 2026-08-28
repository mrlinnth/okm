# Phase 4: Reroll, Move, Delete

Depends on: Phase 3 (Enable/Disable establishes the create/delete-key
primitives this phase reuses).

### Task [4.1]: Shared create-before-destroy helper [DONE]

#### Subtasks

- [ ] Add a protected helper in `SubscriptionsService` — e.g.
      `replaceKey(array $subscription, string $targetServerId): array` —
      encapsulating the pattern shared by Reroll and Move: create the new
      key on `$targetServerId`'s server first, update the Cockpit record
      (`outlineKeyId`, `accessUrl`, and `serverId` if it changed) so the
      subscription is never left without a working key, _then_ attempt to
      delete the old key on its original server. If the old-key delete
      fails, catch the exception, keep the new key as recorded, and return
      a result that includes a `warning` field with the failure's error
      text — do not fail the overall operation.
- [ ] This is the one place the "create before destroy, warn don't fail on
      cleanup" rule from `requirements.md` is implemented — Reroll and Move
      both call it rather than each reimplementing the ordering.

#### Key Files

- `app/Libraries/SubscriptionsService.php`

#### Verification

`vendor/bin/phpunit --filter=SubscriptionsServiceTest` — unit test
`replaceKey()` with a faked `OutlineService`: successful cleanup returns no
warning; induced cleanup failure returns the new key as recorded plus a
`warning` field, and does not throw.

---

### Task [4.2]: Reroll [DONE]

#### Subtasks

- [ ] Add `SubscriptionsService::reroll(string $id): array` — calls
      `replaceKey($subscription, $subscription['serverId'])` (same server,
      new key). Requires `status === 'active'`; reject otherwise.
- [ ] Implement `Subscriptions::reroll(string $id)`, returning the updated
      record (including any `warning`).

#### Key Files

- `app/Libraries/SubscriptionsService.php`
- `app/Controllers/Subscriptions.php`

#### Verification

Feature test: reroll on an active subscription changes `outlineKeyId`/
`accessUrl`, keeps `serverId` unchanged; rejects on a disabled/expired
subscription.

---

### Task [4.3]: Move

#### Subtasks

- [ ] Add `SubscriptionsService::move(string $id, string
    $destinationServerId): array` — validates the destination is an
      active saved server and differs from the current one, then calls
      `replaceKey($subscription, $destinationServerId)`.
- [ ] Implement `Subscriptions::move(string $id)`: accept
      `destinationServerId` from the POST body, call `move()`, return the
      updated record.

#### Key Files

- `app/Libraries/SubscriptionsService.php`
- `app/Controllers/Subscriptions.php`

#### Verification

Feature test: moving to a valid active destination updates `serverId`,
`outlineKeyId`, `accessUrl`; rejects when the destination is inactive,
missing, or the same as the current server.

---

### Task [4.4]: Delete

#### Subtasks

- [ ] Add `SubscriptionsService::delete(string $id): void` — deletes the
      Outline key if `status === 'active'`, then deletes the Cockpit
      record via `CockpitService::deleteItem`.
- [ ] Implement `Subscriptions::delete(string $id)`, returning a success
      JSON response.

#### Key Files

- `app/Libraries/SubscriptionsService.php`
- `app/Controllers/Subscriptions.php`

#### Verification

Feature tests: deleting an active subscription calls Outline delete then
Cockpit delete; deleting a disabled/expired one skips the Outline call and
only deletes the Cockpit record.
