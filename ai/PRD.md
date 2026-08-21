# Product Requirements Document
Generated: 2026-08-21
Confirmed by developer: yes

## Overview
A rewrite of Outline Key Manager (OKM) — a self-hosted tool for creating and managing Outline VPN access keys and recipient subscriptions. The rewrite keeps the same job the current app does but moves to a simpler stack (CodeIgniter 4 monolith on top of Cockpit CMS) and reworks the UI and a few workflows based on real pain points from running the current version.

## Problem
The current React + Express app is hard to use on mobile, which is how it's mostly operated day to day. Looking up a specific recipient's key takes too many steps (scan their QR, enter their secret, then see the key). Keys created outside the app (via the Outline Manager Linux app) silently drift out of sync with the subscription records in Cockpit.

## Target Users
Two superadmins, authenticated with the Cockpit API token as a shared secret — no user accounts. Roughly 60 recipients, each with a public link to their own key/subscription status page — no accounts, no secret phrase.

## Core Features

### Classic key manager
**Scope:** Connect to an Outline server via its exported JSON. List, create, copy, and delete keys. Delete-all. Migrate keys between servers with duplicate-name handling, sequential processing, and retry-on-failure.
**Dependencies:** None
**Priority:** 1

### Saved servers registry
**Scope:** Store Outline server credentials in Cockpit instead of pasting JSON on every visit. Label servers, activate/deactivate, migrate subscriptions between saved servers.
**Dependencies:** None
**Priority:** 1

### Subscription ledger
**Scope:** Create, edit, extend, disable, enable, reroll, move, and delete subscriptions, each tied to a saved server and an Outline key. Search and filter by recipient, status, and saved server.
**Dependencies:** Saved servers registry
**Priority:** 1

### Recipient public page
**Scope:** `/s/:token` shows the recipient's key and a copy button directly — no secret phrase prompt, no QR code. The token in the URL is the only gate.
**Dependencies:** Subscription ledger
**Priority:** 1

### Automated expiry job
**Scope:** Daily cron deletes the Outline key for any subscription past its configurable grace period and marks the record expired. Continues past individual failures, retries on the next run.
**Dependencies:** Subscription ledger
**Priority:** 1

### Admin inline key copy
**Scope:** A copy button sits directly on each ledger row. Replaces the current flow of scanning a QR and entering a secret just to see a key.
**Dependencies:** Subscription ledger
**Priority:** 2

### Key sync / reconciliation
**Scope:** A "Sync now" action per saved server compares the Outline server's live key list against Cockpit subscription records and shows a diff. The same check also runs as part of the daily cron, so drift caused by keys created outside the app (e.g. the Outline Manager Linux app) gets caught automatically.
**Dependencies:** Saved servers registry
**Priority:** 2

Mobile-first UI is a cross-cutting requirement rather than a standalone feature: every screen above is built responsive (card layouts on small screens, tables on larger ones) from the start using Tailwind + daisyUI, not retrofitted afterward.

## Out of Scope
- User accounts of any kind, for admins or recipients
- Recipient secret phrases (dropped — the link token is the only access control)
- QR codes, anywhere in the app (admin and recipient sides both move to copy-link)
- TLS certificate pinning for Outline connections (same as current app — certificate validation stays disabled to support self-signed Outline servers)

## Success Criteria
Qualitative: the app is easier to maintain solo, faster to extend with new features, and noticeably better to use on a phone. Specific pain points fixed: admin key lookup is one click instead of scan-and-type, and subscription records stay in sync with whatever actually exists on the Outline server.

## References
- `CURRENT_FEATURES.md` — source-verified feature inventory of the current app
- `ci4-cockpit-starter` (github.com/mrlinnth/ci4-cockpit-starter) — base template for this rewrite, already wired to Cockpit CMS
