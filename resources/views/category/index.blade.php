@extends('layouts.app')

@php
    $productImageFallback = \App\Support\ProductImageCatalog::placeholderUrl();
    $categoryTitle = \App\Support\SeoMetadata::categoryTitle($currentCategory, $products?->currentPage() ?? 1);
    $categoryDescription = \App\Support\SeoMetadata::categoryDescription($currentCategory);
    $categorySummary = $currentCategory->intro ?: $currentCategory->meta_description ?: \App\Support\ProductContent::excerpt($currentCategory->description, 240);
    $categorySeoHtml = \App\Support\ProductContent::sanitizeRichText($currentCategory->seo_content ?: $currentCategory->description);

    $currentPage = $products?->currentPage() ?? 1;
    $canonicalQuery = $currentPage > 1 ? ['page' => $currentPage] : [];
    $canonicalUrl = \App\Support\SeoMetadata::canonicalOverride($currentCategory)
        ?: \App\Support\CanonicalUrl::category($currentCategory, $canonicalQuery);

    $breadcrumbItems = [['name' => 'Home', 'url' => \App\Support\CanonicalUrl::route('home')]];
    foreach ($currentCategory->ancestors() as $ancestor) {
        $breadcrumbItems[] = ['name' => $ancestor->name, 'url' => \App\Support\SwitchCatalog::urlFor($ancestor)];
    }
    $breadcrumbItems[] = ['name' => $currentCategory->name, 'url' => $canonicalUrl];
    $breadcrumbSchema = \App\Support\StructuredData::breadcrumbs($breadcrumbItems);

    $faqSchema = ($faqItems !== []) ? \App\Support\StructuredData::faq($faqItems) : null;

    $collectionSchema = null;
    if (! $isHub && $products && $products->total() > 0) {
        $collectionItems = $products->map(fn ($p) => [
            'name' => \App\Support\ProductSeo::displayName($p),
            'url' => \App\Support\CanonicalUrl::route('product.show', $p),
        ])->all();
        $collectionSchema = \App\Support\StructuredData::collectionPage(
            $currentCategory->name,
            $categoryDescription,
            $canonicalUrl,
            $collectionItems
        );
    }

    $currentFilters = $filters;
    $brandOptions = \App\Support\SwitchCatalog::brands();
    $portOptions = \App\Support\SwitchCatalog::ports();
    $managementOptions = \App\Support\SwitchCatalog::managementTypes();
    $poeOptions = ['af' => 'PoE (802.3af)', 'at' => 'PoE+ (802.3at)', 'bt' => 'PoE++ (802.3bt)', 'non-poe' => 'Non-PoE'];
    $speedOptions = \App\Support\SwitchCatalog::speeds();
    $uplinkOptions = \App\Support\SwitchCatalog::uplinks();
    $budgetOptions = collect(\App\Support\SwitchCatalog::poeBudgetRanges())->map(fn ($r) => $r['label'])->all();
    $installOptions = \App\Support\SwitchCatalog::installationTypes();
    $availabilityOptions = ['in_stock' => 'In Stock', 'out_of_stock' => 'Out of Stock', 'preorder' => 'Preorder'];
@endphp

@section('title', $categoryTitle)
@section('meta_description', $categoryDescription)
@section('canonical_url', $canonicalUrl)
@section('og_title', $categoryTitle)
@section('og_description', $categoryDescription)
@if($currentCategory->image_url)
    @section('og_image', $currentCategory->image_url)
@endif
@if($hasFilters)
    @section('robots', 'noindex,follow')
@elseif(\App\Support\SeoMetadata::robots($currentCategory))
    @section('robots', \App\Support\SeoMetadata::robots($currentCategory))
@endif

@if($breadcrumbSchema)
    @push('head')
        <script type="application/ld+json">@json($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endpush
@endif
@if($faqSchema)
    @push('head')
        <script type="application/ld+json">@json($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endpush
@endif
@if($collectionSchema)
    @push('head')
        <script type="application/ld+json">@json($collectionSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endpush
@endif

@section('content')
<section class="category-page">
    <nav class="product-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        @foreach($currentCategory->ancestors() as $ancestor)
            <span>/</span>
            <a href="{{ \App\Support\SwitchCatalog::urlFor($ancestor) }}">{{ $ancestor->name }}</a>
        @endforeach
        <span>/</span>
        <span>{{ $currentCategory->name }}</span>
    </nav>

    <header class="category-header">
        <h1>{{ $currentCategory->name }}</h1>
        @if($categorySummary)
            <p class="category-header-summary">{{ $categorySummary }}</p>
        @endif
    </header>

    @if($isHub)
        <section class="hub-grid">
            @foreach($hubChildren as $child)
                <a class="hub-card" href="{{ \App\Support\SwitchCatalog::urlFor($child) }}">
                    <span>{{ $child->name }}</span>
                    <span class="hub-card-arrow">&rarr;</span>
                </a>
            @endforeach
        </section>
    @else
        @if($currentCategory->children->isNotEmpty())
            <nav class="category-sub-nav" aria-label="Subcategories">
                @foreach($currentCategory->children as $child)
                    <a class="category-sub-chip" href="{{ \App\Support\SwitchCatalog::urlFor($child) }}">{{ $child->name }}</a>
                @endforeach
            </nav>
        @endif

        <div class="category-layout">
            <aside class="filter-sidebar">
                <div class="filter-sidebar-head">
                    <h2>Filters</h2>
                    @if($hasFilters)
                        <a class="filter-clear" href="{{ \App\Support\SwitchCatalog::urlFor($currentCategory) }}">Clear</a>
                    @endif
                </div>

                <form class="filter-form" method="get" action="{{ \App\Support\SwitchCatalog::urlFor($currentCategory) }}">
                    <div class="filter-group">
                        <label for="filter-brand">Brand</label>
                        <select id="filter-brand" name="brand">
                            <option value="">All brands</option>
                            @foreach($brandOptions as $slug => $label)
                                <option value="{{ $slug }}" @selected(($currentFilters['brand'] ?? '') === $slug)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter-ports">Port Count</label>
                        <select id="filter-ports" name="ports">
                            <option value="">All</option>
                            @foreach($portOptions as $ports)
                                <option value="{{ $ports }}" @selected((string)($currentFilters['ports'] ?? '') === (string)$ports)>{{ $ports }} Ports</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter-management">Management</label>
                        <select id="filter-management" name="management">
                            <option value="">All</option>
                            @foreach($managementOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($currentFilters['management'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter-poe">PoE</label>
                        <select id="filter-poe" name="poe">
                            <option value="">All</option>
                            @foreach($poeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($currentFilters['poe'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter-speed">Speed</label>
                        <select id="filter-speed" name="speed">
                            <option value="">All</option>
                            @foreach($speedOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($currentFilters['speed'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter-uplink">Uplink</label>
                        <select id="filter-uplink" name="uplink">
                            <option value="">All</option>
                            @foreach($uplinkOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($currentFilters['uplink'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter-budget">PoE Budget</label>
                        <select id="filter-budget" name="budget">
                            <option value="">All</option>
                            @foreach($budgetOptions as $key => $label)
                                <option value="{{ $key }}" @selected(($currentFilters['budget'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter-install">Installation</label>
                        <select id="filter-install" name="install">
                            <option value="">All</option>
                            @foreach($installOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($currentFilters['install'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter-avail">Availability</label>
                        <select id="filter-avail" name="avail">
                            <option value="">All</option>
                            @foreach($availabilityOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($currentFilters['avail'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group filter-group--price">
                        <label>Price (KES)</label>
                        <div class="filter-price-row">
                            <input type="number" name="min" min="0" value="{{ $currentFilters['min'] ?? '' }}" placeholder="Min">
                            <span>&ndash;</span>
                            <input type="number" name="max" min="0" value="{{ $currentFilters['max'] ?? '' }}" placeholder="Max">
                        </div>
                    </div>

                    <button type="submit" class="filter-apply">Apply Filters</button>
                </form>
            </aside>

            <div class="category-results">
                <div class="category-toolbar">
                    <button type="button" class="filter-toggle" data-filter-toggle>Filters</button>
                    <p class="category-count">{{ $products->total() }} switch{{ $products->total() === 1 ? '' : 'es' }} found</p>
                    <form class="sort-form" method="get" action="{{ \App\Support\SwitchCatalog::urlFor($currentCategory) }}">
                        @foreach($filters as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <label for="sort">Sort by</label>
                        <select id="sort" name="sort" onchange="this.form.submit()">
                            @foreach($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

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
                    <p class="empty">No switches match your filters. <a href="{{ \App\Support\SwitchCatalog::urlFor($currentCategory) }}">Clear filters</a>.</p>
                @endif

                @if($categorySeoHtml)
                    <section class="category-seo-panel rich-content">
                        {!! $categorySeoHtml !!}
                    </section>
                @endif

                @if($faqItems !== [])
                    <section class="category-faq-panel">
                        <h2>{{ $currentCategory->name }} FAQs</h2>
                        <div class="faq-list">
                            @foreach($faqItems as $item)
                                @if(!empty($item['question']) && !empty($item['answer']))
                                    <details class="faq-item" @if($loop->first) open @endif>
                                        <summary>{{ $item['question'] }}</summary>
                                        <p>{{ $item['answer'] }}</p>
                                    </details>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </div>
    @endif

    @if($relatedCategories->isNotEmpty())
        <section class="category-related">
            <h2>Related Switch Categories</h2>
            <nav class="category-hub-links" aria-label="Related categories">
                @foreach($relatedCategories as $relatedCategory)
                    <a href="{{ \App\Support\SwitchCatalog::urlFor($relatedCategory) }}">{{ $relatedCategory->name }}</a>
                @endforeach
            </nav>
        </section>
    @endif
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('[data-filter-toggle]');
    var sidebar = document.querySelector('.filter-sidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
    }
});
</script>
@endsection
