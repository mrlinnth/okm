# Progress

## Current

- **Feature**: — (none in progress)
- **Task**: —
- **Branch**: develop
- **Status**: All six planned features shipped and merged to `develop`.

### Notes

- Every plan under `ai/plans/` is complete (60/60 tasks marked `[DONE]`):
  classic-key-manager, saved-servers-registry, subscription-ledger,
  recipient-public-page, automated-expiry-job, key-sync-reconciliation.
- Plus the admin access gate (`/manage` shared-password login) — built
  ad-hoc, no plan directory. See `ai/PRD.md`.
- Verification: full PHPUnit suite green (165 tests). The Saved Servers
  Sync now / Migrate / import-summary UI has been manually smoke-tested
  against live Outline drift.
- `feature-key-sync-reconciliation` was fast-forward merged into `develop`
  and deleted; `develop` == `origin/develop`.

### Operational notes

- Two Spark commands are meant to run from cron (not installed by the app):
  - `php spark subscriptions:expire` — daily ~00:05 UTC
  - `php spark servers:sync` — daily ~00:10 UTC (offset from the above)
- `.env` keys added since the PRD: `adminaccess.password` (required for
  subscription mode), `recipient.telegramUsername`, `recipient.viberNumber`.

## Up Next

- No planned features remain. Future work starts with feature-planner.

## Blockers

- None.
