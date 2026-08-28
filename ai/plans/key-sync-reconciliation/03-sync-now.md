# Phase 3: Sync Now (Manual)

Depends on: Phase 1 (`diffServer()`, `createFromOutlineKey()`).

### Task [3.1]: Diff endpoint [DONE]

#### Subtasks

- [ ] Implement `Servers::sync(string $id)`: call
      `SavedServersService::diffServer($id)`, return the two sections as
      JSON for the Sync now modal.
- [ ] Register `POST /servers/{id}/sync` in `app/Config/Routes.php`.

#### Key Files

- `app/Controllers/Servers.php`
- `app/Config/Routes.php`

#### Verification

Feature test: `POST /servers/{id}/sync` against a faked diff returns both
sections correctly shaped for the modal.

---

### Task [3.2]: Resolve "found on server" section [DONE]

#### Subtasks

- [ ] Add `SubscriptionsService::resolveFoundOnServer(string $serverId,
  array $keys, string $pastedText): array` — parses `$pastedText` for
      `key_name: date` lines (one per line; tolerate extra whitespace,
      ignore blank lines). For each key in `$keys` (the `foundOnServer` list
      from the diff): if a parsed line matches the key's name and its date
      parses as a valid future-or-today date, use that as `expiryDate`;
      otherwise use `addMonthsClamped(today, 1)` (the default term). Calls
      `createFromOutlineKey()` per key. Continues past individual failures.
      Returns per-key results: `{name, status: 'resolved'|'failed',
    expiryDate?, error?}`.
- [ ] Implement `Servers::syncImport(string $id)`: accept `keys` (array of
      `{id, name, accessUrl}`, from the diff response the client already
      has) and `pastedText` (string, optional) from the POST body, call
      `resolveFoundOnServer()`, return the per-key results.
- [ ] Register `POST /servers/{id}/sync/import`.

#### Key Files

- `app/Libraries/SubscriptionsService.php`
- `app/Controllers/Servers.php`
- `app/Config/Routes.php`

#### Verification

`vendor/bin/phpunit --filter=SubscriptionsServiceTest` — unit test
`resolveFoundOnServer()`: a key with a matching pasted date gets that exact
`expiryDate`; a key with no match gets the 1-month default; a malformed
date line falls back to the default rather than erroring; one induced
`createFromOutlineKey()` failure doesn't stop processing the rest.

---

### Task [3.3]: Resolve "missing on server" section [DONE]

#### Subtasks

- [ ] Implement `Servers::syncRemove(string $id)`: accept `subscriptionId`
      from the POST body, call `CockpitService::deleteItem('subscriptions',
  $subscriptionId)` directly (no Outline call — the key is already
      confirmed absent), return a success response.
- [ ] Register `POST /servers/{id}/sync/remove`.

#### Key Files

- `app/Controllers/Servers.php`
- `app/Config/Routes.php`

#### Verification

Feature test: `POST /servers/{id}/sync/remove` deletes the specified
subscription record and does not attempt any Outline API call.
