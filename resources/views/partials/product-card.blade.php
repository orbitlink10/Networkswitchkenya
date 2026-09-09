@php
    $productDisplayName = \App\Support\ProductSeo::displayName($product);
    $productImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
    $uploadedProductImage = \App\Support\ProductImageCatalog::uploadedUrlFor($product->name, $product->slug);
    $officialProductImages = \App\Support\ProductImageCatalog::officialUrls($product);
    $officialProductImage = $officialProductImages[0] ?? null;
    $image = $productImage?->publicUrl()
        ?: $uploadedProductImage
        ?: $officialProductImage
        ?: $productImageFallback;
    $imageErrorFallback = $image !== $uploadedProductImage && $uploadedProductImage
        ? $uploadedProductImage
        : ($image !== $officialProductImage && $officialProductImage ? $officialProductImage : $productImageFallback);
    $imageAlt = $productImage?->altText() ?: \App\Support\ImageManager::altText($product);
    $imageSrcset = $productImage ? \App\Support\ImageManager::srcsetFor($productImage->publicUrl()) : '';
    $productDescription = \App\Support\ProductContent::excerpt($product->meta_description ?: $product->description, 132);
    $productDescription = $productDescription !== ''
        ? $productDescription
        : $productDisplayName . ' is available in Kenya.';
    $specSummary = \App\Support\ProductSeo::specSummary($product);
    $brandLabel = \App\Support\ProductSeo::brand($product);
    $hasDiscount = (float) $product->compare_at_price > (float) $product->price;
    $availabilityLabel = $product->availabilityStatus() === 'in_stock' ? 'In Stock' : ($product->availabilityStatus() === 'preorder' ? 'Preorder' : 'Out of Stock');
    $availabilityClass = $product->availabilityStatus() === 'in_stock' ? 'is-available' : 'is-unavailable';
@endphp

<article class="product-card">
    <a class="product-media-link" href="{{ route('product.show', $product) }}" aria-label="View {{ $productDisplayName }}">
        <img
            class="product-image"
            src="{{ $image }}"
            alt="{{ $imageAlt }}"
            @if($imageSrcset !== '')srcset="{{ $imageSrcset }}" sizes="(max-width: 640px) 400px, 600px"@endif
            width="480"
            height="360"
            loading="lazy"
            decoding="async"
            onerror="this.onerror=null;this.src='{{ $imageErrorFallback }}';"
        >
    </a>
    <div class="product-body">
        <p class="product-card-brand">{{ $brandLabel }}</p>
        <h3 class="product-name">
            <a href="{{ route('product.show', $product) }}">{{ $productDisplayName }}</a>
        </h3>
        <p class="product-card-spec">{{ $specSummary }}</p>
        <span class="product-stock-badge {{ $availabilityClass }}">{{ $availabilityLabel }}</span>
        <div class="product-bottom">
            <div class="product-price-stack">
                <span class="price">KES {{ number_format((float) $product->price, 2) }}</span>
                @if($hasDiscount)
                    <span class="product-card-was">KES {{ number_format((float) $product->compare_at_price, 2) }}</span>
                @endif
            </div>
        </div>
        <div class="product-card-actions">
            <form method="post" action="{{ route('cart.add', $product) }}" class="product-compare-form product-add-cart-form">
                @csrf
                <input type="hidden" name="quantity" value="1">
                <input type="hidden" name="redirect" value="back">
                <button type="submit" class="view-btn">Add to Cart</button>
            </form>
            <a class="view-btn" href="{{ route('product.show', $product) }}" title="View {{ $productDisplayName }}">View Product</a>
        </div>
    </div>
</article>
