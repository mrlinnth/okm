# CLAUDE.md

Outline Key Manager — a CodeIgniter 4 + BladeOne app on top of Cockpit CMS.
Built from the `ci4-cockpit-starter` template; the Aimeos pieces from that
template are unused.

## Critical Rules

1. **No Models/Entities/Migrations** — all data lives in Cockpit CMS
   (`servers`, `subscriptions` collections) or on Outline servers. There is
   no local database.
2. **Two areas, two auth stances:**
   - `/classic` and `/s/{token}` are **public** (unauthenticated by design).
   - Everything under `/servers` and `/subscriptions` sits behind the
     `adminauth` + `csrf` filters — a shared password (`adminaccess.password`)
     unlocks a signed session. See `app/Filters/AdminAuthFilter.php`.
3. **Web pages extend `WebController`** — not `BaseController`.
4. **Cache every Cockpit read** — use the `*Cached()` helpers. The
   reconciliation diff and recipient token lookup use a short (~60s) TTL.
5. **The Outline client is SSRF-safe** — never bypass `OutlineService`'s
   request path (HTTPS-only, DNS-resolve-before-connect, blocked-range
   checks, IP pinning). TLS verification is deliberately off for
   self-signed Outline servers.

## Services

```php
use Config\Services;

Services::cockpit()       // Cockpit CMS API client
Services::outline()       // Outline VPN API client (SSRF-safe)
Services::blade()         // BladeOne templating
Services::savedServers()  // Saved Servers registry: CRUD, diffServer(), migrate()
Services::subscriptions() // Subscription ledger: lifecycle, expiry, reconciliation
Services::adminAccess()   // Shared-password validation + per-IP throttling
```

## Controller pattern

Thin controllers — validation and delegation only, no business logic.

```php
class Subscriptions extends WebController
{
    public function index(): string
    {
        return $this->render('subscriptions.index', [
            'title'         => 'Subscriptions',
            'subscriptions' => Services::subscriptions()->list(),
        ]);
    }
}
```

## Spark commands (cron, not app-installed)

- `php spark subscriptions:expire` — expire subscriptions past their grace period
- `php spark servers:sync` — reconcile every active saved server against the ledger

## Views

- `app/Views/**/*.blade.php`, rendered via `Services::blade()` / `$this->render()`.
- Blade syntax: `@extends`, `@section`, `{{ $var }}`, `@php(...)`.
- daisyUI 5 + Tailwind v4. Rebuild CSS with `npm run build:css` after class
  changes — the stylesheet is cache-busted by file mtime in the layout.
- Modals: `class="modal" :class="{ 'modal-open': state }"` — never a static
  `modal-open` (daisyUI's `:has(.modal-open)` locks page scroll).

## Key files

| File                                                                        | Purpose                      |
| --------------------------------------------------------------------------- | ---------------------------- |
| `app/Controllers/WebController.php`                                         | Base for web pages           |
| `app/Controllers/{Classic,AdminAccess,Servers,Subscriptions,Recipient}.php` | Feature controllers          |
| `app/Libraries/OutlineService.php`                                          | SSRF-safe Outline API client |
| `app/Libraries/CockpitService.php`                                          | Cockpit API client           |
| `app/Libraries/{SavedServers,Subscriptions,AdminAccess}Service.php`         | Business logic               |
| `app/Filters/AdminAuthFilter.php`                                           | Admin session gate           |
| `app/Commands/{ExpireSubscriptions,SyncServers}.php`                        | Cron jobs                    |
| `app/Config/{Services,Routes,Expiry,AdminAccess,Recipient}.php`             | Wiring & config              |

## Testing

```bash
docker compose exec cli vendor/bin/phpunit   # or: vendor/bin/phpunit
```

## Documentation

- [README.md](README.md) — setup, operator guide, config keys
- [ai/CURRENT_FEATURES.md](ai/CURRENT_FEATURES.md) — source-verified feature inventory
- [docs/BLADE.md](docs/BLADE.md), [docs/STYLING.md](docs/STYLING.md), [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
