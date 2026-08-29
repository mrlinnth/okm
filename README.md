# Outline Key Manager (OKM)

A self-hosted tool for creating and managing Outline VPN access keys and recipient subscriptions. Built on CodeIgniter 4, Blade templating, and Cockpit CMS as the only datastore — no local database, no user accounts. Two superadmins operate it behind a single shared password; recipients get a public link to their own key.

It has two areas:

- **Classic Manager** (`/`) — unauthenticated. Connect straight to an Outline server by pasting its JSON; list/create/copy/delete/migrate keys. Nothing is persisted.
- **Subscription management** (`/manage` → `/servers`, `/subscriptions`) — behind the admin password. Saved servers, a subscription ledger, per-recipient public share links, an automated expiry job, and drift reconciliation, all stored in Cockpit.

## Requirements

- PHP 8.5 with extensions: `intl`, `mbstring`, `json`, `libcurl`
- Composer
- Node.js & npm (for Tailwind CSS build)
- Docker + Docker Compose (recommended dev path — see below)
- A Cockpit CMS instance with an API token
- An Outline VPN server (Outline Manager-managed) with its exported JSON config

## Installation

### Option A — Docker (recommended)

```bash
git clone <repository-url> okm && cd okm
composer install
npm install
npm run build:css

cp env .env
# Edit .env — set CI_ENVIRONMENT and Cockpit settings (see Configuration)

docker compose up -d web
```

Visit: `http://localhost:8080`

Run the test suite inside the `cli` container:

```bash
docker compose exec cli vendor/bin/phpunit
```

### Option B — Local PHP

```bash
git clone <repository-url> okm && cd okm
composer install
npm install
npm run build:css

cp env .env
# Edit .env — set CI_ENVIRONMENT and Cockpit settings (see Configuration)

chmod -R 755 writable/
php spark serve
```

Visit: `http://localhost:8080`

## Configuration

Copy `env` to `.env` and set at minimum:

```env
CI_ENVIRONMENT = development

# Cockpit CMS
cockpit.apiUrl = https://your-cockpit-instance.com
cockpit.apiToken = your-api-token

# Admin login for /manage — REQUIRED to use subscription mode.
# An empty value fails closed (nobody can sign in). Use a long random string.
adminaccess.password = 'change-me'

# Optional — recipient page contact footer (defaults shown)
recipient.telegramUsername = 'okm_admin'
recipient.viberNumber = '+959000000000'
```

`CI_ENVIRONMENT = development` is required locally (enables debug output and is not committed). Use `production` for a deployed instance.

Optional throttle knobs for the admin login: `adminaccess.maxAttempts` (default 5) and `adminaccess.throttleSeconds` (default 900).

Outline servers are **not** configured via `.env` — each server is connected to at request time by pasting its exported JSON (Classic Manager), or saved once into Cockpit (Saved Servers). This is deliberate: it lets an admin manage multiple Outline servers without wiring credentials into the app config.

## Manual Guide — Classic Key Manager

The Classic Key Manager (`/classic`) is a two-panel workspace for connecting directly to an Outline server, no saved credentials required.

### 1. Get your server's JSON

In the Outline Manager desktop app, use **Access Key export → Manual mode** (or equivalent) to copy the server's JSON config. It looks like:

```json
{
  "apiUrl": "https://your-outline-server:port/secret-path",
  "certSha256": "..."
}
```

### 2. Connect

1. Open `http://localhost:8080/classic`.
2. Paste the JSON into the **Current server** panel's textarea.
3. Click **Connect**. On success the panel shows the server's host and its existing key list.

### 3. Manage keys

With a server connected:

- **Create** — enter a key name and press Enter (or the Create button) to add a new access key.
- **Copy** — each key row has a copy button for its access-key URL (no QR scan needed).
- **Delete** — remove a single key by name.
- **Delete all** — clears every key on the connected server; per-key results are shown, including any that failed.

### 4. Migrate keys to another server

1. Paste the destination server's JSON into the **Migrate to** panel and click **Connect**.
2. Select which keys from the source panel to migrate (or leave unselected to migrate all).
3. Click **Migrate**. Keys are created on the destination sequentially; a name that already exists on the destination is automatically suffixed to avoid collisions.
4. If any keys fail to migrate, use **Retry** — it re-attempts only the failed entries and merges the results into the original migration, leaving prior successes untouched.

### Notes

- Classic Manager never persists Outline server credentials — reconnect by re-pasting JSON each session.
- Certificate validation is intentionally disabled for Outline connections (self-signed certs are the norm for Outline servers); this is not a bug.
- There is no authentication on `/classic` itself in this build — treat the URL as admin-only and don't expose it publicly.

## Manual Guide — Subscription Management

Everything under `/servers` and `/subscriptions` is behind the admin password.

### 1. Sign in

Go to `/manage`, enter `adminaccess.password`. The session lasts 30 days. Failed logins are rate-limited per IP.

### 2. Saved Servers (`/servers`)

- **Add server** — label, optional public host, and the server JSON. The connection is validated and reachability-checked before it's saved to Cockpit. Any keys that already exist on that server are imported as active subscriptions (1-month term); the success panel reports how many.
- **Sync now** — compares the server's live keys against the ledger. Resolve _found on server_ keys into subscriptions (optionally paste `key_name: date` lines to set expiries), or _missing on server_ rows by removing the stale record. An amber dot on a card means there's something unresolved.
- **Migrate** — moves every subscription on the server to another active server, creating fresh keys with duplicate-name suffixing and best-effort cleanup of the old keys. One-shot; re-run if items fail.
- **Activate / Deactivate** — immediate. **Delete** is blocked while subscriptions reference the server.

### 3. Subscription ledger (`/subscriptions`)

- **New subscription** — pick an active server, recipient name, key name, notes, 1–3 month duration. A success panel gives the copyable recipient link.
- Per row: **Copy key**, **Copy link**, and a menu — Extend, Move, Reroll key, Enable/Disable, Delete. The key name links to the recipient page.
- Filter by recipient text, status, saved server, and "expiring soon".

### 4. Recipient page (`/s/{token}`)

Public, no login, Myanmar copy. Shows the access key + copy button while active and unexpired; an unavailable state otherwise. The Telegram/Viber footer links come from `recipient.telegramUsername` / `recipient.viberNumber`.

### 5. Scheduled jobs

Two Spark commands are meant to run from cron (the app does not install them):

- `subscriptions:expire` — deletes the Outline key and marks `expired` for any subscription past `expiryDate + Config\Expiry::$gracePeriodDays` (default 3 days). Retries failures on the next run.
- `servers:sync` — the same reconcile the _Sync now_ button runs, across every active server: auto-imports orphan Outline keys as subscriptions and auto-removes ledger records whose key is gone.

**Bare-metal / VM** — edit the crontab of the user that owns the app (`crontab -e`):

```cron
5  0 * * *  cd /path/to/okm && php spark subscriptions:expire >> /var/log/okm-cron.log 2>&1
10 0 * * *  cd /path/to/okm && php spark servers:sync        >> /var/log/okm-cron.log 2>&1
```

**Docker Compose** — the `cli` container is always up (`command: tail -f /dev/null`), so run the commands in it from the **host's** crontab:

```cron
5  0 * * *  cd /path/to/okm && docker compose exec -T cli php spark subscriptions:expire >> /var/log/okm-cron.log 2>&1
10 0 * * *  cd /path/to/okm && docker compose exec -T cli php spark servers:sync        >> /var/log/okm-cron.log 2>&1
```

`-T` disables TTY allocation (required from cron). Verify a command works before scheduling it:

```bash
docker compose exec cli php spark servers:sync
# → Imported: 0, Removed: 0, Failed: 0
```

Both jobs are idempotent and log failures to CI4's log (`writable/logs/`), so a missed run is harmless — the next run catches up.

## Available Services

```php
use Config\Services;

Services::cockpit()       // Cockpit CMS API client
Services::outline()       // Outline VPN API client (SSRF-safe)
Services::blade()         // Blade templating
Services::savedServers()  // Saved Servers registry + diff/migrate
Services::subscriptions() // Subscription ledger + expiry/reconciliation
Services::adminAccess()   // Shared-password validation + throttling
```

### Cockpit CMS Methods

```php
$this->cockpit->getSingletonCached($name, $ttl);
$this->cockpit->getCollectionCached($name, $filter, $ttl);
```

### Outline Methods

```php
Services::outline()->listKeys($apiUrl);
Services::outline()->createKey($apiUrl, $name);
Services::outline()->renameKey($apiUrl, $id, $name);
Services::outline()->deleteKey($apiUrl, $name);       // by name
Services::outline()->deleteKeyById($apiUrl, $id);     // by id
Services::outline()->deleteAllKeys($apiUrl);
Services::outline()->migrateKeys($sourceKeys, $destApiUrl, $onlyNames);
Services::outline()->resolveUniqueName($name, $existing, $reserved);
```

## Controller Pattern

All web pages extend `WebController`:

```php
class Classic extends WebController
{
    public function index(): string
    {
        return $this->render('classic.index', ['title' => 'Classic Manager']);
    }
}
```

## Testing

```bash
# Docker
docker compose exec cli vendor/bin/phpunit

# Local
vendor/bin/phpunit
```

## Documentation

| Guide                                   | Description                              |
| --------------------------------------- | ---------------------------------------- |
| [BLADE.md](docs/BLADE.md)               | Complete Blade templating guide          |
| [STYLING.md](docs/STYLING.md)           | Tailwind CSS + daisyUI styling           |
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture rules and project structure |
| [ai/PRD.md](ai/PRD.md)                  | Product requirements and feature scope   |
| [ai/CONSTRAINTS.md](ai/CONSTRAINTS.md)  | Technical stack and conventions          |

## External Resources

- [CodeIgniter 4 User Guide](https://codeigniter.com/user_guide/)
- [BladeOne GitHub](https://github.com/EFTEC/BladeOne)
- [daisyUI Components](https://daisyui.com/components/)
- [Cockpit CMS API](https://getcockpit.com/documentation/api)
- [Outline Server Management API](https://github.com/Jigsaw-Code/outline-server/tree/master/src/shadowbox)

## License

MIT License
