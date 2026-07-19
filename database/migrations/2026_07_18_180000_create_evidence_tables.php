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

        Schema::create('vulnerability_report_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_vulnerability_id');
            $table->string('type');
            $table->string('status')->default('draft');
            $table->text('summary')->nullable();
            $table->text('impact')->nullable();
            $table->text('affected_versions_text')->nullable();
            $table->text('workaround')->nullable();
            $table->text('corrective_action')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submission_channel')->nullable();
            $table->string('submission_reference')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_comment')->nullable();
            $table->foreignId('evidence_id')->nullable()->constrained('evidence')->nullOnDelete();
            $table->timestamps();

            $table->foreign('product_vulnerability_id', 'vuln_report_sub_vuln_fk')
                ->references('id')
                ->on('product_vulnerabilities')
                ->cascadeOnDelete();
            $table->unique(
                ['product_vulnerability_id', 'type'],
                'vuln_report_submission_type_unique',
            );
            $table->index(
                ['product_vulnerability_id', 'status'],
                'vuln_report_sub_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vulnerability_report_submissions');
        Schema::dropIfExists('evidence_links');
        Schema::dropIfExists('evidence');
    }
};
