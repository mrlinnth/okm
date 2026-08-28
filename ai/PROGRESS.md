# Progress

## Current

- **Feature**: subscription-ledger
- **Task**: 1.3 (Controller skeleton and routes)
- **Branch**: feature-subscription-ledger-foundation
- **Started**: 2026-08-28
- **Status**: Implementing Phase 1 service foundation

### Notes

- The Cockpit `subscriptions` collection has been created manually with the
  schema in `ai/plans/subscription-ledger/requirements.md`.
- Task 1.1 is complete: `SubscriptionsService::addMonthsClamped()` uses
  `DateTimeImmutable` month-end calculation because the Docker PHP image does
  not provide the calendar extension.
- Task 1.2 is complete: tokens use 16 cryptographically random bytes encoded
  as 32 lowercase hexadecimal characters.
- Docker verification passed: focused date-helper tests (4/4) and full suite
  (72/72).

## Up Next

- 1.3: Controller skeleton and routes
- 1.4: Saved Servers delete guard

## Blockers

- None.
