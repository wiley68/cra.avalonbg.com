<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incident_customer_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                ->constrained('product_incidents')
                ->cascadeOnDelete();
            $table->timestamp('communicated_at');
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('channel');
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->string('audience')->nullable();
            $table->string('subject');
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('evidence_id')
                ->nullable()
                ->constrained('evidence')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['incident_id', 'communicated_at'],
                'incident_comms_incident_communicated_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_customer_communications');
    }
};
