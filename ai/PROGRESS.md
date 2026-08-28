# Progress

## Current

- **Feature**: saved-servers-registry
- **Task**: 1.2 (SavedServersService)
- **Branch**: feature-saved-servers-cockpit-write
- **Started**: 2026-08-28
- **Status**: Task 1.1 done. Next: build SavedServersService.

### Notes

- **classic-key-manager**: 14/14 tasks `[DONE]`, merged to `develop`. Still
  wants a live sign-off against a real Outline server, but not blocking.
- **Task 1.1 (Cockpit write layer)** — added `createItem`/`updateItem`/
  `deleteItem` + a `sendWrite()` transport seam to
  `app/Libraries/CockpitService.php`. Generic (model is always a param).
  Each write invalidates `clearCollectionCache` (+ `clearItemCache` for
  update/delete) on 2xx. Covered by `tests/unit/CockpitServiceTest.php`
  (7 tests) via a `TestableCockpitService` subclass that stubs `sendWrite`.
  Full suite: 44/44 green.
- **UNVERIFIED against live Cockpit** — `.env` has placeholder Cockpit
  creds, and the `servers` collection does not exist on `cms.hiyan.xyz`
  yet. Assumed Cockpit v2 Content API shapes:
  - create/update: `POST /api/content/item/{model}` body `{data: {...}}`,
    update carries `_id` in `data`.
  - delete: `DELETE /api/content/item/{model}/{id}`.
    Confirm these against the live instance before Phase 3's end-to-end pass;
    if they differ, only `createItem`/`updateItem`/`deleteItem` URLs/bodies
    need adjusting (tests assert on those exact shapes).
- **Prereq for later tasks**: create the Cockpit `servers` collection
  manually (schema in `ai/plans/saved-servers-registry/requirements.md`).

### CSS build caveat (not part of this feature)

- A `npm run watch:css` is running in another terminal. Tailwind v4 ignores
  the legacy `tailwind.config.js` `content` globs and auto-scans the whole
  repo (including `ai/prototype/index.html` and `ai/plans/**/*.md`), so
  `public/css/output.css` churns with stray classes. The one-shot builds
  committed on `develop` (commits 7b19eab/ae7d5c1/364077f) may differ from
  the watcher's output. Worth scoping scanning via `@source` in
  `public/css/input.css` — separate chore, not tracked here.

## Up Next

- 1.2: SavedServersService (parse/validate JSON, reachability via
  OutlineService, create/setActive/delete/list; register `savedServers()`
  in `app/Config/Services.php`)
- 1.3: `Servers` controller skeleton + `/servers/*` routes + placeholder view
- Phase 2: CRUD endpoints (2.1–2.4)
- Phase 3: Frontend UI (3.1–3.3)

## Blockers

- None. Live-Cockpit confirmation of write endpoint shapes is deferred to
  Phase 3, not blocking Phase 1/2 (unit-tested against stubs).
