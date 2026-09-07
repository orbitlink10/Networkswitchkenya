<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HomepageContent;
use App\Models\Page;
use App\Models\Product;
use App\Models\Testimonial;
use App\Support\CanonicalUrl;
use App\Support\ProductContent;
use App\Support\ProductSeo;
use App\Support\SeoMetadata;
use App\Support\StructuredData;
use App\Support\SwitchCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    private const CATEGORY_PAGE_SIZE = 24;

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->filled('category')) {
            $category = Category::query()->find($request->integer('category'));

            if ($category) {
                return redirect(SwitchCatalog::urlFor($category), 301);
            }
        }

        if ($request->filled('search')) {
            return $this->searchResults($request);
        }

        return $this->renderHomepage($request);
    }

    public function showShop(Request $request, ?string $category = null): View|RedirectResponse
    {
        return $this->renderCategoryGroup($request, SwitchCatalog::TYPE_SHOP, $category, 'network-switches');
    }

    public function showPorts(Request $request, ?string $category = null): View|RedirectResponse
    {
        return $this->renderCategoryGroup($request, SwitchCatalog::TYPE_PORTS, $category, 'ports');
    }

    public function showBrands(Request $request, ?string $category = null): View|RedirectResponse
    {
        return $this->renderCategoryGroup($request, SwitchCatalog::TYPE_BRANDS, $category, 'brands');
    }

    public function showSolutions(Request $request, ?string $category = null): View|RedirectResponse
    {
        return $this->renderCategoryGroup($request, SwitchCatalog::TYPE_SOLUTIONS, $category, 'solutions');
    }

    public function show(string $product): View|RedirectResponse
    {
        $product = $this->resolveProduct($product);

        if ($product instanceof RedirectResponse) {
            return $product;
        }

        $product->load(['vendor', 'category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')]);

        if ($product->status !== 'active' || ! $product->vendor?->is_approved) {
            abort(404);
        }

        $relatedProducts = Product::query()
            ->with(['vendor', 'category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')])
            ->active()
            ->whereKeyNot($product->id)
            ->when($product->category_id, function (Builder $query) use ($product): void {
                $query->where(function (Builder $w) use ($product): void {
                    $w->where('category_id', $product->category_id)
                        ->orWhere('brand', $product->brand);
                });
            })
            ->latest()
            ->limit(4)
            ->get();

        return view('product.show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'relatedCategories' => $this->relatedCategories($product->category),
        ]);
    }

    public function deals(Request $request): View
    {
        $products = Product::query()
            ->with(['vendor', 'category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')])
            ->active()
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->latest()
            ->paginate(self::CATEGORY_PAGE_SIZE)
            ->withQueryString();

        return view('deals', [
            'products' => $products,
            'title' => 'Network Switch Deals in Kenya',
            'description' => 'Shop discounted network switches in Kenya. Compare sale prices on PoE, managed, unmanaged and gigabit switches.',
        ]);
    }

    public function blog(Request $request): View
    {
        $category = (string) $request->query('category', '');
        $categories = SwitchCatalog::blogCategories();

        $posts = Page::query()
            ->where('type', 'post')
            ->when($category !== '' && isset($categories[$category]), fn ($query) => $query->where('category', $category))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $title = $category !== '' && isset($categories[$category])
            ? $categories[$category].' | Network Switch Blog'
            : 'Network Switch Buying Guides & Blog';

        return view('blog.index', [
            'posts' => $posts,
            'categories' => $categories,
            'activeCategory' => $category,
            'title' => $title,
            'description' => 'Network switch buying guides, comparisons and configuration tips for Kenya.',
        ]);
    }

    public function blogPost(string $slug): View|RedirectResponse
    {
        $post = Page::query()->where('type', 'post')->where('slug', Str::slug($slug))->first();

        abort_unless($post, 404);

        return view('page.show', [
            'page' => $post,
            'pageBody' => ProductContent::sanitizeRichText($post->body) ?: '<p>No content available.</p>',
            'pageMetaDescription' => SeoMetadata::pageDescription($post, ProductContent::excerpt($post->body, 160)),
        ]);
    }

    public function switchFinder(Request $request): View
    {
        $answers = $request->only(['devices', 'poe', 'devices_type', 'speed', 'management', 'budget']);
        $hasAnswers = collect($answers)->filter(fn ($value): bool => $value !== null && $value !== '')->isNotEmpty();

        $results = collect();
        if ($hasAnswers) {
            $results = SwitchCatalog::finderQuery($answers)->limit(24)->get();
        }

        return view('finder', [
            'steps' => SwitchCatalog::finderSteps(),
            'answers' => $answers,
            'results' => $results,
        ]);
    }

    public function showPage(string $page): View|RedirectResponse
    {
        $requestedSlug = Str::slug($page);
        $page = Page::query()->whereRaw('LOWER(slug) = ?', [Str::lower($requestedSlug)])->first();

        if (! $page) {
            return $this->showTrustPage($requestedSlug);
        }

        if ($page->slug !== $requestedSlug) {
            return redirect()->route('pages.show', ['page' => $page->slug], 301);
        }

        return view('page.show', [
            'page' => $page,
            'pageBody' => ProductContent::sanitizeRichText($page->body) ?: '<p>No content available.</p>',
            'pageMetaDescription' => SeoMetadata::pageDescription($page, ProductContent::excerpt($page->body, 160)),
        ]);
    }

    public function redirectLegacyPage(string $page): RedirectResponse
    {
        return redirect()->route('pages.show', ['page' => Str::slug($page)], 301);
    }

    public function redirectLegacyProduct(string $product): RedirectResponse
    {
        $product = $this->findProductBySlug($product);

        abort_unless($product, 404);

        return redirect()->route('product.show', $product, 301);
    }

    public function redirectLegacyCategory(string $category): RedirectResponse
    {
        $categoryModel = $this->findCategoryBySlug($category);

        abort_unless($categoryModel, 404);

        return redirect(SwitchCatalog::urlFor($categoryModel), 301);
    }

    private function searchResults(Request $request): View|RedirectResponse
    {
        $search = trim((string) $request->query('search', ''));
        $searchSlug = Str::slug($search);
        $normalizedSearch = Str::lower($search);

        if ($search !== '') {
            $exactProduct = Product::query()
                ->active()
                ->where(function (Builder $query) use ($normalizedSearch, $searchSlug): void {
                    $query->whereRaw('LOWER(name) = ?', [$normalizedSearch])
                        ->orWhereRaw('LOWER(sku) = ?', [$normalizedSearch]);

                    if ($searchSlug !== '') {
                        $query->orWhere('slug', $searchSlug);
                    }
                })
                ->first();

            if ($exactProduct) {
                return redirect()->route('product.show', $exactProduct);
            }
        }

        $products = Product::query()
            ->with(['vendor', 'category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')])
            ->active()
            ->where(function (Builder $query) use ($search, $searchSlug): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('brand', 'like', '%'.$search.'%')
                    ->orWhere('model_number', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('meta_description', 'like', '%'.$search.'%');

                if ($searchSlug !== '') {
                    $query->orWhere('slug', 'like', '%'.$searchSlug.'%');
                }
            })
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('search', [
            'products' => $products,
            'search' => $search,
        ]);
    }

    private function renderHomepage(Request $request): View
    {
        $homepageContent = HomepageContent::current();

        $featuredProducts = collect();
        $featuredIds = $homepageContent->featuredProductIds();
        if ($featuredIds !== []) {
            $featuredProducts = Product::query()
                ->with(['vendor', 'category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')])
                ->active()
                ->whereIn('id', $featuredIds)
                ->get()
                ->sortBy(fn (Product $product): int => array_search($product->id, $featuredIds, true) === false ? PHP_INT_MAX : array_search($product->id, $featuredIds, true))
                ->take(8)
                ->values();
        }

        if ($featuredProducts->isEmpty()) {
            $featuredProducts = Product::query()
                ->with(['vendor', 'category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')])
                ->active()
                ->latest()
                ->limit(8)
                ->get();
        }

        $blogPosts = Page::query()->where('type', 'post')->latest()->limit(3)->get();

        return view('home', [
            'homepageContent' => $homepageContent,
            'featuredProducts' => $featuredProducts,
            'testimonials' => Testimonial::homepageItems(),
            'blogPosts' => $blogPosts,
            'portCards' => $this->cardsFor([5, 8, 16, 24, 48], 'port-switches'),
            'poeCards' => $this->cardsFor([4, 8, 16, 24, 48], 'port-poe-switches'),
            'typeCards' => $this->cardsFor([
                'poe-switches', 'managed-switches', 'unmanaged-switches', 'gigabit-switches', '2-5g-switches', '10g-switches', 'layer-3-switches', 'fiber-switches',
            ]),
            'brandCards' => $this->cardsFor(['tp-link-switches', 'ubiquiti-switches', 'mikrotik-switches', 'd-link-switches', 'tenda-switches', 'ruijie-switches', 'cisco-switches', 'huawei-switches']),
            'solutionCards' => $this->cardsFor(['cctv-switches', 'wifi-access-point-switches', 'office-switches', 'isp-switches', 'enterprise-switches', 'hotel-switches', 'school-switches', 'data-centre-switches']),
        ]);
    }

    private function renderCategoryGroup(Request $request, string $type, ?string $categorySlug, string $rootSlug): View|RedirectResponse
    {
        $targetSlug = $categorySlug ? Str::slug($categorySlug) : $rootSlug;

        $category = $this->findCategoryBySlug($targetSlug);

        if ($category) {
            // If requested through the wrong group, redirect to its canonical URL.
            $categoryType = $category->type ?: SwitchCatalog::TYPE_SHOP;
            if ($categoryType !== $type && ! ($type === SwitchCatalog::TYPE_SHOP && in_array($category->slug, ['network-switches'], true))) {
                return redirect(SwitchCatalog::urlFor($category), 301);
            }

            if ($category->slug !== $targetSlug) {
                return redirect(SwitchCatalog::urlFor($category), 301);
            }
        } else {
            // Root page for a group.
            if ($targetSlug === $rootSlug) {
                $category = Category::query()->where('slug', $rootSlug)->first();
            }

            if (! $category) {
                abort(404);
            }
        }

        $category->load(['parent', 'children']);

        $isHub = $type !== SwitchCatalog::TYPE_SHOP && $category->parent_id === null;

        $filters = SwitchCatalog::extractFilters($request);
        $sort = (string) $request->query('sort', 'featured');
        if (! array_key_exists($sort, SwitchCatalog::sortOptions())) {
            $sort = 'featured';
        }

        $products = null;
        if (! $isHub) {
            $query = Product::query()
                ->with(['vendor', 'category', 'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')])
                ->active();

            SwitchCatalog::applyCategory($query, $category);
            SwitchCatalog::applyFilters($query, $filters);
            SwitchCatalog::applySort($query, $sort);

            $products = $query->paginate(self::CATEGORY_PAGE_SIZE)->withQueryString();
        }

        $hubChildren = $isHub
            ? $category->children()->orderBy('name')->get()
            : collect();

        $hasFilters = $filters !== [];

        return view('category.index', [
            'currentCategory' => $category,
            'products' => $products,
            'filters' => $filters,
            'hasFilters' => $hasFilters,
            'sort' => $sort,
            'sortOptions' => SwitchCatalog::sortOptions(),
            'isHub' => $isHub,
            'hubChildren' => $hubChildren,
            'relatedCategories' => $this->relatedCategories($category),
            'faqItems' => is_array($category->faq_items) ? $category->faq_items : [],
        ]);
    }

    /**
     * @param  array<int, int|string>  $slugs
     * @return array<int, array{label: string, url: string}>
     */
    private function cardsFor(array $slugs, ?string $suffix = null): array
    {
        $cards = [];

        foreach ($slugs as $slug) {
            $categorySlug = $suffix ? $slug.'-'.$suffix : (string) $slug;
            $definition = SwitchCatalog::definition($categorySlug);
            if (! $definition) {
                continue;
            }

            $cards[] = [
                'label' => $definition['name'],
                'url' => SwitchCatalog::categoryUrl($categorySlug),
            ];
        }

        return $cards;
    }

    private function findCategoryBySlug(string $slug): ?Category
    {
        return Category::query()->whereRaw('LOWER(slug) = ?', [Str::lower(Str::slug($slug))])->first();
    }

    private function resolveProduct(string $slug): Product|RedirectResponse
    {
        $requestedSlug = Str::slug($slug);
        $product = $this->findProductBySlug($requestedSlug);

        abort_unless($product, 404);

        if ($product->slug !== $requestedSlug) {
            return redirect()->route('product.show', $product, 301);
        }

        return $product;
    }

    private function findProductBySlug(string $slug): ?Product
    {
        return Product::query()->whereRaw('LOWER(slug) = ?', [Str::lower(Str::slug($slug))])->first();
    }

    private function relatedCategories(?Category $currentCategory)
    {
        $slugs = ['poe-switches', 'managed-switches', 'unmanaged-switches', 'gigabit-switches', '10g-switches', 'fiber-switches', 'tp-link-switches', 'ubiquiti-switches'];

        if ($currentCategory && ($currentCategory->type === SwitchCatalog::TYPE_BRANDS || $currentCategory->type === SwitchCatalog::TYPE_PORTS || $currentCategory->type === SwitchCatalog::TYPE_SOLUTIONS)) {
            $slugs = ['poe-switches', 'managed-switches', 'unmanaged-switches', 'gigabit-switches', 'fiber-switches'];
        }

        return Category::query()
            ->whereIn('slug', $slugs)
            ->when($currentCategory, fn ($query) => $query->whereKeyNot($currentCategory->id))
            ->get();
    }

    private function showTrustPage(string $slug): View
    {
        $trustPages = [
            'about-us' => [
                'title' => 'About Us',
                'heading' => 'About Network Switches Kenya',
                'summary' => 'Information about the business behind this network switch ecommerce website can be added from the admin content area.',
            ],
            'contact-us' => [
                'title' => 'Contact Us',
                'heading' => 'Contact Network Switches Kenya',
                'summary' => 'Use the available contact details below to enquire about products, quotations, delivery and support.',
            ],
            'delivery-policy' => [
                'title' => 'Delivery Policy',
                'heading' => 'Delivery Policy',
                'summary' => 'Delivery availability, cost and timelines are confirmed before dispatch based on order size, stock location and destination.',
            ],
            'returns-policy' => [
                'title' => 'Returns Policy',
                'heading' => 'Returns Policy',
                'summary' => 'Return eligibility depends on product condition, seller terms and the reason for return. Confirm terms before completing high-value orders.',
            ],
            'warranty-policy' => [
                'title' => 'Warranty Policy',
                'heading' => 'Warranty Policy',
                'summary' => 'Warranty coverage depends on the product, seller and supplier terms. Confirm warranty status before purchase or quotation approval.',
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'heading' => 'Privacy Policy',
                'summary' => 'Customer information is used to process accounts, orders, delivery communication and support requests.',
            ],
            'terms-and-conditions' => [
                'title' => 'Terms and Conditions',
                'heading' => 'Terms and Conditions',
                'summary' => 'Orders, quotations, payments, delivery and support are handled under the seller terms shown during purchase or direct enquiry.',
            ],
        ];

        abort_unless(isset($trustPages[$slug]), 404);

        return view('page.trust', [
            'slug' => $slug,
            'trustPage' => $trustPages[$slug],
            'canonicalUrl' => CanonicalUrl::route('pages.show', ['page' => $slug]),
        ]);
    }
}
