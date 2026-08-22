# Phase 1: Outline Client & Frontend Foundation

### Task [1.1]: Outline config [DONE]

#### Subtasks

- [ ] Create `app/Config/Outline.php` (mirrors `app/Config/Cockpit.php`'s
      `BaseConfig` pattern): `public int $timeout = 10` (seconds, matches
      current app's 10s Outline timeout), `public array $blockedRanges` listing
      the always-blocked CIDR ranges (unspecified/loopback `0.0.0.0/8,
127.0.0.0/8`, link-local `169.254.0.0/16`, cloud metadata
      `169.254.169.254/32`, multicast `224.0.0.0/4`, reserved `240.0.0.0/4`,
      plus IPv6 equivalents `::1/128`, `fe80::/10`, `ff00::/8`).
- [ ] Do not add an `SSRF_BLOCK_PRIVATE`-style toggle for RFC1918/CGNAT/ULA
      ranges — out of scope per `requirements.md`.

#### Key Files

- `app/Config/Outline.php` (new)

#### Verification

`php spark` boots without config errors (e.g. `php spark list` exits 0).

---

### Task [1.2]: SSRF-safe Outline HTTP client service [DONE]

#### Subtasks

- [ ] Create `app/Libraries/OutlineService.php`. Unlike `CockpitService`
      (single configured endpoint), this service takes a target `apiUrl` per
      call, since each classic-mode request supplies its own server JSON —
      do not read a fixed endpoint from config.
- [ ] Implement a protected method (e.g. `request(string $method, string
$apiUrl, string $path, ?array $json = null): array`) that: rejects any
      `$apiUrl` not starting with `https://`; resolves the host via DNS
      (`dns_get_record` or `gethostbyname`) before connecting; rejects if the
      resolved IP falls in any range from `Config\Outline::$blockedRanges`;
      pins the connection to the resolved IP (e.g. `CURLOPT_RESOLVE` mapping
      host:port to the resolved IP) to prevent DNS-rebinding between the
      check and the connect; disables TLS certificate verification
      (`CURLOPT_SSL_VERIFYPEER = false`) to support self-signed Outline
      servers, per `CONSTRAINTS.md` exclusions; applies the configured
      timeout.
- [ ] On any transport or non-2xx response, throw a typed exception (e.g.
      `App\Libraries\OutlineRequestException`) carrying the underlying error
      message — callers need the full text for failure reporting (delete-all
      and migrate results must show full error text per requirements).
- [ ] Do not implement public list/create/delete methods yet — those land in
      Phase 2. This task is the transport layer only.

#### Key Files

- `app/Libraries/OutlineService.php` (new)
- `app/Libraries/OutlineRequestException.php` (new)

#### Verification

- `vendor/bin/phpunit --filter=OutlineServiceTest` — add
  `tests/unit/OutlineServiceTest.php` covering: rejects non-HTTPS URLs,
  rejects a URL resolving to a blocked-range IP (e.g. stub/mock DNS
  resolution or test against a loopback-resolving hostname), and confirms a
  well-formed HTTPS request path is attempted (mock the underlying transport
  rather than hitting a real network).

---

### Task [1.3]: Wire Alpine.js and htmx into the layout [DONE]

#### Subtasks

- [ ] Alpine.js (`3.16.x`) and htmx (`2.0.10`) are listed in
      `CONSTRAINTS.md` but are not yet present anywhere in this project (no
      script tags in `app/Views/layouts/master.blade.php`, not in
      `package.json` — confirmed by inspection). Add both via pinned-version
      CDN `<script>` tags in `app/Views/layouts/master.blade.php`'s `<head>`
      (Alpine with `defer`, matching how the prototype loads it), since the
      project has no JS bundler (only the Tailwind CLI build in
      `package.json`).
- [ ] Use the exact pinned versions from `CONSTRAINTS.md` (Alpine `3.16.x`,
      htmx `2.0.10`) — not the prototype's `3.13.5`, which predates the
      confirmed constraint.
- [ ] Verify no CSP filter is currently active (`app/Config/Filters.php` has
      no CSP entry in `$globals` — confirmed by inspection) so the CDN
      scripts won't be blocked; no CSP config changes needed for this task.

#### Key Files

- `app/Views/layouts/master.blade.php`

#### Verification

Load any existing page (e.g. `/products`) in a browser and confirm via
devtools console that `window.Alpine` and `window.htmx` are defined, with no
console errors.

---

### Task [1.4]: Classic controller skeleton and routes [DONE]

#### Subtasks

- [ ] Create `app/Controllers/Classic.php` extending `WebController`
      (per `CLAUDE.md`: web pages extend `WebController`, not
      `BaseController`), following the thin-controller pattern in
      `app/Controllers/Products.php`.
- [ ] Add `index(): string` rendering a new `classic.index` Blade view
      (built out in Phase 4) — for now a minimal placeholder view is fine.
- [ ] Add empty stub methods for the JSON endpoints implemented in Phases 2–3
      (`listKeys`, `createKey`, `deleteKey`, `deleteAllKeys`, `migrate`),
      each returning `$this->response->setJSON([...])` — bodies filled in by
      later tasks.
- [ ] Register routes in `app/Config/Routes.php`: `GET /classic` →
      `Classic::index`, and `POST /classic/keys/{list,create,delete,
delete-all,migrate}` → the corresponding methods.

#### Key Files

- `app/Controllers/Classic.php` (new)
- `app/Config/Routes.php`
- `app/Views/classic/index.blade.php` (new, placeholder)

#### Verification

`vendor/bin/phpunit` passes; `GET /classic` returns 200 and `POST
/classic/keys/list` returns a JSON response (stub) when hit manually or via
a basic `FeatureTestTrait`-based test.
