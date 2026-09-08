<?php

namespace App\Support;

use App\Models\HomepageContent;
use App\Models\Page;
use App\Models\Product;

class StructuredData
{
    /**
     * @param  array<int, string>  $images
     */
    public static function product(Product $product, array $images, string $description, string $canonicalUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => ProductSeo::displayName($product),
            'image' => array_values(array_filter($images)),
            'description' => $description,
            'sku' => $product->sku,
            'mpn' => ProductSeo::model($product),
            'brand' => [
                '@type' => 'Brand',
                'name' => ProductSeo::brand($product),
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonicalUrl,
                'priceCurrency' => 'KES',
                'price' => number_format((float) $product->price, 2, '.', ''),
                'availability' => $product->availabilityStatus() === 'out_of_stock'
                    ? 'https://schema.org/OutOfStock'
                    : 'https://schema.org/InStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => config('business.name', config('app.name', 'Network Switches Kenya')),
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $items
     */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ],
                array_values($items),
                array_keys(array_values($items))
            ),
        ];
    }

    /**
     * @param  array<int, array{question: string, answer: string}>  $items
     */
    public static function faq(array $items): ?array
    {
        $items = array_values(array_filter($items, fn (array $item): bool => $item['question'] !== '' && $item['answer'] !== ''));
        if ($items === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ], $items),
        ];
    }

    /**
     * BlogPosting schema for blog articles. Uses real database values only.
     */
    public static function article(Page $post, string $canonicalUrl, string $description, ?string $imageUrl = null): array
    {
        $publisherName = config('business.name', config('app.name', 'Network Switches Kenya'));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => SeoMetadata::pageTitle($post),
            'description' => $description,
            'url' => $canonicalUrl,
            'mainEntityOfPage' => $canonicalUrl,
            'author' => [
                '@type' => 'Organization',
                'name' => $publisherName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $publisherName,
                'url' => CanonicalUrl::normalize('/'),
            ],
        ];

        if ($post->created_at) {
            $schema['datePublished'] = $post->created_at->toIso8601String();
        }
        if ($post->updated_at) {
            $schema['dateModified'] = $post->updated_at->toIso8601String();
        }
        if ($imageUrl !== null && $imageUrl !== '') {
            $schema['image'] = CanonicalUrl::absoluteAsset($imageUrl);
        }

        return $schema;
    }

    /**
     * CollectionPage + ItemList schema for curated category landing pages.
     *
     * @param  array<int, array{name: string, url: string}>  $items
     */
    public static function collectionPage(string $name, string $description, string $canonicalUrl, array $items): array
    {
        $itemListElement = [];
        foreach (array_values($items) as $index => $item) {
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'url' => $item['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'description' => $description,
            'url' => $canonicalUrl,
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => count($itemListElement),
                'itemListElement' => $itemListElement,
            ],
        ];
    }

    public static function organization(?HomepageContent $homepageContent = null): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('business.name', config('app.name', 'Network Switches Kenya')),
            'url' => CanonicalUrl::normalize('/'),
        ];
        if (config('business.legal_name')) {
            $schema['legalName'] = config('business.legal_name');
        }

        if ($homepageContent?->siteLogoUrl()) {
            $schema['logo'] = CanonicalUrl::absoluteAsset($homepageContent->siteLogoUrl());
        }

        $phone = $homepageContent?->contactPhone() ?: config('business.phone');

        if ($phone) {
            $schema['telephone'] = $phone;
        }

        if (config('business.email')) {
            $schema['email'] = config('business.email');
        }

        if (config('business.address')) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => config('business.address'),
            ];
        }

        if (config('business.social_profiles')) {
            $schema['sameAs'] = config('business.social_profiles');
        }

        return $schema;
    }

    public static function website(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name', 'Network Switches Kenya'),
            'url' => CanonicalUrl::normalize('/'),
        ];

        if (CanonicalUrl::normalize('/') !== '') {
            $schema['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => CanonicalUrl::normalize('/').'?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $schema;
    }
}
