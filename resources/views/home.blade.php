@extends('layouts.app')

@php
    $productImageFallback = \App\Support\ProductImageCatalog::placeholderUrl();
    $faqItems = $homepageContent->faqItems();
    $faqSchema = $faqItems !== [] ? \App\Support\StructuredData::faq($faqItems) : null;
@endphp

@section('title', \App\Support\SeoMetadata::homepageTitle())
@section('meta_description', \App\Support\SeoMetadata::homepageDescription())
@section('canonical_url', \App\Support\CanonicalUrl::route('home'))

@if($faqSchema)
    @push('head')
        <script type="application/ld+json">@json($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endpush
@endif

@section('content')
<section class="home-layout home-layout--full">
    <section
        class="hero-banner hero-banner--switch"
        @if($homepageContent->heroImageUrl())
            style="background-image: linear-gradient(120deg, rgba(10, 69, 136, 0.9), rgba(22, 119, 255, 0.78)), url('{{ $homepageContent->heroImageUrl() }}'); background-size: cover; background-position: center;"
        @endif
    >
        <div class="hero-banner-inner">
            <p class="hero-kicker">Kenya's Specialist Network Switch Store</p>
            <h1>{{ $homepageContent->hero_title }}</h1>
            <p>{{ $homepageContent->hero_description }}</p>

            <form class="hero-search" method="get" action="{{ route('home') }}" role="search">
                <input type="search" name="search" placeholder="Search by model, port count, PoE, speed or brand..." aria-label="Search switches" autocomplete="off" required>
                <button type="submit">Search</button>
            </form>

            <div class="hero-actions">
                <a class="hero-cta hero-cta--primary" href="{{ \App\Support\SwitchCatalog::categoryUrl('network-switches') }}">Shop Network Switches</a>
                <a class="hero-cta hero-cta--secondary" href="{{ route('finder') }}">Find My Switch</a>
            </div>
        </div>
    </section>

    {{-- Section 2: Shop by Port Count --}}
    <section class="home-section home-section--cards">
        <div class="home-section-head">
            <h2>Shop by Port Count</h2>
            <a class="featured-rows-link" href="{{ route('ports.index') }}">View all ports &rarr;</a>
        </div>
        <div class="shop-card-grid shop-card-grid--ports">
            @foreach($portCards as $card)
                <a class="shop-card" href="{{ $card['url'] }}">{{ $card['label'] }}</a>
            @endforeach
        </div>
    </section>

    {{-- Section 3: Shop PoE Switches --}}
    <section class="home-section home-section--cards">
        <div class="home-section-head">
            <h2>Shop PoE Switches</h2>
            <a class="featured-rows-link" href="{{ \App\Support\SwitchCatalog::categoryUrl('poe-switches') }}">All PoE switches &rarr;</a>
        </div>
        <div class="shop-card-grid shop-card-grid--poe">
            @foreach($poeCards as $card)
                <a class="shop-card" href="{{ $card['url'] }}">{{ $card['label'] }}</a>
            @endforeach
        </div>
    </section>

    {{-- Section 4: Shop by Type --}}
    <section class="home-section home-section--cards">
        <div class="home-section-head">
            <h2>Shop by Type</h2>
        </div>
        <div class="shop-card-grid shop-card-grid--type">
            @foreach($typeCards as $card)
                <a class="shop-card" href="{{ $card['url'] }}">{{ $card['label'] }}</a>
            @endforeach
        </div>
    </section>

    {{-- Section 5: Featured Brands --}}
    <section class="home-section home-section--cards">
        <div class="home-section-head">
            <h2>Featured Brands</h2>
            <a class="featured-rows-link" href="{{ route('brands.index') }}">All brands &rarr;</a>
        </div>
        <div class="brand-card-grid">
            @foreach($brandCards as $card)
                <a class="brand-card" href="{{ $card['url'] }}">{{ $card['label'] }}</a>
            @endforeach
        </div>
    </section>

    {{-- Section 6: Featured Products --}}
    @if($featuredProducts->isNotEmpty())
        <section class="home-section home-section--products">
            <div class="home-section-head">
                <h2>Featured Network Switches</h2>
                <a class="featured-rows-link" href="{{ \App\Support\SwitchCatalog::categoryUrl('network-switches') }}">View all &rarr;</a>
            </div>
            <section class="products-grid products-grid--catalog">
                @foreach($featuredProducts as $product)
                    @include('partials.product-card', ['product' => $product, 'productImageFallback' => $productImageFallback])
                @endforeach
            </section>
        </section>
    @endif

    {{-- Section 7: Shop by Application --}}
    <section class="home-section home-section--cards">
        <div class="home-section-head">
            <h2>Shop by Application</h2>
            <a class="featured-rows-link" href="{{ route('solutions.index') }}">All solutions &rarr;</a>
        </div>
        <div class="shop-card-grid shop-card-grid--type">
            @foreach($solutionCards as $card)
                <a class="shop-card" href="{{ $card['url'] }}">{{ $card['label'] }}</a>
            @endforeach
        </div>
    </section>

    {{-- Section 8: Why Buy From Us --}}
    <section class="home-section home-section--why-choose">
        <div class="home-section-head">
            <h2>{{ $homepageContent->whyChooseTitle() }}</h2>
            @if($homepageContent->whyChooseIntro())
                <p>{{ $homepageContent->whyChooseIntro() }}</p>
            @endif
        </div>
        <div class="why-choose-grid">
            @foreach($homepageContent->whyChooseItems() as $item)
                <article class="why-choose-card">
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['description'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Section 9: Buying Guides --}}
    @if($blogPosts->isNotEmpty())
        <section class="home-section home-section--guides">
            <div class="home-section-head">
                <h2>Switch Buying Guides</h2>
                <a class="featured-rows-link" href="{{ route('blog.index') }}">View all guides &rarr;</a>
            </div>
            <div class="blog-card-grid">
                @foreach($blogPosts as $post)
                    <a class="blog-card" href="{{ route('blog.show', ['slug' => $post->slug]) }}">
                        <h3>{{ $post->title }}</h3>
                        <p>{{ \App\Support\ProductContent::excerpt($post->body, 120) }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- FAQ --}}
    @if($faqItems !== [])
        <section class="home-section home-section--faq">
            <div class="home-section-head">
                <h2>{{ $homepageContent->faqTitle() }}</h2>
                @if($homepageContent->faqIntro())
                    <p>{{ $homepageContent->faqIntro() }}</p>
                @endif
            </div>
            <div class="faq-list">
                @foreach($faqItems as $item)
                    <details class="faq-item" @if($loop->first) open @endif>
                        <summary>{{ $item['question'] }}</summary>
                        <p>{{ $item['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Testimonials --}}
    @if($testimonials->isNotEmpty())
        <section class="home-section home-section--testimonials">
            <div class="home-section-head">
                <h2>{{ $homepageContent->testimonialsTitle() }}</h2>
            </div>
            <div class="testimonial-grid">
                @foreach($testimonials as $testimonial)
                    @php($rating = max(1, min(5, (int) $testimonial->rating)))
                    <article class="testimonial-card">
                        <div class="testimonial-stars" aria-label="{{ $rating }} out of 5 stars">{{ str_repeat('★', $rating) }}</div>
                        <p class="testimonial-quote">{{ $testimonial->quote }}</p>
                        <h3>{{ $testimonial->name }}</h3>
                        <p class="testimonial-role">{{ $testimonial->role }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- SEO content --}}
    <section class="home-section home-section--guide">
        <div class="rich-content home-seo-content">
            {!! $homepageContent->contentBody() !!}
        </div>
    </section>
</section>
@endsection
