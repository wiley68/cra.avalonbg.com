<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_vcs_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('auth_type');
            $table->text('token');
            $table->string('label')->nullable();
            $table->string('status');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'provider']);
        });

        Schema::create('product_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('organization_vcs_connections')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('full_name');
            $table->string('remote_url');
            $table->string('default_branch')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_summary')->nullable();
            $table->timestamps();

            $table->unique('product_id');
        });

        Schema::create('vcs_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained('product_repositories')->cascadeOnDelete();
            $table->string('status');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['repository_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vcs_sync_runs');
        Schema::dropIfExists('product_repositories');
        Schema::dropIfExists('organization_vcs_connections');
    }
};
