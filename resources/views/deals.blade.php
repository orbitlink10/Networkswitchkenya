@extends('layouts.app')

@php
    $productImageFallback = \App\Support\ProductImageCatalog::placeholderUrl();
    $canonicalUrl = \App\Support\CanonicalUrl::route('deals');
@endphp

@section('title', $title)
@section('meta_description', $description)
@section('canonical_url', $canonicalUrl)
@section('og_title', $title)
@section('og_description', $description)

@section('content')
<section class="category-page">
    <nav class="product-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        <span>/</span>
        <span>Deals</span>
    </nav>

    <header class="category-header">
        <h1>{{ $title }}</h1>
        <p class="category-header-summary">Genuine sale prices pulled from the product catalogue. Only switches with a marked-down price appear here.</p>
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
        <p class="empty">No discounted switches right now. Check back soon.</p>
    @endif
</section>
@endsection
