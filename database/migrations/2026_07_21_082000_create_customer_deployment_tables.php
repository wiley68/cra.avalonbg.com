<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('external_ref')->nullable();
            $table->string('primary_contact')->nullable();
            $table->string('criticality');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'name']);
        });

        Schema::create('product_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('environment');
            $table->date('installation_date')->nullable();
            $table->boolean('internet_exposure')->default(false);
            $table->string('update_channel')->nullable();
            $table->timestamp('last_confirmed_at')->nullable();
            $table->boolean('custom_modifications')->default(false);
            $table->boolean('end_of_support_exception')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'product_id', 'environment']);
            $table->index(['organization_id', 'product_id']);
        });

        Schema::create('patch_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_version_id')->constrained('product_versions')->restrictOnDelete();
            $table->foreignId('product_vulnerability_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'product_id', 'status']);
        });

        Schema::create('patch_campaign_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('patch_campaigns')->cascadeOnDelete();
            $table->foreignId('deployment_id')->constrained('product_deployments')->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notification_note')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'deployment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patch_campaign_targets');
        Schema::dropIfExists('patch_campaigns');
        Schema::dropIfExists('product_deployments');
        Schema::dropIfExists('customers');
    }
};
