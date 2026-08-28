# Outline Key Manager — Current Feature Inventory

**Purpose:** a source-verified description of the application as implemented
on `develop`. This replaces the earlier inventory, which described the
pre-rewrite React/Express app used as the planning baseline.

## Product at a glance

Outline Key Manager (OKM) is a self-hosted web app for operating
[Outline](https://getoutline.org/) VPN access keys. It has two areas:

1. **Classic Manager** (`/classic`, also `/`) — always available,
   unauthenticated. Connect straight to an Outline management API by pasting
   its exported server JSON; list, create, copy, delete, and migrate keys.
   No state is persisted; everything lives in the browser for that session.
2. **Subscription management** (`/manage`, `/servers`, `/subscriptions`) —
   gated by a shared admin password. Saved Outline servers, a subscription
   ledger, per-recipient public share links, an expiry job, and drift
   reconciliation. All records are stored in Cockpit CMS v2.

There is no local database and no user accounts. Recipients reach their own
key through a public tokenised URL with no login and no secret phrase.

## Technology and deployment

| Area                 | Implementation                                                                                                                                                |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Language / framework | PHP 8.5 (`strict_types` everywhere), CodeIgniter 4.6                                                                                                          |
| Templating           | BladeOne 4.x (`@extends`/`@section`, `Services::blade()`)                                                                                                     |
| Frontend             | Server-rendered Blade + Alpine.js 3 (CDN) + htmx 2 (CDN); no build-time JS bundle                                                                             |
| CSS                  | Tailwind CSS v4 + daisyUI v5, compiled to `public/css/output.css` (`npm run build:css`), cache-busted by file mtime                                           |
| Datastore            | Cockpit CMS v2 Content API over HTTPS — `servers` and `subscriptions` collections (the app does not provision the models)                                     |
| Outline integration  | SSRF-safe cURL client: HTTPS-only, DNS-resolve-before-connect, blocked-range rejection, IP pinning; TLS verification disabled for self-signed Outline servers |
| Dev / deploy         | Docker Compose — `web` on port 8080, a `cli` container for `phpunit` / `spark`. `php spark serve` also works locally.                                         |
| Background jobs      | Two Spark commands run from OS cron (not installed by the app)                                                                                                |

## User-facing functionality

### Classic Manager (`/classic`)

- **Connect:** paste Outline server JSON. Loose client + server validation
  (parseable JSON, `https://` `apiUrl`). The `certSha256` fingerprint is
  accepted but unused.
- **Key list:** on connect, fetches access keys merged with transfer
  metrics; each row shows the name and formatted usage (B/KB/MB/GB).
- **Create key:** create then rename (two Outline calls) to apply the name.
- **Copy key:** copy an `ss://` URL with brief "Copied!" feedback.
- **Delete one key** by name (re-resolves the Outline id first).
- **Delete all keys:** confirm; continues past per-key failures; reports
  deleted/failed counts and full failure detail.
- **Migrate to another server:** connect a destination, pick keys (or all),
  batch-create on the destination. Duplicate names get `_2`/`_3` suffixes
  (names reserved within the batch too); sequential; continues past errors;
  per-key results with requested-vs-final name and error text. **Retry
  failed** re-runs only failures and keeps prior successes.

### Admin access gate (`/manage`)

- **Sign in:** a single shared password (`adminaccess.password`). An empty
  value fails closed — nobody can sign in. Successful auth regenerates the
  session and sets `adminAuthenticated`.
- **Throttling:** failed attempts are rate-limited per IP
  (`adminaccess.maxAttempts` / `adminaccess.throttleSeconds`, default 5 /
  900s) via CI4's Throttler.
- **Session:** file-based CI4 session, 30-day lifetime, id regenerated with
  destroy on rotation.
- **Gate:** every `/servers*` and `/subscriptions*` route runs the
  `adminauth` + `csrf` filters. Unauthenticated `GET` redirects to
  `/manage`; unauthenticated `POST`/JSON returns `401 {error, login}` so
  the SPA-ish pages can bounce to the login screen.
- **Logout:** destroys the session.
- The nav shows a **Manage** link to guests and **Subscriptions / Saved
  Servers / Logout** once signed in.

### Saved Servers (`/servers`)

- **Add server:** unique label, optional public-host label, server JSON.
  Validated (JSON, HTTPS `apiUrl`) and reachability-checked (a live
  key-list request) before the Cockpit `servers` record is written. The
  stored `serverJson` credential blob is never returned to the page.
- **Import on add:** immediately after creating the server, every existing
  Outline key on it is turned into an active subscription (recipient name =
  key name = the key name, generated token, `today + 1 month` expiry).
  Continues past individual Cockpit write failures; the response carries an
  `{imported, failed, failures[]}` summary shown in the success panel.
- **Activate / deactivate:** immediate toggle, no confirm.
- **Delete:** blocked (422) while any subscription references the server —
  deactivate instead.
- **Sync now:** compares the server's live Outline keys against its ledger
  records and shows two sections:
  - _Found on server, not in ledger_ — with an optional textarea for
    `key_name: date` lines. A matched, valid, today-or-future date is used
    as the expiry; anything else falls back to `today + 1 month`. Creates
    one subscription per key; per-key results flip resolved rows green.
  - _In ledger, missing on server_ — a **Remove record** button per row
    (deletes only the Cockpit record; the Outline key is already gone).
  - Shows "Everything's in sync" when both are empty.
  - An amber dot on each server card marks unresolved differences
    (best-effort check on page load; omitted if the check fails).
- **Migrate:** move every subscription on this server (any status) to
  another active server. Active subscriptions get a fresh key on the
  destination (collision-suffixed, tracked within the batch), the record is
  repointed, then the old key is best-effort deleted — a cleanup failure is
  reported as a warning, never an item failure. Inactive subscriptions are
  just repointed. One-shot run with a full per-subscription results panel;
  no retry button. After a full migrate the source has zero subscriptions
  and becomes eligible for Delete.

### Subscription ledger (`/subscriptions`)

- **Create:** active saved server + recipient name + key name + optional
  notes + 1/2/3-month duration. Creates an Outline key, a Cockpit record,
  and an immutable landing token; a success panel shows the expiry and a
  copyable recipient link. No QR, no secret phrase.
- **List:** ordered by expiry. Desktop table / mobile cards. Each row:
  recipient name (click-to-edit), key name (links to the recipient page),
  saved server, status badge (+ "soon" within 7 days), click-to-edit
  expiry, and actions.
- **Actions:** Copy key, Copy link, and a kebab menu — Extend (one calendar
  month from the later of today / current expiry, month-end clamped), Move
  (replacement key on another active server), Reroll key (replacement key
  on the same server), Enable/Disable, Delete (with confirm). Move / Reroll
  create the new key before deleting the old one and surface a warning if
  cleanup fails.
- **Search & filters:** recipient text, status, saved server, and
  "expiring soon" (active, within 7 days) — combinable.
- Rename of an active subscription's key name also renames the Outline key.

### Recipient public page (`/s/{token}`)

- Standalone page, Myanmar (Burmese) copy, no admin nav, no header bar.
- **Active + unexpired:** recipient name, expiry date, the `ss://` access
  URL, and a copy button.
- **Disabled / expired / unknown token:** an unavailable state with no
  access URL. `status === 'expired'` (set by the job) and a derived
  past-`expiryDate` render identically.
- **Contact footer:** Telegram and Viber links built from
  `recipient.telegramUsername` and `recipient.viberNumber`.

### Automated jobs (cron)

- **`php spark subscriptions:expire`** — finds `active` subscriptions past
  `expiryDate + Config\Expiry::$gracePeriodDays` (default 3), deletes each
  Outline key, marks the record `expired`. A key that is already gone
  counts as success; a genuine failure leaves the record untouched for the
  next run. Logs failures, prints `Expired: N, Failed: M`.
- **`php spark servers:sync`** — for every active saved server, runs the
  same diff as _Sync now_: auto-imports orphan keys (`today + 1 month`
  term) and auto-removes stale ledger records. Continues past per-server
  and per-item failures; logs them; prints `Imported / Removed / Failed`.
- Neither command is scheduled by the app. Suggested crontab: `5 0 * * *`
  and `10 0 * * *` respectively.

## Backend HTTP surface

| Method | Route                                                                   | Filters         | Purpose                    |
| ------ | ----------------------------------------------------------------------- | --------------- | -------------------------- |
| GET    | `/`, `/classic`                                                         | —               | Classic Manager page       |
| POST   | `/classic/keys/{list,create,delete,delete-all,migrate}`                 | —               | Classic key operations     |
| GET    | `/manage`                                                               | —               | Admin login page           |
| POST   | `/manage`                                                               | csrf            | Authenticate               |
| POST   | `/manage/logout`                                                        | adminauth, csrf | Sign out                   |
| GET    | `/servers`                                                              | adminauth, csrf | Saved Servers page         |
| POST   | `/servers`                                                              | adminauth, csrf | Add server (+ import)      |
| POST   | `/servers/{id}/{activate,deactivate,delete}`                            | adminauth, csrf | Server lifecycle           |
| POST   | `/servers/{id}/sync`                                                    | adminauth, csrf | Reconciliation diff        |
| POST   | `/servers/{id}/sync/{import,remove}`                                    | adminauth, csrf | Resolve a diff section     |
| POST   | `/servers/{id}/migrate`                                                 | adminauth, csrf | Bulk migrate subscriptions |
| GET    | `/subscriptions`                                                        | adminauth, csrf | Subscription ledger page   |
| POST   | `/subscriptions`                                                        | adminauth, csrf | Create subscription        |
| POST   | `/subscriptions/{id}`                                                   | adminauth, csrf | Rename recipient / key     |
| POST   | `/subscriptions/{id}/{extend,expiry,enable,disable,reroll,move,delete}` | adminauth, csrf | Lifecycle operations       |
| GET    | `/s/{token}`                                                            | —               | Recipient public page      |

## Security and operational behaviour

- **Outline client safeguards:** HTTPS-only, DNS resolved before connect,
  blocked ranges (loopback, link-local, cloud metadata, multicast,
  reserved) rejected, connection pinned to the resolved IP (DNS-rebinding
  mitigation). Configurable `SSRF_BLOCK_PRIVATE` extends this to RFC1918 /
  CGNAT / IPv6 ULA; off by default so LAN/VPN Outline servers work.
- **Outline TLS:** certificate validation deliberately disabled
  (self-signed Outline servers are the norm).
- **CSRF:** session-based tokens, non-regenerating (admin pages fire
  several AJAX requests from one rendered token); sent via `X-CSRF-TOKEN`
  header from a `csrf_meta()` tag rendered only for signed-in admins.
- **Admin auth:** shared-password → signed session cookie; per-IP throttle
  on failed logins.
- **Credential handling:** saved-server `serverJson` is stored in Cockpit
  and stripped from every list/JSON response and rendered page.
- **Caching:** all Cockpit reads go through `*Cached()` helpers; the
  reconciliation diff and recipient token lookup use a short (~60s) TTL and
  are never persisted.

## Verification in the repository

- **PHPUnit** (`vendor/bin/phpunit`, run in the `cli` container): 165 tests
  covering the Outline SSRF/HTTPS client, Cockpit client, saved-servers and
  subscriptions services (diff, migrate, expiry, reconciliation,
  create-before-destroy), the admin auth service and filter, all
  controllers (feature tests with an injected admin session + CSRF), and
  the two Spark commands.
- Blade views are exercised through the feature tests that render them.
- The Saved Servers Sync now / Migrate / import-summary UI has been
  manually smoke-tested against live Outline drift.

## Constraints, limitations, known residual risks

- `certSha256` is stored but not used for pinning; with TLS verification
  off, an on-path attacker could intercept Outline management traffic.
- `/classic` is intentionally unauthenticated — restrict it at the reverse
  proxy / network boundary if the deployment shouldn't be public.
- No user accounts, roles, audit log, owned database, job queue, or
  soft-delete / undo.
- Expiry and sync are cron-triggered Spark commands, not a durable
  scheduler; a missed run is only retried on the next run.
- Key operations and migrations are sequential — predictable but slow for
  large key sets.
- Some lifecycle operations can leave partial external state by design (a
  new key may exist if a later Cockpit write fails; old-key cleanup
  failures after reroll/move/migrate are reported, not auto-reconciled).
- Cockpit `servers` / `subscriptions` models must already exist.
