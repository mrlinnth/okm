# Architecture Rules

Detailed architecture guidelines for this CodeIgniter 4 + Cockpit CMS + Aimeos starter project.

## Core Principles

### What This Project Does
- Uses Blade templating engine for views
- Consumes APIs from Cockpit CMS and Aimeos
- Displays content fetched from external services
- Demonstrates headless CMS/e-commerce integration patterns

### What This Project Does NOT Do
- Use a local database (all data from APIs)
- Implement user authentication/authorization
- Store or persist data locally

## Architecture Rules

### 1. No Models or Entities
- **Rule**: Do NOT create local CodeIgniter 4 Models or Entities
- **Reason**: All data comes from external APIs
- **Instead**: Use `CockpitService` or `AimeosService`

### 2. No Migrations
- **Rule**: Do NOT create CodeIgniter 4 Migrations
- **Reason**: No local database; data structures managed externally

### 3. Controller Inheritance

**WebController** - For all HTML pages:
```php
class Home extends WebController
{
    public function index()
    {
        $data = $this->cockpit->getSingletonCached('homepage');
        return $this->render('home', ['data' => $data]);
    }
}
```
- Extends `BaseController`
- Auto-initializes `$this->cockpit`, `$this->aimeos`, `$this->blade`
- Provides `render()` method

**BaseController** - Keep clean for API/CLI:
- Do NOT add web-specific initialization
- Reserve for REST APIs, CLI commands, cron jobs

### 4. Services Layer
Access libraries via `Config\Services`:
```php
Services::blade()    // BladeView instance
Services::cockpit()  // CockpitService instance
Services::aimeos()   // AimeosService instance
```

### 5. Caching Strategy
All API calls MUST be cached:
```php
// Cockpit
$data = $this->cockpit->getSingletonCached('homepage', 1800);
$posts = $this->cockpit->getCollectionCached('posts', ['published' => true]);

// Aimeos
$products = $this->aimeos->getProductsCached();
$product = $this->aimeos->getProductCached($id);
```
Default TTL: 3600 seconds (1 hour)

### 6. Data Flow Pattern
```
Controller (WebController)
    ↓
CockpitService / AimeosService (with caching)
    ↓
External API (Cockpit CMS / Aimeos)
    ↓
Blade View (via BladeView)
```

## Development Guidelines

1. **API Consumption**: All data from Cockpit CMS or Aimeos APIs
2. **No Database**: Do not add database models or migrations
3. **No Auth**: Do not implement authentication features
4. **Keep It Simple**: Focus on API integration patterns
5. **Cache Everything**: Always use `*Cached()` methods for API calls

## Project Structure

```
ci4/
├── app/
│   ├── Config/              # Configuration files
│   │   ├── Routes.php       # Application routes
│   │   ├── Services.php     # Service definitions
│   │   └── Aimeos.php       # Aimeos configuration
│   ├── Controllers/         # Application controllers
│   │   └── WebController.php # Base for web pages
│   ├── Views/               # Blade templates (*.blade.php)
│   │   ├── layouts/         # Master layouts
│   │   └── components/      # Reusable components
│   └── Libraries/           # BladeView, CockpitService, AimeosService
├── public/
│   ├── css/
│   │   ├── input.css        # Tailwind + daisyUI input
│   │   └── output.css       # Compiled CSS
│   └── index.php            # Entry point
├── writable/
│   └── cache/blade/         # Compiled Blade templates
├── .env                     # Environment configuration
├── composer.json            # PHP dependencies
└── package.json             # Node dependencies
```

## Resources

- [CodeIgniter 4 Documentation](https://codeigniter.com/user_guide/)
- [Laravel Blade Syntax](https://laravel.com/docs/blade)
- [BladeOne GitHub](https://github.com/EFTEC/BladeOne)
- [Cockpit CMS API](https://getcockpit.com/documentation/api)
- [Aimeos JSON API](https://aimeos.org/docs/latest/frontend/jsonapi/)
