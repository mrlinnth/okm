# Progress

## Current

- **Feature**: saved-servers-registry
- **Task**: 1.3 (Controller skeleton and routes)
- **Branch**: feature-saved-servers-service
- **Started**: 2026-08-28
- **Status**: Phase 1 tasks 1.1 + 1.2 done. Next: `Servers` controller + routes.

### Notes

- **classic-key-manager**: 14/14 `[DONE]`, merged to `develop`. Still wants a
  live sign-off against a real Outline server, not blocking.
- **Task 1.1 (Cockpit write layer)** — `createItem`/`updateItem`/`deleteItem`
  - `sendWrite()` seam on `app/Libraries/CockpitService.php`. Generic,
    cache-invalidating on 2xx. `tests/unit/CockpitServiceTest.php` (7 tests).
- **Task 1.2 (SavedServersService)** — `app/Libraries/SavedServersService.php`:
  `parseServerJson` (loose validation → `InvalidServerJsonException`),
  `checkReachable` (delegates to `OutlineService::listKeys`, no SSRF logic
  duplicated), `create` / `setActive` / `delete` / `list`. `create`
  short-circuits before any Cockpit write on bad JSON or unreachable server;
  unreachable throws `ServerUnreachableException` (distinct from the JSON
  exception so Task 2.2's controller can return distinct 422 messages).
  Constructor takes optional `CockpitService`/`OutlineService` for test
  injection; defaults to `Services::cockpit()`/`Services::outline()`.
  Registered as `Services::savedServers()`.
  `tests/unit/SavedServersServiceTest.php` (14 tests).
  New exception classes: `app/Libraries/InvalidServerJsonException.php`,
  `app/Libraries/ServerUnreachableException.php`.
- Full suite: **58/58 green**.

- **UNVERIFIED against live Cockpit** — `.env` has placeholder Cockpit creds
  and the `servers` collection does not exist on `cms.hiyan.xyz` yet.
  Assumed Cockpit v2 Content API shapes (see CockpitService docblocks):
  create/update `POST /api/content/item/{model}` body `{data:{...}}` (update
  carries `_id`), delete `DELETE /api/content/item/{model}/{id}`. Confirm
  before Phase 3's end-to-end pass.
- **Prereq for Phase 2/3**: create the Cockpit `servers` collection manually
  (schema in `ai/plans/saved-servers-registry/requirements.md`).

### CSS build caveat (not part of this feature)

- A `npm run watch:css` runs in another terminal. Tailwind v4 ignores the
  legacy `tailwind.config.js` `content` globs and auto-scans the whole repo
  (incl. `ai/prototype/`, `ai/plans/**/*.md`), so `public/css/output.css`
  churns. Worth scoping via `@source` in `public/css/input.css` — separate
  chore, not tracked here.

## Up Next

- 1.3: `Servers` controller skeleton + `/servers/*` routes + placeholder
  `servers/index` view. Uses `SavedServersService::list()`; stub `store` /
  `activate` / `deactivate` / `delete` returning JSON.
- Phase 2: CRUD endpoints (2.1–2.4)
- Phase 3: Frontend UI (3.1–3.3)

## Blockers

- None. Live-Cockpit confirmation of write endpoint shapes deferred to
  Phase 3.
