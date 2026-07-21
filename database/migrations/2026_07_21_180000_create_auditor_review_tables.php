<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auditor_review_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status');
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'auditor_packages_org_status_idx');
            $table->index(['product_id', 'status'], 'auditor_packages_product_status_idx');
        });

        Schema::create('auditor_review_package_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')
                ->constrained('auditor_review_packages')
                ->cascadeOnDelete();
            $table->foreignId('evidence_id')->constrained('evidence')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['package_id', 'evidence_id'], 'auditor_package_evidence_unique');
        });

        Schema::create('auditor_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')
                ->constrained('auditor_review_packages')
                ->cascadeOnDelete();
            $table->string('severity');
            $table->string('status');
            $table->string('title');
            $table->text('body');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('remediated_at')->nullable();
            $table->timestamps();

            $table->index(['package_id', 'status'], 'auditor_findings_package_status_idx');
            $table->index(['package_id', 'severity'], 'auditor_findings_package_severity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditor_findings');
        Schema::dropIfExists('auditor_review_package_evidence');
        Schema::dropIfExists('auditor_review_packages');
    }
};
