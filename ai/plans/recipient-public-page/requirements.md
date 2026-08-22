# Recipient Public Page — Requirements

## Overview

A public, unauthenticated page at `/s/:token` where a recipient views their own
Outline key and copies it in one tap. The token in the URL is the sole access
gate — no secret phrase, no account, no QR code (per `ai/PRD.md`'s global
out-of-scope list). Content is entirely in Myanmar (Burmese); there is no
English fallback or language switcher.

This feature depends on Subscription ledger, which is already planned and
provides the `token`, `accessUrl`, `status`, `expiryDate`, and `recipientName`
fields this page reads.

## UI reference

Matches the prototype's "Recipient Page" screen (`ai/prototype/blueprint.md`),
minus its demo-only elements:

- No preview toggle (Active/Disabled/Expired) — that only exists in
  `index.html` to demo the three states.
- No "Back to admin" link and no admin nav — the real page has no path back
  to `/subscriptions`.
- Standalone public layout, card wrapper carries `lang="my"`.

## Lookup and states

`GET /s/{token}` looks up the subscription by `token`
(`SubscriptionsService`, short-TTL cache — see Caching below) and renders one
of four states:

1. **Active** — `status === 'active'` AND `expiryDate >= today`: recipient
   name, expiry date, monospace `accessUrl` box, full-width Copy button
   (label flips to "Copied!" for 1.5s on click).
2. **Unavailable — disabled** — `status === 'disabled'`: lock icon, a
   disabled-specific message, no key or copy button shown.
3. **Unavailable — expired** — `status === 'active'` AND `expiryDate <
today`: lock icon, an expired-specific message. This is derived live from
   the date at request time, not from a stored "expired" status — it must be
   correct even before the Automated expiry job feature exists.
4. **Unavailable — unknown token** — no subscription matches: same
   unavailable card shape as above, but with a generic, non-specific message
   (not "expired", not "disabled", not a 404/error page). This avoids
   confirming or denying that a token ever existed.

The contact footer ("Need help? Message your admin" + Telegram/Viber buttons)
renders in every state, including unavailable ones.

## Configuration

New `app/Config/Recipient.php` (`BaseConfig`) holding `telegramHandle` and
`viberNumber` (or a Viber deep-link template). The footer reads from this
config rather than hardcoding the prototype's placeholder strings. Real
values are supplied by the developer before shipping; the config ships with
the same placeholder-style defaults as the prototype in the meantime.

## Caching

The token lookup uses a short TTL (30–60s), distinct from the project's
default Cockpit collection cache TTL, so that admin actions on the
subscription (disable, reroll, delete, extend, enable) are reflected on the
recipient's page promptly rather than after the standard longer TTL.

## Business rules and edge cases

- No route back to admin, no status-preview control — prototype-only, not
  part of the real page.
- The Copy button copies `accessUrl` exactly as stored on the subscription
  record — the same field Subscription ledger's create/reroll/move flows
  write.
- No rate limiting or brute-force protection on token guesses. Relying on
  token entropy alone, consistent with the PRD's explicit "the token in the
  URL is the only gate" scope.
- No page-visit analytics or tracking.
- Read-only page — no action beyond copying the key is available here.

## Acceptance criteria

- A valid active token shows the correct recipient name, expiry date, key,
  and a working copy button with "Copied!" feedback — all Myanmar text.
- A disabled-status token shows the unavailable card with a disabled-specific
  message and no key.
- An active-status token with a past `expiryDate` shows the unavailable card
  with an expired-specific message, computed live (no dependency on a cron
  job).
- An unknown/garbage token shows the unavailable card with a generic message,
  behaviorally indistinguishable (no leak) from a real disabled/expired
  token.
- The contact footer is present in every state, with values sourced from
  `Config\Recipient`.
- No admin-only UI elements are present (nav, back link, preview toggle).
- The page is responsive per the PRD's mobile-first cross-cutting
  requirement.

## Out of scope

- English or bilingual support — Myanmar only, no i18n layer
- QR codes, secret phrases, accounts (per PRD global out-of-scope)
- The Automated expiry job itself — this page only derives "expired" live
  from `expiryDate`; it does not run any scheduled job or mutate records
- Any edit/action capability on this page beyond copying the key
- Rate limiting or token brute-force protection
