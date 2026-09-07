@extends('layouts.app')

@php
    $productImageFallback = \App\Support\ProductImageCatalog::placeholderUrl();
    $title = 'Search results for "'.$search.'" | Network Switches Kenya';
@endphp

@section('title', $title)
@section('meta_description', 'Search results for network switches in Kenya.')
@section('robots', 'noindex,follow')

@section('content')
<section class="category-page">
    <nav class="product-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        <span>/</span>
        <span>Search</span>
    </nav>

    <header class="category-header">
        <h1>Search results for "{{ $search }}"</h1>
        <p class="category-header-summary">{{ $products->total() }} switch{{ $products->total() === 1 ? '' : 'es' }} found.</p>
    </header>

    @if($products->total() > 0)
        <section class="products-grid products-grid--catalog">
            @foreach($products as $product)
                @include('partials.product-card', ['product' => $product, 'productImageFallback' => $productImageFallback])
            @endforeach
        </section>

        @if($products->hasPages())
            <div class="pager">
                @if($products->onFirstPage())
                    <span class="pager-link disabled">Previous</span>
                @else
                    <a class="pager-link" href="{{ $products->previousPageUrl() }}">Previous</a>
                @endif
                <span>Page {{ $products->currentPage() }} of {{ $products->lastPage() }}</span>
                @if($products->hasMorePages())
                    <a class="pager-link" href="{{ $products->nextPageUrl() }}">Next</a>
                @else
                    <span class="pager-link disabled">Next</span>
                @endif
            </div>
        @endif
    @else
        <p class="empty">No switches matched "{{ $search }}". Try a model number, brand or keyword like "24 port PoE".</p>
    @endif
</section>
@endsection
