# Repository Guidelines

## Project Structure & Architecture

This is the Outline Key Manager (OKM), a CodeIgniter 4 monolith. Application code lives in `app/`: controllers in `app/Controllers/`, external-API and domain logic in `app/Libraries/`, configuration and routes in `app/Config/`, and Blade templates in `app/Views/`. Public assets are in `public/`; Tailwind input and generated CSS are `public/css/input.css` and `public/css/output.css`. PHPUnit tests are organized under `tests/unit/` and `tests/feature/`.

Cockpit CMS is the datastore: do not introduce local models, entities, migrations, or a database. Keep controllers thin and put integration/business logic in services. HTML controllers should extend `WebController` and render Blade views.

## Build, Test, and Development Commands

Docker is the recommended development environment:

```bash
docker compose up -d web                 # Start the application on :8080
docker compose exec cli vendor/bin/phpunit # Run the PHP test suite
npm run watch:css                        # Rebuild Tailwind CSS while editing views
npm run build:css                        # Produce public/css/output.css once
```

For local PHP development, use `vendor/bin/phpunit` and `php spark serve` after installing dependencies. Copy `env` to `.env`; never commit Cockpit tokens or server credentials.

## Coding Style & Naming Conventions

Use PHP strict types in every new PHP file, explicit nullable types (for example, `?string`), and CodeIgniter naming conventions: `SavedServersService.php`, `Servers.php`, and `index.blade.php`. Prefer focused service classes over speculative abstractions. Use Blade plus mobile-first Tailwind utilities and daisyUI components; do not add React or Vue.

## Testing Guidelines

Write PHPUnit tests beside comparable coverage: unit tests for libraries in `tests/unit/`, controller behavior in `tests/feature/`. Name files `*Test.php` and test methods by observable behavior. Run the full suite before committing; all tests must pass.

## Commit & Pull Request Guidelines

Follow the established Conventional Commit style: `feat(3.2): add server modal`, `fix: handle invalid config`, or `docs: update guide`. Keep commits scoped to one logical change. Pull requests should describe the user-facing result, list verification performed, link relevant issues or plan tasks, and include screenshots for UI changes. Do not push without explicit approval.
