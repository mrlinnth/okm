@extends('layouts.master')

@section('title', $title)

@section('content')
<!-- Hero Section -->
<div class="hero bg-gradient-to-r from-primary to-secondary text-primary-content rounded-box mb-8">
    <div class="hero-content text-center py-12">
        <div class="max-w-2xl">
            <h1 class="text-5xl font-bold mb-4">{{ $product['label'] }}</h1>
        </div>
    </div>
</div>

<!-- Product Details -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="card bg-base-100 shadow-lg">
            <div class="card-body">
                <!-- Product Image -->
                <figure class="mb-6">
                    @if(!empty($product['images']))
                    <img src="{{ $product['images'][0]['url'] }}" alt="{{ $product['label'] }}" class="rounded-xl w-full h-64 object-cover">
                    @else
                    <div class="bg-base-200 rounded-xl w-full h-64 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-24 h-24 text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    @endif
                </figure>

                <!-- Product Info -->
                <div class="space-y-4">
                    <!-- Badges -->
                    <div class="flex items-center gap-2">
                        <span class="badge badge-secondary">{{ $product['productType'] }}</span>
                        @if($product['status'] == 1)
                        <span class="badge badge-success">Active</span>
                        @else
                        <span class="badge badge-error">Inactive</span>
                        @endif
                    </div>

                    <!-- Details Table -->
                    <table class="table">
                        <tbody>
                            <tr>
                                <td class="font-semibold w-1/3">Product Code</td>
                                <td>{{ $product['code'] }}</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">URL Slug</td>
                                <td>{{ $product['url'] }}</td>
                            </tr>
                            <tr>
                                <td class="font-semibold">Type</td>
                                <td>{{ $product['productType'] }}</td>
                            </tr>
                            @if($product['ratings'] > 0)
                            <tr>
                                <td class="font-semibold">Rating</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span>{{ $product['rating'] }} ({{ $product['ratings'] }} reviews)</span>
                                    </div>
                                </td>
                            </tr>
                            @endif
                            @if($product['datestart'])
                            <tr>
                                <td class="font-semibold">Start Date</td>
                                <td>{{ $product['datestart'] }}</td>
                            </tr>
                            @endif
                            @if($product['dateend'])
                            <tr>
                                <td class="font-semibold">End Date</td>
                                <td>{{ $product['dateend'] }}</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- Back Link -->
                <div class="card-actions justify-center mt-6">
                    <a href="/products" class="btn btn-outline">
                        Back to Products
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection