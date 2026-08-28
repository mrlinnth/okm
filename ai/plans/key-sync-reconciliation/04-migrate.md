# Phase 4: Migrate

Depends on: Saved servers registry (`SavedServersService`), Subscription
ledger (`SubscriptionsService`, specifically the `replaceKey()`
create-before-destroy pattern from
`ai/plans/subscription-ledger/04-reroll-move-delete.md`), Classic key
manager (`OutlineService::createKey`, `OutlineService::resolveUniqueName`).

### Task [4.1]: Bulk migrate logic [DONE]

#### Subtasks

- [ ] Add `SavedServersService::migrate(string $sourceId, string
  $destinationId): array` — validates the destination is an active
      server and differs from the source (reject otherwise, same validation
      shape as Subscription ledger's single Move).
- [ ] Load every subscription referencing `$sourceId`, any status, via
      `SubscriptionsService`. Fetch the destination's existing key names
      once (`OutlineService::listKeys($destApiUrl)`) to seed collision
      avoidance.
- [ ] Process subscriptions sequentially, continuing past individual
      failures: - **`status === 'active'`**: resolve a collision-free name via
      `OutlineService::resolveUniqueName($subscription['keyName'],
      $destExistingNames, $reservedInBatch)` (tracking names reserved
      within this batch as it goes, same convention as Classic key
      manager's migrate), create the key on the destination
      (`OutlineService::createKey()`), update the subscription's
      `serverId`/`outlineKeyId`/`accessUrl` (and `keyName` if it was
      suffixed) in Cockpit — **then** best-effort delete the old key on
      the source. If that cleanup call fails, catch it, keep the new key
      as recorded, and note a `warning` on this item's result — never
      fail the item over a cleanup failure (same discipline as
      `SubscriptionsService::replaceKey()`). - **`status !== 'active'`** (disabled/expired, no live key): just
      update `serverId` in Cockpit — no Outline calls. - Record each item's result: `{id, recipientName, status:
      'success'|'failed', renamed_from?, warning?, error?}`.
- [ ] Return the full results array plus aggregate `moved`/`failed` counts.

#### Key Files

- `app/Libraries/SavedServersService.php`
- `app/Libraries/SubscriptionsService.php`

#### Verification

`vendor/bin/phpunit --filter=SavedServersServiceTest` — with faked
`OutlineService`/`CockpitService`: an active subscription with a colliding
key name gets suffixed and its old key best-effort deleted; an induced
old-key cleanup failure produces a `warning` but still counts as
`'success'`; a disabled subscription is repointed without any Outline call;
rejects when the destination is inactive, missing, or equal to the source.

---

### Task [4.2]: Migrate endpoint [DONE]

#### Subtasks

- [ ] Implement `Servers::migrate(string $id)`: accept
      `destinationServerId` from the POST body, call
      `SavedServersService::migrate($id, $destinationServerId)`, return the
      full results JSON. No retry-failed variant — this is a one-shot run;
      the admin re-invokes the same endpoint if needed.
- [ ] Register `POST /servers/{id}/migrate` in `app/Config/Routes.php`.

#### Key Files

- `app/Controllers/Servers.php`
- `app/Config/Routes.php`

#### Verification

Feature test for `POST /servers/{id}/migrate`: confirms the full
per-subscription results are returned and that a source server with all its
subscriptions successfully migrated ends up referenced by zero
subscriptions (verified via `SubscriptionsService::countByServer($id) ===
0`, reusing the guard helper from Subscription ledger).
