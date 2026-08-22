# Automated Expiry Job — Requirements

## Overview

A CLI-triggered background job that finds subscriptions past their grace
period, deletes their Outline key, and marks them `expired`. No UI of its
own — it is backend automation over the Subscription ledger's data
(`ai/plans/subscription-ledger/`).

## Trigger

A new Spark command, `php spark subscriptions:expire`, run by an OS-level
crontab entry (documented here, not automated by the app itself — e.g. daily
at 00:05 UTC, matching the old app's schedule per `ai/CURRENT_FEATURES.md`).
No in-process/on-request triggering and no lazy fallback — CodeIgniter 4 has
no built-in daemon/scheduler, so this is a standalone CLI entry point,
consistent with CI4's stateless request model. Wiring the crontab entry into
the real host is a deploy-time step, not app code.

## Grace period

New `app/Config/Expiry.php` (`BaseConfig`) with `gracePeriodDays = 3` (int,
tunable). A subscription becomes eligible for processing when:

```
today > expiryDate + gracePeriodDays
```

## Selection and processing

- Scan `subscriptions` where `status === 'active'` and eligible per the
  grace-period rule above. Skips `disabled` records (no live key to delete)
  and already-`expired` records (already processed).
- For each eligible record: resolve its saved server via
  `SavedServersService`, then attempt `OutlineService::deleteKey()` on
  `outlineKeyId`.
  - **Success** → set `status = 'expired'` on the Cockpit record.
  - **Failure** → leave the record untouched (still `active`, still
    overdue) — log the failure via CI4's logger. No status change, so the
    next scheduled run retries it automatically.
- Continues past individual failures — one bad record does not stop the
  batch; the command processes every eligible record and reports a
  summary (counts of expired vs. failed) to stdout/log at the end.

## Recipient page fix (bundled in, small)

`SubscriptionsService::resolveRecipientState()` (from Recipient public page,
`ai/plans/recipient-public-page/`) currently only derives `'expired'` live
from `expiryDate` and has no branch for an explicit stored
`status === 'expired'`. This job starts setting that status, so the resolver
needs a matching branch: `status === 'expired'` → `'expired'`, identical to
the derived case — both a not-yet-processed overdue subscription and an
already-processed one must render the same way on `/s/:token`.

## Business rules and edge cases

- Only `status === 'active'` records are ever touched by this job.
- A record is never marked `expired` unless the Outline key delete actually
  succeeded (or the key was already gone — see below).
- If `OutlineService::deleteKey()` fails because the key is already missing
  on the Outline server (e.g. deleted manually, or a prior partial run),
  treat that as success for the purposes of marking `expired` — the desired
  end state (no live key) is already true. Only genuine failures (network
  error, server unreachable, auth failure) count as a failure to retry.
- No admin-UI failure surfacing — log-only via CI4's logger, matching the
  old app's documented behavior.
- No queue, no per-record retry counters — "retry" simply means the record
  stays eligible and gets picked up again on the next scheduled run.

## Acceptance criteria

- Running the command against a subscription overdue by more than the grace
  period deletes its Outline key and sets `status = expired`.
- Running it against a subscription overdue by less than the grace period
  leaves it untouched.
- A simulated Outline delete failure (key exists, delete call errors) leaves
  the record `active` and logs the failure; the record is picked up again on
  a subsequent run.
- A subscription whose key is already gone on the Outline server is still
  marked `expired` (treated as success, not failure).
- Already-`disabled` and already-`expired` records are never touched.
- `/s/:token` renders the same unavailable/expired state whether `status`
  was set by this job or is merely derived live from a past `expiryDate`.

## Out of scope

- Any UI (no ledger badge, no dashboard) for this job's activity
- Installing the actual crontab entry on the host — the plan documents the
  command and suggested schedule; deploying it is a separate ops step
- Retry backoff/scheduling logic beyond "the next scheduled run retries it"
- Key sync/reconciliation (separate feature) — this job only handles
  expiry, not drift detection between Outline and Cockpit
