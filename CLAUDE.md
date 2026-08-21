# CLAUDE.md

CodeIgniter 4 + Blade + Cockpit CMS + Aimeos starter template.

## Critical Rules

1. **No Models/Entities/Migrations** - All data comes from external APIs
2. **No Authentication** - This is a read-only display application
3. **Web pages extend `WebController`** - Not BaseController
4. **All API calls must be cached** - Use `*Cached()` methods

## Available Services

```php
use Config\Services;

$this->blade    // or Services::blade()    - Blade templating
$this->cockpit  // or Services::cockpit()  - Cockpit CMS API
$this->aimeos   // or Services::aimeos()   - Aimeos e-commerce API
```

## Controller Pattern

```php
class MyPage extends WebController
{
    public function index()
    {
        $content = $this->cockpit->getSingletonCached('homepage');
        $products = $this->aimeos->getProductsCached();
        return $this->render('mypage', compact('content', 'products'));
    }
}
```

## API Methods

**Cockpit CMS:**
- `getSingletonCached($name, $ttl)` - Single content item
- `getCollectionCached($name, $filter, $ttl)` - Content collections

**Aimeos:**
- `getProductsCached()` - All products
- `getProductCached($id)` - Single product
- `getCategoriesCached()` - Categories
- `searchProductsCached($query)` - Search

## Views

- Location: `app/Views/*.blade.php`
- Use Blade syntax: `@extends`, `@section`, `{{ $var }}`
- Helper: `view_blade('viewname', $data)`

## Key Files

| File | Purpose |
|------|---------|
| `app/Controllers/WebController.php` | Base for web pages |
| `app/Libraries/CockpitService.php` | Cockpit API client |
| `app/Libraries/AimeosService.php` | Aimeos API client |
| `app/Config/Services.php` | Service definitions |
| `app/Config/Aimeos.php` | Aimeos configuration |

## Documentation

- [BLADE.md](docs/BLADE.md) - Blade templating guide
- [STYLING.md](docs/STYLING.md) - Tailwind CSS + daisyUI styling
- [ARCHITECTURE.md](docs/ARCHITECTURE.md) - Detailed architecture rules
