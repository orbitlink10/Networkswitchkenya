<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_title',
        'meta_description',
        'title',
        'heading_two',
        'slug',
        'image_url',
        'alt_text',
        'type',
        'category',
        'body',
        'seo_title',
        'canonical_url',
        'robots',
        'og_title',
        'og_description',
        'og_image',
        'faq_items',
    ];

    protected $casts = [
        'faq_items' => 'array',
    ];

    public static function storageReady(): bool
    {
        return Schema::hasTable((new static)->getTable());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function seoFieldsReady(): bool
    {
        $table = (new static)->getTable();

        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'seo_title')
            && Schema::hasColumn($table, 'canonical_url')
            && Schema::hasColumn($table, 'faq_items');
    }
}
