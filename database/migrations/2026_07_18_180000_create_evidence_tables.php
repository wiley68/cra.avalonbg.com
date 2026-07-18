<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_version_id')->nullable()->constrained('product_versions')->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->string('source')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('storage_path')->nullable();
            $table->string('source_filename')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('confidentiality');
            $table->timestamp('collected_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->date('review_due_at')->nullable();
            $table->string('freshness_status');
            $table->foreignId('supersedes_evidence_id')->nullable()->constrained('evidence')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'freshness_status']);
            $table->index(['product_id', 'type']);
        });

        Schema::create('evidence_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evidence_id');
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->timestamps();

            $table->foreign('evidence_id', 'evidence_links_evidence_fk')
                ->references('id')
                ->on('evidence')
                ->cascadeOnDelete();

            $table->unique(
                ['evidence_id', 'linkable_type', 'linkable_id'],
                'evidence_links_unique',
            );
            $table->index(['linkable_type', 'linkable_id'], 'evidence_links_morph_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_links');
        Schema::dropIfExists('evidence');
    }
};
