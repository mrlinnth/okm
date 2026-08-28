# Progress

## Current

- **Feature**: key-sync-reconciliation
- **Task**: Complete (pending manual UI verification)
- **Branch**: feature-key-sync-reconciliation
- **Started**: 2026-08-28
- **Status**: All 13 tasks implemented; full suite green

### Notes

- Phase 1: `SavedServersService::diffServer()`,
  `SubscriptionsService::createFromOutlineKey()`.
- Phase 2: `importAllFromServer()` wired into `Servers::store()`; response
  carries an `import` summary.
- Phase 3: `Servers::sync` / `syncImport` / `syncRemove` +
  `resolveFoundOnServer()` / `removeRecord()` + routes.
- Phase 4: `SavedServersService::migrate()` → `migrateAllToServer()`
  (create-before-destroy, collision suffixing, inactive repoint) +
  `Servers::migrate` + route.
- Phase 5: `php spark servers:sync` (crontab note `10 0 * * *`). A
  smoke-test run on 2026-08-28 created 57 real subscription records in live
  Cockpit from pre-existing key drift — user chose to keep them (1-month
  expiry, will edit manually).
- Phase 6: `app/Views/servers/index.blade.php` — Sync now button + amber
  unresolved-diff dot (best-effort `POST /servers/{id}/sync` on load), Sync
  now modal (two sections, green-flip on resolve, "Everything's in sync"),
  Migrate modal (active-destination picker + results panel, no retry), Add
  Server success panel with import summary.
- Verification: full suite 165 tests green. `ServersControllerTest`
  renders the rebuilt blade successfully. **Phase 6 interactive behaviour
  (modals, fetches against real Outline drift) not yet manually verified —
  needs a browser session with the admin password.**

## Up Next

- Manual smoke test of the Saved Servers UI (sync/migrate/import panels).
- No further planned features after this one.

### UI fixes (commit 0837cc4)

- Stylesheet was served `immutable`/1-year with no cache-buster, so CSS
  changes never reached returning visitors → added `?v={filemtime}` to
  every `<link href="/css/output.css">`.
- Every modal was `class="modal modal-open"` (hidden only via Alpine
  `x-show`), so daisyUI 5's `:root:has(.modal-open){overflow:hidden}`
  locked page scroll permanently → switched to
  `class="modal" :class="{ 'modal-open': state }"` across classic /
  subscriptions / servers views.
- Removed dead daisyUI-v4 classes (`input-bordered`, `select-bordered`,
  `textarea-bordered`, `label-text`); servers Add/Migrate fields now use
  the v5 `fieldset` / `fieldset-legend` idiom.

## Blockers

- None.
