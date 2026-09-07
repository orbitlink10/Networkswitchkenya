<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Support\ImageManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportProductImages extends Command
{
    protected $signature = 'images:import
        {--limit= : Maximum number of products to process}
        {--only-slug= : Process a single product by slug}
        {--force : Re-import products that already have local images}
        {--dry-run : Report only, do not download or write anything}
        {--include-categories : Also import external category images}';

    protected $description = 'Import external manufacturer images into local, optimized storage';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stats = [
            'products_checked' => 0,
            'external_found' => 0,
            'imported' => 0,
            'skipped' => 0,
            'already_local' => 0,
            'duplicates' => 0,
            'failed' => 0,
            'invalid' => 0,
            'categories_checked' => 0,
            'categories_imported' => 0,
        ];

        $query = Product::query()->active();

        if ($onlySlug = $this->option('only-slug')) {
            $query->where('slug', $onlySlug);
        }

        $query->limit($this->limitFromOption());

        $products = $query->get();

        foreach ($products as $product) {
            $stats['products_checked']++;

            $urls = ImageManager::externalUrls($product);
            if ($urls === []) {
                continue;
            }
            $stats['external_found'] += count($urls);

            $hasLocal = $product->images()->count() > 0;
            if ($hasLocal && ! $this->option('force')) {
                $stats['already_local'] += count($urls);
                $this->line("<comment>skip</comment> {$product->slug} (already local)");
                continue;
            }

            $primary = ! $hasLocal;

            foreach ($urls as $index => $url) {
                if ($dryRun) {
                    $stats['skipped']++;
                    $this->line("<comment>dry-run</comment> {$product->slug} {$url}");
                    continue;
                }

                $result = ImageManager::import($product, $url, $primary && $index === 0);

                match ($result['status']) {
                    'imported' => $stats['imported']++,
                    'duplicate' => $stats['duplicates']++,
                    'invalid' => $stats['invalid']++,
                    'failed' => $stats['failed']++,
                    default => $stats['skipped']++,
                };

                $tag = match ($result['status']) {
                    'imported' => 'info',
                    'duplicate' => 'comment',
                    default => 'error',
                };
                $this->line("<{$tag}>{$result['status']}</{$tag}> {$product->slug} {$result['message']}");
            }
        }

        if ($this->option('include-categories')) {
            $this->importCategories($stats, $dryRun);
        }

        $stillExternal = $this->countStillExternal();

        $this->newLine();
        $this->info('Image import report');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Products checked', $stats['products_checked']],
                ['External images found', $stats['external_found']],
                ['Imported', $stats['imported']],
                ['Duplicates detected', $stats['duplicates']],
                ['Already local (skipped)', $stats['already_local']],
                ['Failed downloads', $stats['failed']],
                ['Invalid URLs', $stats['invalid']],
                ['Categories checked', $stats['categories_checked']],
                ['Categories imported', $stats['categories_imported']],
                ['Products still using external URLs', $stillExternal],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function importCategories(array &$stats, bool $dryRun): void
    {
        $categories = Category::query()
            ->whereNotNull('image_url')
            ->where('image_url', 'like', 'http%')
            ->get();

        foreach ($categories as $category) {
            $stats['categories_checked']++;
            $url = $category->image_url;

            if (! ImageManager::isSafeImageUrl($url)) {
                $this->line("<error>invalid</error> category {$category->slug}");
                continue;
            }

            if ($dryRun) {
                $this->line("<comment>dry-run</comment> category {$category->slug} {$url}");
                continue;
            }

            $local = ImageManager::importCategoryImage($category, $url);
            if ($local) {
                $stats['categories_imported']++;
                $this->line("<info>imported</info> category {$category->slug}");
            } else {
                $this->line("<error>failed</error> category {$category->slug}");
            }
        }
    }

    private function countStillExternal(): int
    {
        return Product::query()
            ->active()
            ->whereDoesntHave('images')
            ->where(function ($query) {
                $query->whereNotNull('official_image_url')
                    ->orWhereNotNull('official_gallery_images');
            })
            ->count();
    }

    private function limitFromOption(): ?int
    {
        $limit = $this->option('limit');

        return $limit !== null && (int) $limit > 0 ? (int) $limit : null;
    }
}
