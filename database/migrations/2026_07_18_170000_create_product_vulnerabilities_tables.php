<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_vulnerabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('cve_id')->nullable();
            $table->string('advisory_url')->nullable();
            $table->string('discovery_source');
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('awareness_at')->nullable();
            $table->string('status');
            $table->decimal('cvss_score', 3, 1)->nullable();
            $table->string('business_severity');
            $table->string('exploitation_status');
            $table->boolean('is_public')->default(false);
            $table->text('workaround')->nullable();
            $table->text('corrective_action')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['product_id', 'business_severity']);
            $table->index(['product_id', 'cve_id']);
        });

        Schema::create('product_vulnerability_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_vulnerability_id');
            $table->unsignedBigInteger('product_component_id');
            $table->timestamps();

            $table->foreign('product_vulnerability_id', 'pvc_vulnerability_fk')
                ->references('id')
                ->on('product_vulnerabilities')
                ->cascadeOnDelete();
            $table->foreign('product_component_id', 'pvc_component_fk')
                ->references('id')
                ->on('product_components')
                ->cascadeOnDelete();

            $table->unique(
                ['product_vulnerability_id', 'product_component_id'],
                'product_vuln_component_unique',
            );
        });

        Schema::create('product_vulnerability_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_vulnerability_id');
            $table->unsignedBigInteger('product_version_id');
            $table->string('relation');
            $table->timestamps();

            $table->foreign('product_vulnerability_id', 'pvv_vulnerability_fk')
                ->references('id')
                ->on('product_vulnerabilities')
                ->cascadeOnDelete();
            $table->foreign('product_version_id', 'pvv_version_fk')
                ->references('id')
                ->on('product_versions')
                ->cascadeOnDelete();

            $table->unique(
                ['product_vulnerability_id', 'product_version_id', 'relation'],
                'product_vuln_version_relation_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_vulnerability_versions');
        Schema::dropIfExists('product_vulnerability_components');
        Schema::dropIfExists('product_vulnerabilities');
    }
};
