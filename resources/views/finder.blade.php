@extends('layouts.app')

@php
    $productImageFallback = \App\Support\ProductImageCatalog::placeholderUrl();
    $description = 'Answer a few simple questions and find the right network switch for your devices, PoE, speed and budget in Kenya.';
    $hasAnswers = collect($answers)->filter(fn ($value): bool => $value !== null && $value !== '')->isNotEmpty();
@endphp

@section('title', 'Network Switch Finder | Find the Right Switch')
@section('meta_description', $description)
@section('canonical_url', \App\Support\CanonicalUrl::route('finder'))
@section('og_title', 'Network Switch Finder')
@section('og_description', $description)

@section('content')
<section class="finder-page">
    <nav class="product-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        <span>/</span>
        <span>Switch Finder</span>
    </nav>

    <header class="category-header">
        <h1>Network Switch Finder</h1>
        <p class="category-header-summary">Not sure which switch you need? Answer these simple questions and we'll recommend matching switches.</p>
    </header>

    <form class="finder-form" method="get" action="{{ route('finder') }}">
        @foreach($steps as $index => $step)
            <fieldset class="finder-step">
                <legend>{{ $index + 1 }}. {{ $step['question'] }}</legend>
                <div class="finder-options">
                    @foreach($step['options'] as $option)
                        <label class="finder-option">
                            <input
                                type="radio"
                                name="{{ ['devices', 'poe', 'devices_type', 'speed', 'management', 'budget'][$index] }}"
                                value="{{ $option['value'] }}"
                                @checked(($answers[['devices', 'poe', 'devices_type', 'speed', 'management', 'budget'][$index]] ?? null) === $option['value'])
                            >
                            <span>{{ $option['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <div class="finder-actions">
            <button type="submit" class="product-primary-cta">Show Recommended Switches</button>
            <a class="view-btn" href="{{ route('finder') }}">Reset</a>
        </div>
    </form>

    @if($hasAnswers)
        <section class="finder-results">
            <h2>Recommended switches</h2>
            @if($results->isNotEmpty())
                <section class="products-grid products-grid--catalog">
                    @foreach($results as $product)
                        @include('partials.product-card', ['product' => $product, 'productImageFallback' => $productImageFallback])
                    @endforeach
                </section>
            @else
                <p class="empty">No switches matched your exact requirements. Try relaxing your speed or management selection, or <a href="{{ route('comparison.index') }}">browse all switches</a>.</p>
            @endif
        </section>
    @endif
</section>
@endsection
