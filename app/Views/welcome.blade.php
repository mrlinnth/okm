@extends('layouts.master')

@section('title', 'Welcome to CI4 + Cockpit + Blade')

@section('header', 'Welcome to CodeIgniter 4 + Cockpit CMS')

@section('content')
<div class="text-center py-8">
    <div class="hero min-h-[200px] bg-base-200 rounded-box mb-8">
        <div class="hero-content text-center">
            <div class="max-w-md">
                <h1 class="text-5xl font-bold">🚀 Blade Templating Enabled!</h1>
                <p class="py-6 text-lg">
                    Your CodeIgniter 4 + Cockpit CMS starter is now powered by Laravel Blade templating engine & daisyUI
                </p>
                <div class="flex gap-2 justify-center">
                    <div class="badge badge-primary">CI4</div>
                    <div class="badge badge-secondary">Blade</div>
                    <div class="badge badge-accent">Cockpit CMS</div>
                    <div class="badge badge-info">daisyUI</div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto text-left">
        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h2 class="card-title text-2xl">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                    </svg>
                    Features
                </h2>
                <?php $features = [
                    ['icon' => '⚡', 'text' => 'BladeOne templating engine (EFTEC)'],
                    ['icon' => '🔥', 'text' => 'CodeIgniter 4 framework'],
                    ['icon' => '🎨', 'text' => 'daisyUI component library'],
                    ['icon' => '🚀', 'text' => 'Cockpit CMS integration ready'],
                    ['icon' => '🧩', 'text' => 'Blade layouts and components support'],
                    ['icon' => '🌐', 'text' => 'No database required - API-driven content']
                ]; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($features as $feature)
                    <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg hover:bg-base-300 transition-colors">
                        <span class="text-2xl">{{ $feature['icon'] }}</span>
                        <span>{{ $feature['text'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl mb-6">
            <div class="card-body">
                <h2 class="card-title text-2xl">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                    </svg>
                    Quick Start
                </h2>

                <div class="collapse collapse-arrow bg-base-200 mb-3">
                    <input type="radio" name="quick-start" checked="checked" />
                    <div class="collapse-title text-lg font-medium">
                        Extend WebController (Recommended)
                    </div>
                    <div class="collapse-content">
                        <div class="mockup-code">
                            <pre data-prefix="$"><code>class Home extends WebController</code></pre>
                            <pre data-prefix=">"><code>{</code></pre>
                            <pre data-prefix=">"><code>    public function index()</code></pre>
                            <pre data-prefix=">"><code>    {</code></pre>
                            <pre data-prefix=">"><code>        $data = $this->cockpit->getSingletonCached('homepage');</code></pre>
                            <pre data-prefix=">"><code>        return $this->render('welcome', ['data' => $data]);</code></pre>
                            <pre data-prefix=">"><code>    }</code></pre>
                            <pre data-prefix=">"><code>}</code></pre>
                        </div>
                    </div>
                </div>

                <div class="collapse collapse-arrow bg-base-200">
                    <input type="radio" name="quick-start" />
                    <div class="collapse-title text-lg font-medium">
                        Or use Services directly
                    </div>
                    <div class="collapse-content">
                        <div class="mockup-code">
                            <pre data-prefix="$"><code>use Config\Services;</code></pre>
                            <pre data-prefix=""></pre>
                            <pre data-prefix=">"><code>$blade = Services::blade();</code></pre>
                            <pre data-prefix=">"><code>$cockpit = Services::cockpit();</code></pre>
                            <pre data-prefix=">"><code>echo $blade->render('welcome', $data);</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-2xl">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                    Documentation
                </h2>
                <div class="flex flex-col gap-2">
                    <a href="https://laravel.com/docs/blade" class="btn btn-outline btn-primary" target="_blank">
                        📖 Laravel Blade Documentation
                    </a>
                    <a href="https://daisyui.com/components/" class="btn btn-outline btn-secondary" target="_blank">
                        🌼 daisyUI Components
                    </a>
                    <a href="https://codeigniter.com/user_guide/" class="btn btn-outline btn-accent" target="_blank">
                        📖 CodeIgniter 4 User Guide
                    </a>
                    <a href="https://getcockpit.com/documentation/api" class="btn btn-outline btn-info" target="_blank">
                        📖 Cockpit CMS API Documentation
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection