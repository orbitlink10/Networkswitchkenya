<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductSeo
{
    public static function brand(Product $product): string
    {
        $brand = self::columnValue($product, 'brand');
        if ($brand) {
            return $brand;
        }

        return self::detectBrand($product) ?? config('app.name', 'Network Switches Kenya');
    }

    /**
     * Infer a brand from the product name/category when the brand column is empty.
     */
    public static function detectBrand(Product $product): ?string
    {
        $haystack = Str::lower($product->name.' '.$product->category?->name.' '.$product->slug);

        foreach (SwitchCatalog::brands() as $slug => $name) {
            foreach (SwitchCatalog::brandAliases($slug) as $alias) {
                if ($alias !== '' && Str::contains($haystack, $alias)) {
                    return $name;
                }
            }
        }

        return null;
    }

    public static function displayName(Product $product): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $product->name) ?? $product->name);
        $name = trim(preg_replace('/\s*[–—-]\s*$/u', '', $name)) ?? $name;

        return $name !== '' ? $name : self::model($product);
    }

    public static function model(Product $product): string
    {
        if ($model = self::columnValue($product, 'model_number')) {
            return trim(preg_replace('/\s*[–—-]\s*$/u', '', $model) ?? $model);
        }

        $name = trim(preg_replace('/\s+/u', ' ', $product->name) ?? $product->name);
        $model = trim(preg_replace('/\s*[–—-]\s*$/u', '', $name)) ?? $name;

        return trim($model) !== '' ? trim($model) : $product->sku;
    }

    public static function typeLabel(Product $product): string
    {
        return 'Network Switch';
    }

    /**
     * Compact specification summary for product cards.
     */
    public static function specSummary(Product $product): string
    {
        $parts = [];

        if ($product->port_count) {
            $parts[] = $product->port_count.'-Port';
        }

        if ($product->port_speed && isset(SwitchCatalog::speeds()[$product->port_speed])) {
            $parts[] = match ($product->port_speed) {
                '1g' => 'Gigabit',
                '2.5g' => '2.5G',
                '5g' => '5G',
                '10g' => '10G',
                '25g' => '25G',
                '40g' => '40G',
                '100g' => '100G',
                default => 'Fast Ethernet',
            };
        }

        if ($product->hasPoe()) {
            $parts[] = match ((string) $product->poe_standard) {
                'at' => 'PoE+',
                'bt' => 'PoE++',
                default => 'PoE',
            };
        }

        $uplinkParts = [];
        if ((int) $product->sfp_ports > 0) {
            $uplinkParts[] = $product->sfp_ports.'× SFP';
        }
        if ((int) $product->sfp_plus_ports > 0) {
            $uplinkParts[] = $product->sfp_plus_ports.'× SFP+';
        }
        if ($uplinkParts !== []) {
            $parts[] = implode(' ', $uplinkParts);
        }

        if ($product->management_type && isset(SwitchCatalog::managementTypes()[$product->management_type])) {
            $parts[] = SwitchCatalog::managementTypes()[$product->management_type];
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Network Switch';
    }

    public static function keyUse(Product $product): string
    {
        if ($keyUse = self::columnValue($product, 'key_use')) {
            return $keyUse;
        }

        return 'Switching, network expansion and device connectivity';
    }

    /**
     * @return array<string, string>
     */
    public static function specs(Product $product): array
    {
        $specs = [
            'Model' => self::model($product),
            'Brand' => self::brand($product),
            'SKU' => $product->sku,
            'Category' => $product->category?->name ?? 'MikroTik products',
            'Current price' => 'KSh '.number_format((float) $product->price, 2),
            'Availability' => $product->stock > 0 ? 'In stock' : 'Out of stock',
        ];

        foreach (self::linesFromColumn($product, 'technical_specifications') as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));
                if ($key !== '' && $value !== '') {
                    $specs[$key] = $value;
                }
            }
        }

        return array_filter($specs, fn (?string $value): bool => trim((string) $value) !== '');
    }

    /**
     * @return array<int, string>
     */
    public static function useCases(Product $product): array
    {
        $custom = self::linesFromColumn($product, 'use_cases');
        if ($custom !== []) {
            return $custom;
        }

        return ['Office LAN expansion', 'CCTV and IP camera networks', 'WiFi access point connectivity'];
    }

    /**
     * @return array<int, string>
     */
    public static function applications(Product $product): array
    {
        return self::linesFromColumn($product, 'recommended_applications') ?: self::useCases($product);
    }

    public static function compatibility(Product $product): string
    {
        return self::columnValue($product, 'compatibility')
            ?: 'Works with standard Ethernet networking equipment including routers, computers, IP cameras, access points and servers. Confirm port, PoE and mounting requirements before purchase.';
    }

    public static function powerRequirements(Product $product): string
    {
        return self::columnValue($product, 'power_requirements')
            ?: 'Check the product label or manufacturer datasheet for exact input voltage, power adapter and PoE requirements.';
    }

    public static function warrantyInfo(Product $product): string
    {
        return self::columnValue($product, 'warranty_info')
            ?: (self::columnValue($product, 'warranty') ?: 'Warranty terms depend on the seller and product condition. Confirm warranty coverage before checkout.');
    }

    public static function deliveryInfo(Product $product): string
    {
        return self::columnValue($product, 'delivery_info')
            ?: 'Delivery options and timelines are confirmed during checkout or direct enquiry based on stock location and destination.';
    }

    public static function paymentInfo(Product $product): string
    {
        return self::columnValue($product, 'payment_info')
            ?: 'Payment options are confirmed at checkout or through the seller before dispatch.';
    }

    public static function chooseAnotherModel(Product $product): ?string
    {
        return self::columnValue($product, 'choose_another_model');
    }

    /**
     * @return array<int, string>
     */
    public static function whatsInBox(Product $product): array
    {
        return self::linesFromColumn($product, 'whats_in_box')
            ?: [self::displayName($product).' unit', 'Power adapter (where included)', 'Installation guide'];
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public static function faqs(Product $product): array
    {
        $custom = self::faqItems($product);
        if ($custom !== []) {
            return $custom;
        }

        $displayName = self::displayName($product);

        return [
            [
                'question' => 'Is '.$displayName.' available in Kenya?',
                'answer' => $product->stock > 0
                    ? $displayName.' is currently listed as available. Stock can change, so confirm availability before placing a large order.'
                    : $displayName.' is currently listed as out of stock. Contact the seller to confirm the next availability date.',
            ],
            [
                'question' => 'What is the current price of '.$displayName.'?',
                'answer' => 'The current listed price is KSh '.number_format((float) $product->price, 2).'. Prices are generated from the product catalogue and may change when inventory is updated.',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    public static function comparisonLinks(Product $product): array
    {
        return [];
    }

    public static function youtubeVideoId(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('/(?:youtube\.com|youtube-nocookie\.com)\/(?:watch\?(?:[^#]*&)?v=|embed\/|shorts\/)([A-Za-z0-9_-]{6,})/', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('/youtu\.be\/([A-Za-z0-9_-]{6,})/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function youtubeEmbedUrl(?string $url): ?string
    {
        $videoId = self::youtubeVideoId($url);

        return $videoId ? 'https://www.youtube-nocookie.com/embed/'.$videoId : null;
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    private static function faqItems(Product $product): array
    {
        if (! self::columnReady($product, 'faq_items') || ! is_array($product->faq_items)) {
            return [];
        }

        $items = [];
        foreach ($product->faq_items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question !== '' && $answer !== '') {
                $items[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private static function linesFromColumn(Product $product, string $column): array
    {
        $value = self::columnValue($product, $column);
        if (! $value) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $line): string => trim(strip_tags($line)),
            preg_split('/\r\n|\r|\n/', $value) ?: []
        )));
    }

    private static function columnValue(Product $product, string $column): ?string
    {
        if (! self::columnReady($product, $column)) {
            return null;
        }

        $value = trim((string) ($product->{$column} ?? ''));

        return $value !== '' ? $value : null;
    }

    private static function columnReady(Product $product, string $column): bool
    {
        static $cache = [];
        $key = $product->getTable().'.'.$column;

        return $cache[$key] ??= Schema::hasColumn($product->getTable(), $column);
    }
}
