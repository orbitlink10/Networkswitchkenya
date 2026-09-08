<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SwitchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwitchStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SwitchCatalogSeeder::class);
    }

    public function test_homepage_renders_switch_positioning_and_sections(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Network Switches in Kenya')
            ->assertSee('Shop by Port Count')
            ->assertSee('Shop PoE Switches')
            ->assertSee('Featured Brands')
            ->assertSee('Find My Switch');
    }

    public function test_shop_root_and_category_pages_render(): void
    {
        $this->get('/network-switches')->assertOk();
        $this->get('/network-switches/poe-switches')->assertOk();
        $this->get('/network-switches/managed-switches')->assertOk();
        $this->get('/network-switches/gigabit-switches')->assertOk();
    }

    public function test_ports_pages_render(): void
    {
        $this->get('/ports')->assertOk();
        $this->get('/ports/8-port-switches')->assertOk();
        $this->get('/ports/24-port-switches')->assertOk();
    }

    public function test_brand_pages_render(): void
    {
        $this->get('/brands')->assertOk();
        $this->get('/brands/tp-link-switches')->assertOk()->assertSee('TP-Link');
        $this->get('/brands/mikrotik-switches')->assertOk()->assertSee('MikroTik');
    }

    public function test_solution_pages_render(): void
    {
        $this->get('/solutions')->assertOk();
        $this->get('/solutions/cctv-switches')->assertOk();
    }

    public function test_product_page_renders_with_specs(): void
    {
        $product = Product::where('sku', 'TPL-SG2428P')->first();
        $this->assertNotNull($product);

        $this->get(route('product.show', $product))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Key specifications');
    }

    public function test_search_returns_results(): void
    {
        $this->get('/?search=SG2428P')->assertOk()->assertSee('TL-SG2428P');
        $this->get('/?search=MikroTik')->assertOk()->assertSee('MikroTik');
    }

    public function test_filters_and_sorting_work(): void
    {
        $this->get('/network-switches?brand=tp-link')->assertOk();
        $this->get('/ports/8-port-switches?poe=at')->assertOk();
        $this->get('/network-switches?management=managed')->assertOk();
        $this->get('/network-switches/gigabit-switches?sort=price_asc')->assertOk();
    }

    public function test_deals_page_lists_discounted_switches(): void
    {
        $this->get('/deals')->assertOk()->assertSee('TL-SG105');
    }

    public function test_switch_finder_renders_steps_and_results(): void
    {
        $this->get('/switch-finder')
            ->assertOk()
            ->assertSee('How many devices do you want to connect?');

        $this->get('/switch-finder?devices=24&poe=yes&devices_type=cctv&speed=1g&management=none')
            ->assertOk();
    }

    public function test_blog_index_renders(): void
    {
        $this->get('/blog')->assertOk();
    }

    public function test_blog_post_renders_with_article_schema(): void
    {
        $post = \App\Models\Page::where('type', 'post')->first();
        $this->assertNotNull($post);

        $this->get(route('blog.show', ['slug' => $post->slug]))
            ->assertOk()
            ->assertSee('BlogPosting');
    }

    public function test_category_page_includes_collection_schema(): void
    {
        $this->get('/network-switches/8-port-poe-switches')
            ->assertOk()
            ->assertSee('CollectionPage');
    }

    public function test_utility_pages_are_noindexed(): void
    {
        $this->get('/switch-finder')->assertOk()->assertSee('noindex,follow');
        $this->get('/compare')->assertOk()->assertSee('noindex,follow');
    }

    public function test_sitemap_excludes_utility_finder(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/switch-finder');
    }

    public function test_sitemap_returns_xml(): void
    {
        $response = $this->get('/sitemap.xml');
        $response->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('content-type'));
        $response->assertSee('/network-switches/poe-switches');
    }

    public function test_legacy_category_url_redirects_to_friendly_url(): void
    {
        $this->get('/category/poe-switches')
            ->assertRedirect('/network-switches/poe-switches');
    }

    public function test_category_auto_assignment_surfaces_products_without_duplication(): void
    {
        $poeCategory = Category::where('slug', 'poe-switches')->first();
        $query = Product::query()->active();
        \App\Support\SwitchCatalog::applyCategory($query, $poeCategory);

        $ids = $query->pluck('id')->all();
        $this->assertNotEmpty($ids);
        $this->assertSame($ids, array_values(array_unique($ids)), 'Category query must not return duplicate products');
    }
}
