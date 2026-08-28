# Progress

## Current

- **Feature**: subscription-ledger
- **Task**: 3.1 (Extend subscription)
- **Branch**: feature-subscription-ledger-create-read-edit
- **Started**: 2026-08-28
- **Status**: Implementing Phase 2 create, list, and edit endpoints

### Notes

- The Cockpit `subscriptions` collection has been created manually with the
  schema in `ai/plans/subscription-ledger/requirements.md`.
- Task 1.1 is complete: `SubscriptionsService::addMonthsClamped()` uses
  `DateTimeImmutable` month-end calculation because the Docker PHP image does
  not provide the calendar extension.
- Task 1.2 is complete: tokens use 16 cryptographically random bytes encoded
  as 32 lowercase hexadecimal characters.
- Task 1.3 is complete: the `/subscriptions` controller, route contract,
  service registration, and placeholder view are ready for later phases.
- Task 1.4 is complete: saved servers with linked subscriptions return a 422
  response instead of being deleted.
- Task 2.1 is complete: subscriptions are retrieved from Cockpit in expiry
  order, and only active saved servers are supplied to the ledger view.
- Task 2.2 is complete: valid requests create an active Outline key and a
  Cockpit subscription with a month-clamped expiry and recipient share link.
- Task 2.3 is complete: active key-name changes sync to Outline before
  Cockpit updates; disabled subscriptions update Cockpit only.
- Docker verification passed: focused date-helper tests (4/4) and full suite
  (72/72).

## Up Next

- 3.1: Extend subscription
- 3.2: Inline expiry edit
- 3.3: Enable and disable

## Blockers

- None.
