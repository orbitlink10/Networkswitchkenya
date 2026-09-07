<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'role',
        'quote',
        'rating',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'rating' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    public static function storageReady(): bool
    {
        return Schema::hasTable((new static())->getTable());
    }

    /**
     * @return Collection<int, static>
     */
    public static function homepageItems(): Collection
    {
        if (!static::storageReady()) {
            return static::defaultItems();
        }

        return static::query()
            ->visible()
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, static>
     */
    public static function defaultItems(): Collection
    {
        return collect([
            [
                'name' => 'Joan K., Nairobi',
                'role' => 'Customer',
                'quote' => 'The installation team arrived on time, explained the ideal mounting position, and got us online the same day. The experience felt professional from start to finish.',
                'rating' => 5,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Samuel O., Meru',
                'role' => 'Customer',
                'quote' => 'Our children now attend online classes without interruptions, and video meetings are finally stable. Starlink has made a visible difference in our day-to-day routine.',
                'rating' => 5,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Victor M., Rongai',
                'role' => 'Customer',
                'quote' => 'Uploads that used to take forever now finish quickly, which matters a lot for my content work. For creators working outside strong fiber zones, this is a serious upgrade.',
                'rating' => 5,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ])->map(fn (array $attributes): static => new static($attributes));
    }
}
