<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ImageManager;
use Database\Seeders\SwitchCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SwitchCatalogSeeder::class);
    }

    private function pngBytes(int $width = 900, int $height = 600): string
    {
        $img = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($img, 200, 30, 30);
        imagefill($img, 0, 0, $color);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    public function test_filename_is_seo_friendly(): void
    {
        $product = Product::where('sku', 'TPL-SG2428P')->first();

        $filename = ImageManager::filename($product);

        $this->assertSame('tp-link-tl-sg2428p-network-switch', $filename);
        $this->assertStringNotContainsString(' ', $filename);
        $this->assertSame($filename, strtolower($filename));
    }

    public function test_alt_text_is_meaningful(): void
    {
        $product = Product::where('sku', 'MIK-CRS32824P')->first();

        $alt = ImageManager::altText($product);

        $this->assertStringContainsString('MikroTik', $alt);
        $this->assertStringContainsString('CRS328-24P-4S+RM', $alt);
        $this->assertStringContainsString('PoE', $alt);
        $this->assertStringContainsString('Network Switch', $alt);
    }

    public function test_private_and_reserved_ips_are_blocked(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '172.16.0.1', '192.168.1.1', '169.254.169.254', '0.0.0.0', '::1', 'fe80::1'] as $ip) {
            $this->assertTrue(ImageManager::isPrivateIp($ip), "Expected $ip to be treated as private");
        }

        $this->assertFalse(ImageManager::isPrivateIp('8.8.8.8'));
    }

    public function test_unsafe_urls_are_rejected(): void
    {
        $this->assertFalse(ImageManager::isSafeImageUrl('file:///etc/passwd'));
        $this->assertFalse(ImageManager::isSafeImageUrl('ftp://example.com/a.png'));
        $this->assertFalse(ImageManager::isSafeImageUrl('http://127.0.0.1/a.png'));
        $this->assertFalse(ImageManager::isSafeImageUrl('http://localhost/a.png'));
        $this->assertFalse(ImageManager::isSafeImageUrl('http://10.0.0.1/a.png'));
        $this->assertFalse(ImageManager::isSafeImageUrl('http://169.254.169.254/a.png'));
    }

    public function test_import_downloads_stores_and_optimizes_image(): void
    {
        Http::fake(['https://8.8.8.8/*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png'])]);

        $product = Product::where('sku', 'TPL-SG108')->first();

        $result = ImageManager::import($product, 'https://8.8.8.8/tp-link-sg108.png', true);

        $this->assertSame('imported', $result['status']);

        $image = $result['image'];
        $this->assertNotNull($image);
        $this->assertSame('https://8.8.8.8/tp-link-sg108.png', $image->original_image_url);
        $this->assertSame('8.8.8.8', $image->source_domain);
        $this->assertStringContainsString('Network Switch', $image->image_alt);

        $relative = ltrim($image->image_url, '/');
        $this->assertStringStartsWith('images/products/', $relative);
        $this->assertTrue(File::isFile(public_path($relative)), 'Local image file should exist');
        $this->assertStringEndsWith('.webp', $relative);

        // Responsive variants generated (no upscaling of the 900px source).
        $this->assertTrue(File::isFile(public_path($this->variant($relative, 400))), '400px variant should exist');
        $this->assertTrue(File::isFile(public_path($this->variant($relative, 800))), '800px variant should exist');

        $srcset = ImageManager::srcsetFor('/'.$relative);
        $this->assertStringContainsString('400w', $srcset);
        $this->assertStringContainsString('800w', $srcset);
    }

    private function variant(string $relative, int $width): string
    {
        $extension = pathinfo($relative, PATHINFO_EXTENSION);
        $base = substr($relative, 0, -(strlen($extension) + 1));

        return $base.'-'.$width.'.'.$extension;
    }

    public function test_duplicate_imports_reuse_existing_file(): void
    {
        Http::fake(['https://8.8.8.8/*' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png'])]);

        $product = Product::where('sku', 'TPL-SG108')->first();

        $first = ImageManager::import($product, 'https://8.8.8.8/dup.png', true);
        $this->assertSame('imported', $first['status']);

        $second = ImageManager::import($product, 'https://8.8.8.8/dup.png', false);
        $this->assertSame('duplicate', $second['status']);
        $this->assertSame($first['image']->image_url, $second['image']->image_url);

        // The same image + same product must not create a duplicate record or file.
        $this->assertSame(1, ProductImage::where('product_id', $product->id)->count());
    }

    public function test_invalid_mime_is_rejected(): void
    {
        Http::fake(['https://8.8.8.8/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html'])]);

        $product = Product::where('sku', 'TPL-SG108')->first();

        $result = ImageManager::import($product, 'https://8.8.8.8/not-image.png', true);

        $this->assertSame('invalid', $result['status']);
        $this->assertNull($result['image']);
        $this->assertSame(0, ProductImage::where('product_id', $product->id)->count());
    }

    public function test_srcset_is_built_from_local_variants(): void
    {
        $this->assertSame('', ImageManager::srcsetFor(null));
        $this->assertSame('', ImageManager::srcsetFor('/images/products/nonexistent/foo.webp'));
    }
}
