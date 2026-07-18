<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sboms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_version_id')->constrained('product_versions')->cascadeOnDelete();
            $table->string('format');
            $table->string('source_filename');
            $table->string('storage_path')->nullable();
            $table->string('checksum_sha256', 64);
            $table->unsignedInteger('component_count')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index(['product_id', 'product_version_id']);
        });

        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_version_id')->constrained('product_versions')->cascadeOnDelete();
            $table->foreignId('sbom_id')->nullable()->constrained('sboms')->nullOnDelete();
            $table->string('name');
            $table->string('supplier')->nullable();
            $table->string('package_ecosystem');
            $table->string('version')->nullable();
            $table->string('licence')->nullable();
            $table->string('purl')->nullable();
            $table->string('hash')->nullable();
            $table->boolean('is_direct')->default(true);
            $table->boolean('is_dev')->default(false);
            $table->string('usage_context')->nullable();
            $table->string('support_status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['product_version_id', 'purl']);
            $table->index(['product_id', 'product_version_id']);
            $table->index(['product_version_id', 'package_ecosystem', 'name', 'version'], 'product_components_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_components');
        Schema::dropIfExists('sboms');
    }
};
