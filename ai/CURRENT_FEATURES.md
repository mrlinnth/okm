# Outline Key Manager — Current Feature Handoff

**Purpose:** a source-verified description of the application as implemented on this branch. It is a baseline for defining a future version, not a proposed scope or a promise of future behavior.

## Product at a glance

Outline Key Manager is a self-hosted web application for operating [Outline](https://getoutline.org/) VPN access keys. It has two modes:

1. **Classic manager** is always available and lets someone connect directly to an Outline management API using its exported server JSON.
2. **Subscription management** appears only when Cockpit CMS credentials and the related environment variables are configured. It adds saved servers, an admin-only subscription ledger, recipient share links, and expiry automation.

The application has no database of its own. Classic-mode state exists only in the browser. Subscription-mode server and subscription records are stored in Cockpit CMS v2.

## Technology and deployment

| Area | Current implementation |
| --- | --- |
| Frontend | React 18 single-page application, built with Vite 5; JavaScript function components and hooks |
| Routing | `react-router-dom` browser routes |
| UI | Plain CSS with shared controls, panels, alerts, badges, and responsive table wrappers |
| QR codes | `qrcode.react` renders share-link and access-key QR codes |
| Backend | Node.js 20 + Express 4, ES modules |
| Outline integration | Native `https.request`, with a 10-second timeout and TLS certificate verification disabled for self-signed Outline servers |
| Subscription datastore | Cockpit CMS v2 Content API over HTTP/2 |
| Production | Multi-stage Docker build; Express serves the built SPA and API from one container on port 3000 |
| Development | Vite dev server proxies `/api` to Express; a separate host-network Compose file supports Outline servers reachable only through a local VPN tunnel |

The repo includes a web-app manifest, standalone display mode, and 192/512px icons. There is no service-worker implementation in the source, so offline behavior is not implemented.

## User-facing functionality

### Classic Outline key manager

- **Connect to an Outline server:** paste the server JSON exported by Outline Manager. Client validation requires parseable JSON with an `https://` `apiUrl`; the certificate fingerprint in the JSON is accepted but not used.
- **Automatic key loading:** after a valid current-server connection is entered, the app fetches access keys and transfer metrics, then shows each key name and formatted usage (B, KB, MB, or GB).
- **Create a key:** create one named key on the connected server. The backend creates it first and then performs the separate Outline rename operation required to apply the requested name.
- **Copy an access key:** copy an individual `ss://` access URL to the clipboard, with short-lived “Copied!” feedback.
- **Remove one key:** confirm and delete a key selected by name. The backend re-fetches the server key list to resolve the Outline key ID before deletion.
- **Delete all keys:** confirm a destructive bulk deletion. Processing continues after per-key failures, then reports the number deleted and failed plus full failure details.
- **Migrate keys to a new server:** load keys from the current/source server, paste destination server JSON, and batch-create corresponding destination keys.
  - The destination is checked before work begins.
  - Existing names are made unique with `_2`, `_3`, and later suffixes.
  - Names allocated during the same batch are also reserved, preventing duplicate names within the batch.
  - The batch is sequential and does not stop after an error.
  - Results show every key, success/failure status, requested vs. final name where renamed, access URL for successes, and full error text for failures.
  - **Retry failed keys** re-checks destination names and retains prior successes.
  - **Start over** clears only the destination connection/results.

### Optional subscription management

This mode is enabled only when `COCKPIT_API_URL`, `COCKPIT_API_TOKEN`, `COOKIE_SECRET`, and `PUBLIC_BASE_URL` are supplied. Otherwise its API routes, UI, cookies, and expiry job are absent and the application remains in classic mode.

#### Admin access and saved servers

- **Admin unlock:** `/subscriptions` requires a server-issued, signed, HTTP-only cookie. The browser submits a dedicated `ADMIN_PASSWORD` when configured; otherwise the backend falls back to the Cockpit API token and emits a startup warning.
- **Lock:** the admin can clear the browser cookie from the subscription UI.
- **Saved-server registry:** admins can save a labeled Outline connection with an optional public-host label. Credentials are kept in Cockpit and are not returned in the normal server list.
- **Connection validation on save:** a saved server must have a unique label, valid JSON, an HTTPS API URL, and a successful Outline key-list request.
- **Automatic key import:** saving a server turns each already-existing Outline key into an active subscription with the key name as recipient/key name, a generated token and secret, and a one-calendar-month expiry. Imports continue after individual Cockpit write failures and show one-time share details plus failures.
- **Server lifecycle:** active/inactive state can be toggled. A saved server cannot be deleted while subscriptions reference it; it must be deactivated instead.
- **Saved-server migration:** choose a source and an active destination saved server. The app recreates source keys on the destination with duplicate suffix handling, processes the full set, and updates subscriptions associated with each migrated source key ID to point to the new key/server/access URL.

#### Subscription ledger and lifecycle

- **Create a subscription:** choose an active saved server; enter recipient name, Outline key name, optional internal notes, and a 1-, 2-, or 3-month duration. A new Outline key, Cockpit record, random landing token, and four-word secret are created.
- **Share details:** after creation or import, show a QR code for the public link, the recipient secret, expiry, and a button that copies a ready-to-send share message.
- **Ledger:** list subscriptions ordered by expiry with recipient/key, visible secret, saved server, editable expiry, status, share-link QR code, and actions.
- **Search and filters:** filter the ledger by recipient-name text, status (active/disabled/expired), saved server, and active subscriptions expiring in seven days; filters can be combined and cleared.
- **Edit recipient/key names:** update the Cockpit record; for an active subscription, changing the key name also renames the corresponding Outline key.
- **Edit expiry:** choose today or a future date. Extension adds one calendar month from the later of now and current expiry, including correct month-end clamping.
- **Secret controls:** copy the visible secret or reset it. Resetting creates a different generated secret and invalidates existing recipient verification cookies.
- **QR controls:** download the per-subscription share-link QR code as PNG or copy that image when the browser supports image clipboard access.
- **Status lifecycle:** disable an active subscription (deletes its Outline key), then enable it later (creates and records a replacement key). Expired subscriptions can also be enabled.
- **Reroll an active key:** create a replacement key and update the ledger before trying to delete the old key. If cleanup fails, the replacement remains usable and the response displays a warning.
- **Move an active subscription:** move it to a different active saved server using a compact, keyboard-dismissable action popover. It creates and records the new key before attempting source cleanup, preserving the new key on cleanup failure.
- **Delete a subscription:** delete its Outline key when it is active, then permanently delete the Cockpit record. This has a browser confirmation.
- **Automated expiry:** on subscription-enabled server startup and daily at 00:05 UTC, records that have been expired longer than the configurable grace period are processed. The job deletes each Outline key and marks successful records `expired`; it continues after failures and retries them on later runs.

#### Recipient-facing public access

- Each subscription has a public landing route at `/s/:token`.
- A recipient enters the supplied four-word secret phrase. Successful verification creates a signed, HTTP-only verification cookie valid for one year.
- A verified recipient sees their name, expiry, copyable Outline access URL, and a QR code only while the subscription is active and unexpired.
- Disabled or expired records show an unavailable state without the access URL.

## UI and interaction model

- Classic mode uses a two-panel current-server → new-server workspace. On wide displays it includes a visual directional connector; on smaller screens panels stack.
- Subscription mode provides a protected overview, saved-servers page, navigation back to classic tools, and a lock action.
- Shared UI primitives supply associated form labels/hints/errors, native disabled states, semantic alerts/status badges, focusable horizontal table scroll regions, responsive layout, and visible focus treatment.
- Async work uses button/status text such as “Loading…”, “Creating…”, “Migrating…”, and “Retrying…”. Errors are shown in the UI without simplifying the underlying API message.
- Destructive browser actions use native confirmation dialogs. The app has no undo, recycle bin, or local client persistence; refreshing loses classic-mode inputs/results.

## Backend HTTP surface

### Always available

| Endpoint | Behavior |
| --- | --- |
| `GET /api/capabilities` | Reports whether subscription mode is enabled. |
| `POST /api/keys/list` | Lists access keys for a supplied `apiUrl`. |
| `POST /api/keys/create` | Creates then names a key for `apiUrl` and `name`. |
| `POST /api/keys/delete` | Deletes the first key matching `name` on `apiUrl`. |
| `POST /api/keys/delete-all` | Lists and attempts to delete every key, returning per-key outcomes. |
| `POST /api/keys/transfer` | Retrieves Outline transfer metrics. |

### Available only in subscription mode

| Route group | Behavior |
| --- | --- |
| `/api/admin` | Unlock, lock, and signed-cookie status. |
| `/api/admin/servers` | Authenticated saved-server list/create/activation/delete operations. |
| `/api/admin/subscriptions` | Authenticated subscription listing, creation, extension, rename, expiry edit, secret reset, reroll, move, enable/disable, delete, and saved-server migration. |
| `/api/public/subscriptions/:token` | Recipient secret verification and verified subscription retrieval. |

## Security and operational behavior

- **Outline request safeguards:** all classic Outline targets must be HTTPS. DNS is resolved before connection, blocked addresses are rejected, and the connection is pinned to the resolved IP to mitigate DNS rebinding.
- **Always-blocked destinations:** unspecified/loopback, link-local, cloud metadata, multicast, and reserved IP ranges. `SSRF_BLOCK_PRIVATE=1` additionally blocks private RFC1918, CGNAT, and IPv6 unique-local ranges; it is off by default so LAN/VPN Outline servers remain possible.
- **Outline TLS:** certificate validation is deliberately disabled to support self-signed Outline servers.
- **Response headers:** CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, and same-origin cross-origin opener policy are sent for all responses.
- **Cookie properties:** admin and recipient cookies are signed, HTTP-only, `SameSite=Lax`, path-wide, and `Secure` in production.
- **Rate limiting:** admin unlock allows 10 attempts per IP/15 minutes with a 30-attempt global cap; public secret verification allows 5 per IP/15 minutes with a 60-attempt global cap.
- **Secret generation:** public landing tokens use random bytes; recipient secrets contain four randomly selected words. Cockpit subscription lookups by token are cached for five minutes and invalidated when relevant writes occur.
- **Error handling:** the backend sends detailed underlying error messages to the client. Batch processes are designed to continue after individual failures.

## Verification currently in the repository

- **Backend:** Node’s built-in test runner covers SSRF/HTTPS restrictions, config/capability behavior, cookies and constant-time secret comparison, rate limits, Cockpit HTTP/2 usage, saved-server import, subscription lifecycle transitions, secret reset, expiry validation/job behavior, and saved-server migration reconciliation.
- **Frontend:** Vitest + Testing Library covers route gating, classic workspace structure, public secret verification, ledger actions/filters/editing, secret controls, QR download/copy behavior, saved-server import display, and migration feedback.
- **UI primitives:** tests cover label/error accessibility, button state/variants, scrollable table semantics, and alert/badge semantics.
- **Build/deploy configuration:** Docker builds the frontend then installs production backend dependencies; `docker-compose.yml` runs the production container on port 3000.

## Constraints, limitations, and known residual risks

These are current facts, included so that a future scope can consciously decide what to change.

- The Outline certificate fingerprint (`certSha256`) is stored for saved servers but is not used for certificate pinning. Since Outline TLS verification is disabled, an on-path attacker could intercept Outline key-management traffic.
- Classic tools are intentionally unauthenticated. A deployment that should not allow public use must enforce access control at its reverse proxy or network boundary.
- There are no user accounts, multi-admin roles, audit logs, owned database, background queue, soft delete/undo, or client-side persistence.
- Expiry is an in-process daily task rather than a durable job scheduler. A missed execution is retried only on a later app run.
- The PWA metadata does not provide offline caching or install-specific behavior beyond manifest support.
- Key operations and migrations are sequential. This makes outcomes predictable but can be slow for large key sets.
- Some lifecycle operations can leave partial external state by design: for example, a new key may exist if a later Cockpit write fails, and old-key cleanup failures after reroll/move are reported but not automatically reconciled.
- Subscriptions use Cockpit’s expected `servers` and `subscriptions` models; the app does not provision those models itself.
- The current stack is JavaScript, React 18, Vite 5, Express 4, and Node 20. No TypeScript, framework upgrade, or standalone database layer is presently used.

## Existing planning documents: do not treat as shipped features

The repository includes historic and completed plan files under `ai/plans/` plus `TODO-v1.md`, `TODO-v2.md`, and `TODO-v3.md`. They are useful context, but this document classifies a capability as current only when it is represented in the application source, configuration, or tests. Any future scope should re-evaluate those documents against this inventory rather than assuming every listed idea is live.
