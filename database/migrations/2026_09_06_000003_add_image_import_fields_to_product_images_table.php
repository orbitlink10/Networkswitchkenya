<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_images', 'original_image_url')) {
                $table->string('original_image_url', 500)->nullable()->after('image_url');
            }

            if (! Schema::hasColumn('product_images', 'image_alt')) {
                $table->string('image_alt', 255)->nullable()->after('original_image_url');
            }

            if (! Schema::hasColumn('product_images', 'source_domain')) {
                $table->string('source_domain', 190)->nullable()->after('image_alt');
            }

            if (! Schema::hasColumn('product_images', 'file_hash')) {
                $table->string('file_hash', 64)->nullable()->after('source_domain')->index();
            }

            if (! Schema::hasColumn('product_images', 'imported_at')) {
                $table->timestamp('imported_at')->nullable()->after('file_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $columns = ['original_image_url', 'image_alt', 'source_domain', 'file_hash', 'imported_at'];
            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn('product_images', $column)));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
