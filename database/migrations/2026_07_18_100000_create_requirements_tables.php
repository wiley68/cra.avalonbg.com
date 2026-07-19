<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regulations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('jurisdiction')->nullable();
            $table->timestamps();
        });

        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('article_ref')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['regulation_id', 'code']);
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('requirement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('requirement_text');
            $table->text('requirement_text_bg')->nullable();
            $table->text('plain_language')->nullable();
            $table->text('plain_language_bg')->nullable();
            $table->text('applicability_notes')->nullable();
            $table->text('applicability_notes_bg')->nullable();
            $table->text('suggested_controls_text')->nullable();
            $table->text('suggested_controls_text_bg')->nullable();
            $table->text('required_evidence_text')->nullable();
            $table->text('required_evidence_text_bg')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['requirement_id', 'version']);
            $table->index(['requirement_id', 'is_current']);
        });

        Schema::create('product_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_version_id')->constrained('requirement_versions')->restrictOnDelete();
            $table->string('status');
            $table->text('rationale')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'requirement_id']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('product_requirement_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_requirement_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('rationale')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['product_requirement_id', 'created_at'],
                'pr_histories_req_created_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_requirement_histories');
        Schema::dropIfExists('product_requirements');
        Schema::dropIfExists('requirement_versions');
        Schema::dropIfExists('requirements');
        Schema::dropIfExists('regulations');
    }
};
