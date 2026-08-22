# Phase 3: Migrate

Depends on: Phase 2 (`OutlineService::createKey`, `listKeys`).

### Task [3.1]: Duplicate-name suffix resolution [DONE]

#### Subtasks

- [ ] Add a pure, easily-unit-testable method — e.g.
      `OutlineService::resolveUniqueName(string $requested, array
$existingNames, array $reservedInBatch): string` — that returns
      `$requested` unchanged if it doesn't collide with `$existingNames` or
      `$reservedInBatch`, otherwise appends `_2`, `_3`, ... (underscore,
      matching the current app exactly — confirmed in
      `CURRENT_FEATURES.md`) until unique against both sets.
- [ ] Keep this method free of I/O (no Outline calls) so it can be unit
      tested directly with plain arrays.

#### Key Files

- `app/Libraries/OutlineService.php`

#### Verification

`vendor/bin/phpunit --filter=OutlineServiceTest` — table-test
`resolveUniqueName()` against: no collision, single collision (`_2`),
multiple prior collisions (`_2` and `_3` both taken → `_4`), and a name only
colliding with `$reservedInBatch` (not yet created on the destination).

---

### Task [3.2]: Migrate batch endpoint [DONE]

#### Subtasks

- [ ] Add `OutlineService::migrateKeys(array $sourceKeys, string
$destApiUrl, array $onlyNames = []): array`. `$sourceKeys` is the
      already-loaded source key list (name required per key); `$onlyNames`,
      when non-empty, restricts processing to those names (used by retry).
      Behavior: - Check the destination is reachable (a `listKeys()` call) before any
      writes; if it fails, throw `OutlineRequestException` immediately —
      matches "destination checked before work begins." - Fetch destination's existing key names once, then process source
      keys sequentially: resolve a unique name via
      `resolveUniqueName()` (tracking names reserved within this batch as
      it goes), create the key on the destination via `createKey()`. - Continue past individual failures — never let one exception stop
      the batch. - Return per-key results: `{name, status: 'success'|'failed',
  renamed_from?, accessUrl?, error?}`.
- [ ] Implement `Classic::migrate()`: validate `sourceKeys` (array) and
      `destApiUrl` (string) from POST body; optional `onlyNames` (array) for
      retry; call `migrateKeys()`; return the full results JSON.
- [ ] No separate "retry" endpoint — retry re-calls the same
      `POST /classic/keys/migrate` with `onlyNames` set to the failed names
      from the prior response; the client (Phase 4) merges the new results
      into the existing list, keeping prior successes untouched.

#### Key Files

- `app/Libraries/OutlineService.php`
- `app/Controllers/Classic.php`

#### Verification

`vendor/bin/phpunit --filter=OutlineServiceTest` — feature/unit test with a
faked destination client: (a) full batch with one induced failure and one
induced name collision confirms continue-on-error and `renamed_from`
reporting; (b) a second call with `onlyNames` set to the failed key confirms
only that key is retried.
