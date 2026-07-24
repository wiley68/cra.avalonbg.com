<?php

namespace App\Services;

use App\Models\AiAnalysisJob;
use App\Models\Evidence;
use App\Models\Product;
use App\Models\Sbom;
use Illuminate\Support\Facades\Storage;

/**
 * Removes product-scoped files from local disk before DB cascade delete.
 */
class ProductStorageCleanup
{
    public function purge(Product $product): void
    {
        $disk = Storage::disk('local');
        $paths = [];

        foreach (
            Evidence::query()
                ->where('product_id', $product->id)
                ->whereNotNull('storage_path')
                ->pluck('storage_path') as $path
        ) {
            $paths[] = (string) $path;
        }

        foreach (
            Sbom::query()
                ->where('product_id', $product->id)
                ->whereNotNull('storage_path')
                ->pluck('storage_path') as $path
        ) {
            $paths[] = (string) $path;
        }

        foreach (
            AiAnalysisJob::query()
                ->where('product_id', $product->id)
                ->pluck('payload') as $payload
        ) {
            if (!is_array($payload)) {
                continue;
            }

            $stored = $payload['stored_path'] ?? null;
            if (is_string($stored) && $stored !== '') {
                $paths[] = $stored;
            }
        }

        foreach (array_unique($paths) as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        foreach (["evidence/{$product->id}", "sboms/{$product->id}"] as $directory) {
            if ($disk->exists($directory)) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
