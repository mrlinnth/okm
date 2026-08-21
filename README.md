# CodeIgniter 4 + Blade + Cockpit CMS + Aimeos Starter

A modern starter template integrating **CodeIgniter 4**, **BladeOne templating**, **Cockpit CMS** for content management, and **Aimeos** for e-commerce product catalogs. API-driven architecture with no local database required.

## Features

- **CodeIgniter 4** - Lightweight PHP framework
- **BladeOne** - Laravel Blade templating engine
- **Cockpit CMS** - Headless CMS for content via API
- **Aimeos** - E-commerce product catalog via API
- **Tailwind CSS + daisyUI** - Modern styling with components
- **Built-in Caching** - Optimized API calls

## Quick Start

```bash
# Clone and install
git clone <repository-url> && cd ci4
composer install
npm install

# Configure environment
cp env.example .env
# Edit .env with your Cockpit and Aimeos API settings

# Build CSS and start server
npm run build:css
chmod -R 755 writable/
php spark serve
```

Visit: `http://localhost:8080`

## Requirements

- PHP 8.1+ with extensions: `intl`, `mbstring`, `json`, `libcurl`
- Composer
- Node.js & npm
- Cockpit CMS instance with API key
- Aimeos instance with API key (optional)

## Configuration

Add to `.env`:

```env
# Cockpit CMS
cockpit.apiUrl = https://your-cockpit-instance.com
cockpit.apiToken = your-api-token

# Aimeos (optional)
aimeos.apiUrl = https://your-aimeos-instance.com/jsonapi
aimeos.apiToken = your-api-token
```

## Usage

### Controller Pattern

All web pages extend `WebController`:

```php
class Home extends WebController
{
    public function index()
    {
        // Cockpit CMS content
        $content = $this->cockpit->getSingletonCached('homepage');

        // Aimeos products
        $products = $this->aimeos->getProductsCached();

        return $this->render('home', compact('content', 'products'));
    }
}
```

### Blade Views

Views use Blade syntax in `app/Views/*.blade.php`:

```blade
@extends('layouts.master')

@section('content')
    <h1>{{ $content['title'] }}</h1>

    @foreach($products as $product)
        <div class="card">{{ $product['attributes']['product.label'] }}</div>
    @endforeach
@endsection
```

### Available Services

```php
use Config\Services;

Services::blade()    // Blade templating
Services::cockpit()  // Cockpit CMS API
Services::aimeos()   // Aimeos e-commerce API
```

### Cockpit CMS Methods

```php
$this->cockpit->getSingletonCached($name, $ttl);
$this->cockpit->getCollectionCached($name, $filter, $ttl);
```

### Aimeos Methods

```php
$this->aimeos->getProductsCached();
$this->aimeos->getProductCached($id);
$this->aimeos->getCategoriesCached();
$this->aimeos->searchProductsCached($query);
```

## Documentation

| Guide | Description |
|-------|-------------|
| [BLADE.md](docs/BLADE.md) | Complete Blade templating guide |
| [STYLING.md](docs/STYLING.md) | Tailwind CSS + daisyUI styling |
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture rules and project structure |

## External Resources

- [CodeIgniter 4 User Guide](https://codeigniter.com/user_guide/)
- [BladeOne GitHub](https://github.com/EFTEC/BladeOne)
- [daisyUI Components](https://daisyui.com/components/)
- [Cockpit CMS API](https://getcockpit.com/documentation/api)
- [Aimeos JSON API](https://aimeos.org/docs/latest/frontend/jsonapi/)

## License

MIT License
