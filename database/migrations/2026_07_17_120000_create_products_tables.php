<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('product_line')->nullable();
            $table->text('description')->nullable();
            $table->text('intended_purpose')->nullable();
            $table->string('product_type');
            $table->string('manufacturer')->nullable();
            $table->string('trademark')->nullable();
            $table->string('licensing_model');
            $table->boolean('has_remote_data_processing')->default(false);
            $table->boolean('has_network_connectivity')->default(false);
            $table->string('deployment_model')->nullable();
            $table->text('support_period_notes')->nullable();
            $table->text('end_of_support_policy')->nullable();
            $table->foreignId('product_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('security_contact_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope_status');
            $table->text('scope_rationale')->nullable();
            $table->timestamp('scope_reviewed_at')->nullable();
            $table->foreignId('scope_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('classification_status');
            $table->text('classification_rationale')->nullable();
            $table->timestamp('classification_reviewed_at')->nullable();
            $table->foreignId('classification_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('classification_next_review_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version_number');
            $table->date('release_date')->nullable();
            $table->string('state');
            $table->string('support_status');
            $table->date('security_support_deadline')->nullable();
            $table->string('git_ref')->nullable();
            $table->string('build_identifier')->nullable();
            $table->string('artifact_hash')->nullable();
            $table->text('changelog')->nullable();
            $table->foreignId('previous_version_id')->nullable()->constrained('product_versions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_versions');
        Schema::dropIfExists('products');
    }
};
