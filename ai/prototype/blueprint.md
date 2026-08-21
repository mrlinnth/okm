# Prototype Blueprint
Generated: 2026-08-21

## Navigation Structure
- Sidebar (desktop, ≥lg breakpoint) / top nav with slide-out drawer (mobile)
- Nav items: Subscriptions, Saved Servers, Classic Manager
- Secondary link (outside main nav): "Preview recipient page" — demo affordance only, not part of the real admin nav
- State variable: `page` on root `x-data`, values: `subscriptions` (default), `servers`, `classic`, `recipient`

## Screens

### Screen: Subscriptions
**Route/State:** x-show="page === 'subscriptions'"
**Components:**
- Header: title + "New subscription" button
- Filter bar: recipient search (live text filter), status select (all/active/disabled/expired), saved-server select, "Expiring soon" checkbox
- Desktop: table with 8 sample rows — Recipient/key name, Server, Status badge (+ "soon" tag), Expiry, Actions (Copy + kebab menu)
- Mobile: same 8 records as stacked cards — recipient/status up top, server/expiry row, full-width Copy button + kebab menu
- Kebab menu (both layouts): Extend, Move, Reroll key, Enable/Disable (label flips with state), Delete (red)
- Empty state: "No subscriptions match these filters" when the filtered list is empty
**Interactions:**
- Type in search → list filters live by recipient name
- Change status / server select, or toggle "Expiring soon" → list filters live
- Click Copy → button label flips to "Copied!" for 1.5s
- Click kebab → dropdown opens (click outside closes it)
- Extend → one click, no modal → adds 1 month from whichever is later, today or the current expiry (menu item label stays "Extend")
- Expiry date (table cell and mobile card) is click-to-edit → clicking the date text swaps it for a native date input; picking a date or clicking away saves it immediately, no modal, no separate confirm step
- Move → modal with server select → updates the row's server
- Reroll key → menu item label flips to "New key issued" for 1.8s, no modal
- Enable/Disable → immediate toggle, badge color updates, no confirmation
- Delete → confirm modal (red action) → removes row from the list
- New subscription → modal form (recipient, key name, saved server, duration 1/2/3 months) → Create adds a row at the top and opens a Success panel showing the recipient, expiry, and a copyable share link (no QR, per PRD)

### Screen: Saved Servers
**Route/State:** x-show="page === 'servers'"
**Components:**
- Header: title + "Add server" button
- Grid of server cards (1 col mobile, 2 cols tablet+) — label, host, status badge, subscription count, action row
- Per-card actions: Sync now (with an amber dot indicator when unresolved diff items exist), Migrate, Activate/Deactivate, Delete (disabled + tooltip when subCount > 0, matching "must deactivate before delete")
- Two sample servers, consistent with the Subscriptions screen: Contabo SG (5 subs) and Azure SG (3 subs)
**Interactions:**
- Add server → modal (label, optional public host, server JSON textarea) → Save adds the card and opens an Import success panel reporting how many existing keys were imported as subscriptions (mocked as 4)
- Sync now → modal comparing live server keys vs ledger records, two sections: "Found on server, not in ledger" (with an optional textarea to paste `key_name: date` lines — Import uses the matching date if present, otherwise falls back to a default term; resolved rows show which expiry was actually applied) and "In ledger, missing on server" (Remove record action per item); shows "Everything's in sync" when both are empty; resolved items flip their button label and turn green
- Migrate → modal, pick an active destination server (source excluded) → moves the subscription count over, zeroes out the source
- Activate/Deactivate → immediate toggle, no confirmation
- Delete → disabled with a tooltip while the server has subscriptions; no working delete path in this screen since neither sample server reaches zero subscriptions

### Screen: Classic Manager
**Route/State:** x-show="page === 'classic'"
**Components:**
- Two-panel workspace: "Current server" (left) and "Migrate to" (right), with a directional arrow connector between them on lg+ screens; panels stack vertically on mobile (no connector shown)
- Current server panel: JSON textarea + Connect (before connection); once connected, shows label, 5 sample keys (name + usage), Copy/Delete per key, Create key and Delete all buttons
- Migrate-to panel: disabled state ("Connect a current server first") until the current server is connected; then its own JSON textarea + Connect; once connected, a "Migrate N keys" button and a results list
**Interactions:**
- Connect (either panel) → validates the pasted text loosely (must start with `{` and contain `apiUrl`); shows an inline error otherwise; on success shows a mock label and, for the current server, loads 5 sample keys
- Start over (either panel) → disconnects and clears that panel's state; on the current-server panel this also resets the migrate panel
- Copy key → button label flips to "Copied!" for 1.5s
- Delete key → confirm modal → removes it from the list
- Create key → modal with a name field → appends a new key with 0 B usage
- Delete all → confirm modal → result panel showing deleted/failed counts and a per-key status list (mocked with one simulated failure once there are more than 2 keys, to demonstrate "continues after failures")
- Migrate → button shows "Migrating…" for ~900ms, then a per-key result list: success (with a "renamed from X" note when a duplicate name was resolved), or failed (with an error message). One key is mocked to fail and one to get a duplicate-name suffix, to demonstrate both paths
- Retry failed keys → shown only when a failed row exists → flips failed rows to success
- Start over (migrate results) → clears the destination connection and results, back to the JSON textarea

### Screen: Recipient Page
**Route/State:** x-show="page === 'recipient'"
**Language:** Myanmar (Burmese) for all real recipient-facing text — greeting, expiry line, copy button, status messages, contact footer. This is the only screen in Myanmar; every other screen (including this page's demo-only preview toggle and back link) stays English. Card wrapper carries `lang="my"`. App name "OKM" and the Telegram/Viber labels are left untranslated (brand names).
**Components:**
- Standalone public layout — no sidebar, no admin nav, minimal header with just the app name
- Demo-only "Preview:" segmented control (Active / Disabled / Expired) — dashed border to visually mark it as prototype-only, not part of the real page
- Card: recipient name, then either the active state (expiry line, monospace key box, full-width Copy key button) or the unavailable state (lock icon, "expired"/"disabled" message, contact-admin note) — no key or copy action shown when not active
- Contact footer (inside the card, below a divider, shown in every state): "Need help? Message your admin" + Telegram and Viber link buttons. URLs are placeholders (`t.me/okm_admin`, a Viber deep link with a placeholder number) — swap in the real handle/number before shipping
**Interactions:**
- Preview toggle → switches which of the three states the card shows
- Copy key → button label flips to "Copied!" for 1.5s (active state only)
- "Back to admin (preview only)" link — demo-only, returns to Subscriptions. Not part of the real product (the real recipient page has no admin path back, and no status toggle — the token in the URL determines which state renders)

## Modals
- None yet

## Shared Components
- **Desktop sidebar:** fixed 240px column, app wordmark, 3 nav buttons, secondary recipient-preview link
- **Mobile top nav:** sticky header with hamburger, opens a right-side drawer with the same 3 nav items
- **Mobile drawer:** slide-out panel, dismissible via backdrop click or close button
