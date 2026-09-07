<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image import & optimization
    |--------------------------------------------------------------------------
    | Local storage and optimization of product images imported from external
    | manufacturer URLs.
    |
    */

    // Storage disk + relative directory for locally hosted images.
    'disk' => env('IMAGE_DISK', 'public'),

    // Relative directory (under the disk root) for product images.
    'directory' => env('IMAGE_DIRECTORY', 'images/products'),

    // Preferred output formats, in order of preference. WebP is used when the
    // GD/Imagick support is available; otherwise the original is kept.
    'formats' => ['webp'],

    // WebP encode quality (1-100). Keep high enough for sharp product shots.
    'webp_quality' => (int) env('IMAGE_WEBP_QUALITY', 82),

    // Responsive widths (px) generated for each imported image. Only widths up
    // to the source's own width are generated (never upscaled).
    'sizes' => [400, 800, 1600],

    // Hard safety limits for remote downloads.
    'max_file_bytes' => (int) env('IMAGE_MAX_FILE_BYTES', 10485760), // 10 MB
    'timeout' => (int) env('IMAGE_TIMEOUT', 30),

    // Trusted manufacturer image domains. When non-empty, only these hostnames
    // (and their subdomains) may be imported. Leave empty to allow any public
    // HTTP(S) image URL (private/loopback/link-local hosts are always blocked).
    'trusted_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('IMAGE_TRUSTED_DOMAINS', ''))
    ))),

    // Allowed MIME types for imported images.
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ],
];
