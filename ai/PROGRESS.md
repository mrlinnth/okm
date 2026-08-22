# Progress

## Current

- **Feature**: classic-key-manager
- **Task**: 2.2 (Create key)
- **Branch**: feature-classic-key-manager
- **Started**: 2026-08-22
- **Status**: Task 2.1 done, implementing 2.2

### Notes

- Feature has 4 plan files, 14 tasks total, 5 complete.
- `OutlineService::listKeys()` calls `/access-keys` then `/metrics/transfer`
  and merges by key id; `formatBytes()` produces B/KB/MB/GB. Both public
  (needed for the `Config\Services::outline()` service + controller use).
- Added `Config\Services::outline()` (mirrors `cockpit()`/`aimeos()`) so
  `Classic` controller resolves `OutlineService` through the service
  container — lets feature tests swap in a fake via `Services::injectMock`.
- `Classic::listKeys()` reads `apiUrl` from the JSON body (`getJSON(true)`),
  422s on missing/invalid, 502s with the exception message on
  `OutlineRequestException`.
- Test convention for faking Outline: anonymous class `extends OutlineService`
  with an empty `__construct()` (skips real config binding) overriding just
  the method under test — see `tests/feature/ClassicControllerTest.php`.
  For unit-level transport tests, use `TestableOutlineService` in
  `tests/unit/OutlineServiceTest.php` (overrides `executeCurl()`,
  supports a `fakeResponseQueue` for multi-call methods like `listKeys()`).
  Always use a literal IP (e.g. `203.0.113.10`) as `apiUrl` in tests that go
  through real `request()` — a hostname needs live DNS resolution.
- Full suite: 18/18 passing.

## Up Next

- 2.3: Delete key
- 2.4: Delete all keys

## Blockers

- None
