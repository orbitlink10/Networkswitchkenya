@extends('layouts.app')

@section('title', $title)
@section('meta_description', $description)
@section('canonical_url', \App\Support\CanonicalUrl::route('blog.index'))
@section('og_title', $title)
@section('og_description', $description)

@section('content')
<section class="category-page">
    <nav class="product-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        <span>/</span>
        <span>Blog</span>
    </nav>

    <header class="category-header">
        <h1>Network Switch Buying Guides &amp; Blog</h1>
        <p class="category-header-summary">Guides, comparisons and tips to help you choose and configure the right network switch in Kenya.</p>
    </header>

    <nav class="category-sub-nav" aria-label="Blog categories">
        <a class="category-sub-chip {{ $activeCategory === '' ? 'is-active' : '' }}" href="{{ route('blog.index') }}">All</a>
        @foreach($categories as $slug => $label)
            <a class="category-sub-chip {{ $activeCategory === $slug ? 'is-active' : '' }}" href="{{ route('blog.index', ['category' => $slug]) }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($posts->total() > 0)
        <div class="blog-card-grid blog-card-grid--index">
            @foreach($posts as $post)
                <a class="blog-card" href="{{ route('blog.show', ['slug' => $post->slug]) }}">
                    <h3>{{ $post->title }}</h3>
                    <p>{{ \App\Support\ProductContent::excerpt($post->body, 160) }}</p>
                </a>
            @endforeach
        </div>

        @if($posts->hasPages())
            <div class="pager">
                @if($posts->onFirstPage())
                    <span class="pager-link disabled">Previous</span>
                @else
                    <a class="pager-link" href="{{ $posts->previousPageUrl() }}">Previous</a>
                @endif
                <span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
                @if($posts->hasMorePages())
                    <a class="pager-link" href="{{ $posts->nextPageUrl() }}">Next</a>
                @else
                    <span class="pager-link disabled">Next</span>
                @endif
            </div>
        @endif
    @else
        <p class="empty">Buying guides are coming soon.</p>
    @endif
</section>
@endsection
