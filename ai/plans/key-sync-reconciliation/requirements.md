# Key Sync / Reconciliation — Requirements

## Overview

Closes the gap where keys created outside the app (e.g. via the Outline
Manager Linux app) silently drift out of sync with Cockpit subscription
records. Covers all three deferred actions from the Saved Servers screen
(`ai/plans/saved-servers-registry/requirements.md`'s "Out of scope"):
**Import** (auto-subscribe existing keys when adding a server), **Migrate**
(bulk move all subscriptions between servers), and **Sync now** (manual
diff + resolve) — plus the daily automated **reconciliation cron** the PRD
calls for. Import and Migrate have no other PRD feature claiming them, so
they're bundled in here alongside the PRD's literal "Key sync/
reconciliation" scope (Sync now + cron drift check).

Depends on Saved servers registry (`SavedServersService`, `servers`
collection) and Subscription ledger (`SubscriptionsService`, `subscriptions`
collection), and reuses Classic key manager's `OutlineService` methods
(`listKeys`, `createKey`, `deleteKey`, `resolveUniqueName`).

## Reconciliation core

`SavedServersService::diffServer(string $serverId): array` — compares
`OutlineService::listKeys($apiUrl)` against Cockpit `subscriptions` where
`serverId` matches. Returns:

- `foundOnServer` — live keys with no matching subscription (`outlineKeyId`
  not present in the ledger for this server).
- `missingOnServer` — subscriptions referencing this server whose
  `outlineKeyId` isn't in the live key list.

Computed live wherever needed, short-TTL cached (~30–60s, same pattern as
Recipient public page's token lookup) rather than persisted on the `servers`
record — no schema change, and the daily cron already keeps things
converged.

## Import (on Add Server)

Extends `POST /servers` (from Saved servers registry): after creating the
server record, list its existing Outline keys and create one active
subscription per key — `recipientName = keyName = key name`, generated
`token`, `expiryDate = today + 1 month` (default term, see below),
`outlineKeyId`/`accessUrl` from the key, `serverId` = the new server.
Continues past individual Cockpit write failures. The Add Server success
response includes an import summary (imported count + any failures) for the
UI's success panel.

## Sync now (manual)

- `POST /servers/{id}/sync` → runs `diffServer()`, returns both sections.
- `POST /servers/{id}/sync/import` → resolves `foundOnServer`: accepts
  pasted `key_name: date` lines. A listed key with a matched date uses that
  `expiryDate`; anything unmatched or left blank uses the 1-month default
  term. Creates one subscription per key (same shape as Import above).
  Returns per-item results so the UI can flip each resolved row.
- `POST /servers/{id}/sync/remove` → resolves one item from
  `missingOnServer`: deletes that Cockpit subscription record directly (no
  Outline call needed — the key is already gone).

## Default term

When no explicit date is available (cron, or a Sync-now import with no
matching pasted date), new subscriptions get `expiryDate = today + 1 month`
(month-end clamped, reusing `SubscriptionsService::addMonthsClamped()`).

## Cron — `php spark servers:sync`

Separate Spark command from `subscriptions:expire` (Automated expiry job),
same pattern: documented crontab note (e.g. daily 00:05 UTC), not installed
by the app itself. Iterates active saved servers, runs `diffServer()` on
each:

- `foundOnServer` → auto-imports every key with the 1-month default term
  (same path as a Sync-now import with no date).
- `missingOnServer` → auto-removes every stale Cockpit record.

Fully automated — no admin interaction required, matching the PRD's "drift
gets caught automatically." Continues past per-server and per-item
failures; logs failures via CI4's logger; prints an end-of-run summary
(imported / removed / failed counts).

## Migrate (bulk)

`POST /servers/{id}/migrate` with `destinationServerId` in the body
(must be an active server, different from the source).

Loads every subscription referencing the source server, any status.
Processes sequentially, continuing past failures:

- **Active** (has a live key): resolve a collision-free name against the
  destination's existing keys via `OutlineService::resolveUniqueName()`
  (same `_2`/`_3` convention as Classic key manager, tracking names reserved
  within this batch), create the new key on the destination
  (`OutlineService::createKey()`), update the subscription's
  `serverId`/`outlineKeyId`/`accessUrl` (and `keyName` if it was suffixed)
  — **then** best-effort delete the old key on the source. A cleanup
  failure doesn't fail the migration for that item; it's reported as a
  warning — same create-before-destroy discipline as Subscription ledger's
  Reroll/Move (`SubscriptionsService::replaceKey()` pattern, applied here
  per-item across the whole source server).
- **Disabled/expired** (no live key): just repoint `serverId` — no key to
  create or delete.

Returns full per-subscription results: `{id, recipientName, status:
'success'|'failed', renamed_from?, warning?, error?}` — same reporting shape
as Classic key manager's migrate results. One-shot run, no retry-failed
endpoint (confirmed) — if something fails, the admin re-runs Migrate.

Since every subscription referencing the source moves regardless of status,
the source ends up with zero subscriptions ("zeroes out the source,"
matching the prototype), making it eligible for the Saved Servers delete
guard to pass afterward.

## UI

- Server card: amber dot when a live `diffServer()` check finds anything
  unresolved. Best-effort — if the check itself fails or times out for a
  server, omit the indicator rather than blocking the page.
- Sync now modal: two sections (`foundOnServer` / `missingOnServer`)
  matching the prototype's resolve/green-flip interaction described in
  `blueprint.md`.
- Migrate modal: destination picker (active servers, source excluded),
  results panel matching Classic key manager's migrate-results UI
  conventions (per-item success/failure, continue-on-error) — no retry UI.
- Add Server: success panel gains the import summary line ("Imported N
  existing keys as subscriptions" ± failure count).

## Business rules and edge cases

- Import/Sync-now-import/cron-import all share the same subscription-
  creation shape and default-term logic — implemented once, reused three
  ways.
- Migrate and Reroll/Move share the create-before-destroy discipline: a
  subscription is never left without a working key because of a cleanup
  failure on the old key.
- Migrate is the only place duplicate-name suffixing applies in this
  project outside Classic key manager — Subscription ledger's single-item
  Move explicitly does not suffix (Outline doesn't enforce unique names;
  collisions there are rare and left as-is). Bulk migration suffixes to
  avoid confusing duplicate names accumulating on the destination.
- `servers:sync`'s auto-remove only deletes the Cockpit record — it never
  attempts an Outline delete call (the key is already confirmed absent).

## Acceptance criteria

- Adding a server with pre-existing keys creates one active subscription
  per key, reported in the success panel.
- Sync now correctly categorizes keys into both sections against real data;
  resolving either section updates Cockpit and clears that item from the
  diff on the next check.
- `php spark servers:sync` auto-imports orphan keys and auto-removes stale
  records across all active servers, continuing past failures, with no
  admin interaction, and logs a run summary.
- Migrate moves every subscription (active and inactive) off the source
  server, creates/repoints keys correctly, applies duplicate-suffix
  handling only on actual collisions, and never leaves a previously-active
  subscription without a working key even when source cleanup fails.
- After a full Migrate, the source server has zero subscriptions and its
  Delete action (guard from Subscription ledger) succeeds.

## Out of scope

- Persisted diff/unresolved-state history — the amber indicator and diff
  results are always computed fresh, never stored.
- A "retry failed items" endpoint for Migrate — one-shot run with full
  results; the admin re-runs Migrate if needed.
- QR codes, secret phrases (global out-of-scope, per PRD).
