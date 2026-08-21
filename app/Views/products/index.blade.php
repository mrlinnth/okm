@extends('layouts.master')

@section('title', $title)

@section('content')
<!-- Hero Section -->
<div class="hero bg-gradient-to-r from-primary to-secondary text-primary-content rounded-box mb-8">
    <div class="hero-content text-center py-12">
        <div class="max-w-2xl">
            <h1 class="text-5xl font-bold mb-4">{{ $title }}</h1>
            <p class="text-lg opacity-90">Browse our collection of products</p>
        </div>
    </div>
</div>

<!-- Products Grid Section -->
@if(empty($products))
<div class="container mx-auto px-4 py-16">
    <div class="max-w-md mx-auto text-center">
        <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-base-200 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">No products found</h3>
        <p class="text-base-content/60">Check back later for new products.</p>
    </div>
</div>
@else
<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-4 gap-6">
        @foreach($products as $product)
        <div class="card bg-base-100 shadow-sm hover:shadow-lg transition-shadow">
            <figure class="px-4 pt-4">
                @if(!empty($product['images']))
                <img src="{{ $product['images'][0]['url'] }}" alt="{{ $product['label'] }}" class="rounded-xl w-full h-48 object-cover">
                @else
                <div class="bg-base-200 rounded-xl w-full h-48 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                @endif
            </figure>
            <div class="card-body">
                <!-- Product Type Badge -->
                <div class="flex items-center gap-2 mb-1">
                    <span class="badge badge-secondary badge-sm">{{ $product['productType'] }}</span>
                    @if($product['status'] == 1)
                    <span class="badge badge-success badge-sm">Active</span>
                    @endif
                </div>

                <!-- Product Title -->
                <h2 class="card-title text-lg">
                    {{ $product['label'] }}
                </h2>

                <!-- Product Code -->
                <p class="text-sm text-base-content/60">
                    Code: {{ $product['code'] }}
                </p>

                <!-- Rating -->
                @if($product['ratings'] > 0)
                <div class="flex items-center gap-2 mt-2">
                    <span class="text-sm text-base-content/60">
                        {{ $product['rating'] }} ({{ $product['ratings'] }} reviews)
                    </span>
                </div>
                @endif

                <!-- Actions -->
                <div class="card-actions justify-end mt-4">
                    <a href="/products/{{ $product['id'] }}" class="btn btn-primary btn-sm">
                        View Details
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection