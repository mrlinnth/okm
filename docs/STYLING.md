# Styling Guide

Frontend styling with **Tailwind CSS v4** and **daisyUI** components.

## Quick Examples

```blade
{{-- Buttons --}}
<button class="btn btn-primary">Primary Button</button>
<button class="btn btn-secondary">Secondary</button>

{{-- Cards --}}
<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h2 class="card-title">Card Title</h2>
        <p>Card content goes here</p>
        <div class="card-actions justify-end">
            <button class="btn btn-primary">Action</button>
        </div>
    </div>
</div>

{{-- Hero --}}
<div class="hero min-h-screen bg-base-200">
    <div class="hero-content text-center">
        <div class="max-w-md">
            <h1 class="text-5xl font-bold">Hello there</h1>
            <p class="py-6">Beautiful UI with daisyUI components</p>
            <button class="btn btn-primary">Get Started</button>
        </div>
    </div>
</div>
```

## Theme Support

- Automatic dark/light theme toggle
- Persisted in localStorage
- Supports all daisyUI themes

## Development Workflow

1. Watch CSS during development: `npm run watch:css`
2. Use daisyUI components in your Blade views
3. Apply Tailwind utilities for custom styling
4. Build for production: `npm run build:css`

## Resources

- [daisyUI Components](https://daisyui.com/components/)
- [Tailwind CSS Docs](https://tailwindcss.com/docs)
