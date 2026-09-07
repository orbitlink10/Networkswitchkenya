<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\CanonicalUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComparisonController extends Controller
{
    private const MAX_PRODUCTS = 4;

    /**
     * @return array<int, array{key: string, label: string, value: \Closure}>
     */
    private function attributes(): array
    {
        return [
            ['key' => 'price', 'label' => 'Price', 'value' => fn (Product $p): string => 'KSh '.number_format((float) $p->price, 2)],
            ['key' => 'brand', 'label' => 'Brand', 'value' => fn (Product $p): string => (string) ($p->brand ?: '—')],
            ['key' => 'model', 'label' => 'Model', 'value' => fn (Product $p): string => (string) ($p->model_number ?: '—')],
            ['key' => 'port_count', 'label' => 'Total Ports', 'value' => fn (Product $p): string => $p->port_count ? (string) $p->port_count : '—'],
            ['key' => 'rj45_ports', 'label' => 'RJ45 Ports', 'value' => fn (Product $p): string => $p->rj45_ports ? (string) $p->rj45_ports : '—'],
            ['key' => 'poe_ports', 'label' => 'PoE Ports', 'value' => fn (Product $p): string => $p->poe_ports ? (string) $p->poe_ports : '—'],
            ['key' => 'poe_standard', 'label' => 'PoE Standard', 'value' => fn (Product $p): string => self::poeStandard($p)],
            ['key' => 'poe_budget', 'label' => 'PoE Budget', 'value' => fn (Product $p): string => $p->poe_budget ? $p->poe_budget.'W' : '—'],
            ['key' => 'port_speed', 'label' => 'Port Speed', 'value' => fn (Product $p): string => self::speedLabel($p)],
            ['key' => 'uplink_type', 'label' => 'Uplink', 'value' => fn (Product $p): string => $p->uplink_type ? strtoupper($p->uplink_type) : '—'],
            ['key' => 'sfp_ports', 'label' => 'SFP Ports', 'value' => fn (Product $p): string => $p->sfp_ports ? (string) $p->sfp_ports : '—'],
            ['key' => 'sfp_plus_ports', 'label' => 'SFP+ Ports', 'value' => fn (Product $p): string => $p->sfp_plus_ports ? (string) $p->sfp_plus_ports : '—'],
            ['key' => 'management', 'label' => 'Management', 'value' => fn (Product $p): string => self::managementLabel($p)],
            ['key' => 'layer', 'label' => 'Layer', 'value' => fn (Product $p): string => $p->layer ? strtoupper($p->layer) : '—'],
            ['key' => 'switching_capacity', 'label' => 'Switching Capacity', 'value' => fn (Product $p): string => $p->switching_capacity ?: '—'],
            ['key' => 'vlan_support', 'label' => 'VLAN', 'value' => fn (Product $p): string => self::boolLabel($p->vlan_support)],
            ['key' => 'qos', 'label' => 'QoS', 'value' => fn (Product $p): string => self::boolLabel($p->qos)],
            ['key' => 'rackmount', 'label' => 'Rackmount', 'value' => fn (Product $p): string => self::boolLabel($p->rackmount)],
            ['key' => 'warranty', 'label' => 'Warranty', 'value' => fn (Product $p): string => $p->warranty ?: ($p->warranty_info ?: '—')],
            ['key' => 'availability', 'label' => 'Availability', 'value' => fn (Product $p): string => self::availabilityLabel($p)],
        ];
    }

    private static function poeStandard(Product $p): string
    {
        return match ((string) $p->poe_standard) {
            'af' => 'PoE (802.3af)',
            'at' => 'PoE+ (802.3at)',
            'bt' => 'PoE++ (802.3bt)',
            default => $p->hasPoe() ? 'PoE' : 'Non-PoE',
        };
    }

    private static function speedLabel(Product $p): string
    {
        return \App\Support\SwitchCatalog::speeds()[(string) $p->port_speed] ?? '—';
    }

    private static function managementLabel(Product $p): string
    {
        return \App\Support\SwitchCatalog::managementTypes()[(string) $p->management_type] ?? '—';
    }

    private static function boolLabel(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    private static function availabilityLabel(Product $p): string
    {
        return match ($p->availabilityStatus()) {
            'in_stock' => 'In Stock',
            'preorder' => 'Preorder',
            default => 'Out of Stock',
        };
    }

    /**
     * @return array<int, int>
     */
    private function sessionIds(Request $request): array
    {
        $ids = (array) session()->get('comparison', []);

        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function storeIds(array $ids): void
    {
        session()->put('comparison', array_slice(array_values(array_unique($ids)), 0, self::MAX_PRODUCTS));
    }

    public function index(Request $request): View
    {
        $ids = $this->sessionIds($request);
        $products = collect();

        if ($ids !== []) {
            $products = Product::query()
                ->with(['vendor', 'category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')])
                ->active()
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(fn (Product $product): int => array_search($product->id, $ids, true) === false ? PHP_INT_MAX : array_search($product->id, $ids, true))
                ->values();
        }

        return view('comparison.index', [
            'products' => $products,
            'attributes' => $this->attributes(),
            'canonicalUrl' => CanonicalUrl::route('comparison.index'),
        ]);
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        if ($product->status !== 'active' || ! $product->vendor?->is_approved) {
            abort(404);
        }

        $ids = $this->sessionIds($request);
        if (count($ids) >= self::MAX_PRODUCTS) {
            array_shift($ids);
        }
        $ids[] = $product->id;
        $this->storeIds($ids);

        return back()->with('success', $product->name.' added to comparison.');
    }

    public function remove(Request $request, Product $product): RedirectResponse
    {
        $ids = array_values(array_filter($this->sessionIds($request), fn (int $id): bool => $id !== $product->id));
        $this->storeIds($ids);

        return back()->with('success', 'Removed from comparison.');
    }

    public function clear(Request $request): RedirectResponse
    {
        session()->forget('comparison');

        return back()->with('success', 'Comparison cleared.');
    }
}
