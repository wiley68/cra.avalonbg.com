<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_security_instructions', function (Blueprint $table) {
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
                ->constrained('user_security_instructions')
                ->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('evidence_id')->nullable()->constrained('evidence')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'usi_org_status_idx');
            $table->index(['product_id', 'status'], 'usi_product_status_idx');
            $table->index(['product_id', 'product_version_id', 'locale'], 'usi_product_version_locale_idx');
        });

        Schema::create('user_security_instruction_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instruction_id')
                ->constrained('user_security_instructions')
                ->cascadeOnDelete();
            $table->string('section_key');
            $table->string('title_override')->nullable();
            $table->longText('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_applicable')->default(true);
            $table->timestamps();

            $table->unique(['instruction_id', 'section_key'], 'usi_section_key_unique');
            $table->index(['instruction_id', 'sort_order'], 'usi_section_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_security_instruction_sections');
        Schema::dropIfExists('user_security_instructions');
    }
};
