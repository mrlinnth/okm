# Progress

## Current

- **Feature**: recipient-public-page
- **Task**: Complete
- **Branch**: feature-recipient-public-page
- **Started**: 2026-08-28
- **Status**: Recipient public page feature complete

### Notes

- Task 1.1 is complete: public recipient contact configuration, 60-second
  Cockpit token lookup, and live active/disabled/expired/not-found resolution.
- Focused Docker PHPUnit verification passed (32 tests, 10,051 assertions).
- Task 1.2 is complete: the public `/s/{token}` controller and route resolve
  recipient state server-side without admin authentication.
- Full Docker PHPUnit verification passed (114 tests, 10,249 assertions).
- Task 1.3 is complete: standalone Myanmar recipient page with active-key
  copy, unavailable states, and config-driven Telegram/Viber footer.
- Final verification passed: 118 PHPUnit tests, plus Tailwind CSS build.
- The Docker CLI image lacks npm, so the CSS build was run with the checked-in
  local Node toolchain after Docker PHPUnit verification.

## Up Next

- Automated expiry job is the next planned feature.

## Blockers

- None.
