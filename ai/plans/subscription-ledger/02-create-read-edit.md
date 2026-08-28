# Phase 2: Create, List, Edit

Depends on: Phase 1 (`SubscriptionsService` foundation, controller/routes).

### Task [2.1]: List endpoint [DONE]

#### Subtasks

- [ ] Add `SubscriptionsService::list(): array` — `getCollectionCached
    ('subscriptions')`, sorted by `expiryDate` ascending.
- [ ] Implement `Subscriptions::index()`: call `list()`, also fetch active
      saved servers (via `SavedServersService::list()`, filtered to
      `active`) for the filter dropdown and the New-subscription/Move
      server pickers. Pass both to the view.

#### Key Files

- `app/Libraries/SubscriptionsService.php`
- `app/Controllers/Subscriptions.php`

#### Verification

Feature test: `GET /subscriptions` returns 200 with subscriptions ordered
by expiry and the active-servers list present in the response data.

---

### Task [2.2]: Create subscription [DONE]

#### Subtasks

- [ ] Add `SubscriptionsService::create(string $recipientName, string
    $keyName, string $serverId, int $durationMonths, ?string $notes):
    array`: - Look up the server's `apiUrl` via `SavedServersService`. - Create the Outline key via `OutlineService::createKey()` (reused
      from Classic key manager — same create-then-rename call pattern). - Generate the token via `generateToken()`. - Compute `expiryDate` as `addMonthsClamped(today, $durationMonths)`. - Write the Cockpit record via `CockpitService::createItem
      ('subscriptions', [...])` with `status = 'active'`. - Return the created record including the share link
      (`base_url('/s/' . $token)`).
- [ ] Implement `Subscriptions::store()`: validate `recipientName`,
      `keyName`, `serverId` (required), `duration` (must be 1, 2, or 3),
      `notes` (optional). Reject if the target server isn't active. Call
      `create()`, return the record (including share link) as JSON for the
      success panel.

#### Key Files

- `app/Libraries/SubscriptionsService.php`
- `app/Controllers/Subscriptions.php`

#### Verification

Feature test for `POST /subscriptions`: valid input creates a record with
the correct `expiryDate` for each duration option (1/2/3 months, including
a month-end edge case), `status = active`, and a well-formed token/share
link; rejects when the target server is inactive or missing.

---

### Task [2.3]: Edit recipient / key name

#### Subtasks

- [ ] Add `SubscriptionsService::rename(string $id, ?string $recipientName,
    ?string $keyName): array`. If `$keyName` is provided and the
      subscription's `status === 'active'`, call `OutlineService`'s rename
      operation on `outlineKeyId` before updating Cockpit — keep the ledger
      and the live key in sync, per requirements. If the subscription is
      `disabled`/`expired`, update Cockpit only (no live key to rename).
      Update `recipientName` in Cockpit unconditionally when provided.
- [ ] Implement `Subscriptions::update(string $id)`: accept optional
      `recipientName`/`keyName` in the POST body, call `rename()`, return
      the updated record.
- [ ] **UI note for Phase 5:** the prototype's kebab menu doesn't include a
      distinct "Edit" item — only Extend/Move/Reroll/Enable-Disable/Delete,
      plus a click-to-edit pattern already established for expiry. Phase 5
      should apply that same click-to-edit interaction to the recipient
      name and key name cells rather than introducing a new modal or menu
      item not present in the design.

#### Key Files

- `app/Libraries/SubscriptionsService.php`
- `app/Controllers/Subscriptions.php`

#### Verification

Feature tests for `POST /subscriptions/{id}`: renaming `keyName` on an
active subscription triggers the Outline rename call; on a
disabled/expired subscription it does not (Cockpit-only update);
`recipientName`-only updates never touch Outline.
