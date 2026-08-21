@extends('layouts.master')

@section('title', $title)

@section('content')
<!-- Modern Hero Section -->
<div class="hero bg-gradient-to-r from-primary to-secondary text-primary-content rounded-box mb-8">
    <div class="hero-content text-center py-12">
        <div class="max-w-2xl">
            @if($title ?? null)
            <h1 class="text-5xl font-bold mb-4">{{ $title }}</h1>
            @else
            <h1 class="text-5xl font-bold mb-4">Blog Page</h1>
            @endif
        </div>
    </div>
</div>

<!-- Blog Posts Section -->
@if(empty($posts))
<div class="container mx-auto px-4 py-16">
    <div class="max-w-md mx-auto text-center">
        <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-base-200 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
            </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">No blog posts found</h3>
        <p class="text-base-content/60">Check back later for new articles and updates.</p>
    </div>
</div>
@else
<div class="container mx-auto px-4 py-12">
    <div class="overflow-x-auto bg-base-100 rounded-lg shadow-sm">
        <table class="table table-zebra w-full">
            <thead>
                <tr class="bg-base-200">
                    <th class="w-1/4">Title</th>
                    <th class="w-1/6">Type</th>
                    <th class="w-1/6">Date</th>
                    <th class="w-1/3">Excerpt</th>
                    <th class="w-1/6">Tags</th>
                    <th class="w-1/12">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($posts as $post)
                <tr class="hover">
                    <!-- Title -->
                    <td>
                        <a href="/blog/{{ $post['_id'] }}" class="font-semibold text-primary hover:underline link">
                            {{ $post['title'] ?? 'Untitled' }}
                        </a>
                    </td>

                    <!-- Type -->
                    <td>
                        @if(isset($post['type']) && $post['type'])
                        <span class="badge badge-primary badge-sm">{{ $post['type'] }}</span>
                        @else
                        <span class="text-base-content/40">—</span>
                        @endif
                    </td>

                    <!-- Date -->
                    <td>
                        @if(isset($post['published_at']) && $post['published_at'])
                        <div class="flex items-center gap-2 text-sm text-base-content/60">
                            <span>{{ date('M j, Y', strtotime($post['published_at'])) }}</span>
                        </div>
                        @else
                        <span class="text-base-content/40">—</span>
                        @endif
                    </td>

                    <!-- Excerpt -->
                    <td>
                        @if(isset($post['excerpt']) && $post['excerpt'])
                        <p class="text-base-content/70 line-clamp-2 leading-relaxed text-sm">
                            {{ $post['excerpt'] }}
                        </p>
                        @else
                        <span class="text-base-content/40 text-sm">No excerpt</span>
                        @endif
                    </td>

                    <!-- Tags -->
                    <td>
                        @if(isset($post['tags']) && is_array($post['tags']) && !empty($post['tags']))
                        <div class="flex flex-wrap gap-1">
                            @foreach(array_slice($post['tags'], 0, 3) as $tag)
                            <span class="badge badge-ghost badge-xs">{{ $tag }}</span>
                            @endforeach
                            @if(count($post['tags']) > 3)
                            <span class="badge badge-ghost badge-xs">+{{ count($post['tags']) - 3 }}</span>
                            @endif
                        </div>
                        @else
                        <span class="text-base-content/40 text-sm">—</span>
                        @endif
                    </td>

                    <!-- Action -->
                    <td>
                        <a href="/blog/{{ $post['_id'] }}" class="btn btn-primary btn-sm">
                            Read
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection