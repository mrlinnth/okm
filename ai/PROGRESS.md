# Progress

## Current

- **Feature**: subscription-ledger
- **Task**: 4.4 (Delete subscription)
- **Branch**: feature-subscription-ledger-phase-4
- **Started**: 2026-08-28
- **Status**: Implementing Phase 4 delete endpoint

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
- Task 3.1 is complete: extending starts from the later of today or the
  current expiry and uses month-end-safe date math.
- Task 3.2 is complete: the expiry endpoint accepts exact valid ISO dates
  and rejects past or malformed dates with a 422 response.
- Task 3.3 is complete: disable removes the current Outline key before
  marking the record disabled; enable creates a replacement key without
  changing the expiry date.
- Task 4.1 is complete: replacement keys are saved before old-key cleanup;
  failed cleanup yields a warning while preserving the new active key.
- Task 4.2 is complete: active subscriptions can reroll to a replacement key;
  disabled and expired subscriptions are rejected.
- Task 4.3 is complete: moves validate an active, different destination then
  safely replace the key on that server.
- Docker verification passed: focused subscription service/controller tests (36/36).

## Up Next

- 4.4: Delete subscription

## Blockers

- None.
