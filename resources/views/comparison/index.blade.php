@extends('layouts.app')

@php
    $description = 'Compare network switches side by side in Kenya - price, ports, PoE, speed, management and more.';
    $productImageFallback = \App\Support\ProductImageCatalog::placeholderUrl();
    $breadcrumbSchema = \App\Support\StructuredData::breadcrumbs([
        ['name' => 'Home', 'url' => \App\Support\CanonicalUrl::route('home')],
        ['name' => 'Compare', 'url' => $canonicalUrl],
    ]);
@endphp

@section('title', 'Compare Network Switches | Network Switches Kenya')
@section('meta_description', $description)
@section('canonical_url', $canonicalUrl)
@section('og_title', 'Compare Network Switches')
@section('og_description', $description)
@section('robots', 'noindex,follow')

@push('head')
    <script type="application/ld+json">@json($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endpush

@section('content')
<article class="comparison-page">
    <nav class="product-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        <span>/</span>
        <span>Compare</span>
    </nav>

    <section class="panel comparison-head">
        <p class="catalog-search-eyebrow">Network switch comparison</p>
        <h1>Compare Network Switches</h1>
        <p>{{ $description }}</p>
    </section>

    @if($products->isEmpty())
        <section class="panel comparison-empty">
            <p>No switches selected yet. Browse the catalogue and click "Compare" on any switch to add it here (up to 4 switches).</p>
            <a class="button-link" href="{{ \App\Support\SwitchCatalog::categoryUrl('network-switches') }}">Browse Switches</a>
        </section>
    @else
        <section class="panel">
            <div class="table-wrap comparison-table-wrap">
                <table class="router-price-table comparison-table">
                    <thead>
                    <tr>
                        <th>Specification</th>
                        @foreach($products as $product)
                            <th>
                                <div class="comparison-product-head">
                                    <a href="{{ route('product.show', $product) }}">{{ \App\Support\ProductSeo::displayName($product) }}</a>
                                    <form method="post" action="{{ route('comparison.remove', $product) }}">
                                        @csrf
                                        <button type="submit" class="comparison-remove" aria-label="Remove {{ $product->name }}">&times;</button>
                                    </form>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($attributes as $attribute)
                        <tr>
                            <td class="comparison-attr-label">{{ $attribute['label'] }}</td>
                            @foreach($products as $product)
                                <td>{{ $attribute['value']($product) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="comparison-actions">
                <form method="post" action="{{ route('comparison.clear') }}">
                    @csrf
                    <button type="submit" class="view-btn">Clear comparison</button>
                </form>
            </div>
        </section>

        <section class="products-grid">
            @foreach($products as $product)
                @include('partials.product-card', ['product' => $product, 'productImageFallback' => $productImageFallback])
            @endforeach
        </section>
    @endif
</article>
@endsection
