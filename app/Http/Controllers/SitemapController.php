<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\CanonicalUrl;
use App\Support\SeoMetadata;
use App\Support\SwitchCatalog;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            [
                'loc' => CanonicalUrl::route('home'),
                'lastmod' => now()->toDateString(),
                'priority' => '1.0',
            ],
            [
                'loc' => CanonicalUrl::route('finder'),
                'lastmod' => null,
                'priority' => '0.6',
            ],
            [
                'loc' => CanonicalUrl::route('deals'),
                'lastmod' => null,
                'priority' => '0.6',
            ],
            [
                'loc' => CanonicalUrl::route('blog.index'),
                'lastmod' => null,
                'priority' => '0.5',
            ],
        ]);

        $categoryUrls = Category::query()
            ->whereNotIn('slug', array_keys(SwitchCatalog::legacyCategoryRedirects()))
            ->when(SeoMetadata::columnReady('categories', 'robots'), fn ($query) => $query->where(fn ($robotsQuery) => $robotsQuery->whereNull('robots')->orWhere('robots', 'not like', 'noindex%')))
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Category $category): array => [
                'loc' => SwitchCatalog::urlFor($category),
                'lastmod' => optional($category->updated_at)->toDateString(),
                'priority' => '0.8',
            ]);

        $productUrls = Product::query()
            ->active()
            ->when(SeoMetadata::columnReady('products', 'robots'), fn ($query) => $query->where(fn ($robotsQuery) => $robotsQuery->whereNull('robots')->orWhere('robots', 'not like', 'noindex%')))
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Product $product): array => [
                'loc' => CanonicalUrl::route('product.show', $product),
                'lastmod' => optional($product->updated_at)->toDateString(),
                'priority' => '0.7',
            ]);

        $pageUrls = Page::query()
            ->when(SeoMetadata::columnReady('pages', 'robots'), fn ($query) => $query->where(fn ($robotsQuery) => $robotsQuery->whereNull('robots')->orWhere('robots', 'not like', 'noindex%')))
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Page $page): array => [
                'loc' => $page->type === 'post'
                    ? CanonicalUrl::route('blog.show', ['slug' => $page->slug])
                    : CanonicalUrl::route('pages.show', ['page' => $page->slug]),
                'lastmod' => optional($page->updated_at)->toDateString(),
                'priority' => '0.6',
            ]);

        foreach (['about-us', 'contact-us', 'delivery-policy', 'returns-policy', 'warranty-policy', 'privacy-policy', 'terms-and-conditions'] as $slug) {
            if (! $pageUrls->contains('loc', CanonicalUrl::route('pages.show', ['page' => $slug]))) {
                $pageUrls->push([
                    'loc' => CanonicalUrl::route('pages.show', ['page' => $slug]),
                    'lastmod' => null,
                    'priority' => '0.4',
                ]);
            }
        }

        $urls = $urls->merge($categoryUrls)->merge($productUrls)->merge($pageUrls);

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
