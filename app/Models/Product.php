<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'category_id',
        'name',
        'slug',
        'description',
        'meta_description',
        'price',
        'compare_at_price',
        'stock',
        'sku',
        'status',
        'seo_title',
        'canonical_url',
        'robots',
        'og_title',
        'og_description',
        'og_image',
        'model_number',
        'brand',
        'key_use',
        'key_specifications',
        'use_cases',
        'technical_specifications',
        'whats_in_box',
        'recommended_applications',
        'choose_another_model',
        'compatibility',
        'power_requirements',
        'warranty_info',
        'delivery_info',
        'payment_info',
        'faq_items',
        'official_image_url',
        'official_gallery_images',
        'official_video_url',
        'official_media_synced_at',
        'port_count',
        'rj45_ports',
        'sfp_ports',
        'sfp_plus_ports',
        'sfp28_ports',
        'qsfp_ports',
        'poe_ports',
        'poe_budget',
        'poe_max_per_port',
        'poe_standard',
        'management_type',
        'layer',
        'port_speed',
        'uplink_type',
        'switching_capacity',
        'forwarding_rate',
        'mac_table',
        'vlan_support',
        'qos',
        'stp',
        'lacp',
        'snmp',
        'acl',
        'rackmount',
        'desktop',
        'din_rail',
        'outdoor',
        'industrial',
        'stackable',
        'cooling',
        'power_supply',
        'power_consumption',
        'operating_temperature',
        'warranty',
        'availability',
        'applications',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'faq_items' => 'array',
        'official_gallery_images' => 'array',
        'official_media_synced_at' => 'datetime',
        'port_count' => 'integer',
        'rj45_ports' => 'integer',
        'sfp_ports' => 'integer',
        'sfp_plus_ports' => 'integer',
        'sfp28_ports' => 'integer',
        'qsfp_ports' => 'integer',
        'poe_ports' => 'integer',
        'poe_budget' => 'integer',
        'poe_max_per_port' => 'integer',
        'vlan_support' => 'boolean',
        'qos' => 'boolean',
        'stp' => 'boolean',
        'lacp' => 'boolean',
        'snmp' => 'boolean',
        'acl' => 'boolean',
        'rackmount' => 'boolean',
        'desktop' => 'boolean',
        'din_rail' => 'boolean',
        'outdoor' => 'boolean',
        'industrial' => 'boolean',
        'stackable' => 'boolean',
        'applications' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function seoFieldsReady(): bool
    {
        $table = (new static)->getTable();

        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'seo_title')
            && Schema::hasColumn($table, 'model_number')
            && Schema::hasColumn($table, 'faq_items');
    }

    public static function officialMediaFieldsReady(): bool
    {
        $table = (new static)->getTable();

        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'official_image_url')
            && Schema::hasColumn($table, 'official_gallery_images')
            && Schema::hasColumn($table, 'official_video_url');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function primaryImage(): HasMany
    {
        return $this->hasMany(ProductImage::class)->where('is_primary', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereHas('vendor', fn (Builder $builder) => $builder->where('is_approved', true));
    }

    public static function switchFieldsReady(): bool
    {
        $table = (new static)->getTable();

        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'port_count')
            && Schema::hasColumn($table, 'management_type')
            && Schema::hasColumn($table, 'applications');
    }

    /**
     * Availability status derived from explicit override or stock quantity.
     */
    public function availabilityStatus(): string
    {
        $explicit = trim((string) $this->availability);

        if (in_array($explicit, ['in_stock', 'out_of_stock', 'preorder'], true)) {
            return $explicit;
        }

        return $this->stock > 0 ? 'in_stock' : 'out_of_stock';
    }

    public function isInStock(): bool
    {
        return $this->availabilityStatus() === 'in_stock';
    }

    /**
     * @return array<int, string>
     */
    public function applicationSlugs(): array
    {
        if (! is_array($this->applications)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($slug): string => trim((string) $slug), $this->applications),
            fn (string $slug): bool => $slug !== ''
        )));
    }

    public function hasPoe(): bool
    {
        return in_array((string) $this->poe_standard, ['af', 'at', 'bt'], true)
            || (int) $this->poe_ports > 0;
    }
}
