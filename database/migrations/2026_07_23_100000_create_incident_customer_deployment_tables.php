<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incident_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                ->constrained('product_incidents')
                ->cascadeOnDelete();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['incident_id', 'customer_id'], 'incident_customer_unique');
        });

        Schema::create('incident_product_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                ->constrained('product_incidents')
                ->cascadeOnDelete();
            $table->foreignId('product_deployment_id')
                ->constrained('product_deployments')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['incident_id', 'product_deployment_id'],
                'incident_deployment_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_product_deployments');
        Schema::dropIfExists('incident_customers');
    }
};
