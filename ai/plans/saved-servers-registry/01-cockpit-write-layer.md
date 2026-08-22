# Phase 1: Cockpit Write Layer & Service Foundation

Depends on: Classic key manager Phase 1 (`app/Libraries/OutlineService.php`,
`ai/plans/classic-key-manager/01-outline-client.md`) — reused for the
reachability check on Add Server.

### Task [1.1]: Extend CockpitService with write methods

#### Subtasks

- [ ] Add `createItem(string $model, array $data): ?array` to
      `app/Libraries/CockpitService.php`, following the existing
      `getSingleton`/`getCollection` methods' structure (try/catch, log on
      error via `log_message('error', ...)`, return `null` on failure).
      POSTs to Cockpit's Content API create endpoint for the model — verify
      the exact path/payload shape against Cockpit v2's Content API docs
      (likely `POST {apiUrl}/api/content/item/{model}` with a `{data:
    {...}}` body, mirroring the existing GET singleton path convention at
      `getSingleton()` — confirm against the live instance during
      implementation, don't assume without checking).
- [ ] Add `updateItem(string $model, string $id, array $data): ?array` —
      same conventions, targets the item by ID.
- [ ] Add `deleteItem(string $model, string $id): bool` — same conventions,
      returns whether the delete succeeded.
- [ ] Each write method must invalidate the relevant cache after a
      successful write: call the existing `clearCollectionCache($model)`
      (for list caches) and, for update/delete, `clearItemCache($model,
    $id)` — reuse these methods as-is, do not duplicate cache-key logic.
- [ ] Keep these methods generic (model name is always a parameter) — do
      not hardcode `servers` anywhere in `CockpitService`, per
      `requirements.md`'s acceptance criteria that future features reuse
      these unchanged.

#### Key Files

- `app/Libraries/CockpitService.php`

#### Verification

`vendor/bin/phpunit --filter=CockpitServiceTest` — add
`tests/unit/CockpitServiceTest.php` covering create/update/delete against a
faked HTTP client (mock `Services::curlrequest` response), confirming the
correct method/path/body per call and that cache-clear methods are invoked
after a successful write.

---

### Task [1.2]: SavedServersService

#### Subtasks

- [ ] Create `app/Libraries/SavedServersService.php`. Responsibilities: - `parseServerJson(string $json): array` — loose validation
      (parseable JSON, `apiUrl` present and starts with `https://`),
      matching Classic key manager's Connect validation exactly; throws a
      typed exception (e.g. `InvalidServerJsonException`) with a
      user-facing message on failure. - `checkReachable(string $apiUrl): bool` — delegates to
      `OutlineService` (inject via `Services::outline()`, added in
      Classic key manager Phase 1) for the light reachability check; does
      not duplicate any SSRF-safety logic. - `create(string $label, string $serverJson, ?string $publicHost):
      array` — validates via `parseServerJson`, checks reachability via
      `checkReachable`, then calls `Services::cockpit()->createItem
      ('servers', [...])` with `label`, `serverJson`, the derived
      `apiUrl`, `publicHost`, and `active = true`. Returns the created
      item or throws on validation/reachability failure — do not create
      the Cockpit item if either check fails. - `setActive(string $id, bool $active): array` — calls
      `updateItem('servers', $id, ['active' => $active])`. - `delete(string $id): bool` — calls `deleteItem('servers', $id)`. - `list(): array` — calls `getCollectionCached('servers')`.
- [ ] Register the service in `app/Config/Services.php` following the
      existing `cockpit()`/`aimeos()` pattern (`savedServers()` method,
      `getSharedInstance`).

#### Key Files

- `app/Libraries/SavedServersService.php` (new)
- `app/Config/Services.php`

#### Verification

`vendor/bin/phpunit --filter=SavedServersServiceTest` — unit test
`parseServerJson` (valid/invalid JSON, missing/non-https `apiUrl`), and a
feature-level test of `create()` with faked `OutlineService`/`CockpitService`
confirming it short-circuits (no Cockpit write) when either validation or
the reachability check fails.

---

### Task [1.3]: Controller skeleton and routes

#### Subtasks

- [ ] Create `app/Controllers/Servers.php` extending `WebController` (per
      `CLAUDE.md`), following the thin-controller pattern already used in
      `app/Controllers/Products.php` and (once built) `app/Controllers/
    Classic.php`.
- [ ] Add `index(): string` rendering a new `servers.index` Blade view
      (placeholder for now, built out in Phase 3) using
      `SavedServersService::list()`.
- [ ] Add empty stub methods for the JSON endpoints implemented in Phase 2
      (`store`, `activate`, `deactivate`, `delete`), each returning
      `$this->response->setJSON([...])`.
- [ ] Register routes in `app/Config/Routes.php`: `GET /servers` →
      `Servers::index`; `POST /servers` → `Servers::store`; `POST
    /servers/(:segment)/activate` → `Servers::activate/$1`; `POST
    /servers/(:segment)/deactivate` → `Servers::deactivate/$1`; `POST
    /servers/(:segment)/delete` → `Servers::delete/$1`.

#### Key Files

- `app/Controllers/Servers.php` (new)
- `app/Config/Routes.php`
- `app/Views/servers/index.blade.php` (new, placeholder)

#### Verification

`vendor/bin/phpunit` passes; `GET /servers` returns 200 and the stub POST
routes return JSON when hit manually or via a basic `FeatureTestTrait`-based
test.
