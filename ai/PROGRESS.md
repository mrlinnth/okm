# Progress

## Current

- **Feature**: saved-servers-registry
- **Task**: 2.1 (List endpoint) — Phase 2
- **Branch**: feature-servers-controller-skeleton
- **Started**: 2026-08-28
- **Status**: Phase 1 complete (1.1, 1.2, 1.3). Next: Phase 2 CRUD endpoints.

### Notes

- **classic-key-manager**: 14/14 `[DONE]`, merged. Wants a live Outline
  sign-off, not blocking.
- **Phase 1 — saved-servers-registry:**
  - **1.1** — `createItem`/`updateItem`/`deleteItem` + `sendWrite()` seam on
    `CockpitService`. Generic, cache-invalidating on 2xx.
    `tests/unit/CockpitServiceTest.php` (7).
  - **1.2** — `SavedServersService` (`parseServerJson` →
    `InvalidServerJsonException`; `checkReachable` via `OutlineService::listKeys`;
    `create`/`setActive`/`delete`/`list`). `create` short-circuits before any
    Cockpit write on bad JSON / unreachable (`ServerUnreachableException`).
    Ctor takes optional `CockpitService`/`OutlineService` for tests; registered
    as `Services::savedServers()`.
    `tests/unit/SavedServersServiceTest.php` (14).
  - **1.3** — `app/Controllers/Servers.php` (`index` renders `servers.index`
    from `SavedServersService::list()`; `store`/`activate`/`deactivate`/`delete`
    are JSON stubs returning `[]`). Routes `/servers`, `POST /servers`,
    `POST /servers/(:segment)/{activate,deactivate,delete}` in
    `app/Config/Routes.php`. Placeholder view
    `app/Views/servers/index.blade.php` (dumps the list as JSON).
    `tests/feature/ServersControllerTest.php` (5). `GET /servers` → 200 live
    (Cockpit unreachable → `list()` returns `[]`, no hang).
- Full suite: **63/63 green**.

- **UNVERIFIED against live Cockpit** — `.env` has placeholder creds; the
  `servers` collection does not exist on `cms.hiyan.xyz` yet. Assumed Cockpit
  v2 Content API write shapes (see `CockpitService` docblocks): create/update
  `POST /api/content/item/{model}` body `{data:{...}}` (update carries `_id`);
  delete `DELETE /api/content/item/{model}/{id}`. Confirm before Phase 3's
  end-to-end pass.
- **Prereq for Phase 2 live testing / Phase 3**: create the Cockpit `servers`
  collection manually (schema in
  `ai/plans/saved-servers-registry/requirements.md`). Phase 2 unit/feature
  tests use fakes, so this isn't blocking the code — only manual verification.

### CSS build caveat (not part of this feature)

- `npm run watch:css` runs elsewhere; Tailwind v4 ignores `tailwind.config.js`
  `content` globs and auto-scans the whole repo, so `public/css/output.css`
  churns. Scope via `@source` in `public/css/input.css` — separate chore.

## Up Next

- 2.1: `Servers::index` — pass a trimmed record shape to the view
  (`id`, `label`, `apiUrl`, `publicHost`, `active` — no `serverJson`).
- 2.2: `Servers::store` — validate `label`/`serverJson`/`publicHost`, call
  `SavedServersService::create`, 422 with distinct messages for invalid JSON
  vs. unreachable.
- 2.3: `Servers::activate` / `deactivate` → `setActive`.
- 2.4: `Servers::delete` → `SavedServersService::delete`.
- Phase 3: Frontend UI (3.1–3.3).

## Blockers

- None. Live-Cockpit confirmation deferred to Phase 3.
