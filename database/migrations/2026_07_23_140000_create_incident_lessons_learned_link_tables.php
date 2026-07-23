<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incident_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                ->constrained('product_incidents')
                ->cascadeOnDelete();
            $table->foreignId('evidence_id')
                ->constrained('evidence')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['incident_id', 'evidence_id'], 'incident_evidence_unique');
        });

        Schema::create('incident_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                ->constrained('product_incidents')
                ->cascadeOnDelete();
            $table->foreignId('control_id')
                ->constrained('controls')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['incident_id', 'control_id'], 'incident_controls_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_controls');
        Schema::dropIfExists('incident_evidence');
    }
};
