# Phase 1: Recipient Public Page

Depends on: Subscription ledger (`app/Libraries/SubscriptionsService.php`,
the `subscriptions` Cockpit collection with `token`, `accessUrl`, `status`,
`expiryDate`, `recipientName` fields).

### Task [1.1]: Recipient config, token lookup & state resolution [DONE]

#### Subtasks

- [ ] Create `app/Config/Recipient.php` (`BaseConfig`) with `telegramHandle`
      and `viberNumber` (or a Viber deep-link template) properties, defaulted
      to the same placeholder-style values as the prototype
      (`t.me/okm_admin`, a placeholder Viber number/link).
- [ ] Add `SubscriptionsService::findByToken(string $token): ?array` — looks
      up the subscription by `token` via `CockpitService::getCollectionCached`
      with a filter on `token`, using a short TTL (30–60s) distinct from the
      project's default Cockpit collection TTL — pass an explicit `$ttl`
      argument rather than relying on the default, so admin actions
      (disable, reroll, delete, extend, enable) show up on the recipient
      page promptly. Returns `null` when no record matches.
- [ ] Add `SubscriptionsService::resolveRecipientState(?array $subscription):
  string` — pure function, no I/O: - `null` → `'not_found'` - `status === 'disabled'` → `'disabled'` - `status === 'active'` AND `expiryDate < today` → `'expired'`
      (derived live from the date — do not rely on a stored "expired"
      status; the Automated expiry job feature does not exist yet) - `status === 'active'` AND `expiryDate >= today` → `'active'`

#### Key Files

- `app/Config/Recipient.php` (new)
- `app/Libraries/SubscriptionsService.php`

#### Verification

`vendor/bin/phpunit --filter=SubscriptionsServiceTest` — unit test
`resolveRecipientState()` against all four cases (not found, disabled,
active-but-past-expiry, active-and-current), including a boundary case where
`expiryDate` is exactly today (should resolve `'active'`).

---

### Task [1.2]: Controller and route [DONE]

#### Subtasks

- [ ] Create `app/Controllers/Recipient.php` extending `WebController`,
      following the thin-controller pattern used in `Classic.php` /
      `Servers.php` / `Subscriptions.php`.
- [ ] Add `show(string $token): string` — calls
      `SubscriptionsService::findByToken()` then `resolveRecipientState()`,
      renders `recipient.show` passing the subscription (if any), the
      resolved state, and `Config\Recipient` values needed for the footer.
      No JSON branch — this is a server-rendered page only.
- [ ] Register the route in `app/Config/Routes.php`:
      `GET /s/(:any) → Recipient::show/$1`, public, outside any admin
      grouping.

#### Key Files

- `app/Controllers/Recipient.php` (new)
- `app/Config/Routes.php`

#### Verification

`vendor/bin/phpunit` passes. Feature test: `GET /s/{valid-token}` returns 200
with the active-state view data; `GET /s/{disabled-token}` and
`GET /s/{unknown-token}` both return 200 (not 404) with unavailable-state
data — confirms no information leak via status code.

---

### Task [1.3]: View — Myanmar recipient page, all states [DONE]

#### Subtasks

- [ ] Create `app/Views/recipient/show.blade.php` — standalone public
      layout (no admin nav, no sidebar), card wrapper with `lang="my"`,
      matching the prototype's "Recipient Page" screen minus its demo-only
      preview toggle and "Back to admin" link.
- [ ] Active state: recipient name, expiry date, monospace `accessUrl` box,
      full-width Copy button — Alpine `x-data` copies `accessUrl` to the
      clipboard and flips the label to "Copied!" for 1.5s, reusing the same
      copy-button interaction pattern already used in Classic key manager
      and Subscription ledger.
- [ ] Unavailable states (`disabled` / `expired` / `not_found`): lock icon +
      per-state Myanmar message text — `disabled` and `expired` get distinct,
      specific wording; `not_found` gets a generic message that does not
      read as "expired" or "disabled" (avoids confirming whether the token
      ever existed). No key box or copy button in any unavailable state.
- [ ] Contact footer, shown in every state: "Need help? Message your admin" + Telegram and Viber buttons sourced from `Config\Recipient`
      (`telegramHandle`, `viberNumber`), not hardcoded.
- [ ] Responsive per the PRD's mobile-first requirement (single-card layout,
      no desktop/mobile split needed given the prototype's design).

#### Key Files

- `app/Views/recipient/show.blade.php` (new)

#### Verification

Visit `/s/{token}` for an active, a disabled, an expired (active status,
past `expiryDate`), and an unknown token; confirm each renders the correct
state with correct Myanmar text, the copy button works and shows "Copied!"
only in the active state, and the contact footer appears in all four cases
with values pulled from `Config\Recipient`.
