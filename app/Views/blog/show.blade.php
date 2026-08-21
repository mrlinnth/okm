@extends('layouts.master')

@section('title', $title)

@section('content')
<!-- Hero Section -->
<div class="hero bg-gradient-to-r from-primary to-secondary text-primary-content rounded-box mb-8">
    <div class="hero-content text-center py-12">
        <div class="max-w-2xl">
            <h1 class="text-5xl font-bold mb-4">{{ $post['title'] ?? 'Untitled' }}</h1>
        </div>
    </div>
</div>

<!-- Article Container -->
<div class="container mx-auto px-4 py-8">
    <article class="max-w-4xl mx-auto">
        <!-- Post Information Container -->
        <div class="flex justify-between">
            <!-- Publish Date Time -->
            @if(isset($post['published_at']) && $post['published_at'])
            <div class="flex flex-wrap items-center gap-4 text-base-content">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-base-content/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-base-content/80">{{ date('F j, Y', strtotime($post['published_at'])) }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-base-content/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-base-content/80">{{ date('g:i A', strtotime($post['published_at'])) }}</span>
                </div>
            </div>
            @endif

            <!-- Post Type and Tags -->
            <div class="flex flex-wrap items-center gap-3">
                @if(isset($post['type']) && $post['type'])
                <span class="badge badge-info">
                    {{ $post['type'] }}
                </span>
                @endif

                @if(isset($post['tags']) && is_array($post['tags']) && !empty($post['tags']))
                @foreach($post['tags'] as $tag)
                <span class="badge badge-ghost">
                    {{ $tag }}
                </span>
                @endforeach
                @endif
            </div>
        </div>

        <!-- Article Content -->
        <div class="card my-12">
            <div class="card-body">
                <div class="prose prose-lg prose-slate max-w-none">
                    <div class="article-content">
                        {!! $post['content'] ?? '' !!}
                    </div>
                </div>
            </div>
        </div>

        <!-- Article Footer -->
        <footer>
            <div class="flex flex-col items-center justify-between gap-4">
                <div class="text-sm text-base-content/60">
                    <span>Thanks for reading!</span>
                </div>

                <div class="flex gap-3">
                    <a href="/blog" class="btn btn-outline">
                        All Posts
                    </a>
                </div>
            </div>
        </footer>
    </article>
</div>
@endsection