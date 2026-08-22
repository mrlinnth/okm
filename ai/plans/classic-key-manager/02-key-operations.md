# Phase 2: Key Operations

Depends on: Phase 1 (`OutlineService` transport, `Classic` controller/routes).

### Task [2.1]: List keys (merged with transfer metrics) [DONE]

#### Subtasks

- [ ] Add `OutlineService::listKeys(string $apiUrl): array` — calls the
      Outline Access Keys endpoint and the transfer-metrics endpoint, merges
      them into one array per key (`id`, `name`, `accessUrl`, `bytesUsed`),
      matching the current app's automatic-key-loading behavior.
- [ ] Add a small formatting helper (e.g. `formatBytes(int $bytes): string`)
      producing `B`/`KB`/`MB`/`GB` output, matching the current app's usage
      display.
- [ ] Implement `Classic::listKeys()`: validate `apiUrl` from the POST body
      (required, string), call `OutlineService::listKeys()`, catch
      `OutlineRequestException` and return its message as a 422/502 JSON
      error, otherwise return the key list as JSON.

#### Key Files

- `app/Libraries/OutlineService.php`
- `app/Controllers/Classic.php`

#### Verification

`vendor/bin/phpunit --filter=OutlineServiceTest` (extend from Phase 1) and a
feature test hitting `POST /classic/keys/list` with a mocked/faked
`OutlineService` (inject via `Services::injectMock('outline', $fake)` or
constructor override) confirming the merged shape and formatted usage.

---

### Task [2.2]: Create key [DONE]

#### Subtasks

- [ ] Add `OutlineService::createKey(string $apiUrl, string $name): array` —
      performs the Outline create-key call, then the separate rename call to
      apply `$name` (two API calls, matching current app), returns the final
      key record.
- [ ] Implement `Classic::createKey()`: validate `apiUrl` and `name`
      (required, non-empty string) from POST body, call the service method,
      return the created key as JSON; surface `OutlineRequestException`
      message on failure.

#### Key Files

- `app/Libraries/OutlineService.php`
- `app/Controllers/Classic.php`

#### Verification

Feature test for `POST /classic/keys/create` with a faked `OutlineService`
confirming both the create and rename calls are invoked (in order) and the
response reflects the requested name.

---

### Task [2.3]: Delete key [DONE]

#### Subtasks

- [ ] Add `OutlineService::deleteKey(string $apiUrl, string $name): void` —
      re-fetches the server's key list to resolve the Outline key ID by
      `name` (matching current app — do not assume a cached ID), then calls
      the Outline delete-key endpoint by ID. Throw `OutlineRequestException`
      if no key matches `name`.
- [ ] Implement `Classic::deleteKey()`: validate `apiUrl` and `name` from
      POST body, call the service method, return a success JSON response or
      the error message on failure.

#### Key Files

- `app/Libraries/OutlineService.php`
- `app/Controllers/Classic.php`

#### Verification

Feature test for `POST /classic/keys/delete` confirming the service
re-resolves the ID by name before deleting, and returns an error when the
name isn't found.

---

### Task [2.4]: Delete all keys [DONE]

#### Subtasks

- [ ] Add `OutlineService::deleteAllKeys(string $apiUrl): array` — lists all
      keys, then deletes each sequentially, continuing past individual
      failures (do not let one exception abort the loop); returns an array
      of per-key results (`name`, `status: 'deleted'|'failed'`, `error?`)
      plus aggregate `deleted`/`failed` counts.
- [ ] Implement `Classic::deleteAllKeys()`: validate `apiUrl`, call the
      service method, return the full results JSON (deleted/failed counts +
      per-key details) — do not simplify the error text, per
      `CURRENT_FEATURES.md`'s "errors shown without simplifying" convention.

#### Key Files

- `app/Libraries/OutlineService.php`
- `app/Controllers/Classic.php`

#### Verification

`vendor/bin/phpunit --filter=OutlineServiceTest` — add a test that induces
one failure among several keys (fake client throws for one key) and asserts
processing continues for the rest and the result summary is accurate.
