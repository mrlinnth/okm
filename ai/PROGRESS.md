# Progress

## Current

- **Feature**: saved-servers-registry — **all 10 tasks `[DONE]` (Phases 1–3)**
- **Branch**: feature-servers-ui
- **Status**: Complete and verified live against real Cockpit. Ready to merge.

### Notes

- **classic-key-manager**: 14/14 `[DONE]`, merged. Wants a live Outline
  sign-off, not blocking.

- **saved-servers-registry — done:**
  - **Phase 1** — `CockpitService` write layer (`createItem`/`updateItem`/
    `deleteItem` + `sendWrite()` seam), `SavedServersService`
    (`parseServerJson`/`checkReachable`/`create`/`setActive`/`delete`/`list`,
    two exception types), `Servers` controller skeleton + `/servers*` routes.
  - **Phase 2** — CRUD endpoints. `index` trims records via `presentServer()`
    (`serverJson` never leaves the server). `store` returns distinct 422s for
    bad JSON vs unreachable. `activate`/`deactivate` → `setActive`. `delete`
    → `{success}`.
  - **Phase 3** — `app/Views/servers/index.blade.php`: daisyUI grid of server
    cards (label / host / active|inactive badge / Activate-Deactivate +
    Delete), Add Server modal (client-side loose validation, POST `/servers`,
    inline 422 message), Delete confirm modal. Alpine `savedServers()` factory
    seeded from the controller list; `displayHost()` shows `publicHost` or the
    apiUrl host (never the full secret URL path).
  - Tests: `CockpitServiceTest` (7), `SavedServersServiceTest` (14),
    `ServersControllerTest` (10). Full suite **68/68 green**.

- **VERIFIED LIVE against `cms.hiyan.xyz`** (after the user fixed the API
  token — the placeholder was rejected with 412):
  - Cockpit write layer: create → update (partial, `_id`-merged) → delete,
    plus cache invalidation (list reflects each write immediately). The
    assumed Cockpit v2 shapes were correct: `POST /api/content/item/{model}`
    body `{data:{...}}` (update carries `_id`), `DELETE .../{id}`.
  - `/servers` endpoints end-to-end via curl: deactivate/activate/delete all
    200, list updates without manual cache clear.
  - Temp `spark cockpit:smoke` / `servers:seed` commands used for this were
    removed; no demo rows left in Cockpit.

- **Not yet done manually**: the browser walkthrough of the Add Server modal
  with a _real_ Outline server JSON (exercises the reachability check + card
  append in the UI). The Chrome tool was unavailable this session. Endpoint
  behavior for that path is unit/curl-tested; only the in-browser Alpine
  wiring is unconfirmed.

### CSS build caveat

- `npm run watch:css` elsewhere + Tailwind v4 whole-repo scan → occasional
  `public/css/output.css` churn in commits. Scope via `@source` — separate
  chore.

## Up Next

- Merge `feature-servers-ui` to develop.
- Optional: browser walkthrough of Add Server with a real Outline JSON.
- Next feature (per PRD priority + deps): **subscription-ledger** (depends on
  saved-servers-registry, now done). 18 tasks across 5 phases.
  Other candidates: key-sync-reconciliation, recipient-public-page,
  automated-expiry-job — all also depend on subscription-ledger except
  key-sync (depends on saved-servers only).

## Blockers

- None.
