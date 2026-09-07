<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\SwitchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwitchAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SwitchCatalogSeeder::class);
    }

    public function test_guest_is_redirected_from_admin_and_cart(): void
    {
        $this->get('/admin')->assertRedirect('/login.php');
        $this->get('/cart')->assertRedirect('/login.php');
        $this->get('/checkout')->assertRedirect('/login.php');
    }

    public function test_admin_can_view_product_form_with_switch_fields(): void
    {
        $admin = User::where('email', 'admin@demo.com')->first();
        $this->actingAs($admin);

        $this->get('/admin/products/create')
            ->assertOk()
            ->assertSee('Switch Specifications')
            ->assertSee('PoE Budget');
    }

    public function test_admin_can_create_product_with_switch_attributes(): void
    {
        $admin = User::where('email', 'admin@demo.com')->first();
        $this->actingAs($admin);

        $category = Category::where('slug', '16-port-poe-switches')->first();

        $response = $this->post('/admin/products', [
            'name' => 'Test 16-Port Gigabit PoE Switch',
            'price' => 15000,
            'stock' => 10,
            'category_id' => $category->id,
            'port_count' => 18,
            'rj45_ports' => 16,
            'poe_ports' => 16,
            'poe_standard' => 'at',
            'poe_budget' => 200,
            'management_type' => 'smart',
            'port_speed' => '1g',
            'applications' => ['cctv', 'wifi'],
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::where('name', 'Test 16-Port Gigabit PoE Switch')->first();
        $this->assertNotNull($product);
        $this->assertSame(18, $product->port_count);
        $this->assertSame(16, $product->poe_ports);
        $this->assertSame('at', $product->poe_standard);
        $this->assertSame(200, $product->poe_budget);
        $this->assertSame('smart', $product->management_type);
        $this->assertSame(['cctv', 'wifi'], $product->applicationSlugs());
    }
}
