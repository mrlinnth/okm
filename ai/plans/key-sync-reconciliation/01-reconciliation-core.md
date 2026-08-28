# Phase 1: Reconciliation Core

Depends on: Saved servers registry (`SavedServersService`, `servers`
collection), Subscription ledger (`SubscriptionsService`, `subscriptions`
collection), Classic key manager (`OutlineService::listKeys`).

### Task [1.1]: Diff live keys against the ledger [DONE]

#### Subtasks

- [ ] Add `SavedServersService::diffServer(string $serverId): array` —
      resolves the server's `apiUrl`, calls `OutlineService::listKeys()`,
      and compares against Cockpit `subscriptions` where `serverId` matches
      (via `CockpitService::getCollectionCached` with a short TTL, ~30–60s —
      same freshness pattern as Recipient public page's token lookup, not
      the project's default longer collection TTL).
- [ ] Return `['foundOnServer' => [...], 'missingOnServer' => [...]]`: - `foundOnServer`: live keys (`id`, `name`, `accessUrl`) whose `id`
      doesn't match any subscription's `outlineKeyId` for this server. - `missingOnServer`: subscriptions for this server whose
      `outlineKeyId` isn't present in the live key list.
- [ ] No caching of the diff result itself — always computed fresh from the
      (short-TTL cached) key list and subscription list; no new Cockpit
      schema field for this.

#### Key Files

- `app/Libraries/SavedServersService.php`

#### Verification

`vendor/bin/phpunit --filter=SavedServersServiceTest` — unit test
`diffServer()` with a faked `OutlineService`/`CockpitService`: keys present
on both sides produce no diff entries; a server-only key appears in
`foundOnServer`; a ledger-only subscription appears in `missingOnServer`.

---

### Task [1.2]: Shared subscription-creation-from-key helper [DONE]

#### Subtasks

- [ ] Add `SubscriptionsService::createFromOutlineKey(string $serverId,
  array $outlineKey, \DateTimeImmutable $expiryDate): array` — creates one
      active subscription from a live Outline key record (`id`, `name`,
      `accessUrl`): `recipientName = keyName = $outlineKey['name']`,
      generated `token` (reuse `generateToken()`), `outlineKeyId =
    $outlineKey['id']`, `accessUrl = $outlineKey['accessUrl']`, `status =
    'active'`, `expiryDate` as passed in. Writes via
      `CockpitService::createItem('subscriptions', [...])`. Returns the
      created record.
- [ ] This is the single implementation shared by Import (Phase 2), Sync
      now's import action (Phase 3), and the reconciliation cron (Phase 5)
      — none of those phases re-implement subscription creation from a raw
      Outline key.

#### Key Files

- `app/Libraries/SubscriptionsService.php`

#### Verification

`vendor/bin/phpunit --filter=SubscriptionsServiceTest` — unit test
`createFromOutlineKey()` with a faked `CockpitService`: confirms the created
record has `recipientName === keyName === outlineKey['name']`, `status ===
'active'`, and the exact `expiryDate` passed in (no date math inside this
method — callers compute the date).
