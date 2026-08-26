# Outline Key Manager (OKM)

A self-hosted tool for creating and managing Outline VPN access keys and recipient subscriptions. Built on CodeIgniter 4, Blade templating, and Cockpit CMS as the only datastore — no local database, no user accounts. Two superadmins operate it with the Cockpit API token as a shared secret; recipients get a public link to their own key.

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
```

`CI_ENVIRONMENT = development` is required locally (enables debug output and is not committed). Use `production` for a deployed instance.

Outline servers are **not** configured via `.env` — each server is connected to at request time by pasting its exported JSON (see Manual Guide below). This is deliberate: it lets an admin manage multiple Outline servers without wiring credentials into the app config.

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

- The app never persists Outline server credentials — reconnect by re-pasting JSON each session.
- Certificate validation is intentionally disabled for Outline connections (self-signed certs are the norm for Outline servers); this is not a bug.
- There is no authentication on `/classic` itself in this build — treat the URL as admin-only and don't expose it publicly.

## Available Services

```php
use Config\Services;

$this->cockpit  // or Services::cockpit()  - Cockpit CMS API client
$this->outline  // or Services::outline()  - Outline VPN API client
$this->blade    // or Services::blade()    - Blade templating
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
Services::outline()->deleteKey($apiUrl, $name);
Services::outline()->deleteAllKeys($apiUrl);
Services::outline()->migrateKeys($sourceKeys, $destApiUrl, $onlyNames);
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
