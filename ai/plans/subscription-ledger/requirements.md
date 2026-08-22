# Requirements: Subscription Ledger

## Overview

Full lifecycle management for subscriptions — each tied to a saved server
and an Outline key: create, edit (recipient/key name), extend, disable,
enable, reroll key, move to another server, delete. Includes inline key
copy directly on every ledger row, absorbing the PRD's separate P2 "Admin
inline key copy" feature since the prototype's baseline Subscriptions
screen already builds it in — there's no gated/toggled variant to defer to.

No secret phrase, no QR (dropped per PRD). A random landing token is
generated once at creation and never changes — it's the only thing this
feature produces for the recipient link; the actual `/s/:token` page is a
separate downstream feature ("Recipient public page").

## Cockpit schema

**Not yet created on the live instance (`cms.hiyan.xyz`)** — create this
collection manually in Cockpit's admin UI before implementation starts.

Collection `subscriptions`:

| Field           | Type                                     | Required | Notes                                                                                    |
| --------------- | ---------------------------------------- | -------- | ---------------------------------------------------------------------------------------- |
| `recipientName` | Text                                     | yes      |                                                                                          |
| `keyName`       | Text                                     | yes      | Outline key name; renaming syncs to the live Outline key when the subscription is active |
| `notes`         | Textarea                                 | no       | Internal admin notes, carried over from the current app                                  |
| `serverId`      | Link → `servers`                         | yes      | Which saved server (from `ai/plans/saved-servers-registry/`) this key lives on           |
| `outlineKeyId`  | Text                                     | yes      | Current Outline key ID — changes on enable/reroll/move                                   |
| `accessUrl`     | Text                                     | yes      | Current `ss://` access URL, refreshed whenever `outlineKeyId` changes                    |
| `status`        | Select (`active`, `disabled`, `expired`) | yes      |                                                                                          |
| `expiryDate`    | Date                                     | yes      |                                                                                          |
| `token`         | Text                                     | yes      | Random, generated once at creation, immutable afterward                                  |

## UI reference

Prototype "Subscriptions" screen (`ai/prototype/blueprint.md`,
`ai/prototype/index.html`), built as-is including the inline Copy button:

- Header + "New subscription" button.
- Filter bar: recipient search (live), status select, saved-server select,
  "Expiring soon" checkbox.
- Desktop table / mobile cards: recipient/key name, server, status badge
  (+"soon" tag), expiry, Copy button, kebab menu (Extend, Move, Reroll key,
  Enable/Disable, Delete).
- Empty state: "No subscriptions match these filters."

daisyUI modals for New subscription, Move, and Delete (matching Classic key
manager / Saved servers registry conventions). Extend, Reroll, and
Enable/Disable are single-click with no modal, matching the prototype.
Expiry is click-to-edit inline (swap to a native date input, save on
change).

## User flows

1. **New subscription** — modal: recipient name, key name, active saved
   server (select), duration (1/2/3 months). On submit: create the Outline
   key on the chosen server, generate the token (random bytes), compute
   `expiryDate` as today + duration with correct month-end clamping, write
   the Cockpit record. Success panel shows recipient, expiry, and a
   copyable share link (`base_url('/s/' . token)` — no separate
   public-base-URL config; this is a monolith, unlike the old split
   frontend/backend). New row appears at the top of the ledger.
2. **Edit** — recipient name and/or key name. Editing `keyName` while
   `status = active` also issues an Outline rename call on `outlineKeyId`
   so the ledger and the real key never drift; for `disabled`/`expired`
   subscriptions (no live key) it's Cockpit-only.
3. **Extend** — one click, no modal. New `expiryDate` = one calendar month
   from the later of (today, current `expiryDate`), with correct
   month-end clamping (e.g. Jan 31 → Feb 28/29, not Mar 3) — shared logic
   with the New-subscription duration calculation.
4. **Expiry inline edit** — click the expiry value → swap to a native date
   input → save immediately on change/blur, no modal or confirm step.
5. **Enable/Disable** — immediate toggle, no confirmation. Disable deletes
   the Outline key and sets `status = disabled`. Enable creates a
   _replacement_ Outline key (new `outlineKeyId`/`accessUrl` — not the same
   key restored) and sets `status = active`.
6. **Reroll key** — create the replacement key and update the Cockpit
   record first, then best-effort delete the old key. If cleanup fails, the
   replacement stays recorded and usable; the response carries a warning
   rather than failing the operation — a subscription must never end up
   with no working key. Kebab menu label flips to "New key issued" for
   1.8s, no modal.
7. **Move** — modal, pick an active destination server (source excluded).
   Create the new key on the destination and update `serverId`/
   `outlineKeyId`/`accessUrl` first, then best-effort delete on the source
   — same failure-safe ordering as Reroll. No duplicate-name suffix
   handling needed (Outline doesn't enforce unique key names; suffix logic
   is specific to the bulk saved-server migration in Key sync/
   reconciliation, not here).
8. **Delete** — confirm modal (red action) → delete the Outline key if
   `status = active`, then permanently delete the Cockpit record.
9. **Copy** — copies `accessUrl` directly from the ledger row; button label
   flips to "Copied!" for 1.5s.
10. **Search and filters** — recipient text search, status, saved server,
    "expiring soon" (active subscriptions with `expiryDate` within 7 days,
    matching the current app's threshold). All client-side over one cached
    full-list fetch (`getCollectionCached('subscriptions')`) — filters
    combine and clear, matching the prototype's live-filter UX. Not
    server-side per-filter queries; the dataset is small (~60 recipients).
11. **Ledger ordering** — sorted by `expiryDate` ascending (soonest first).

## API endpoints

CI4 routes under `/subscriptions/*`, JSON in/out, following the same
pattern as Classic key manager and Saved servers registry:

| Endpoint                           | Behavior                                                  |
| ---------------------------------- | --------------------------------------------------------- |
| `GET /subscriptions`               | Renders the ledger page with the full cached list.        |
| `POST /subscriptions`              | Create: Outline key + Cockpit record + token.             |
| `POST /subscriptions/{id}`         | Edit recipient/key name (syncs Outline rename if active). |
| `POST /subscriptions/{id}/extend`  | One-click extend.                                         |
| `POST /subscriptions/{id}/expiry`  | Set expiry to an explicit date (inline edit).             |
| `POST /subscriptions/{id}/enable`  | Create replacement key, set active.                       |
| `POST /subscriptions/{id}/disable` | Delete key, set disabled.                                 |
| `POST /subscriptions/{id}/reroll`  | Replace key, best-effort old-key cleanup.                 |
| `POST /subscriptions/{id}/move`    | Move to a destination server, best-effort source cleanup. |
| `POST /subscriptions/{id}/delete`  | Delete key (if active) + Cockpit record.                  |

Thin controllers per `CONSTRAINTS.md`; the create-before-delete ordering,
month-end date math, and Outline calls live in a dedicated
`SubscriptionsService`, not the controller.

## Business rules and edge cases

- **Create-before-destroy ordering** applies to Enable, Reroll, and Move:
  the new/replacement key is created and recorded before any attempt to
  remove the old one. A cleanup failure never leaves the subscription
  without a working key — it surfaces as a warning in the response instead.
- **No duplicate-name suffixing** for same-server (enable, reroll) or
  single-subscription cross-server (move) key creation — Outline doesn't
  enforce unique names. Suffix handling only applies to the bulk saved-
  server migration feature, not here.
- **Saved Servers Registry delete guard gets wired in as part of this
  plan**: `Servers::delete()` (in `app/Controllers/Servers.php`, from
  `ai/plans/saved-servers-registry/`) is extended to reject deletion when
  any `subscriptions` record has a matching `serverId`, closing the gap
  documented as a follow-up in that feature's requirements.
- **Token is immutable** — generated once at creation, never regenerated by
  any lifecycle action (enable/reroll/move only change the underlying key,
  not the token/share-link).

## Acceptance criteria

- Create, edit, extend, inline-expiry-edit, enable, disable, reroll, move,
  delete, and copy all work end-to-end against real Outline servers and
  Cockpit data.
- Extend and New-subscription duration math both handle month-end
  clamping correctly.
- Enable, Reroll, and Move never leave a subscription without a working
  key, even when the old-key cleanup step fails.
- Filters (recipient, status, server, expiring-soon) combine correctly and
  clear correctly, all without a network round-trip per change.
- Deleting a saved server that still has subscriptions is rejected by
  `Servers::delete()`.

## Out of scope

- The `/s/:token` recipient-facing page itself — separate "Recipient
  public page" feature (this plan only produces the `token` field it will
  read).
- The daily automated-expiry cron — separate "Automated expiry job"
  feature (this plan only ensures `status`/`expiryDate` exist for it to
  act on).
- Sync now / diff-against-Outline reconciliation — "Key sync/
  reconciliation" feature.
- QR codes, secret phrases (dropped per PRD).
