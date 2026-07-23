<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sdl_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_version_id')
                ->nullable()
                ->constrained('product_versions')
                ->nullOnDelete();
            $table->string('title');
            $table->string('status');
            $table->string('current_stage');
            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'sdl_run_org_status_idx');
            $table->index(['product_id', 'status'], 'sdl_run_product_status_idx');
            $table->index(['product_id', 'current_stage'], 'sdl_run_product_stage_idx');
            $table->index(['product_version_id'], 'sdl_run_version_idx');
        });

        Schema::create('sdl_stage_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sdl_run_id')
                ->constrained('sdl_runs')
                ->cascadeOnDelete();
            $table->string('stage');
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['sdl_run_id', 'stage'], 'sdl_stage_entry_unique');
            $table->index(['sdl_run_id', 'status'], 'sdl_stage_entry_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdl_stage_entries');
        Schema::dropIfExists('sdl_runs');
    }
};
