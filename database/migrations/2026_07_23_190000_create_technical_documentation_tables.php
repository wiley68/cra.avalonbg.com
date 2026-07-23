<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('technical_documentation_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_version_id')
                ->nullable()
                ->constrained('product_versions')
                ->nullOnDelete();
            $table->string('title');
            $table->string('status');
            $table->string('version_label');
            $table->string('locale', 8);
            $table->foreignId('supersedes_id')
                ->nullable()
                ->constrained('technical_documentation_packages')
                ->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'tech_doc_org_status_idx');
            $table->index(['product_id', 'status'], 'tech_doc_product_status_idx');
            $table->index(['product_id', 'product_version_id', 'locale'], 'tech_doc_product_version_locale_idx');
        });

        Schema::create('technical_documentation_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')
                ->constrained('technical_documentation_packages')
                ->cascadeOnDelete();
            $table->string('section_key');
            $table->string('source');
            $table->longText('body_markdown')->nullable();
            $table->json('generated_payload')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_applicable')->default(true);
            $table->text('override_reason')->nullable();
            $table->boolean('changed_since_parent')->default(false);
            $table->timestamps();

            $table->unique(['package_id', 'section_key'], 'tech_doc_section_key_unique');
            $table->index(['package_id', 'sort_order'], 'tech_doc_section_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_documentation_sections');
        Schema::dropIfExists('technical_documentation_packages');
    }
};
