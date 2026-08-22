# Requirements: Classic Key Manager

## Overview

Always-available, unauthenticated feature to connect to an Outline server via
its exported server JSON, then list, create, copy, and delete access keys;
delete all keys; and migrate keys to a second Outline server. No local
persistence — no Cockpit writes, no PHP session storage of server JSON. State
lives entirely client-side in Alpine `x-data` for the duration of the page load;
a full page refresh clears the connection and any in-progress results, matching
the current app's behavior.

This is the first feature in the rewrite to talk to an Outline server, so it
also builds the SSRF-safe Outline client service (HTTPS-only, DNS-resolve-
before-connect, blocked-range checks, IP pinning) that later features
(Saved servers registry, Subscription ledger, etc.) will reuse.

## UI reference

Prototype "Classic Manager" screen (`ai/prototype/blueprint.md`, `ai/prototype/index.html`),
built as close to 1:1 as the backend allows:

- Two-panel workspace — "Current server" (left) and "Migrate to" (right) —
  with a directional connector between them on `lg+` screens; panels stack
  vertically on mobile with no connector.
- Migrate-to panel is disabled ("Connect a current server first") until the
  current server is connected.

**Deviation from the current app:** all destructive/confirm actions (Delete
key, Delete all) use daisyUI confirm modals matching the prototype, not the
current app's native browser `confirm()` dialogs.

## User flows

1. **Connect** (either panel): paste server JSON → client-side loose
   validation (parseable JSON, `apiUrl` starts with `https://`) → on submit,
   backend fetches keys and transfer metrics for that server and merges them
   into one list (name + formatted usage: B/KB/MB/GB). Current-server panel
   shows the key list on success; migrate-to panel just shows a connected
   label (its own key list isn't needed until a migrate runs).
2. **Create key**: modal with a name field → backend creates the key, then
   performs the separate Outline rename call to apply the requested name
   (two Outline API calls, one user action) → new key appended to the list.
3. **Copy key**: copies the key's `ss://` access URL to the clipboard; button
   label flips to "Copied!" for 1.5s.
4. **Delete key**: daisyUI confirm modal → on confirm, backend re-fetches the
   server's key list to resolve the Outline key ID, then deletes by name →
   key removed from the list.
5. **Delete all**: daisyUI confirm modal → on confirm, backend lists and
   deletes every key sequentially, continuing past individual failures →
   returns a deleted/failed count plus full per-key failure details, shown
   in a results panel.
6. **Migrate**: single server-side batch operation. Client sends the source
   key list (already loaded) and the destination server JSON to one backend
   endpoint. Backend:
   - Checks the destination server is reachable before starting any writes.
   - Processes keys sequentially, does not stop on individual failure.
   - Resolves duplicate names against existing destination keys with `_2`,
     `_3`, ... suffixes; names allocated earlier in the same batch are also
     reserved so two source keys can't collide with each other in one run.
   - Returns per-key results: success (with `renamed_from` when a suffix was
     applied) or failure (with the full underlying error text), plus the
     final access URL for successes.
7. **Retry failed keys**: re-invokes the same migrate logic scoped to only
   the previously-failed keys, re-checking destination names fresh, while
   keeping all prior successes in the results list untouched.
8. **Start over**: on the current-server panel, clears both panels back to
   empty JSON textareas (matches current app — losing the current server
   invalidates any in-progress migration). On the migrate-to panel alone,
   clears only that panel's connection/results; the current-server panel and
   its key list are untouched.

## API endpoints

CI4 routes under `/classic/keys/*`, JSON in/out, mirroring the current app's
surface plus one new endpoint for migrate:

| Endpoint                        | Behavior                                                                                                             |
| ------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `POST /classic/keys/list`       | Lists access keys + transfer metrics for a given `apiUrl`, merged into one response.                                 |
| `POST /classic/keys/create`     | Creates then renames a key for `apiUrl` and `name`.                                                                  |
| `POST /classic/keys/delete`     | Re-resolves the key ID by name on `apiUrl`, then deletes it.                                                         |
| `POST /classic/keys/delete-all` | Lists and attempts to delete every key on `apiUrl`, returns per-key outcomes.                                        |
| `POST /classic/keys/migrate`    | New. Takes source key list + destination `apiUrl`; runs the full batch migrate server-side; returns per-key results. |

All requests validated at the controller boundary (thin controllers per
`CONSTRAINTS.md`); business logic — including the SSRF-safe HTTP client,
duplicate-suffix resolution, and batch continue-on-error handling — lives in
service classes.

## Business rules and edge cases

- **Outline request safeguards** (ported from the current app per
  `CONSTRAINTS.md`): all Outline targets must be HTTPS; DNS is resolved
  before connecting; blocked address ranges are rejected; the connection is
  pinned to the resolved IP to mitigate DNS rebinding.
- **Outline TLS:** certificate validation stays disabled to support
  self-signed Outline servers (PRD exclusion, matches current app).
- **Batch operations never abort on a single failure** — delete-all and
  migrate always process every item and report a full per-item outcome.
- **Duplicate-name suffixes** use `_2`, `_3`, ... (underscore, matching the
  current app exactly — not the prototype's cosmetic hyphen).
- **No persistence** — nothing about classic-mode connections, key lists, or
  migrate results is written to Cockpit or PHP session. A page refresh loses
  all in-progress state, by design.

## Acceptance criteria

- Connect, list, create, copy, delete, delete-all, and migrate all work
  end-to-end against a real Outline server.
- Delete-all and migrate correctly continue past induced per-item failures
  and report accurate deleted/failed or success/failed summaries.
- Migrate produces `_2`/`_3` suffixes for name collisions, including
  collisions introduced within the same batch.
- Retry failed keys retries only previously-failed rows and leaves prior
  successes in the results list unchanged.
- Refreshing the page clears all classic-mode state — no leaked connections
  or stale results.
- Outline client rejects non-HTTPS targets and blocked address ranges
  (unspecified/loopback/link-local/cloud-metadata/multicast/reserved) before
  connecting.

## Out of scope

- Saved servers / persisting server credentials to Cockpit — separate
  "Saved servers registry" feature.
- Any subscription-ledger integration.
- QR codes anywhere (dropped per PRD).
- Recipient secret phrases (not applicable to this feature).
- `SSRF_BLOCK_PRIVATE`-style additional private-range blocking toggle — build
  the always-blocked ranges only; revisit if a later feature needs the
  configurable toggle.
