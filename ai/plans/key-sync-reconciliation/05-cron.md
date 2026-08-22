# Phase 5: Reconciliation Cron

Depends on: Phase 1 (`diffServer()`, `createFromOutlineKey()`), Phase 3
(the same auto-import path used by Sync now's no-date case).

### Task [5.1]: `servers:sync` Spark command

#### Subtasks

- [ ] Create `app/Commands/SyncServers.php` extending CI4's `BaseCommand`,
      registered as `subscriptions_sync` / callable via
      `php spark servers:sync`. Separate command from
      `subscriptions:expire` (Automated expiry job) — a distinct concern,
      run on its own schedule.
- [ ] For each active saved server (`SavedServersService::list()` filtered
      to `active`), call `diffServer($serverId)`: - `foundOnServer` → for each key, call `createFromOutlineKey()` with
      `expiryDate = addMonthsClamped(today, 1)` (1-month default term —
      same as the no-date Sync-now import path). - `missingOnServer` → for each subscription, call
      `CockpitService::deleteItem('subscriptions', $id)` directly.
- [ ] Continue past per-server and per-item failures — one server's
      unreachability or one Cockpit write failure doesn't stop processing
      the rest. Log every failure via `log_message('error', ...)`.
- [ ] Print an end-of-run summary to stdout (e.g. "Imported: N, Removed: M,
      Failed: K").
- [ ] Document the suggested crontab entry as a note in this task (e.g.
      `10 0 * * * cd /path/to/app && php spark servers:sync`, daily,
      offset a few minutes from `subscriptions:expire`'s 00:05 UTC slot to
      avoid both jobs hitting Cockpit/Outline at the exact same moment) —
      a deploy-time note, not installed by the app itself.

#### Key Files

- `app/Commands/SyncServers.php` (new)

#### Verification

Run `php spark servers:sync` against test data
(`vendor/bin/phpunit --filter=SyncServersTest` using CI4's command test
helpers, or a manual run against a sandboxed Cockpit/Outline setup):
confirms orphan keys across all active servers get imported with the
correct default expiry, stale ledger records get removed, inactive servers
are skipped entirely, and a simulated failure on one server doesn't prevent
processing the others.
