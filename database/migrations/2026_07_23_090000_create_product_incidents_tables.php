<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status');
            $table->string('severity');
            $table->text('summary')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_measures')->nullable();
            $table->text('lessons_learned')->nullable();
            $table->foreignId('product_vulnerability_id')
                ->nullable()
                ->constrained('product_vulnerabilities')
                ->nullOnDelete();
            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('actual_started_at')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('awareness_at')->nullable();
            $table->timestamp('classified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'incident_org_status_idx');
            $table->index(['product_id', 'status'], 'incident_product_status_idx');
            $table->index(['product_id', 'severity'], 'incident_product_severity_idx');
        });

        Schema::create('incident_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                ->constrained('product_incidents')
                ->cascadeOnDelete();
            $table->timestamp('occurred_at');
            $table->string('label');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['incident_id', 'occurred_at'], 'incident_timeline_occurred_idx');
        });

        Schema::create('incident_product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                ->constrained('product_incidents')
                ->cascadeOnDelete();
            $table->foreignId('product_version_id')
                ->constrained('product_versions')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['incident_id', 'product_version_id'], 'incident_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_product_versions');
        Schema::dropIfExists('incident_timeline_events');
        Schema::dropIfExists('product_incidents');
    }
};
