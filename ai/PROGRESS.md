# Progress

## Current

- **Feature**: saved-servers-registry
- **Task**: 3.1 (Grid scaffold and server card) — Phase 3
- **Branch**: feature-servers-crud-endpoints
- **Started**: 2026-08-28
- **Status**: Phases 1 + 2 complete. Next: Phase 3 frontend UI.

### Notes

- **classic-key-manager**: 14/14 `[DONE]`, merged. Wants a live Outline
  sign-off, not blocking.
- **Phase 1 — saved-servers-registry:** `CockpitService` write layer
  (1.1, `CockpitServiceTest`), `SavedServersService` (1.2,
  `SavedServersServiceTest`), `Servers` controller skeleton + routes + a
  placeholder view (1.3, `ServersControllerTest`).
- **Phase 2 — CRUD endpoints (all `[DONE]`):**
  - **2.1** `Servers::index` maps raw Cockpit items to a trimmed shape via
    `presentServer()` (`id`, `label`, `apiUrl`, `publicHost`, `active`) —
    `serverJson` never reaches the page or any JSON response.
  - **2.2** `Servers::store` — manual validation (`label`/`serverJson`
    required, `publicHost` optional), delegates to
    `SavedServersService::create`. Catches `InvalidServerJsonException` /
    `ServerUnreachableException` separately → 422 with the exception's own
    message (distinct). Private `requireString` / `errorResponse` helpers
    mirror the Classic controller.
  - **2.3** `Servers::activate` / `deactivate` → private `updateActive()` →
    `SavedServersService::setActive($id, bool)`, returns the trimmed record.
    502 if the Cockpit write fails.
  - **2.4** `Servers::delete` → `SavedServersService::delete($id)`, returns
    `{success: bool}`. No `subCount` guard (deferred to Subscription ledger).
  - `tests/feature/ServersControllerTest.php` — 10 tests, `FakeSavedServers`
    in-memory stand-in. Cache invalidation itself is covered by
    `CockpitServiceTest` (Phase 1), not re-tested here.
  - Live-smoke-checked the 3 store error paths: missing label / bad JSON /
    unreachable all return 422 with distinct messages.
- Full suite: **68/68 green**.

- **UNVERIFIED against live Cockpit** — `.env` has placeholder creds; the
  `servers` collection does not exist on `cms.hiyan.xyz`. Assumed Cockpit v2
  write shapes (see `CockpitService` docblocks). All Phase 1/2 code is
  tested against fakes, so this blocks only the end-to-end manual pass.
- **Prereq for Phase 3 manual verification:** create the Cockpit `servers`
  collection (schema in `ai/plans/saved-servers-registry/requirements.md`)
  and set real Cockpit creds in `.env`.

### CSS build caveat (not part of this feature)

- `npm run watch:css` runs elsewhere; Tailwind v4 auto-scans the whole repo,
  so `public/css/output.css` occasionally rides along in commits. Scope via
  `@source` in `public/css/input.css` — separate chore.

## Up Next

- 3.1: `app/Views/servers/index.blade.php` — replace the placeholder with the
  real grid (header + "Add server", 1-col mobile / 2-col tablet+ cards:
  label, host, status badge, action row). Root Alpine `x-data` seeded from
  the controller's server list.
- 3.2: Add server modal (label / public host / JSON textarea, client-side
  loose validation, POST `/servers`, distinct inline errors on 422).
- 3.3: Activate/deactivate (immediate fetch, no confirm) + Delete (daisyUI
  confirm modal).

## Blockers

- None. Live-Cockpit confirmation deferred to Phase 3 manual verification.
