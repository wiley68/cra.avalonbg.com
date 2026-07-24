<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('category');
            $table->string('auth_type');
            $table->text('credentials')->nullable();
            $table->string('label')->nullable();
            $table->string('status');
            $table->string('sync_schedule')->default('off');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'provider']);
            $table->index(['organization_id', 'category']);
        });

        Schema::create('product_integration_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained('organization_integrations')->cascadeOnDelete();
            $table->string('external_project_key')->nullable();
            $table->string('external_target_id')->nullable();
            $table->string('external_label')->nullable();
            $table->json('config')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_summary')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'integration_id']);
            $table->index(['integration_id', 'last_synced_at']);
        });

        Schema::create('integration_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_id')->constrained('product_integration_links')->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['link_id', 'status']);
        });

        Schema::create('import_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->constrained('product_integration_links')->cascadeOnDelete();
            $table->string('kind');
            $table->string('external_id');
            $table->string('title');
            $table->json('payload')->nullable();
            $table->string('status');
            $table->string('accepted_entity_type')->nullable();
            $table->unsignedBigInteger('accepted_entity_id')->nullable();
            $table->timestamps();

            $table->unique(['link_id', 'kind', 'external_id']);
            $table->index(['product_id', 'status']);
            $table->index(['link_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_suggestions');
        Schema::dropIfExists('integration_sync_runs');
        Schema::dropIfExists('product_integration_links');
        Schema::dropIfExists('organization_integrations');
    }
};
