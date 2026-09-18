# Bundled product images

The catalog includes local photos for 71 products. `config/product_images.php`
records each product slug, image path, source URL, and model. The files and
responsive variants are stored under `public/images/products/`.

`ProductImageCatalog::uploadedUrlFor()` uses these files when an uploaded image
is unavailable. This works for both listing cards and product detail pages,
including installations without imported image database records. Existing
uploaded images retain priority.

## Deployment

Deploy `app/Support/ProductImageCatalog.php`, `config/product_images.php`, and
the complete `public/images/products/` directory together. Refresh Laravel's
configuration cache if enabled (`php artisan config:cache`). No database import
or migration is required for the bundled fallback.

## Unresolved model

`ruijie-reyee-rg-es209gs-8-port-gigabit-smart-switch` still needs an exact product
photo or model confirmation. Searches did not establish an image for
RG-ES209GS; photos of RG-EG209GS and RG-ES209GC-P are different models and were
not assigned to this entry.

## Validation

All 71 bundled images were decoded and visually reviewed in contact sheets.
Their local HTTP endpoints returned images successfully, and product cards
rendered the bundled sources with their image relationships emptied to check
deployment without image records. PHP syntax and Blade compilation passed.
