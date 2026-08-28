# Phase 1: Service Foundation

Depends on: Saved servers registry (`SavedServersService`,
`app/Controllers/Servers.php`) and Classic key manager
(`app/Libraries/OutlineService.php`) — both reused here.

### Task [1.1]: Date math helper — month-end-safe extension [DONE]

#### Subtasks

- [ ] Create `app/Libraries/SubscriptionsService.php`. Add a static or
      instance helper `addMonthsClamped(\DateTimeImmutable $from, int
    $months): \DateTimeImmutable` that adds calendar months correctly,
      clamping to the target month's last day when the source day doesn't
      exist there (e.g. Jan 31 + 1 month → Feb 28 or 29, not Mar 3). Do not
      use naive `modify("+{$months} month")`, which overflows past
      month-end — implement via day-of-month comparison against
      `cal_days_in_month()` or equivalent.
- [ ] This one helper is shared by both New-subscription duration
      calculation (Phase 2) and Extend (Phase 3) — implement it once here,
      not duplicated later.

#### Key Files

- `app/Libraries/SubscriptionsService.php` (new)

#### Verification

`vendor/bin/phpunit --filter=SubscriptionsServiceTest` — add
`tests/unit/SubscriptionsServiceTest.php` table-testing `addMonthsClamped`:
mid-month dates, Jan 31 → Feb (28 and 29, test both a leap and non-leap
year), and a normal 31-day → 31-day case.

---

### Task [1.2]: Token generation [DONE]

#### Subtasks

- [ ] Add `SubscriptionsService::generateToken(): string` — cryptographically
      random (e.g. `bin2hex(random_bytes(16))` or base64url of
      `random_bytes(16)`), URL-safe, sufficient entropy to serve as the sole
      access gate for `/s/:token` (no secret phrase backing it up, per
      `requirements.md`).

#### Key Files

- `app/Libraries/SubscriptionsService.php`

#### Verification

`vendor/bin/phpunit --filter=SubscriptionsServiceTest` — confirm generated
tokens are URL-safe and don't collide across a large sample (e.g. 10,000
generations, no duplicates).

---

### Task [1.3]: Controller skeleton and routes

#### Subtasks

- [ ] Create `app/Controllers/Subscriptions.php` extending `WebController`,
      following the thin-controller pattern used in `Classic.php` and
      `Servers.php`.
- [ ] Add `index(): string` rendering a new `subscriptions.index` Blade view
      (placeholder for now, built in Phase 5).
- [ ] Add empty stub methods for the endpoints built in Phases 2–4
      (`store`, `update`, `extend`, `setExpiry`, `enable`, `disable`,
      `reroll`, `move`, `delete`), each returning a JSON stub.
- [ ] Register routes in `app/Config/Routes.php` per the table in
      `requirements.md`'s "API endpoints" section.
- [ ] Register the service in `app/Config/Services.php`
      (`subscriptions()` method, following the `cockpit()`/`savedServers()`
      pattern).

#### Key Files

- `app/Controllers/Subscriptions.php` (new)
- `app/Config/Routes.php`
- `app/Config/Services.php`
- `app/Views/subscriptions/index.blade.php` (new, placeholder)

#### Verification

`vendor/bin/phpunit` passes; `GET /subscriptions` returns 200.

---

### Task [1.4]: Wire the Saved Servers delete guard

#### Subtasks

- [ ] In `app/Controllers/Servers.php::delete()`, before calling
      `SavedServersService::delete()`, count `subscriptions` records with a
      matching `serverId` (e.g. `SubscriptionsService::countByServer(string
    $serverId): int`, using `getCollectionCached('subscriptions', ['filter'
    => ['serverId' => $serverId]])`). Reject with a 422 JSON error (e.g.
      "Cannot delete a server with N active subscriptions — deactivate it
      instead") if the count is greater than zero.
- [ ] This closes the follow-up documented in
      `ai/plans/saved-servers-registry/requirements.md`'s "Out of scope"
      section.

#### Key Files

- `app/Controllers/Servers.php`
- `app/Libraries/SubscriptionsService.php`

#### Verification

Feature test on `POST /servers/{id}/delete`: with a faked
`SubscriptionsService::countByServer()` returning `> 0`, confirm the
request is rejected and `SavedServersService::delete()` is never called;
with `0`, confirm deletion proceeds as before.
