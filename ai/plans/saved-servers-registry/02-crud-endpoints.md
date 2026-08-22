# Phase 2: CRUD Endpoints

Depends on: Phase 1 (`SavedServersService`, `Servers` controller/routes).

### Task [2.1]: List endpoint

#### Subtasks

- [ ] Implement `Servers::index()`: call
      `SavedServersService::list()`, pass the servers array to the
      `servers.index` view (built out in Phase 3).
- [ ] Each server record passed to the view should include `id`, `label`,
      `apiUrl` (for host display), `publicHost`, `active` — no
      `serverJson` needed in the rendered list (avoid leaking the full
      credential payload into the page source).

#### Key Files

- `app/Controllers/Servers.php`

#### Verification

Feature test: `GET /servers` returns 200 and the response body reflects
servers returned by a faked `SavedServersService::list()`.

---

### Task [2.2]: Add server endpoint

#### Subtasks

- [ ] Implement `Servers::store()`: validate `label` (required, string),
      `serverJson` (required, string), `publicHost` (optional, string) from
      the POST body. Call `SavedServersService::create()`. On
      `InvalidServerJsonException` or a reachability failure, return a 422
      JSON response with the specific error message (don't collapse both
      failure types into one generic message — the UI needs to show why it
      failed). On success, return the created server record as JSON.

#### Key Files

- `app/Controllers/Servers.php`

#### Verification

Feature tests for `POST /servers`: valid input creates a server (faked
service returns success); invalid JSON and unreachable-server cases each
return 422 with a distinct message; confirm the Cockpit write is never
attempted when validation fails first (faked `SavedServersService` asserts
`checkReachable`/`create` call order or short-circuit).

---

### Task [2.3]: Activate / deactivate endpoints

#### Subtasks

- [ ] Implement `Servers::activate(string $id)` and `Servers::deactivate
    (string $id)`: call `SavedServersService::setActive($id, true|false)`,
      return the updated record as JSON. No confirmation needed
      server-side (matches immediate-toggle UX from requirements).

#### Key Files

- `app/Controllers/Servers.php`

#### Verification

Feature tests for both routes confirming the correct `active` value is
passed to `setActive()` and the cache-invalidation path (from Phase 1 Task
1.1) is exercised — i.e. a subsequent list call reflects the change without
manual cache clearing.

---

### Task [2.4]: Delete endpoint

#### Subtasks

- [ ] Implement `Servers::delete(string $id)`: call
      `SavedServersService::delete($id)`, return a success JSON response.
      No `subCount` guard in this feature (per requirements — always
      allowed for now).

#### Key Files

- `app/Controllers/Servers.php`

#### Verification

Feature test for `POST /servers/{id}/delete` confirming the service is
called with the correct ID and the response indicates success.
