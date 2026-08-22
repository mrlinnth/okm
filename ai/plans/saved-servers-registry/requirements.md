# Requirements: Saved Servers Registry

## Overview

Store Outline server credentials in Cockpit CMS so admins don't paste server
JSON on every visit. Label servers, mark them active/deactivate, delete
unused ones. This feature is deliberately CRUD-only: the prototype's Saved
Servers screen also shows Import (existing keys → subscriptions), Sync now
(diff live keys vs ledger), and Migrate (move subscriptions between
servers) — all of those require subscription records that don't exist yet,
since the PRD's own dependency graph puts Subscription ledger _after_ this
feature. They are explicitly deferred to Subscription ledger and Key
sync/reconciliation, which extend this same screen later.

This feature also extends `app/Libraries/CockpitService.php` with generic
write methods (`createItem`, `updateItem`, `deleteItem`). That service
currently only has read/cache methods (`getSingletonCached`,
`getCollectionCached`, `getItemCached`) because it has only ever been used
for content display — this is the first feature that needs Cockpit as a
read/write admin datastore.

## Cockpit schema

**Not yet created on the live instance (`cms.hiyan.xyz`)** — create this
collection manually in Cockpit's admin UI before implementation starts,
since this stack has no migrations (`CLAUDE.md`: no Models/Entities/
Migrations, all data from external APIs).

Collection `servers`:

| Field        | Type     | Required | Notes                                                                                                                                       |
| ------------ | -------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `label`      | Text     | yes      | Admin-facing name for the server                                                                                                            |
| `serverJson` | Textarea | yes      | The full pasted Outline export JSON, stored verbatim as canonical source (may contain fields beyond `apiUrl`/cert that later features need) |
| `apiUrl`     | Text     | yes      | Extracted from `serverJson` at save time, for display/filtering without re-parsing                                                          |
| `publicHost` | Text     | no       | Recipient-facing hostname/reachability hint. Stored for later features (share links) to read — not used by this feature's own actions       |
| `active`     | Boolean  | yes      | Default `true`                                                                                                                              |

## UI reference

Prototype "Saved Servers" screen (`ai/prototype/blueprint.md`,
`ai/prototype/index.html`), trimmed to CRUD only:

- Header + "Add server" button.
- Grid of server cards (1 col mobile, 2 cols tablet+): label, host, status
  badge, action row.
- Actions per card: Activate/Deactivate, Delete.

**Deviations from the prototype (deferred, not built here):**

- No subscription count on the card — meaningless without a ledger.
- No Sync now action, no amber "unresolved diff" indicator.
- No Migrate action.
- Add Server has no Import success panel — just adds the card.

## User flows

1. **Add server**: modal (label, optional public host, server JSON
   textarea) → client-side loose validation (parseable JSON, `apiUrl`
   starts with `https://`), matching Classic key manager's Connect
   validation exactly. On submit, backend performs a light reachability
   check by reusing `OutlineService` (built in the Classic key manager
   plan, `ai/plans/classic-key-manager/01-outline-client.md`) — no new
   SSRF-safe HTTP client code in this feature. On success, creates the
   Cockpit `servers` item (parsing `apiUrl` out of `serverJson` for the
   dedicated field) and the card appears in the grid. On failure (invalid
   JSON, unreachable server), show the inline error and don't create the
   item.
2. **Activate/Deactivate**: immediate toggle, no confirmation — writes
   `active` to the Cockpit item, badge color updates immediately.
3. **Delete**: daisyUI confirm modal (matching Classic key manager's
   confirm-modal convention, not native `confirm()`) → on confirm, deletes
   the Cockpit item, card removed from the grid. No subscription-count
   guard in this feature (nothing can reference a server yet — the guard
   condition `subCount > 0` from the prototype is a documented extension
   point for Subscription ledger to wire in later, not built now).

## API endpoints

CI4 routes under `/servers/*`, JSON in/out, following the same pattern as
Classic key manager's `/classic/keys/*`:

| Endpoint                        | Behavior                                                                                   |
| ------------------------------- | ------------------------------------------------------------------------------------------ |
| `GET /servers`                  | Renders the Saved Servers page (list from Cockpit, cached read).                           |
| `POST /servers`                 | Validates input, light-checks reachability via `OutlineService`, creates the Cockpit item. |
| `POST /servers/{id}/activate`   | Sets `active = true`.                                                                      |
| `POST /servers/{id}/deactivate` | Sets `active = false`.                                                                     |
| `POST /servers/{id}/delete`     | Deletes the Cockpit item.                                                                  |

Thin controllers per `CONSTRAINTS.md`; validation and delegation only in the
controller, Cockpit read/write logic in `CockpitService`, any
server-specific business rules (e.g. deriving `apiUrl` from `serverJson`) in
a dedicated service or the controller's private helpers — no speculative
abstraction beyond what this feature needs.

## Business rules and edge cases

- `serverJson` is stored verbatim; `apiUrl` is derived once at save time and
  not re-derived on every read.
- The reachability check on Add reuses `OutlineService`'s existing SSRF
  safeguards (HTTPS-only, DNS-resolve-before-connect, blocked-range checks,
  IP pinning) — no new network code.
- Cockpit writes must invalidate/refresh the relevant cache
  (`CockpitService`'s existing `clearCollectionCache`/`clearItemCache`
  methods) so the servers list reflects changes immediately after
  create/activate/deactivate/delete, consistent with `CLAUDE.md`'s "all API
  calls must be cached" rule extended to write-then-invalidate.

## Acceptance criteria

- Add, list, activate, deactivate, and delete all work end-to-end against
  real Cockpit data on `cms.hiyan.xyz`.
- Add Server rejects unreachable or invalid servers with the same
  loose-validation-then-light-check behavior as Classic key manager, and
  does not create a Cockpit item on failure.
- Activate/Deactivate and Delete are reflected immediately in the list (no
  stale cached data).
- `CockpitService::createItem`/`updateItem`/`deleteItem` are generic (not
  hardcoded to the `servers` collection) so Subscription ledger and other
  future features can reuse them without duplicating write logic.

## Out of scope

- Import (existing Outline keys → subscription records).
- Sync now (diff live server keys vs ledger records).
- Migrate (moving subscriptions between saved servers).
- Subscription count display on server cards.
- The `subCount > 0` delete guard — deferred until Subscription ledger
  exists; documented as a follow-up, not implemented here.
