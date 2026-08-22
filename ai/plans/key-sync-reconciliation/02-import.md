# Phase 2: Import on Add Server

Depends on: Phase 1 (`createFromOutlineKey()`), Saved servers registry
(`POST /servers`, `app/Controllers/Servers.php`).

### Task [2.1]: Auto-import existing keys when a server is added

#### Subtasks

- [ ] In `Servers::store()` (Saved servers registry), after the Cockpit
      `servers` item is created and the reachability check has already
      passed: call `OutlineService::listKeys($apiUrl)` on the new server,
      then call `SubscriptionsService::createFromOutlineKey()` for each key
      found, with `expiryDate = addMonthsClamped(today, 1)` (the 1-month
      default term).
- [ ] Continue past individual `createFromOutlineKey()` failures — one bad
      Cockpit write doesn't stop the rest of the import or fail server
      creation itself (the server record is already committed by this
      point).
- [ ] Extend the endpoint's JSON response with an import summary:
      `{imported: <count>, failed: <count>, failures: [{name, error}]}`.
      The server card still appears even if the import summary has
      failures — only server creation itself is a hard failure condition.

#### Key Files

- `app/Controllers/Servers.php`
- `app/Libraries/SubscriptionsService.php` (may need a small batch wrapper,
  e.g. `importAllFromServer(string $serverId, string $apiUrl,
\DateTimeImmutable $expiryDate): array`, to keep the continue-past-
  failures loop out of the controller)

#### Verification

Feature test for `POST /servers`: adding a server with N pre-existing
Outline keys creates N active subscriptions with the correct default
expiry, and the response's `imported` count matches; a simulated Cockpit
write failure for one key is reflected in `failed`/`failures` without
aborting the rest or failing the request.
