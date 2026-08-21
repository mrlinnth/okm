<div style="border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin: 1rem 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    @if(isset($title))
        <h3 style="margin: 0 0 1rem; color: #2c3e50;">{{ $title }}</h3>
    @endif

    <div>
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #ecf0f1; color: #7f8c8d; font-size: 0.9rem;">
            {{ $footer }}
        </div>
    @endif
</div>
