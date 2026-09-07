<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Downloads, validates, optimizes and stores product images locally so the
 * storefront never depends on permanent external manufacturer hotlinks.
 */
class ImageManager
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; NetworkSwitchesKenya/1.0; +image-import)';

    /**
     * @return array<int, string>
     */
    public static function externalUrls(Product $product): array
    {
        $urls = [];

        if (is_array($product->official_gallery_images) && $product->official_gallery_images !== []) {
            foreach ($product->official_gallery_images as $url) {
                $url = trim((string) $url);
                if ($url !== '') {
                    $urls[] = $url;
                }
            }
        }

        if (($single = trim((string) $product->official_image_url)) !== '') {
            $urls[] = $single;
        }

        if ($urls === [] && ($static = ProductImageCatalog::officialUrlFor($product->name))) {
            $urls[] = $static;
        }

        return array_values(array_unique($urls));
    }

    /**
     * Import a single remote image for a product.
     *
     * @return array{status: string, message: string, image: ?ProductImage}
     */
    public static function import(Product $product, string $url, bool $primary = false): array
    {
        if (! self::isSafeImageUrl($url)) {
            return ['status' => 'invalid', 'message' => 'URL is not a safe, public HTTP(S) image URL.', 'image' => null];
        }

        $download = self::download($url);
        if ($download === null) {
            return ['status' => 'failed', 'message' => 'Could not download the image.', 'image' => null];
        }

        $mime = self::validateImage($download['path']);
        if ($mime === null) {
            File::delete($download['path']);

            return ['status' => 'invalid', 'message' => 'File is not a valid image.', 'image' => null];
        }

        $hash = hash_file('sha256', $download['path']);

        $existing = self::existingImageByHash($hash);
        if ($existing !== null) {
            File::delete($download['path']);

            return [
                'status' => 'duplicate',
                'message' => 'Image already imported locally.',
                'image' => self::attachImage($product, $existing, $url, self::altText($product), $primary),
            ];
        }

        $stored = self::optimizeAndStore($download['path'], $mime, $product);
        File::delete($download['path']);

        if ($stored === null) {
            return ['status' => 'failed', 'message' => 'Could not store the image locally.', 'image' => null];
        }

        $image = self::attachImage($product, $stored['path'], $url, self::altText($product), $primary, $hash);

        return ['status' => 'imported', 'message' => 'Image imported.', 'image' => $image];
    }

    /**
     * @return array{status: string, message: string, image: ?ProductImage}
     */
    private static function attachImage(Product $product, string $path, string $originalUrl, string $alt, bool $primary, ?string $hash = null): ProductImage
    {
        if ($primary) {
            $product->images()->update(['is_primary' => false]);
        }

        $image = ProductImage::updateOrCreate(
            ['product_id' => $product->id, 'image_url' => $path],
            [
                'original_image_url' => $originalUrl,
                'image_alt' => $alt,
                'source_domain' => self::hostOf($originalUrl),
                'file_hash' => $hash ?? hash('sha256', $path),
                'imported_at' => now(),
                'is_primary' => $primary || ! $product->images()->where('is_primary', true)->exists(),
                'sort_order' => $product->images()->count(),
            ]
        );

        return $image;
    }

    /**
     * Reuse an already-imported file (same content hash) if it still exists.
     */
    private static function existingImageByHash(string $hash): ?string
    {
        $image = ProductImage::query()
            ->where('file_hash', $hash)
            ->whereNotNull('image_url')
            ->first();

        if (! $image) {
            return null;
        }

        $relative = trim((string) $image->image_url, '/');
        if (is_file(public_path($relative))) {
            return $relative;
        }

        return null;
    }

    /**
     * Download a remote image to a temp file, enforcing size + timeout limits.
     *
     * @return array{path: string}|null
     */
    private static function download(string $url): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'image/*'])
                ->timeout((int) config('images.timeout', 30))
                ->withoutVerifying()
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $maxBytes = (int) config('images.max_file_bytes', 10485760);

        $length = (int) $response->header('Content-Length');
        if ($length > 0 && $length > $maxBytes) {
            return null;
        }

        $body = $response->body();
        if ($body === '' || strlen($body) > $maxBytes) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'img_import_');
        if ($tempPath === false) {
            return null;
        }

        if (File::put($tempPath, $body) === false) {
            return null;
        }

        return ['path' => $tempPath];
    }

    /**
     * Confirm the downloaded file is a valid, allowed image. Returns MIME type.
     */
    private static function validateImage(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        $allowed = (array) config('images.allowed_mime_types', [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif',
        ]);

        if (! in_array($mime, $allowed, true)) {
            return null;
        }

        if (@getimagesize($path) === false) {
            return null;
        }

        return $mime;
    }

    /**
     * Convert + resize the downloaded image into locally stored WebP files.
     *
     * @return array{path: string}|null
     */
    private static function optimizeAndStore(string $sourcePath, string $mime, Product $product): ?array
    {
        $relative = self::relativePath($product, 'webp');

        if (self::gdSupports($mime) && self::gdWebp()) {
            if (self::convertToWebp($sourcePath, $mime, $product, $relative)) {
                return ['path' => $relative];
            }
        }

        // Fallback: store the original (validated) image with its native extension.
        $extension = self::extensionForMime($mime);
        $relative = self::relativePath($product, $extension);
        $absolute = public_path($relative);
        File::ensureDirectoryExists(dirname($absolute));

        if (! copy($sourcePath, $absolute)) {
            return null;
        }

        return ['path' => $relative];
    }

    private static function convertToWebp(string $sourcePath, string $mime, Product $product, string $relative): bool
    {
        $image = self::createImage($sourcePath, $mime);
        if (! $image) {
            return false;
        }

        try {
            $originalWidth = imagesx($image);
            $mainWidth = min($originalWidth, 1600);

            $absolute = public_path($relative);
            File::ensureDirectoryExists(dirname($absolute));

            $mainImage = $originalWidth > 1600 ? self::resize($image, 1600) : $image;

            if (! self::encodeWebp($mainImage, $absolute, (int) config('images.webp_quality', 82))) {
                return false;
            }

            if ($mainImage !== $image) {
                imagedestroy($mainImage);
            }

            foreach ([400, 800] as $width) {
                if ($width >= $originalWidth) {
                    continue;
                }
                $variant = self::resize($image, $width);
                if ($variant) {
                    self::encodeWebp($variant, public_path(self::variantPath($relative, $width)), (int) config('images.webp_quality', 82));
                    imagedestroy($variant);
                }
            }

            return true;
        } finally {
            imagedestroy($image);
        }
    }

    private static function encodeWebp(\GdImage $image, string $path, int $quality): bool
    {
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return @imagewebp($image, $path, $quality);
    }

    private static function resize(\GdImage $source, int $width): ?\GdImage
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($width >= $srcW) {
            return null;
        }

        $height = (int) round($srcH * ($width / $srcW));
        $destination = imagecreatetruecolor($width, $height);

        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
        imagefill($destination, 0, 0, $transparent);

        imagecopyresampled($destination, $source, 0, 0, 0, 0, $width, $height, $srcW, $srcH);

        return $destination;
    }

    private static function createImage(string $path, string $mime): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            default => false,
        };
    }

    private static function gdSupports(string $mime): bool
    {
        if (! function_exists('imagecreatefromwebp')) {
            return false;
        }

        return match ($mime) {
            'image/jpeg', 'image/png', 'image/webp', 'image/gif' => true,
            'image/avif' => function_exists('imagecreatefromavif'),
            default => false,
        };
    }

    private static function gdWebp(): bool
    {
        static $webp = null;
        if ($webp === null) {
            $webp = function_exists('imagewebp') && function_exists('imagecreatefromwebp');
        }

        return $webp;
    }

    /**
     * SEO-friendly filename: brand-model-network-switch.
     */
    public static function filename(Product $product): string
    {
        $parts = array_filter([
            Str::slug((string) $product->brand),
            Str::slug((string) $product->model_number ?: ProductSeo::model($product)),
        ]);

        $name = trim(implode('-', $parts), '-');

        if ($name === '') {
            $name = Str::slug((string) $product->name);
        }

        $name .= '-network-switch';

        return Str::limit($name, 120, '');
    }

    /**
     * @return array{path: string}|null
     */
    public static function relativePath(Product $product, string $extension): string
    {
        $brand = Str::slug((string) $product->brand) ?: 'general';
        $directory = trim((string) config('images.directory', 'images/products'), '/');

        return $directory.'/'.$brand.'/'.self::filename($product).'.'.$extension;
    }

    private static function variantPath(string $relative, int $width): string
    {
        $extension = pathinfo($relative, PATHINFO_EXTENSION);
        $base = substr($relative, 0, -(strlen($extension) + 1));

        return $base.'-'.$width.'.'.$extension;
    }

    /**
     * Build a responsive srcset from locally stored size variants.
     */
    public static function srcsetFor(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || ! str_starts_with($url, '/')) {
            return '';
        }

        $relative = ltrim($url, '/');
        if (! is_file(public_path($relative))) {
            return '';
        }

        $extension = pathinfo($relative, PATHINFO_EXTENSION);
        $base = substr($relative, 0, -(strlen($extension) + 1));

        $parts = [];
        foreach ([400, 800] as $width) {
            $candidate = $base.'-'.$width.'.'.$extension;
            if (is_file(public_path($candidate))) {
                $parts[] = asset($candidate).' '.$width.'w';
            }
        }
        $parts[] = asset($relative).' 1600w';

        return implode(', ', $parts);
    }

    /**
     * Meaningful alt text: Brand Model [ports] [PoE] Network Switch.
     */
    public static function altText(Product $product): string
    {
        $segments = array_filter([trim((string) $product->brand), trim((string) $product->model_number ?: ProductSeo::model($product))]);

        $details = [];
        if ($product->port_count) {
            $details[] = $product->port_count.' Port';
        }
        if ($product->hasPoe()) {
            $details[] = match ((string) $product->poe_standard) {
                'at' => 'PoE+',
                'bt' => 'PoE++',
                'af' => 'PoE',
                default => 'PoE',
            };
        }
        if ($product->management_type && $product->management_type !== 'unmanaged') {
            $details[] = SwitchCatalog::managementTypes()[$product->management_type] ?? ucfirst((string) $product->management_type);
        }
        $details[] = 'Network Switch';

        $label = trim(implode(' ', array_merge($segments, [implode(' ', $details)])));

        return Str::limit($label, 150, '');
    }

    /**
     * Security: only public HTTP(S) URLs whose resolved IPs are not private,
     * loopback, link-local or reserved. Optional trusted-domain allowlist.
     */
    public static function isSafeImageUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        if (self::blockedByAllowlist($host)) {
            return false;
        }

        return ! self::resolvesToPrivateIp($host);
    }

    private static function blockedByAllowlist(string $host): bool
    {
        $trusted = (array) config('images.trusted_domains', []);
        if ($trusted === []) {
            return false;
        }

        foreach ($trusted as $domain) {
            $domain = strtolower(trim((string) $domain));
            if ($domain === '') {
                continue;
            }
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return false;
            }
        }

        return true;
    }

    private static function resolvesToPrivateIp(string $host): bool
    {
        $ips = [];

        $ipv4 = @gethostbyname($host);
        if (filter_var($ipv4, FILTER_VALIDATE_IP)) {
            $ips[] = $ipv4;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        foreach (array_unique($ips) as $ip) {
            if (self::isPrivateIp($ip)) {
                return true;
            }
        }

        return false;
    }

    public static function isPrivateIp(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '' || $ip === '0.0.0.0') {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $normalized = strtolower($ip);
            if ($normalized === '::' || $normalized === '::1') {
                return true;
            }
            if (str_starts_with($normalized, 'fe80') || str_starts_with($normalized, 'fc') || str_starts_with($normalized, 'fd')) {
                return true;
            }

            return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private static function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => 'jpg',
        };
    }

    private static function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host !== false && $host !== null ? strtolower($host) : null;
    }

    /**
     * Import an external category image locally and update the category.
     */
    public static function importCategoryImage(Category $category, string $url): ?string
    {
        if (! self::isSafeImageUrl($url)) {
            return null;
        }

        $download = self::download($url);
        if ($download === null) {
            return null;
        }

        $mime = self::validateImage($download['path']);
        if ($mime === null) {
            File::delete($download['path']);

            return null;
        }

        $directory = 'images/categories';
        $slug = Str::slug((string) $category->name) ?: $category->slug;

        $relative = $directory.'/'.$slug.'.webp';
        $absolute = public_path($relative);
        File::ensureDirectoryExists(dirname($absolute));

        $success = false;
        if (self::gdSupports($mime) && self::gdWebp()) {
            $image = self::createImage($download['path'], $mime);
            if ($image) {
                $success = self::encodeWebp($image, $absolute, (int) config('images.webp_quality', 82));
                imagedestroy($image);
            }
        }

        if (! $success) {
            $extension = self::extensionForMime($mime);
            $relative = $directory.'/'.$slug.'.'.$extension;
            $absolute = public_path($relative);
            File::ensureDirectoryExists(dirname($absolute));
            $success = copy($download['path'], $absolute);
        }

        File::delete($download['path']);

        if (! $success) {
            return null;
        }

        $category->update(['image_url' => ProductImageCatalog::publicPathUrl($relative)]);

        return $relative;
    }
}
