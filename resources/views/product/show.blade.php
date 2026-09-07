@extends('layouts.app')

@php
    $productImageFallback = \App\Support\ProductImageCatalog::placeholderUrl();
    $uploadedProductImage = \App\Support\ProductImageCatalog::uploadedUrlFor($product->name, $product->slug);
    $officialProductImage = \App\Support\ProductImageCatalog::officialUrls($product)[0] ?? null;
    $descriptionHtml = \App\Support\ProductContent::sanitizeRichText($product->description)
        ?: '<p>No description available.</p>';
    $galleryImages = $product->images
        ->map(fn ($image) => $image->publicUrl())
        ->filter()
        ->values();
    if ($galleryImages->isEmpty() && $uploadedProductImage) {
        $galleryImages = collect([$uploadedProductImage]);
    }
    $officialGalleryImages = \App\Support\ProductImageCatalog::officialUrls($product);
    $galleryImageUrls = $galleryImages->all();
    foreach ($officialGalleryImages as $officialGalleryImage) {
        if (! in_array($officialGalleryImage, $galleryImageUrls, true)) {
            $galleryImages->push($officialGalleryImage);
            $galleryImageUrls[] = $officialGalleryImage;
        }
    }
    if ($galleryImages->isEmpty()) {
        $galleryImages = collect([$productImageFallback]);
    }

    $primaryImage = $galleryImages->first();
    $primaryProductImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
    $productAlt = \App\Support\ImageManager::altText($product);
    $mainImageSrcset = $primaryProductImage ? \App\Support\ImageManager::srcsetFor($primaryProductImage->publicUrl()) : '';
    $imageErrorFallback = $primaryImage !== $uploadedProductImage && $uploadedProductImage
        ? $uploadedProductImage
        : ($primaryImage !== $officialProductImage && $officialProductImage ? $officialProductImage : $productImageFallback);
    $currentPrice = (float) $product->price;
    $compareAtPrice = (float) ($product->compare_at_price ?? 0);
    $hasDiscount = $compareAtPrice > $currentPrice && $compareAtPrice > 0;
    $discountPercent = $hasDiscount ? (int) round((($compareAtPrice - $currentPrice) / $compareAtPrice) * 100) : null;

    $availabilityStatus = $product->availabilityStatus();
    $availabilityLabel = $availabilityStatus === 'in_stock' ? 'IN STOCK' : ($availabilityStatus === 'preorder' ? 'PREORDER' : 'OUT OF STOCK');
    $availabilityClass = $availabilityStatus === 'in_stock' ? 'is-available' : 'is-unavailable';

    $productSeoTitle = \App\Support\SeoMetadata::productTitle($product);
    $productMetaDescription = \App\Support\SeoMetadata::productDescription($product);
    $productDisplayName = \App\Support\ProductSeo::displayName($product);
    $summary = trim((string) $product->meta_description);
    if ($summary === '') {
        $summary = \App\Support\ProductContent::summary($product->description, 240);
    }
    $productCanonicalUrl = \App\Support\SeoMetadata::canonicalOverride($product)
        ?: \App\Support\CanonicalUrl::route('product.show', $product);
    $productBrand = \App\Support\ProductSeo::brand($product);
    $productModel = \App\Support\ProductSeo::model($product);
    $productUseCases = \App\Support\ProductSeo::useCases($product);
    $productApplications = \App\Support\ProductSeo::applications($product);
    $productBoxItems = \App\Support\ProductSeo::whatsInBox($product);
    $productFaqItems = \App\Support\ProductSeo::faqs($product);
    $chooseAnotherModel = \App\Support\ProductSeo::chooseAnotherModel($product);

    $vendorPhoneDigits = preg_replace('/\D+/', '', (string) $product->vendor->phone);
    if ($vendorPhoneDigits !== '') {
        if (str_starts_with($vendorPhoneDigits, '0')) {
            $vendorPhoneDigits = '254' . substr($vendorPhoneDigits, 1);
        } elseif (!str_starts_with($vendorPhoneDigits, '254') && strlen($vendorPhoneDigits) === 9) {
            $vendorPhoneDigits = '254' . $vendorPhoneDigits;
        }
    }
    $whatsAppUrl = $vendorPhoneDigits !== ''
        ? 'https://wa.me/' . $vendorPhoneDigits . '?text=' . rawurlencode('Hello, I would like to inquire about ' . $product->name . '.')
        : null;

    $quickSpecs = [];
    if ($product->port_count) $quickSpecs['Ports'] = $product->port_count . ' total';
    if ($product->rj45_ports) $quickSpecs['RJ45 Ports'] = $product->rj45_ports;
    if ($product->port_speed) $quickSpecs['Port Speed'] = \App\Support\SwitchCatalog::speeds()[$product->port_speed] ?? ucfirst($product->port_speed);
    if ($product->hasPoe()) {
        $quickSpecs['PoE'] = \App\Support\ProductSeo::specSummary($product) !== '' && \App\Support\SwitchCatalog::poeStandards()[$product->poe_standard] ?? false
            ? (\App\Support\SwitchCatalog::poeStandards()[$product->poe_standard] ?? 'PoE')
            : 'PoE';
        if ($product->poe_ports) $quickSpecs['PoE Ports'] = $product->poe_ports;
        if ($product->poe_budget) $quickSpecs['PoE Budget'] = $product->poe_budget . 'W';
    }
    if ($product->uplink_type) $quickSpecs['Uplink'] = strtoupper($product->uplink_type);
    if ($product->sfp_ports) $quickSpecs['SFP Ports'] = $product->sfp_ports;
    if ($product->sfp_plus_ports) $quickSpecs['SFP+ Ports'] = $product->sfp_plus_ports;
    if ($product->management_type) $quickSpecs['Management'] = \App\Support\SwitchCatalog::managementTypes()[$product->management_type] ?? ucfirst($product->management_type);
    if ($product->layer) $quickSpecs['Layer'] = strtoupper($product->layer);
    if ($product->rackmount) $quickSpecs['Mounting'] = 'Rackmount';
    elseif ($product->din_rail) $quickSpecs['Mounting'] = 'DIN Rail';
    elseif ($product->desktop) $quickSpecs['Mounting'] = 'Desktop';
    if ($product->warranty) $quickSpecs['Warranty'] = $product->warranty;

    $technicalSpecs = \App\Support\ProductSeo::specs($product);

    $portConfiguration = array_filter([
        'Total Ports' => $product->port_count,
        'RJ45 Ports' => $product->rj45_ports,
        'SFP Ports' => $product->sfp_ports,
        'SFP+ Ports' => $product->sfp_plus_ports,
        'SFP28 Ports' => $product->sfp28_ports,
        'QSFP Ports' => $product->qsfp_ports,
        'Uplink Type' => $product->uplink_type ? strtoupper($product->uplink_type) : null,
        'Port Speed' => isset(\App\Support\SwitchCatalog::speeds()[$product->port_speed]) ? \App\Support\SwitchCatalog::speeds()[$product->port_speed] : null,
    ], fn ($v) => $v !== null && $v !== '' && $v !== 0);

    $poeCapability = $product->hasPoe() ? array_filter([
        'PoE Standard' => isset(\App\Support\SwitchCatalog::poeStandards()[$product->poe_standard]) ? \App\Support\SwitchCatalog::poeStandards()[$product->poe_standard] : null,
        'PoE Ports' => $product->poe_ports ?: null,
        'Total PoE Budget' => $product->poe_budget ? $product->poe_budget.'W' : null,
        'Max Power per Port' => $product->poe_max_per_port ? $product->poe_max_per_port.'W' : null,
    ], fn ($v) => $v !== null && $v !== '') : [];

    $switchingPerformance = array_filter([
        'Switching Capacity' => $product->switching_capacity,
        'Forwarding Rate' => $product->forwarding_rate,
        'MAC Address Table' => $product->mac_table,
    ], fn ($v) => $v !== null && $v !== '');

    $managementFeatures = array_filter([
        'VLAN Support' => $product->vlan_support ? 'Yes' : null,
        'QoS' => $product->qos ? 'Yes' : null,
        'STP / RSTP / MSTP' => $product->stp ? 'Yes' : null,
        'LACP' => $product->lacp ? 'Yes' : null,
        'SNMP' => $product->snmp ? 'Yes' : null,
        'ACL' => $product->acl ? 'Yes' : null,
        'Stackable' => $product->stackable ? 'Yes' : null,
        'Cooling' => $product->cooling ? ucfirst($product->cooling) : null,
    ], fn ($v) => $v !== null && $v !== '');

    $breadcrumbItems = [['name' => 'Home', 'url' => \App\Support\CanonicalUrl::route('home')]];
    if ($product->category) {
        foreach ($product->category->ancestors() as $ancestor) {
            $breadcrumbItems[] = ['name' => $ancestor->name, 'url' => \App\Support\SwitchCatalog::urlFor($ancestor)];
        }
        $breadcrumbItems[] = ['name' => $product->category->name, 'url' => \App\Support\SwitchCatalog::urlFor($product->category)];
    }
    $breadcrumbItems[] = ['name' => $productDisplayName, 'url' => $productCanonicalUrl];

    $productSchema = \App\Support\StructuredData::product(
        $product,
        $galleryImages->map(fn ($image) => \App\Support\CanonicalUrl::absoluteAsset($image))->all(),
        $productMetaDescription,
        $productCanonicalUrl
    );
    $breadcrumbSchema = \App\Support\StructuredData::breadcrumbs($breadcrumbItems);
    $faqSchema = \App\Support\StructuredData::faq($productFaqItems);
@endphp

@section('title', $productSeoTitle)
@section('meta_description', $productMetaDescription)
@section('canonical_url', $productCanonicalUrl)
@section('og_type', 'product')
@section('og_title', \App\Support\SeoMetadata::openGraphTitle($product, $productSeoTitle))
@section('og_description', \App\Support\SeoMetadata::openGraphDescription($product, $productMetaDescription))
@section('og_image', \App\Support\SeoMetadata::openGraphImage($product, $primaryImage))
@if(\App\Support\SeoMetadata::robots($product))
    @section('robots', \App\Support\SeoMetadata::robots($product))
@endif

@push('head')
    <script type="application/ld+json">@json($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    <script type="application/ld+json">@json($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @if($faqSchema)
        <script type="application/ld+json">@json($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endif
@endpush

@section('content')
<div class="product-page">
    <nav class="product-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a>
        @if($product->category)
            @foreach($product->category->ancestors() as $ancestor)
                <span>/</span>
                <a href="{{ \App\Support\SwitchCatalog::urlFor($ancestor) }}">{{ $ancestor->name }}</a>
            @endforeach
            <span>/</span>
            <a href="{{ \App\Support\SwitchCatalog::urlFor($product->category) }}">{{ $product->category->name }}</a>
        @endif
        <span>/</span>
        <span>{{ $productDisplayName }}</span>
    </nav>

    <section class="product-showcase">
        <div class="product-gallery-card" data-product-gallery>
            <div class="product-gallery-stage">
                <img
                    src="{{ $primaryImage }}"
                    alt="{{ $productAlt }}"
                    class="product-gallery-main-image"
                    data-product-main-image
                    @if($mainImageSrcset !== '')srcset="{{ $mainImageSrcset }}" sizes="(max-width: 640px) 400px, (max-width: 1024px) 800px, 1200px"@endif
                    width="900"
                    height="680"
                    fetchpriority="high"
                    decoding="async"
                    onerror="this.onerror=null;this.src='{{ $imageErrorFallback }}';"
                >
            </div>

            @if($galleryImages->count() > 1)
                <div class="product-gallery-thumbs" data-product-gallery-thumbs>
                    @foreach($galleryImages as $index => $galleryImage)
                        <button
                            type="button"
                            class="product-gallery-thumb {{ $index === 0 ? 'is-active' : '' }}"
                            data-product-image="{{ $galleryImage }}"
                            data-product-alt="{{ $productAlt }} image {{ $index + 1 }}"
                            aria-label="View image {{ $index + 1 }} of {{ $productDisplayName }}"
                        >
                            <img src="{{ $galleryImage }}" alt="{{ $productAlt }} thumbnail {{ $index + 1 }}" width="120" height="90" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='{{ $imageErrorFallback }}';">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="product-summary-card">
            <div class="product-summary-topline">
                <p class="product-page-category">
                    @if($product->category)
                        <a href="{{ \App\Support\SwitchCatalog::urlFor($product->category) }}">{{ $product->category->name }}</a>
                    @else
                        General
                    @endif
                </p>
                <span class="product-stock-badge {{ $availabilityClass }}">{{ $availabilityLabel }}</span>
            </div>

            <h1 class="product-page-title">{{ $productDisplayName }}</h1>

            <div class="product-identity-row">
                <span>Brand: {{ $productBrand }}</span>
                <span>Model: {{ $productModel }}</span>
                <span>SKU: {{ $product->sku }}</span>
            </div>

            <div class="product-price-row">
                <span class="product-current-price">KSh {{ number_format($currentPrice, 2) }}</span>
                @if($hasDiscount)
                    <span class="product-compare-price">KSh {{ number_format($compareAtPrice, 2) }}</span>
                    <span class="product-discount-pill">{{ $discountPercent }}% OFF</span>
                @endif
            </div>

            @if($summary !== '')
                <p class="product-summary-copy product-summary-copy--meta">{{ $summary }}</p>
            @endif

            @if($quickSpecs !== [])
                <div class="product-quick-specs">
                    @foreach($quickSpecs as $label => $value)
                        <div class="product-quick-spec"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                    @endforeach
                </div>
            @endif

            <div class="product-summary-divider" aria-hidden="true"></div>

            <div class="product-purchase-card">
                @if($availabilityStatus !== 'out_of_stock')
                    @auth
                        <form class="product-purchase-form" method="post" action="{{ route('cart.add', $product) }}">
                            @csrf
                            <div class="product-quantity-block">
                                <span class="product-quantity-label">Quantity</span>
                                <div class="product-quantity-picker">
                                    <button type="button" class="product-qty-control" data-qty-adjust="-1" aria-label="Decrease quantity">-</button>
                                    <input type="number" name="quantity" value="1" min="1" max="{{ max(1, $product->stock) }}" class="product-quantity-input" data-qty-input>
                                    <button type="button" class="product-qty-control" data-qty-adjust="1" aria-label="Increase quantity">+</button>
                                </div>
                            </div>

                            <div class="product-cta-row">
                                <button type="submit" name="redirect" value="checkout" class="product-primary-cta">Buy Now</button>
                                <button type="submit" name="redirect" value="cart" class="product-secondary-cta">Add to Cart</button>
                                @if($whatsAppUrl)
                                    <a class="product-whatsapp-cta" href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                                @endif
                            </div>
                        </form>
                    @else
                        <div class="product-cta-row">
                            <a class="product-primary-cta" href="{{ route('login') }}">Add to Cart</a>
                            @if($whatsAppUrl)
                                <a class="product-whatsapp-cta" href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                            @endif
                        </div>
                    @endauth
                @else
                    <div class="product-cta-row">
                        <button type="button" class="product-primary-cta" disabled>Out of Stock</button>
                        @if($whatsAppUrl)
                            <a class="product-whatsapp-cta" href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer">Ask on WhatsApp</a>
                        @endif
                    </div>
                @endif

                <form method="post" action="{{ route('comparison.add', $product) }}" class="product-compare-form product-compare-form--inline">
                    @csrf
                    <button type="submit" class="product-secondary-cta product-compare-btn">Add to Compare</button>
                </form>
            </div>

            <div class="product-summary-divider" aria-hidden="true"></div>

            <div class="product-availability-row">
                <span class="product-availability-label">Availability:</span>
                <span class="product-availability-pill {{ $availabilityClass }}">{{ $availabilityStatus === 'preorder' ? 'AVAILABLE FOR PREORDER' : ($availabilityStatus === 'in_stock' ? 'AVAILABLE IN STORE' : 'OUT OF STOCK') }}</span>
            </div>
        </div>
    </section>

    <section class="product-seo-grid">
        <article class="product-info-panel">
            <h2>Who is this switch best for?</h2>
            <ul>
                @foreach($productUseCases as $useCase)
                    <li>{{ $useCase }}</li>
                @endforeach
            </ul>
        </article>

        @if($chooseAnotherModel)
            <article class="product-info-panel">
                <h2>When should you choose another model?</h2>
                <p>{{ $chooseAnotherModel }}</p>
            </article>
        @endif

        <article class="product-info-panel">
            <h2>Key specifications</h2>
            <dl class="product-spec-table">
                @foreach($technicalSpecs as $specLabel => $specValue)
                    <div>
                        <dt>{{ $specLabel }}</dt>
                        <dd>{{ $specValue }}</dd>
                    </div>
                @endforeach
            </dl>
        </article>

        @if($portConfiguration !== [])
            <article class="product-info-panel">
                <h2>Port configuration</h2>
                <dl class="product-spec-table">
                    @foreach($portConfiguration as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>
        @endif

        @if($poeCapability !== [])
            <article class="product-info-panel">
                <h2>PoE capability</h2>
                <dl class="product-spec-table">
                    @foreach($poeCapability as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>
        @endif

        @if($switchingPerformance !== [])
            <article class="product-info-panel">
                <h2>Switching performance</h2>
                <dl class="product-spec-table">
                    @foreach($switchingPerformance as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>
        @endif

        @if($managementFeatures !== [])
            <article class="product-info-panel">
                <h2>Network management features</h2>
                <dl class="product-spec-table">
                    @foreach($managementFeatures as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>
        @endif

        <article class="product-info-panel">
            <h2>Recommended applications</h2>
            <ul>
                @foreach($productApplications as $application)
                    <li>{{ $application }}</li>
                @endforeach
            </ul>
        </article>

        <article class="product-info-panel">
            <h2>Compatibility</h2>
            <p>{{ \App\Support\ProductSeo::compatibility($product) }}</p>
        </article>

        <article class="product-info-panel">
            <h2>Power requirements</h2>
            <p>{{ \App\Support\ProductSeo::powerRequirements($product) }}</p>
        </article>

        <article class="product-info-panel">
            <h2>What's in the box?</h2>
            <ul>
                @foreach($productBoxItems as $boxItem)
                    <li>{{ $boxItem }}</li>
                @endforeach
            </ul>
        </article>

        <article class="product-info-panel">
            <h2>Warranty, delivery and payment</h2>
            <dl class="product-spec-table">
                <div>
                    <dt>Warranty</dt>
                    <dd>{{ \App\Support\ProductSeo::warrantyInfo($product) }}</dd>
                </div>
                <div>
                    <dt>Delivery</dt>
                    <dd>{{ \App\Support\ProductSeo::deliveryInfo($product) }}</dd>
                </div>
                <div>
                    <dt>Payment</dt>
                    <dd>{{ \App\Support\ProductSeo::paymentInfo($product) }}</dd>
                </div>
            </dl>
        </article>
    </section>

    <section class="product-tabs-shell" data-product-tabs>
        <div class="product-tabs" role="tablist" aria-label="Product information tabs">
            <button type="button" class="product-tab-button is-active" data-tab-target="details" role="tab" aria-selected="true">Product details</button>
            <button type="button" class="product-tab-button" data-tab-target="information" role="tab" aria-selected="false">Additional information</button>
        </div>

        <div class="product-tab-panel is-active" data-tab-panel="details" role="tabpanel">
            <div class="rich-content product-description-content">{!! $descriptionHtml !!}</div>
        </div>

        <div class="product-tab-panel" data-tab-panel="information" role="tabpanel" hidden>
            <div class="product-info-grid">
                <div class="product-info-item"><span>Product</span><strong>{{ $productDisplayName }}</strong></div>
                <div class="product-info-item"><span>Category</span><strong>{{ $product->category?->name ?? 'General' }}</strong></div>
                <div class="product-info-item"><span>SKU</span><strong>{{ $product->sku }}</strong></div>
                <div class="product-info-item"><span>Price</span><strong>KSh {{ number_format($currentPrice, 2) }}</strong></div>
                @if($hasDiscount)
                    <div class="product-info-item"><span>Original price</span><strong>KSh {{ number_format($compareAtPrice, 2) }}</strong></div>
                @endif
                <div class="product-info-item"><span>Stock</span><strong>{{ $availabilityStatus === 'in_stock' ? 'Available in store' : ucfirst($availabilityStatus) }}</strong></div>
                <div class="product-info-item"><span>Vendor</span><strong>{{ $product->vendor->shop_name }}</strong></div>
                @if($product->vendor->address)
                    <div class="product-info-item"><span>Location</span><strong>{{ $product->vendor->address }}</strong></div>
                @endif
            </div>
        </div>
    </section>

    @if($productFaqItems !== [])
        <section class="product-tabs-shell product-faq-shell">
            <h2>FAQs about {{ $productModel }}</h2>
            <div class="faq-list">
                @foreach($productFaqItems as $item)
                    <details class="faq-item" @if($loop->first) open @endif>
                        <summary>{{ $item['question'] }}</summary>
                        <p>{{ $item['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    @if($relatedCategories->isNotEmpty())
        <section class="product-tabs-shell product-link-panel">
            <h2>Related switch categories</h2>
            <nav class="category-hub-links" aria-label="Related categories">
                @foreach($relatedCategories as $relatedCategory)
                    <a href="{{ \App\Support\SwitchCatalog::urlFor($relatedCategory) }}">{{ $relatedCategory->name }}</a>
                @endforeach
            </nav>
        </section>
    @endif

    @if($relatedProducts->isNotEmpty())
        <section class="product-tabs-shell product-related-shell">
            <h2>Related products</h2>
            <div class="products-grid">
                @foreach($relatedProducts as $relatedProduct)
                    @include('partials.product-card', ['product' => $relatedProduct, 'productImageFallback' => $productImageFallback])
                @endforeach
            </div>
        </section>
    @endif

    @if($availabilityStatus !== 'out_of_stock')
        <div class="product-sticky-bar" data-product-sticky-bar>
            <span class="product-sticky-price">KSh {{ number_format($currentPrice, 2) }}</span>
            @auth
                <form class="product-sticky-form" method="post" action="{{ route('cart.add', $product) }}">
                    @csrf
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="redirect" value="back">
                    <button type="submit" class="product-sticky-cta">Add to Cart</button>
                </form>
            @else
                <a class="product-sticky-cta" href="{{ route('login') }}">Add to Cart</a>
            @endauth
            @if($whatsAppUrl)
                <a class="product-sticky-whatsapp" href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            @endif
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const defaultProductAlt = @json($productDisplayName);
    const gallery = document.querySelector('[data-product-gallery]');
    if (gallery) {
        const mainImage = gallery.querySelector('[data-product-main-image]');
        const thumbs = gallery.querySelectorAll('[data-product-image]');

        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                thumbs.forEach(function (button) { button.classList.remove('is-active'); });
                thumb.classList.add('is-active');
                if (mainImage) {
                    mainImage.src = thumb.getAttribute('data-product-image') || '';
                    mainImage.alt = thumb.getAttribute('data-product-alt') || defaultProductAlt;
                }
            });
        });
    }

    const tabButtons = document.querySelectorAll('[data-tab-target]');
    const tabPanels = document.querySelectorAll('[data-tab-panel]');

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const target = button.getAttribute('data-tab-target');
            tabButtons.forEach(function (b) { b.classList.remove('is-active'); b.setAttribute('aria-selected', 'false'); });
            tabPanels.forEach(function (panel) {
                const isMatch = panel.getAttribute('data-tab-panel') === target;
                panel.classList.toggle('is-active', isMatch);
                panel.hidden = !isMatch;
            });
            button.classList.add('is-active');
            button.setAttribute('aria-selected', 'true');
        });
    });

    const quantityInput = document.querySelector('[data-qty-input]');
    const quantityControls = document.querySelectorAll('[data-qty-adjust]');
    quantityControls.forEach(function (control) {
        control.addEventListener('click', function () {
            if (!quantityInput) return;
            const step = Number(control.getAttribute('data-qty-adjust') || '0');
            const min = Number(quantityInput.getAttribute('min') || '1');
            const max = Number(quantityInput.getAttribute('max') || '1');
            const current = Number(quantityInput.value || min);
            quantityInput.value = String(Math.min(max, Math.max(min, current + step)));
        });
    });
});
</script>
@endsection
