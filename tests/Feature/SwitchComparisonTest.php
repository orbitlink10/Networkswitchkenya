<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\SwitchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwitchComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SwitchCatalogSeeder::class);
    }

    public function test_comparison_page_renders_empty_state(): void
    {
        $this->get('/compare')->assertOk()->assertSee('No switches selected yet');
    }

    public function test_add_remove_and_clear_comparison(): void
    {
        $first = Product::where('sku', 'TPL-SG105')->first();
        $second = Product::where('sku', 'TPL-SG108')->first();

        $this->post(route('comparison.add', $first))->assertRedirect();
        $this->post(route('comparison.add', $second))->assertRedirect();

        $this->get('/compare')
            ->assertOk()
            ->assertSee($first->name)
            ->assertSee($second->name)
            ->assertSee('PoE Standard');

        $this->post(route('comparison.remove', $first))->assertRedirect();
        $this->get('/compare')->assertOk()->assertSee($second->name)->assertDontSee($first->name);

        $this->post(route('comparison.clear'))->assertRedirect();
        $this->get('/compare')->assertOk()->assertSee('No switches selected yet');
    }
}
