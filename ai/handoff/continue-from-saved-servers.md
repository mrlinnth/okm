# Handoff: continue OKM after saved-servers-registry

**Generated**: 2026-08-28
**For**: GPT (or any agent) picking up the remaining features
**Repo**: CodeIgniter 4 + BladeOne + Cockpit CMS. No local DB. See `CLAUDE.md`.

---

## TL;DR — where things stand

| Feature                 | State                                                                       |
| ----------------------- | --------------------------------------------------------------------------- |
| classic-key-manager     | ✅ 14/14, merged to `develop`                                               |
| saved-servers-registry  | ✅ 10/10 — **3 commits sit on branch `feature-servers-ui`, NOT yet merged** |
| subscription-ledger     | ⬜ 0/18 — **do this next**                                                  |
| key-sync-reconciliation | ⬜ 0/12                                                                     |
| recipient-public-page   | ⬜ 0/3                                                                      |
| automated-expiry-job    | ⬜ 0/3                                                                      |

**First actions:**

1. Ask the user to merge `feature-servers-ui` (`git-local-merge`) — or confirm it's merged — before starting new work.
2. Read `ai/CONSTRAINTS.md`, `ai/PRD.md`, then `ai/plans/subscription-ledger/requirements.md` + its 5 numbered plan files.
3. Follow the task-runner loop (below), starting at subscription-ledger Task 1.1.

---

## How to work here (task-runner workflow)

The plan files (`ai/plans/<feature>/NN-*.md`) already break every task into
subtasks with **Key Files** and **Verification** blocks. Work them in filename
order. Per task:

1. Re-read the task section. Run the **plan freshness check**: for each Key
   File, a "create" file that already exists or a "modify" file that's missing
   = drift → stop and tell the user.
2. Implement only that task.
3. Verify with the command in the plan (default: `docker compose exec -T cli vendor/bin/phpunit`).
4. Append ` [DONE]` to the task heading in the plan file.
5. Update `ai/PROGRESS.md` (current task, notes).
6. Commit: `git-workflow-commit 'feat(N.N): <description>'`.

**Branching / git:**

- `git-workflow-start feature <branch-name>` before a feature/phase (creates `feature-<branch-name>`).
- `git-workflow-commit '<prefix>: <msg>'` — prefixes: `feat: fix: refactor: chore: docs:`, optional scope `feat(1.2): ...`.
- `git-workflow-end` when done.
- **The USER runs `git-local-merge`** to fold a branch into `develop` — don't
  merge or push yourself. After each feature/phase, ask: PR, local merge, or
  leave.
- ⚠️ `git-workflow-commit` does `git add -A`. Never leave temp/scratch files in
  the tree at commit time (put them in the scratchpad dir instead).

**Autonomous mode is OFF** — pause at confirmation gates. But the user has been
running `git-local-merge` after every phase, so keep phases small.

---

## Dev environment

- `docker compose up -d web` → app at `http://localhost:8080`.
- `docker compose exec -T cli vendor/bin/phpunit` — full suite (currently **68 passing**).
- `docker compose exec -T cli vendor/bin/phpunit --filter=SomeTest` — one class.
- `docker compose exec -T cli php spark <cmd>` — CLI / spark commands.
- CSS: `public/css/output.css` is a **committed build artifact**. After editing
  any Blade view, run `npm run build:css` (Tailwind v4 + daisyUI). A
  `npm run watch:css` may be running and can cause noisy output.css diffs —
  Tailwind v4 auto-scans the whole repo (it ignores `tailwind.config.js`
  globs). Not worth fighting; just don't be surprised.

---

## Cockpit (the datastore) — IMPORTANT

- Live instance `https://cms.hiyan.xyz`, real API token already in `.env`
  (`cockpit.apiUrl`, `cockpit.apiToken`). Reads + writes verified working.
- **No migrations. New collections must be created by hand in the Cockpit
  admin UI before you write code against them.** Each feature's
  `requirements.md` has a "Cockpit schema" section. **subscription-ledger
  needs a new `subscriptions` collection** — ask the user to create it first
  (schema in `ai/plans/subscription-ledger/requirements.md`; includes a
  `serverId` Link → `servers`).
- Cockpit v2 Content API shapes (confirmed live):
  - read one: `GET /api/content/item/{model}` / `.../item/{model}/{id}`
  - read many: `GET /api/content/items/{model}`
  - create/update: `POST /api/content/item/{model}` body `{"data": {...}}` —
    update carries `_id` inside `data` (partial updates work).
  - delete: `DELETE /api/content/item/{model}/{id}`
  - auth header: `api-key: <token>`. A bad token → HTTP **412**
    `{"error":"Authentication failed"}` (unusual status, that's Cockpit).
- `app/Libraries/CockpitService.php` already has generic, cache-invalidating
  `createItem` / `updateItem` / `deleteItem` — **reuse them, don't add per-collection write code.**

---

## Conventions & gotchas (learned building the first two features)

- **`declare(strict_types=1)` in every new PHP file.** Do NOT retrofit it to
  old starter files (`CockpitService.php` deliberately has none — adding it
  risks breaking its untyped read methods).
- **Services**: `Services::cockpit()`, `Services::outline()`,
  `Services::savedServers()`. Register new ones in `app/Config/Services.php`
  following that exact pattern (`getSharedInstance`).
- **Controllers are thin** — validation + delegation only. Business logic goes
  in a `*Service` under `app/Libraries/`. Private helper methods on the
  controller are fine for request-shape mapping (see `Servers::presentServer`).
- **Never leak credentials to the client.** `Servers::presentServer()` trims
  `serverJson` out of every response. Do the same for `token`/`accessUrl`
  where the ledger UI doesn't need them.
- **SSRF-safe Outline calls**: always go through `OutlineService`
  (`Services::outline()`). It does HTTPS-only + DNS-resolve-before-connect +
  blocked-range + IP-pinning. Never write raw curl to an Outline server.
- **Testing pattern**:
  - Unit: subclass the service as `TestableX` and override a narrow transport
    seam — `CockpitService::sendWrite()`, `OutlineService::executeCurl()`.
    See `tests/unit/CockpitServiceTest.php`, `tests/unit/OutlineServiceTest.php`.
  - Feature: `use FeatureTestTrait;` + `Services::injectMock('name', $fake)` +
    `Services::reset()` in tearDown. See `tests/feature/ServersControllerTest.php`.
  - A fake that `extends` a real service must **not be `final`** and must
    override the constructor to a no-op: `public function __construct() {}`.
- **CSRF is disabled** (`app/Config/Filters.php` — `csrf` commented out in
  globals). `fetch()` POSTs need no token.
- **Blade views**: `@extends('layouts.master')`, `@section('content')`.
  daisyUI components (`card`, `btn`, `modal modal-open` + `modal-box` +
  `modal-action` + `modal-backdrop`, `input input-bordered`, `badge`).
  Alpine factory goes in an inline `<script>` inside the section. Seed data
  with `{!! json_encode($x, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) !!}`.
  Match the visual language of `app/Views/classic/index.blade.php` and
  `app/Views/servers/index.blade.php`.
- **Nav**: `app/Views/layouts/master.blade.php` — brand → `/` (Classic
  Manager, the landing page), one nav item "Saved Servers" → `/servers`.
  Add a "Subscriptions" item when the ledger UI lands.
- **Routes** live in `app/Config/Routes.php`, flat (`$routes->get(...)`), no
  groups. Pattern: `/<feature>` GET for the page, `POST /<feature>/...` for
  JSON actions, `POST /<feature>/(:segment)/<action>` for per-item.
- New `BaseConfig` classes: recipient-public-page needs `app/Config/Recipient.php`
  (`telegramHandle`, etc.), automated-expiry-job needs `app/Config/Expiry.php`
  (`int $gracePeriodDays = 3`). Both plan files spell out the fields.

---

## Reference files (read these, don't reproduce)

- `ai/CONSTRAINTS.md` — stack versions, language standards, verification cmd.
- `ai/PRD.md` — product context, feature priorities + dependency graph.
- `ai/prototype/index.html` — the visual reference for every screen (trim
  per each feature's requirements — e.g. saved-servers dropped Sync/Migrate).
- `app/Libraries/CockpitService.php` — the write layer you'll build on.
- `app/Libraries/SavedServersService.php` — the service pattern to copy
  (constructor injection of deps with `Services::` defaults for testability).
- `app/Libraries/OutlineService.php` — Outline API + the `TestableX` seam idea.
- `app/Controllers/Servers.php` + `app/Views/servers/index.blade.php` — the
  full CRUD-feature pattern (controller → service → Cockpit, Blade + Alpine).
- `tests/unit/SavedServersServiceTest.php`, `tests/feature/ServersControllerTest.php`
  — test patterns incl. the `FakeSavedServers` in-memory stand-in.

---

## Recommended order

1. **subscription-ledger** (5 phases, 18 tasks) — needs the `subscriptions`
   Cockpit collection created first. Depends on saved-servers-registry (done).
   Note Task 1.4 wires a delete-guard back into `Servers::delete` (the
   `subCount > 0` guard deliberately deferred in saved-servers).
2. **recipient-public-page** (3 tasks) — `/s/:token` public page. Needs
   `app/Config/Recipient.php`. Depends on subscription-ledger.
3. **automated-expiry-job** (3 tasks) — daily `php spark` cron. Needs
   `app/Config/Expiry.php`. Depends on subscription-ledger + recipient page.
4. **key-sync-reconciliation** (12 tasks) — "Sync now" diff + cron. Depends on
   saved-servers + subscription-ledger.

---

## Open loose ends from this session

- `feature-servers-ui` (3 commits) unmerged — merge before new work.
- saved-servers Add-Server modal was verified by curl + unit tests but **not**
  clicked through in a real browser with a real Outline server JSON (Chrome
  tooling was down). Low risk; worth a 2-min manual check.
- classic-key-manager still wants one live sign-off against a real Outline
  server (per its PROGRESS notes) — not blocking anything.
